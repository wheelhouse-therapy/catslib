<?php

require_once CATSLIB."invoice/pdfinvoice.php";
require CATSLIB.'calendar.php';

class CATSInvoice
{
    private $oApp;
    private $oApptDB;
    private $invoiceId;
    private $kfrAppt = null;

    function __construct( SEEDAppSessionAccount $oApp, $invoiceid )
    {
        $this->oApp = $oApp;
        $this->invoiceid = $invoiceid;
        $this->oApptDB = new AppointmentsDB( $oApp );
        if( $invoiceid ) {
            $this->kfrAppt = $this->oApptDB->KFRel()->GetRecordFromDBKey( $invoiceid );
        }
    }

    function PermissionRead()
    /************************
        Return true if the current user has permission to view the current invoice.
     */
    {
        $ok = false;

        // need an invoice to check
        if( !$this->kfrAppt )  goto done;

        // If you are an invoice administrator you can see all invoices.
        if( $this->oApp->sess->CanAdmin( 'invoice' ) ) { $ok = true;  goto done; }

        // If you are the person issuing this invoice you're allowed to see it.
        if( $this->oApp->sess->GetUID() == $this->kfrAppt->Value('fk_professionals') )  { $ok = true;  goto done; }

        // If you are the person paying this invoice you're certainly allowed to see it.
        if( $this->oApp->sess->GetUID() == $this->kfrAppt->Value('fk_clients') )  { $ok = true;  goto done; }

        // There should be something here allowing therapists on the same team, or bookkeepers for the clinic, etc.

        done:
        return( $ok );
    }

    function InvoicePDF( $mode, $raParms = array() )
    {
        if( !$this->kfrAppt ) goto done;
        if( !$this->PermissionRead() )  goto done;

        $pdfname = "";

        $client = $this->kfrAppt->Value("fk_clients");

        if( $mode == 'F' ) {
            if( !($pdfname = $raParms['filename']) )  goto done;
        }

        $pdf = new PDF_Invoice( 'P', 'mm', 'letter' );
        $pdf->AddPage();
        //$pdf->Image("w/img/CATS.png", 10, 10, 50);
        $pdf->Ln();
        $pdf->addSociete( "CATS",
                          "Collaborative Approach Therapy Services\n" .
                          "68 Dunbar Road South\n".
                          "Waterloo ON N2L2E3\n" );
        $pdf->fact_dev( "INVOICE", "" );
        //$pdf->temporaire( "Devis temporaire" );
        $pdf->addDate( date("Y-M-d" ) ); //03/12/2003");
        $pdf->addClient("CL" . $client);
        $pdf->addPageNumber("1");
        $pdf->addClientAdresse("68 Dunbar Rd South\nWaterloo ON N2L 2E3");
        $pdf->addReglement("Payment by cheque");
        $pdf->addEcheance( date( 'Y-M-d', time() + 3600*24*30) );
        $pdf->addNumTVA( $this->kfrAppt->Key() );
        $pdf->addReference("");
        $cols=array( "DETAILS"  => 143.9,
                     "HOURS"     => 22,
                     "AMOUNT"       => 30);
        $pdf->addCols( $cols);
        $cols=array( "DETAILS"  => "L",
                     "HOURS"     => "C",
                     "AMOUNT"       => "R");
        $pdf->addLineFormat( $cols);
        $pdf->addLineFormat($cols);

        $y    = 109;
        $line = array( "DETAILS"  => $this->kfrAppt->Value('session_desc')."\n",
                       "HOURS"     => Appointments::SessionHoursCalc($this->kfrAppt)['time_format'],
                       "AMOUNT"       => "$".number_format(Appointments::SessionHoursCalc($this->kfrAppt)['payment'],2));
        $size = $pdf->addLine( $y, $line );
        $y   += $size + 2;

//         $line = array( "DETAILS"  => "Consulting",
//                        "HOURS"     => "1",
//                        "AMOUNT"       => "60.00");
//         $size = $pdf->addLine( $y, $line );
//         $y   += $size + 2;

        $pdf->addCadreTVAs(); // Draw Tax Box at bottom of screen

        // invoice = array( "px_unit" => value,
        //                  "qte"     => qte,
        //                  "tva"     => code_tva );
        // tab_tva = array( "1"       => 19.6,
        //                  "2"       => 5.5, ... );
        // params  = array( "RemiseGlobale" => [0|1],
        //                      "remise_tva"     => [1|2...],  // {the discount applies on this VAT code}
        //                      "remise"         => value,     // {amount of the discount}
        //                      "remise_percent" => percent,   // {percentage discount on this VAT amount}
        //                  "FraisPort"     => [0|1],
        //                      "portTTC"        => value,     // amount of shipping costs
        //                                                     // VAT default = 19.6%
        //                      "portHT"         => value,     // amount of shipping costs
        //                      "portTVA"        => tva_value, // value of the VAT to be applied on the amount HT
        //                  "AccompteExige" => [0|1],
        //                      "accompte"         => value    // amount of the deposit (TTC)
        //                      "accompte_percent" => percent  // percentage of deposit (TTC)
        //                  "Note" => "texte"              // text
        $tot_prods = array( array ( "px_unit" => 120, "qte" => 1, "tva" => 1 ),
                            array ( "px_unit" =>  60, "qte" => 1, "tva" => 1 ));
        $tab_tva = array( "1"       => 19.6,
                          "2"       => 5.5);
        $pdf->Output( $mode, $pdfname );

        done:
    }
}

?>