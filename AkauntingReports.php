<?php

class AkauntingReports
{
    private $oApp;
    private $oAppAk = null;     // SEEDAppDB
    private $akDb;
    private $akTablePrefix;


    function __construct( SEEDAppConsole $oApp )
    {
        global $config_KFDB;

        $this->oApp = $oApp;

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

        $sql =
            "select A.account_id,A.entry_type,A.debit as d,A.credit as c,LEFT(A.issued_at,10) as date, "
                  ."B.company_id as company_id, B.type_id as type_id, B.code as code, B.name as name "
            ."from {$this->akTablePrefix}_double_entry_ledger A, {$this->akTablePrefix}_double_entry_accounts B "
            ."where A.account_id=B.id"
            .$sOrderBy;

        $raRows = $this->oAppAk->kfdb->QueryRowsRA( $sql );

        return( $raRows );
    }

    function LedgerParmsFromRequest()
    {
        $raParms = [];

        if( ($p = SEEDInput_Str('sort')) ) {
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

        return( $raParms );
    }

    function DrawReport()
    {
        $s = "";

        if( !$this->oAppAk ) goto done;

        $raRows = $this->GetLedgerRA( $this->LedgerParmsFromRequest() );

        $sCurrParms = "sort=".SEEDInput_Str('sort');


        $s .= "<div style='clear:both;float:right; border:1px solid #aaa;border-radius:5px;padding:10px'>"
                 ."<a href='jx.php?cmd=therapist-akaunting-xlsx&$sCurrParms'><button>Download</button></a>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
                 ."<img src='".W_CORE_URL."img/icons/xls.png' height='30'/>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
             ."</div>";

        $s .= "<table cellpadding='10' border='1'>"
            ."<tr><th>Company</th><th><a href='{$_SERVER['PHP_SELF']}?sort=date'>Date</a></th><th><a href='{$_SERVER['PHP_SELF']}?sort=name'>Account</a></th><th>Debit</th><th>Credit</th></tr>"
            .SEEDCore_ArrayExpandRows( $raRows, "<tr><td>[[company_id]]</td><td>[[date]]</td><td>[[code]] : [[name]]</td><td> [[d]]</td><td> [[c]]</tr>" )
            ."</table>";


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
        $raRows = $o->GetLedgerRA( $raParms );

        $raCols = array(
            'date'  => 'Date',
            'name'  => 'Last name',
            'd'     => 'Debit',
            'c'     => 'Credit',
        );

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