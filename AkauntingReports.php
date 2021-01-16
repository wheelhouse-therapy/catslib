<?php

/* Get financial reports from Akaunting.
 *
 * AkauntingReportBase           analyses parameters and fetches ledger data from Akaunting
 * AkauntingReportScreen         uses AkauntingReportBase to show reports on the screen, implements UI to sort/filter
 * AkauntingReportSpreadsheet    uses AkauntingReportBase to download reports to spreadsheets
 *
 * To show reports on the screen, AkauntingReportScreen implements UI controls and gives those parameters to AkauntingReportBase.
 * AkauntingReportBase uses the parameters to fetch the corresponding set of ledger entries and format the report.
 *
 * To download reports to spreadsheet, sort/filter using the UI controls on the screen, and code the Download button to send the
 * corresponding parameters to AkauntingReportSpreadsheet. It uses those parameters with AkauntingReportBase to fetch data and
 * format the report exactly as it appears on the screen, but outputs it as a spreadsheet.
 */


require_once 'AkauntingHook.php';

class AkauntingReportBase
{
    public $oApp;
    public $raClinics;
    public $raReportParms;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->raClinics = new Clinics($this->oApp);
        $this->raReportParms = $this->GetReportParmsFromRequest();
    }

    function GetReportParmsFromRequest()
    /***********************************
        Parms that start with Ak_ are cycled through http. Others are for internal use only.
     */
    {
        $raParms = [];

        // Ak_clinic is the clinic selector.  -1 means Combined clinics
        $raParms['Ak_clinic'] = intval($this->oApp->sess->SmartGPC( 'Ak_clinic', [$this->raClinics->GetCurrentClinic()] ));

        // Ak_report is the report type
        $raParms['Ak_report'] = $this->oApp->sess->SmartGPC('Ak_report', array('monthly'));

        // Ak_date are the bounds of the date range to show.
        $raParms['Ak_date_min'] = $this->oApp->sess->SmartGPC('Ak_date_min');
        $raParms['Ak_date_max'] = $this->oApp->sess->SmartGPC('Ak_date_max');

        // Ak_sort sorts a ledger by date or account name
        // sortdb is the sql ORDER BY
        switch( ($raParms['Ak_sort'] = $this->oApp->sess->SmartGPC('Ak_sort', ['date'])) ) {
            case 'date':
                $raParms['sortdb'] = 'date,name,d,c';
                break;
            case 'name':
                if( $raParms['Ak_clinic'] == -1 ) {
                    $raParms['sortdb'] = 'code,company_id,date,d,c';
                } else {
                    $raParms['sortdb'] = 'code,date,d,c';       // actually degenerate to the other case because company_id is always the same
                }
                break;
        }

        // akCompanyId is the company_id that Akaunting uses. We get this from our Clinics table
        if( $raParms['Ak_clinic'] == -1 ) {
            // Combined clinics view
            $raParms['akCompanyId'] = -1;
        } else {
            $raParms['akCompanyId'] = (new ClinicsDB($this->oApp->kfdb))->GetClinic($raParms['Ak_clinic'])->Value("akaunting_company");
            if( !$raParms['akCompanyId'] ) {
                $this->oApp->oC->AddErrMsg("Clinic does not have an accounting ID set");
            }
        }

        // Make an urlencoded string that can reproduce the current state
        $raParms['parmsForLink'] = "";
        foreach( $raParms as $k => $v ) {
            if( SEEDCore_StartsWith( $k, "Ak_" ) ) {
                if( $raParms['parmsForLink'] ) $raParms['parmsForLink'] .= "&";
                $raParms['parmsForLink'] .= "$k=".urlencode($v);
            }
        }

        return( $raParms );
    }

    function Style()
    {
        return( "
            <style>
            .AkReportTable td { border:1px solid #aaa }
            </style>
            " );
    }
}

class AkauntingReportScreen
/* Implement UI to select/sort/filter reports. Show them on screen.
 */
{
    private $oAkReport;

    private $oApp;
    private $oAppAk = null;     // SEEDAppDB
    private $akDb;
    private $akTablePrefix;
    private $clinics;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oAkReport = new AkauntingReportBase( $oApp );

        global $config_KFDB;

        $this->oApp = $oApp;
        $this->clinics = new Clinics($this->oApp);

        if( isset($config_KFDB['akaunting']) ) {
            $this->oAppAk = new SEEDAppDB( $config_KFDB['akaunting'] );
            $this->akDb = $config_KFDB['akaunting']['kfdbDatabase'];
            $this->akTablePrefix = $config_KFDB['akaunting']['ak_tableprefix'];
        } else {
            $this->oApp->oC->AddErrMsg( "config_KFDB['akaunting'] not defined" );   // cats_page will show this at the top of the screen
        }
        if (CATS_DEBUG) {$this->oAppAk->kfdb->SetDebug(1);}
    }

    function GetLedgerRA( $raParms = array() )
    /*****************************************
        Get ledger data from Akaunting, filtered and sorted according to the input parms
     */
    {
        $raRows = array();

        $sOrderBy = @$raParms['sortdb'] ? "ORDER BY {$raParms['sortdb']} " : "";

        $raCond = array();
        // filter by company
        if( ($p = $raParms['akCompanyId']) != -1 ) {
            $raCond[] = "B.company_id=$p";
        }
        // filter by start / end date
        if( @$raParms['Ak_date_min'] || @$raParms['Ak_date_max'] ) {
            $pMin = addslashes($raParms['Ak_date_min']);
            $pMax = addslashes($raParms['Ak_date_max']);

            if( !$pMin ) {
                $raCond[] = "A.issued_at <= '$pMax'";
            } else if( !$pMax ) {
                $raCond[] = "A.issued_at >= '$pMin'";
            } else {
                $raCond[] = "(A.issued_at BETWEEN '$pMin' AND '$pMax')";
            }
        }
        // filter by account code prefix (e.g. 2=200s, 4=400)
        if( ($p = @$raParms['codePrefix']) ) {
            $len = strlen($p);
            $raCond[] = "LEFT(code,$len)='$p'";
        }

        $sql =
            "select A.id as ledger_id,A.account_id,A.entry_type,ROUND(A.debit,2) as d,ROUND(A.credit,2) as c,LEFT(A.issued_at,10) as date,C.description as description, "
                  ."B.company_id as company_id, B.type_id as type_id, B.code as code, B.name as name "
                      ."from `{$this->akTablePrefix}_double_entry_ledger` A, `{$this->akTablePrefix}_double_entry_accounts` B, `{$this->akTablePrefix}_double_entry_journals` C "
            ."where A.account_id=B.id AND A.ledgerable_id=C.id AND A.deleted_at IS NULL "
            .SEEDCore_ArrayExpandSeries( $raCond, "AND [[]] ", false ) // don't turn sql quotes into entities
            .$sOrderBy;

        $raRows = $this->oAppAk->kfdb->QueryRowsRA( $sql );

        if( $raParms['Ak_clinic'] == -1 ) {
            // In Combined clinics revenue from CORE is not included in the totals,
            // and CATS Contribution is not included as an expense.

            $ra2 = array();
            $coreCompany = (new ClinicsDB($this->oApp->kfdb))->GetClinic(1)->Value('akaunting_company');
            foreach( $raRows as $ra ) {
                if( $ra['company_id'] == $coreCompany && substr($ra['code'],0,1) == '4' )  continue;

// TODO: should there be a better way to determine the CATS Contribution account?
                if( $ra['code'] == '608' ) continue;

                $ra2[] = $ra;
            }
            $raRows = $ra2;
        }

        $ra2 = array();
        foreach ($raRows as $ra){
            $ra['description'] = str_replace(array('�','�'), '"', $ra['description']);
            $ra2[] = $ra;
        }
        $raRows = $ra2;

        done:
        return( $raRows );
    }

    function GetLedgerRAForDisplay( $raParms = array() )
    /***************************************************
        Same as GetLedgerRA but add rows for display purposes.
        e.g. if sorting by account put a total after each account, and handle revenue cases for Combined clinic view
     */
    {
        $raOut = array();
        $raRows = $this->GetLedgerRA( $raParms );

        $raAcctLast = "";
        $total = $dtotal = $ctotal = 0;
        foreach( $raRows as $ra ) {
            // The name of the account shows the clinic name if viewing Combined
            $acctName = ($raParms['Ak_clinic']==-1 ? ($ra['company_id']." : ") : "").$ra['code']." : ".$ra['name'];

            if( $raParms['Ak_sort'] == 'name' ) {
                $bStartSection = $raAcctLast != $ra['code'];

                // total at the end of each account section
                if( $raAcctLast && $bStartSection ) {
                    $raOut[] = ['total'=>$total, 'dtotal'=>$dtotal, 'ctotal'=>$ctotal];
                    $total = $dtotal = $ctotal = 0;
                }
                // opening balance at the beginning of each asset/liability account section
                if( $bStartSection && in_array( substr($ra['code'],0,1), ['2','8'] ) ) {
                    // get the dtotal and ctotal of all entries prior to the first shown entry
                    list($ctotal,$dtotal) = $this->oAppAk->kfdb->QueryRA(
                        "SELECT ROUND(SUM(A.credit),2),ROUND(SUM(A.debit),2) "
                       ."FROM `{$this->akTablePrefix}_double_entry_ledger` A, `{$this->akTablePrefix}_double_entry_accounts` B "
                       ."WHERE A.account_id=B.id AND B.code='".addslashes($ra['code'])."' AND A.deleted_at IS NULL "
                       .(($p = $raParms['akCompanyId']) != -1 ? " AND B.company_id=$p" : "")
                       .(($p = addslashes($raParms['Ak_date_min'])) ? " AND A.issued_at<'$p'" : "") );

                    $ctotal = floatval($ctotal);    // change null to 0.0
                    $dtotal = floatval($dtotal);
                    $total = $this->addCD($ra['code'],$ctotal,$dtotal);
                    $raOut[] = ['bOpening'=>true, 'dOpening'=>$dtotal, 'cOpening'=>$ctotal, 'acct'=>$acctName, 'total1'=>$total];
                }

                $dtotal += $ra['d'];
                $ctotal += $ra['c'];
                $total += $this->addCD($ra['code'],$ra['c'],$ra['d']);

                $raAcctLast = $ra['code'];
            }

            $ra['acct'] = $acctName;
            $ra['total1'] = $total;
            $raOut[] = $ra;
        }
        if( $raParms['Ak_sort'] == 'name' && $total != 0 ) {
// TODO: $total!=0 is not always the correct way to detect the end of a section because it might not always be true
            $raOut[] = ['total'=>$total, 'dtotal'=>$dtotal, 'ctotal'=>$ctotal];
        }

        return( $raOut );
    }

    private function addCD( $code, $c, $d )
    {
        // A credit increases 400s, decreases 600s, etc
        return( in_array( substr($code,0,1), ['2','4'] ) ? $c - $d : $d - $c );
    }

    private function clinicSelector( $reportParms )
    {
        $sForm = "";

        if( ($clinics = $this->clinics->getClinicsWithAkaunting()) ) {
            $clinicsDB = new ClinicsDB($this->oApp->kfdb);
            $sForm = "<form style='display:inline' onSubmit='updateReport(event);'>"
                    ."<input type='hidden' name='cmd' value='therapist-akaunting-updateReport' />"
                    ."<select name='Ak_clinic' onChange=\"this.form.dispatchEvent(new Event('submit'))\">"
                    ."<option value='-1'".($reportParms['Ak_clinic']==-1 ? " selected" : "").">Combined</option>";
            foreach($clinics as $c){
                $selected = $c==$reportParms['Ak_clinic'] ? "selected" : "";
                $sForm .= $clinicsDB->GetClinic($c)->Expand( "<option value='[[_key]]' $selected>[[clinic_name]]</option>" );
            }
            $sForm .= "</select></form>";
        }

        return( $sForm );
    }

    private function reportSelector( $reportParms )
    {
        $sForm = "<form id='formReportSelect' style='display:inline' onSubmit='updateReport(event);'>"
                ."<input type='hidden' name='cmd' value='therapist-akaunting-updateReport' />"
                ."<select name='Ak_report' id='Ak_report' onChange=\"this.form.dispatchEvent(new Event('submit'));\">"
                ."<option value='monthly' "    .($reportParms['Ak_report']=='monthly'     ? "selected" : "").">Monthly</option>"
                ."<option value='monthly_sum' ".($reportParms['Ak_report']=='monthly_sum' ? "selected" : "").">Monthly Sum</option>"
                ."<option value='detail' "     .($reportParms['Ak_report']=='detail'      ? "selected" : "").">Detail</option>"
                ."<option value='ledger' "     .($reportParms['Ak_report']=='ledger'      ? "selected" : "").">Ledger</option>"
                ."<option value='journalForm' ".($reportParms['Ak_report']=='journalForm' ? "selected" : "").">Journal Entry Form</option>"
                ."</select>"
                ."</form>";

        return( $sForm );
    }

    private function dateSelector( $reportParms ){
        $sForm = "<form id='formDateSelect' style='display:inline' onSubmit='updateReport(event);'>"
                ."<input type='hidden' name='cmd' value='therapist-akaunting-updateReport' />"
                ."<input type='date'  name='Ak_date_min' id='Ak_date_min' value='".($reportParms['Ak_date_min']?:"")."' />"
                ."<input type='date'  name='Ak_date_max' id='Ak_date_max' value='".($reportParms['Ak_date_max']?:"")."' />"
                ."<input type='submit' value='Filter' />"
                ."</div>";
        return( $sForm );
    }

    private function updateScript(){
        return <<<UpdateScript
<script>
    function updateReport(e){
        document.getElementById('reportLoading').style.display = "block";
        document.getElementById('reportError').style.display = "none";
        var postData = $(e.currentTarget).serializeArray();
        $.ajax({
            type: "POST",
            data: postData,
            url: "jx.php",
            success: function(data, textStatus, jqXHR) {
//console.log(data);
                var jsData = JSON.parse(data);
                if(jsData.bOk){
                    document.getElementById('report').innerHTML = jsData.sOut;
                    document.getElementById('reportLoading').style.display = "none";
                }
                else{
                    document.getElementById('reportLoading').style.display = "none";
                    document.getElementById('reportError').style.display = "block";
                }
            },
            error: function(jqXHR, status, error) {
                console.log(status + ": " + error);
                document.getElementById('reportLoading').style.display = "none";
                document.getElementById('reportError').style.display = "block";
            }
        });
        e.preventDefault();

        /* Change the Download button url to have the settings in the Report and Date forms
         */
        let downBtn = new URLSearchParams( $('#buttonDownload').attr('href').substring(7) ); // cut off prefix "jx.php" and parse parms
//for (let p of downBtn) {
//  console.log(p);
//}
        downBtn.set( 'Ak_report',   $('#Ak_report').val() );
        downBtn.set( 'Ak_date_min', $('#Ak_date_min').val() );
        downBtn.set( 'Ak_date_max', $('#Ak_date_max').val() );

        $('#buttonDownload').attr('href', "jx.php?"+ downBtn.toString() );
    }
</script>
UpdateScript;
    }

    private function drawOverlays(){
        $s = <<<Overlays
<div class='overlay' id='reportLoading'>
    <div class="loader"></div>
    <div class="overlayText">Report is Loading</div>
</div>
<div class='overlay' id='reportError'>
    <div class="overlayText">
        <img src='CATSDIR_IMGerror.png' />
        An Error occured while loading this report
    </div>
</div>
Overlays;
        return str_replace("CATSDIR_IMG", CATSDIR_IMG, $s);
    }

    function DrawReport(bool $reportOnly)
    {
        $s = "";

        if( !$this->oAppAk ) goto done;

        $reportParms = $this->oAkReport->GetReportParmsFromRequest();
        if( !$reportParms['Ak_clinic'] ) {
            $this->oApp->oC->ErrMsg( "No clinic defined" );
            goto done;
        }

        if(!$reportOnly){
            $s .= "<div style='clear:both;float:right; border:1px solid #aaa;border-radius:5px;padding:10px'>"
                     ."<a id='buttonDownload' href='jx.php?cmd=therapist-akaunting-xlsx&{$reportParms['parmsForLink']}'><button>Download</button></a>"
                     ."&nbsp;&nbsp;&nbsp;&nbsp;"
                     ."<img src='".W_CORE_URL."img/icons/xls.png' height='30'/>"
                     ."&nbsp;&nbsp;&nbsp;&nbsp;"
                 ."</div>";

            $s .= "<div id='companyFormContainer'>".$this->clinicSelector( $reportParms )."</div>"
                 ."<div id='companyFormContainer'>".$this->reportSelector( $reportParms )."</div>"
                 ."<div id='companyFormContainer'>".$this->dateSelector( $reportParms )."</div>";

            $s .= $this->updateScript();
            $s .="<div style='position:relative'>".$this->drawOverlays()."<div id='report'>";
        }

        switch( $reportParms['Ak_report'] ) {
            case 'ledger':       $s .= $this->drawLedgerReport();      break;
            case 'monthly':      $s .= $this->drawMonthlyReport();     break;
            case 'monthly_sum':  $s .= $this->drawMonthlySumReport();  break;
            case 'detail':       $s .= $this->drawDetailReport();      break;
            case 'journalForm':
                $ra = array();
                if($ledger_id = $this->oApp->sess->SmartGPC('ledger_id')){
                    $ra = $this->oAppAk->kfdb->QueryRA("SELECT * FROM {$this->akTablePrefix}_double_entry_journals WHERE id = ".$ledger_id);
                }
                $s .= journalEntryForm($reportParms['akCompanyId'],$ra);
                break;
        }
        if(!$reportOnly){
            $s .="</div></div>";
        }

        done:
        return( $s );
    }

    private function drawLedgerReport()
    {
        $raRows = $this->GetLedgerRAForDisplay( $this->oAkReport->raReportParms );

        $s = "<table id='AkLedgerReport' cellpadding='10' border='1'>"
            ."<tr><th><a href='".CATSDIR."?Ak_sort=date'>Date</a></th>"
                ."<th><a href='".CATSDIR."?Ak_sort=name'>Account</a></th>"
                ."<th>Debit</th>"
                ."<th>Credit</th>"
                ."<th>&nbsp;</th>"
                ."<th>Description</th>"
            ."</tr>";
        foreach( $raRows as $ra ) {
            if( isset($ra['total']) ) {
                // sometimes we insert a special row with a total element
                $s .= SEEDCore_ArrayExpand( $ra, "<tr><td>&nbsp;</td><td>&nbsp;</td>"
                                                    ."<td><span style='color:gray'>[[dtotal]]</span></td>"
                                                    ."<td><span style='color:gray'>[[ctotal]]</span></td>"
                                                    ."<td><strong>[[total]]</strong></td><td>&nbsp;</td></tr>" );
                $s .= "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
            } else {
                $a = substr($ra['acct'], 0, 1); // first digit of acct determines the row colour
                $s .= SEEDCore_ArrayExpand( $ra, "<tr class='AkReportRow$a'><td>[[date]]</td><td>[[acct]]</td>"
                                                    ."<td>[[d]]</td><td>[[c]]</td><td>[[total]]</td><td>[[description]]</td></tr>" );
            }
        }
        $s .= "</table>";

        return( $s );
    }

    private function drawMonthlyReport()
    {
        $s = $this->oAkReport->Style();

        $raRows = $this->GetLedgerRA( $this->oAkReport->raReportParms );
        $raM = [];
        $raMonths = [];
        $raAccts = [];
        foreach( $raRows as $ra ) {
            $month = substr( $ra['date'], 0, 7 );
            $acct = $ra['code']." : ".$ra['name'];
            if( !isset($raM[$month][$acct]) )  $raM[$month][$acct] = 0.0;
            $raM[$month][$acct] += $ra['d'] ?: $ra['c'];

            $raMonths[$month] = date_create($month)->format('M\!Y');
            $raAccts[$acct] = 1;
        }
        ksort($raMonths);
        ksort($raAccts);

        $s .= "<table class='AkReportTable'>"
             ."<tr><td>&nbsp;</td>".str_replace("!", "<br />", SEEDCore_ArrayExpandSeries( $raMonths, "<td><strong>[[]]</strong></td>" ))."</tr>";

        foreach( array_keys($raAccts) as $acct) {
            $s .= "<tr><td><strong>$acct</strong></td>";
            foreach( array_keys($raMonths) as $month) {
                $s .= "<td>".(@$raM[$month][$acct] ?: "")."</td>";
            }
            $s .= "</tr>";
        }
        $s .= "</table>";

        return($s);
    }

    private function drawMonthlySumReport()
    {
        $s = "";

        return( $s );
    }

    private function drawDetailReport()
    {
        $raRows = [];
        foreach( [4,5,6,1,2,3,5,7,8,9] as $c ) {
            $this->oAkReport->raReportParms['codePrefix'] = $c;
            $raRows = array_merge( $raRows, $this->GetLedgerRAForDisplay( $this->oAkReport->raReportParms ) );  // do not use $raRows += because it does the wrong thing
        }

        $s = "<table id='AkLedgerReport' cellpadding='10' border='1'>"
                ."<tr><th><a href='".CATSDIR."?Ak_sort=date'>Date</a></th>"
                ."<th><a href='".CATSDIR."?Ak_sort=name'>Account</a></th>"
                ."<th>Debit</th>"
                ."<th>Credit</th>"
                ."<th>&nbsp;</th>"
                ."<th>Description</th>"
                ."</tr>";
        foreach( $raRows as $ra ) {
            // dollarize these values unless they are blank
            foreach( ['c','d','dtotal','ctotal','total','total1'] as $i ) {
                if( @$ra[$i] )  $ra[$i] = "$".sprintf( "%0.2f", $ra[$i] );
            }

            if( isset($ra['total']) ) {
                // sometimes we insert a special row with a total element
                $s .= SEEDCore_ArrayExpand( $ra,
                        "<tr><td>&nbsp;</td><td>&nbsp;</td>"
                       ."<td><span style='color:gray'>[[dtotal]]</span></td>"
                       ."<td><span style='color:gray'>[[ctotal]]</span></td>"
                       ."<td><strong>[[total]]</strong></td><td>&nbsp;</td></tr>" );
                $s .= "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
            } else if( isset($ra['bOpening']) ) {
                // sometimes we insert a special row with an opening balance
                $a = substr($ra['acct'], 0, 1); // first digit of acct determines the row colour
                $s .= SEEDCore_ArrayExpand( $ra,
                        "<tr class='AkReportRow$a'><td>&nbsp;</td><td>[[acct]] - Opening Balance</td>"
                       ."<td><span style='color:gray'>[[dOpening]]</span></td>"
                       ."<td><span style='color:gray'>[[cOpening]]</span></td>"
                       ."<td><strong>[[total1]]</strong></td><td>&nbsp;</td></tr>" );
            } else {
                $a = substr($ra['acct'], 0, 1); // first digit of acct determines the row colour
                $s .= SEEDCore_ArrayExpand( $ra,
                        "<tr class='AkReportRow$a'><td>[[date]]</td><td>[[acct]]</td>"
                       ."<td>[[d]]</td><td>[[c]]</td><td><strong>[[total1]]</strong></td><td>[[description]]</td></tr>" );
            }
        }
        $s .= "</table>";

        return( $s );
    }

}

function AkauntingReport( SEEDAppConsole $oApp, bool $reportOnly = false )
{
    $o = new AkauntingReportScreen( $oApp );

    return( $o->DrawReport($reportOnly) );
}

function AkauntingReport_OutputXLSX( SEEDAppConsole $oApp )
{
    $oXls = new AkauntingReportSpreadsheet( $oApp );
    $oXls->OutputXLSX();
}


class AkauntingReportSpreadsheet
/* Use parameters from AkauntingReport's UI to put report data in a spreadsheet.
 * The spreadsheet content should be identical to the report on the screen because they both use the same parameters
 * to fetch data from AkauntingReportBase
 */
{
    private $oAkReport;
    private $oApp;
    private $oPeopleDB;
    private $clinics;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oAkReport = new AkauntingReportBase( $oApp );

        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB( $this->oApp );
        $this->clinics = new Clinics($oApp);
        $this->clinics->GetCurrentClinic();
    }

    function OutputXLSX()
    {
        $raParms = $this->oAkReport->GetReportParmsFromRequest();

        // Initialize the spreadsheet with three sheets (one is created by default)
        $oXls = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $oXls->createSheet();
        $oXls->createSheet();

        // Set document properties
        $oXls->getProperties()->setCreator('Collaborative Approach Therapy Services')
            ->setLastModifiedBy('CATS')
            ->setTitle('Clients / Staff / Providers')
            ->setSubject('Clients / Staff / Providers')
            ->setDescription('Spreadsheet containing Akaunting details')
            ->setKeywords('')
            ->setCategory('CATS Akaunting');

        $filename = "CATS Akaunting.xlsx";

// why are we getting this from the Screen object? It should be in the ReportBase object
        $o = new AkauntingReportScreen( $this->oApp );
// $raRows = $o->GetLedgerRAForDisplay( $raParms );

// This does what Detail Report does, which should be encapsulated into this report type only.
// Do we still need Ledger Report at all?
        $raRows = [];
        foreach( [4,5,6,1,2,3,5,7,8,9] as $c ) {
            $raParms['codePrefix'] = $c;
            $raRows = array_merge( $raRows, $o->GetLedgerRAForDisplay( $raParms ) );  // do not use $raRows += because it does the wrong thing
        }

        $raCols = [
            'date'  => 'Date',
            'acct'  => 'Account',
            'd'     => 'Debit',
            'c'     => 'Credit',
            'total' => 'Total',         // removed below for date-sorted reports
            'description' => 'Description'
        ];
        if( $raParms['Ak_sort'] == 'name' ) {
            $ra2 = $raRows;
            $raRows = array();
            foreach( $ra2 as $ra ) {
                if( isset($ra['total']) ) {
                    // special row with a total
                    $raRows[] = ['date'=>'','acct'=>'','d'=>$ra['dtotal'],'c'=>$ra['ctotal'],'total'=>$ra['total'],'description'=>''];
                    $raRows[] = ['date'=>'','acct'=>'','d'=>'','c'=>'','total'=>'','description'=>''];
                } else if( isset($ra['bOpening']) ) {
                    // special row with an opening balance
                    $raRows[] = ['date'=>'','acct'=>$ra['acct']." - Opening Balance",
                                 'd'=>$ra['dOpening'],'c'=>$ra['cOpening'],'total'=>$ra['total1'],'description'=>''];
                } else {
                    $ra['total'] = $ra['total1'];
                    $raRows[] = $ra;
                }
            }
        } else {
            // date-sorted reports don't have running totals
            unset($raCols['total']);
        }

        $this->storeSheet( $oXls, 0, "Akaunting", $raRows, $raCols );

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $oXls->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($oXls, 'Xlsx');
        $writer->save('php://output');
    }

    private function storeSheet( $oXls, $iSheet, $sSheetName, $raRows, $raCols )
    {
        $oSheet = $oXls->setActiveSheetIndex( $iSheet );
        $oSheet->setTitle( $sSheetName );

        // Set the headers in row 1
        $c = 'A';
        foreach( $raCols as $dbfield => $label ) {
            $oSheet->setCellValue($c.'1', $label );
            $c = chr(ord($c)+1);    // Change A to B, B to C, etc
        }

        // Put the data starting at row 2
        $row = 2;
        foreach( $raRows as $ra ) {
            $col = 'A';
            foreach( $raCols as $dbfield => $label ) {
                $oSheet->setCellValue($col.$row, $ra[$dbfield] );
                $col = chr(ord($col)+1);    // Change A to B, B to C, etc
            }
            ++$row;
        }
    }
}

//TODO Move to better location
function journalEntryForm(int $company, array $entry){
    global $email_processor;
    if($company == -1){
        return "";
    }
    //TODO Load accounts and values
    $s = <<<JournalEntryForm
<template id='tableRow'>
    <tr>
        <td class="text-center" style="vertical-align: middle;">
            <button type="button" onclick="/*$(this).tooltip('destroy');*/ $(this).parents().eq(1).remove(); totalItem();" data-toggle="tooltip" title="Delete" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
        </td>
        <td>
            <select id="item-account-id-0" class="form-control account-select2 input-account select2-hidden-accessible" name="item[0][account_id]" tabindex="-1" aria-hidden="true">[[options]]</select>
        </td>
        <td>
            <input value="" class="form-control text-right input-price" required="required" name="item[0][debit]" type="text" id="item-debit-">
        </td>
        <td>
            <input value="" class="form-control text-right input-price" required="required" name="item[0][credit]" type="text" id="item-credit-">
        </td>
    </tr>
</template>
<form method="POST"  accept-charset="UTF-8" role="form" enctype="multipart/form-data">
    <div class="box-body">
        <div class="form-group col-md-6 required ">
        <label for="paid_at" class="control-label">Date</label>
        <div class="input-group">
            <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
            <input class="form-control" placeholder="Enter Date" id="paid_at" required="required" data-inputmask="'alias': 'yyyy-mm-dd'" data-mask="" name="paid_at" type="text" value="[[paid_at]]">
        </div>
    </div>
    <div class="form-group col-md-6  ">
        <label for="reference" class="control-label">Reference</label>
        <div class="input-group">
            <div class="input-group-addon"><i class="fa fa-file-text-o"></i></div>
            <input class="form-control" placeholder="Enter Reference" name="reference" type="text" id="reference" value="[[reference]]">
        </div>
    </div>
    <div class="form-group col-md-12 required ">
        <label for="description" class="control-label">Description</label>
        <textarea class="form-control" placeholder="Enter Description" rows="3" required="required" name="description" cols="50" id="description">[[description]]</textarea>
    </div>
    <div class="form-group col-md-12">
        <label for="items" class="control-label">Items</label>
        <div class="table-responsive">
            <table class="table table-bordered" id="items">
                <thead>
                    <tr style="background-color: #f9f9f9;">
                        <th width="5%" class="text-center" required="">Actions</th>
                        <th width="20%" class="text-left required">Account</th>
                        <th width="20%" class="text-right">Debit</th>
                        <th width="20%" class="text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center" style="vertical-align: middle;">
                            <button type="button" onclick="/*$(this).tooltip('destroy');*/ $(this).parents().eq(1).remove(); totalItem();" data-toggle="tooltip" title="Delete" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                        </td>
                        <td>
                            <select id="item-account-id-0" class="form-control account-select2 input-account select2-hidden-accessible" name="item[0][account_id]" tabindex="-1" aria-hidden="true">[[options]]</select>
                        </td>
                        <td>
                            <input value="" class="form-control text-right input-price" required="required" name="item[0][debit]" type="text" id="item-debit-0">
                        </td>
                        <td>
                            <input value="" class="form-control text-right input-price" required="required" name="item[0][credit]" type="text" id="item-credit-0">

                        </td>
                    </tr>
                    <tr id="addItem">
                        <td class="text-center"><button type="button" id="button-add-item" data-toggle="tooltip" title="Add" class="btn btn-xs btn-primary" data-original-title="Add"><i class="fa fa-plus"></i></button></td>
                        <td class="text-right" colspan="3"></td>
                    </tr>
                    <tr>
                        <td class="text-right" colspan="2"><strong>Subtotal</strong></td>
                        <td class="text-right"><span id="debit-sub-total">0</span></td>
                        <td class="text-right"><span id="credit-sub-total">0</span></td>
                    </tr>
                    <tr>
                        <td class="text-right" colspan="2"><strong>Total</strong></td>
                        <td class="text-right"><span id="debit-grand-total">0</span></td>
                        <td class="text-right"><span id="credit-grand-total">0</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <!-- /.box-body -->
    <div class="box-footer">
        <div class="col-md-12">
            <div class="form-group no-margin">
                <button type="submit" class="btn btn-success  button-submit" data-loading-text="Loading..." disabled=""><span class="fa fa-save"></span> &nbsp;Save</button>
                <a href="https://catherapyservices.ca/akaunting/double-entry/journal-entry" class="btn btn-default"><span class="fa fa-times-circle"></span> &nbsp;Cancel</a>
            </div>
        </div>
    </div>
    <!-- /.box-footer -->
    <input id="currency_code" name="currency_code" type="hidden" value="CAD">
</form>
JournalEntryForm;
    AkauntingHook::login($email_processor['akauntingUSR'],$email_processor['akauntingPSW'],$email_processor['akauntingServer']);
    $s = SEEDCore_ArrayExpand($entry, $s);
    $s = str_replace(array("[[options]]","[[paid_at]]"), array(AkauntingHook::fetchAccounts($company,TRUE,FALSE),date("Y-m-d")), $s);
    AkauntingHook::logout();
    return $s;
}

?>