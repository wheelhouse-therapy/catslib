<?php

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

class AkauntingReports
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

    }

    function GetLedgerRA( $raParms = array() )
    {
        $raRows = array();

        $sOrderBy = @$raParms['sortdb'] ? " ORDER BY {$raParms['sortdb']} " : "";

        $sql =
            "select A.account_id,A.entry_type,A.debit as d,A.credit as c,LEFT(A.issued_at,10) as date,A.reference as reference, "
                  ."B.company_id as company_id, B.type_id as type_id, B.code as code, B.name as name "
            ."from {$this->akTablePrefix}_double_entry_ledger A, {$this->akTablePrefix}_double_entry_accounts B "
            ."where A.account_id=B.id "
                .($raParms['akCompanyId'] != -1 ? "AND B.company_id='{$raParms['akCompanyId']}'" : "")
            .$sOrderBy;

        $raRows = $this->oAppAk->kfdb->QueryRowsRA( $sql );

        if( $raParms['Ak_clinic'] == -1 ) {
            // In Combined clinics revenue from CORE is not included in the totals,
            // and CATS Contribution is not included as an expense.

            $ra2 = array();
            foreach( $raRows as $ra ) {
// TODO: should check company_id corresponds to CORE, not just that it is Akaunting company #1
                if( $ra['company_id'] == 1 && substr($ra['code'],0,1) == '4' )  continue;

// TODO: should there be a better way to determine the CATS Contribution account?
                if( $ra['code'] == '608' ) continue;

                $ra2[] = $ra;
            }
            $raRows = $ra2;
        }

        done:
        return( $raRows );
    }

    function GetLedgerRAForDisplay( $raParms = array() )
    /***************************************************
        Same as GetLedgerRA but if sorting by account put a total after each account, and handle revenue cases for Combined clinic view
     */
    {
        $raOut = array();
        $raRows = $this->GetLedgerRA( $raParms );

        $raAcctLast = "";
        $total = $dtotal = $ctotal = 0;
        foreach( $raRows as $ra ) {
            if( $raParms['Ak_sort'] == 'name' ) {
                if( $raAcctLast && $raAcctLast != $ra['code'] ) {
                    $raOut[] = ['total'=>$total, 'dtotal'=>$dtotal, 'ctotal'=>$ctotal];
                    $total = $dtotal = $ctotal = 0;
                }
                $dtotal += $ra['d'];
                $ctotal += $ra['c'];
                $total += $ra['d'] - $ra['c'];
                $raAcctLast = $ra['code'];
            }

            // Make the name of the account. If viewing Combined clinics show which clinic this account is for.
            $ra['acct'] = ($raParms['Ak_clinic']==-1 ? ($ra['company_id']." : ") : "").$ra['code']." : ".$ra['name'];
            $raOut[] = $ra;
        }

        return( $raOut );
    }

    private function clinicSelector( $reportParms )
    {
        $sForm = "";

        if( ($clinics = $this->clinics->getClinicsWithAkaunting()) ) {
            $clinicsDB = new ClinicsDB($this->oApp->kfdb);
            $sForm = "<form style='display:inline' id='companyForm'>"
                    ."<select name='Ak_clinic' onChange=\"document.getElementById('companyForm').submit()\">"
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
        $sForm = "<form style='display:inline'>"
                ."<select name='Ak_report' onChange='submit();'>"
                ."<option value='monthly' "    .($reportParms['Ak_report']=='monthly'     ? "selected" : "").">Monthly</option>"
                ."<option value='monthly_sum' ".($reportParms['Ak_report']=='monthly_sum' ? "selected" : "").">Monthly Sum</option>"
                ."<option value='detail' "     .($reportParms['Ak_report']=='detail'      ? "selected" : "").">Detail</option>"
                ."<option value='ledger' "     .($reportParms['Ak_report']=='ledger'      ? "selected" : "").">Ledger</option>"
                ."</select>"
                ."</form>";

        return( $sForm );
    }

    function DrawReport()
    {
        $s = "";

        if( !$this->oAppAk ) goto done;

        $reportParms = $this->oAkReport->GetReportParmsFromRequest();
        if( !$reportParms['Ak_clinic'] ) {
            $this->oApp->oC->ErrMsg( "No clinic defined" );
            goto done;
        }

        $s .= "<div style='clear:both;float:right; border:1px solid #aaa;border-radius:5px;padding:10px'>"
                 ."<a href='jx.php?cmd=therapist-akaunting-xlsx&{$reportParms['parmsForLink']}'><button>Download</button></a>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
                 ."<img src='".W_CORE_URL."img/icons/xls.png' height='30'/>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
             ."</div>";

        $s .= "<div id='companyFormContainer'>".$this->clinicSelector( $reportParms )."</div>"
             ."<div id='companyFormContainer'>".$this->reportSelector( $reportParms )."</div>";

        switch( $reportParms['Ak_report'] ) {
            case 'ledger':       $s .= $this->drawLedgerReport();      break;
            case 'monthly':      $s .= $this->drawMonthlyReport();     break;
            case 'monthly_sum':  $s .= $this->drawMonthlySumReport();  break;
            case 'detail':       $s .= $this->drawDetailReport();      break;
        }

        done:
        return( $s );
    }

    private function drawLedgerReport()
    {
        $raRows = $this->GetLedgerRAForDisplay( $this->oAkReport->raReportParms );

        $s = "<table cellpadding='10' border='1'>"
            ."<tr><th><a href='{$_SERVER['PHP_SELF']}?Ak_sort=date'>Date</a></th>"
                ."<th><a href='{$_SERVER['PHP_SELF']}?Ak_sort=name'>Account</a></th>"
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
                $s .= SEEDCore_ArrayExpand( $ra, "<tr><td>[[date]]</td><td>[[acct]]</td>"
                                                    ."<td>[[d]]</td><td>[[c]]</td><td>[[total]]</td><td>[[reference]]</td></tr>" );
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

            $raMonths[$month] = 1;
            $raAccts[$acct] = 1;
        }
        ksort($raMonths);
        ksort($raAccts);

        $s .= "<table class='AkReportTable'>"
             ."<tr><td>&nbsp;</td>".SEEDCore_ArrayExpandSeries( $raMonths, "<td><strong>[[k]]</strong></td>", true, ['bUseKeys'=>true] )."</tr>";

        foreach( $raAccts as $acct => $dummy ) {
            $s .= "<tr><td><strong>$acct</strong></td>";
            foreach( $raMonths as $month => $dummy ) {
                $s .= "<td>".(@$raM[$month][$acct] ?: "")."</td>";
            }
            $s .= "</tr>";
        }
        $s .= "</table>";

        return( $s );
    }

    private function drawMonthlySumReport()
    {
        $s = "";

        return( $s );
    }

    private function drawDetailReport()
    {
        $s = "";

        return( $s );
    }
}

function AkauntingReport( SEEDAppConsole $oApp )
{
    $s = "";

    $o = new AkauntingReports( $oApp );

    return( $o->DrawReport() );
}

function AkauntingReport_OutputXLSX( SEEDAppConsole $oApp )
{
    $o = new AkauntingReports( $oApp );

    $oXls = new AkauntingReportSpreadsheet( $oApp );
    $oXls->OutputXLSX();
}


class AkauntingReportSpreadsheet
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

        $o = new AkauntingReports( $this->oApp );
        $raRows = $o->GetLedgerRAForDisplay( $raParms );

        $raCols = array(
            'date'  => 'Date',
            'acct'  => 'Account',
            'd'     => 'Debit',
            'c'     => 'Credit'
        );
        if( $raParms['Ak_sort'] == 'name' ) {
            $raCols['total'] = 'Total';

            $ra2 = $raRows;
            $raRows = array();
            foreach( $ra2 as $ra ) {
                if( isset($ra['total']) ) {
                    $raRows[] = ['date'=>'','acct'=>'','d'=>$ra['dtotal'],'c'=>$ra['ctotal'],'total'=>$ra['total']];
                    $raRows[] = ['date'=>'','acct'=>'','d'=>'','c'=>'','total'=>''];
                } else {
                    $ra['total'] = '';
                    $raRows[] = $ra;
                }
            }
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

?>