<?php

class Therapist_ClientListSpreadsheet
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

    function OutputXLSX()
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
            ->setDescription('Spreadsheet containing contacts for a clinic')
            ->setKeywords('')
            ->setCategory('CATS clients, staff, providers list');

        $filename = "cats-people.xlsx";

        $condClinic = $this->clinics->isCoreClinic() ? "" : ("clinic='".$this->clinics->GetCurrentClinic()."'");

        // Array of common DB fields
        $raPeople = array(
            'P_first_name'   => 'First name',
            'P_last_name'    => 'Last name',
            'P_address'      => 'Address',
            'P_city'         => 'City',
            'P_province'     => 'Province',
            'P_postal_code'  => 'Postal Code',
            'P_dob'          => 'Date of Birth',
            'P_phone_number' => 'Phone Number',
            'P_email'        => 'Email',
        );

        /* Sheet 0 is Clients
         */
        $raClients    = $this->oPeopleDB->GetList('C', $condClinic);
        $raClientCols = $raPeople + array(
            '_key'             => "Client number",
            'fk_people'        => "P key",
            'parents_name'     => "Parents Name",
            'parents_separate' => "Parents Separate",
            'referral'         => "Referral",
            'background_info'  => "Background Info",
            'clinic'           => 'Clinic'
        );
        $this->storeSheet( $oXls, 0, "Clients", $raClients, $raClientCols );

        /* Sheet 1 is Staff
         */
        $raStaff = $this->oPeopleDB->GetList('PI', $condClinic);
        $raStaffCols = $raPeople + array(
            '_key'         => "Staff number",
            'fk_people'    => "P key",
            'pro_role'     => "Role",
            'fax_number'   => "Fax Number",
            'rate'         => "Rate",
            'clinic'       => 'Clinic'
        );
        $this->storeSheet( $oXls, 1, "Staff", $raStaff, $raStaffCols );

        /* Sheet 2 is External providers
         */
        $raPros = $this->oPeopleDB->GetList('PE', $condClinic);
        $raProsCols = $raPeople + array(
            '_key'         => "Provider number",
            'fk_people'    => "P key",
            'pro_role'     => "Role",
            'fax_number'   => "Fax Number",
            'rate'         => "Rate",
            'clinic'       => 'Clinic'
        );
        $this->storeSheet( $oXls, 2, "Providers", $raPros, $raProsCols );

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
//        $c = 'A';
 //       foreach( $raCols as $ra ) {
  //          var_dump($c);
   //     }exit;

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

function Therapist_ClientList_OutputXLSX( SEEDAppConsole $oApp )
{
    $o = new Therapist_ClientListSpreadsheet( $oApp );
    $o->OutputXLSX();
}

function Therapist_ClientList_LoadXLSX( SEEDAppConsole $oApp, $sFilename )
{
    $outSheets = array();

    if( !($oXls = \PhpOffice\PhpSpreadsheet\IOFactory::load( $sFilename )) ) {
        goto done;
    }
    $raSheets = $oXls->getAllSheets();

    $iSheet = 1;
    foreach( $raSheets as $sheet ) {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        $raRows = array();
        for( $row = 1; $row <= $highestRow; $row++ ) {
            $ra = $sheet->rangeToArray( 'A'.$row.':'.$highestColumn.$row,
                                        NULL, TRUE, FALSE );
//            if( $this->raParms['charset'] != 'utf-8' ) {
//                for( $i = 0; $i < count($ra[0]); ++$i ) {
//                    if( is_string($ra[0][$i]) ) {
//                        $ra[0][$i] = iconv( 'utf-8', $this->raParms['charset'], $ra[0][$i] );
//                    }
//                }
//            }
            $raRows[] = $ra[0];     // $ra is an array of rows, with only one row
        }
        if( !($sheetName = $sheet->getTitle()) ) {
            $sheetName = "Sheet".$iSheet;
        }
        $outSheets[$sheetName] = $raRows;
        ++$iSheet;
    }

    done:
    return( $outSheets );
}

?>