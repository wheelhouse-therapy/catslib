<?php

require_once CATSLIB."invoice/pdfinvoice.php";

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
        $cols=array( "REFERENCE"    => 23,
                     "DESIGNATION"  => 78,
                     "QUANTITY"     => 22,
                     "AMOUNT"       => 30,
                     "TOTAL"        => 11 );
        $pdf->addCols( $cols);
        $cols=array( "REFERENCE"    => "L",
                     "DESIGNATION"  => "L",
                     "QUANTITY"     => "C",
                     "AMOUNT"       => "R",
                     "TOTAL"        => "C" );
        $pdf->addLineFormat( $cols);
        $pdf->addLineFormat($cols);

        $y    = 109;
        $line = array( "REFERENCE"    => "REF1",
                       "DESIGNATION"  => "Therapy services\n",
                       "QUANTITY"     => "1",
                       "AMOUNT"       => "120.00",
                       "TOTAL"        => "1" );
        $size = $pdf->addLine( $y, $line );
        $y   += $size + 2;

        $line = array( "REFERENCE"    => "REF2",
                       "DESIGNATION"  => "Consulting",
                       "QUANTITY"     => "1",
                       "AMOUNT"       => "60.00",
                       "TOTAL"        => "1" );
        $size = $pdf->addLine( $y, $line );
        $y   += $size + 2;

        $pdf->addCadreTVAs();

        // invoice = array( "px_unit" => value,
        //                  "qte"     => qte,
        //                  "tva"     => code_tva );
        // tab_tva = array( "1"       => 19.6,
        //                  "2"       => 5.5, ... );
        // params  = array( "RemiseGlobale" => [0|1],
        //                      "remise_tva"     => [1|2...],  // {la remise s'applique sur ce code TVA}
        //                      "remise"         => value,     // {montant de la remise}
        //                      "remise_percent" => percent,   // {pourcentage de remise sur ce montant de TVA}
        //                  "FraisPort"     => [0|1],
        //                      "portTTC"        => value,     // montant des frais de ports TTC
        //                                                     // par defaut la TVA = 19.6 %
        //                      "portHT"         => value,     // montant des frais de ports HT
        //                      "portTVA"        => tva_value, // valeur de la TVA a appliquer sur le montant HT
        //                  "AccompteExige" => [0|1],
        //                      "accompte"         => value    // montant de l'acompte (TTC)
        //                      "accompte_percent" => percent  // pourcentage d'acompte (TTC)
        //                  "Remarque" => "texte"              // texte
        $tot_prods = array( array ( "px_unit" => 120, "qte" => 1, "tva" => 1 ),
                            array ( "px_unit" =>  60, "qte" => 1, "tva" => 1 ));
        $tab_tva = array( "1"       => 19.6,
                          "2"       => 5.5);
        $params  = array( "RemiseGlobale" => 1,
                              "remise_tva"     => 1,       // {la remise s'applique sur ce code TVA}
                              "remise"         => 0,       // {montant de la remise}
                              "remise_percent" => 10,      // {pourcentage de remise sur ce montant de TVA}
                          "FraisPort"     => 1,
                              "portTTC"        => 10,      // montant des frais de ports TTC
                                                           // par defaut la TVA = 19.6 %
                              "portHT"         => 0,       // montant des frais de ports HT
                              "portTVA"        => 19.6,    // valeur de la TVA a appliquer sur le montant HT
                          "AccompteExige" => 1,
                              "accompte"         => 0,     // montant de l'acompte (TTC)
                              "accompte_percent" => 15,    // pourcentage d'acompte (TTC)
                          "Remarque" => "Avec un acompte, svp..." );

        $pdf->addTVAs( $params, $tab_tva, $tot_prods);
        $pdf->addCadreEurosFrancs();
        $pdf->Output( $mode, $pdfname );

        done:
    }
}

?>