<?php

class AkauntingReports
{
    private $oApp;
    private $oAppAk = null;     // SEEDAppDB
    private $akDb;
    private $akTablePrefix;
    private $clinics;


    function __construct( SEEDAppConsole $oApp )
    {
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
        $sOrderBy = @$raParms['sort'] ? " ORDER BY {$raParms['sort']} " : "";

        $cid =(new ClinicsDB($this->oApp->kfdb))->GetClinic(@$raParms['clinic']?:$this->clinics->GetCurrentClinic())->Value("akaunting_company");
        
        if(!$cid){
            $this->oApp->oC->AddErrMsg("Clinic does not have an accounting ID set");
            return( FALSE );
        }
        
        $sql =
            "select A.account_id,A.entry_type,A.debit as d,A.credit as c,LEFT(A.issued_at,10) as date, "
                  ."B.company_id as company_id, B.type_id as type_id, B.code as code, B.name as name "
            ."from {$this->akTablePrefix}_double_entry_ledger A, {$this->akTablePrefix}_double_entry_accounts B "
            ."where A.account_id=B.id AND company_id=$cid"
            .$sOrderBy;

        $raRows = $this->oAppAk->kfdb->QueryRowsRA( $sql );

        return( $raRows );
    }

    function GetLedgerRAForDisplay( $raParms = array() )
    /***************************************************
        Same as GetLedgerRA but if sorting by account put a total after each account
     */
    {
        $raOut = array();
        $raRows = $this->GetLedgerRA( $raParms );

        $raAcctLast = "";
        $total = $dtotal = $ctotal = 0;
        foreach( $raRows as $ra ) {
            if( SEEDCore_StartsWith( @$raParms['sort'], 'code' ) ) {
                if( $raAcctLast && $raAcctLast != $ra['code'] ) {
                    $raOut[] = ['total'=>$total, 'dtotal'=>$dtotal, 'ctotal'=>$ctotal];
                    $total = $dtotal = $ctotal = 0;
                }
                $dtotal += $ra['d'];
                $ctotal += $ra['c'];
                $total += $ra['d'] - $ra['c'];
                $raAcctLast = $ra['code'];
            }

            $ra['acct'] = $ra['code']." : ".$ra['name'];
            $raOut[] = $ra;
        }

        return( $raOut );
    }

    function LedgerParmsFromRequest()
    {
        $raParms = [];

        if( ($p = $this->oApp->sess->SmartGPC('Akaunting_sort')) ) {
            switch( $p ) {
                case 'date':
                    $raParms['sort'] = 'date,name,d,c';
                    break;
                case 'name':
                    $raParms['sort'] = 'code,date,d,c';
                    break;
                default:
                    break;
            }
        }
        if( ($p = $this->oApp->sess->SmartGPC('Akaunting_clinic'))){
            $raParms['clinic'] = $p;
        }

        return( $raParms );
    }

    function DrawReport()
    {
        $s = "";

        if( !$this->oAppAk ) goto done;

        $raRows = $this->GetLedgerRAForDisplay( $this->LedgerParmsFromRequest() );

        $sCurrParms = "sort=".SEEDInput_Str('sort');


        if($clinics = $this->clinics->getClinicsWithAkaunting()){
            $clinic = $this->oApp->sess->SmartGPC('Akaunting_clinic', array($this->clinics->GetCurrentClinic()));
            $clinicsDB = new ClinicsDB($this->oApp->kfdb);
            $sForm = "<form style='display:inline' id='companyForm'><select name='Akaunting_clinic' onChange=\"document.getElementById('companyForm').submit()\">";
            foreach($clinics as $option){
                $raData = $clinicsDB->GetClinic($option);
                if($option == $clinic){
                    $sForm = SEEDCore_ArrayExpand($raData, "<option selected value='[[akaunting_company]]'>[[clinic_name]]</option>");
                }
                else{
                    $sForm = SEEDCore_ArrayExpand($raData, "<option value='[[akaunting_company]]'>[[clinic_name]]</option>");
                }
            }
        }
        
        $s .= "<div style='clear:both;float:right; border:1px solid #aaa;border-radius:5px;padding:10px'>"
                 ."<a href='jx.php?cmd=therapist-akaunting-xlsx&$sCurrParms'><button>Download</button></a>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
                 ."<img src='".W_CORE_URL."img/icons/xls.png' height='30'/>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
             ."</div>";

        $s .= "<table cellpadding='10' border='1'>"
            ."<tr><th>Company</th><th><a href='{$_SERVER['PHP_SELF']}?sort=date'>Date</a></th><th><a href='{$_SERVER['PHP_SELF']}?sort=name'>Account</a></th><th>Debit</th><th>Credit</th><th>&nbsp;</th></tr>";
        foreach( $raRows as $ra ) {
            if( isset($ra['total']) ) {
                // sometimes we insert a special row with a total element
                $s .= SEEDCore_ArrayExpand( $ra, "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>"
                                                    ."<td><span style='color:gray'>[[dtotal]]</span></td>"
                                                    ."<td><span style='color:gray'>[[ctotal]]</span></td>"
                                                    ."<td><strong>[[total]]</strong></td></tr>" );
                $s .= "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
            } else {
                $s .= SEEDCore_ArrayExpand( $ra, "<tr><td>[[company_id]]</td><td>[[date]]</td><td>[[acct]]</td>"
                                                    ."<td>[[d]]</td><td>[[c]]</td><td>[[total]]</tr>" );
            }
        }
        $s .= "</table>";

        done:
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
    $oXls->OutputXLSX( $o->LedgerParmsFromRequest() );
}


class AkauntingReportSpreadsheet
{
    private $oApp;
    private $oPeopleDB;
    private $clinics;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB( $this->oApp );
        $this->clinics = new Clinics($oApp);
        $this->clinics->GetCurrentClinic();
    }

    function OutputXLSX( $raParms = [] )
    {
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
        if( SEEDCore_StartsWith( @$raParms['sort'], 'code' ) ) {
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