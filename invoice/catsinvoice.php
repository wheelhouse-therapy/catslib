<?php

require_once CATSLIB."invoice/pdfinvoice.php";

function CATSInvoice( SEEDAppSessionAccount $oApp, $apptId, $mode, $raParms = array() )
{
    $pdfname = "";

    $oApptDB = new AppointmentsDB( $oApp );   // for appointments saved in cats_appointments

    if( !($kfrAppt = $oApptDB->KFRel()->GetRecordFromDBKey( $apptId )) ) goto done;

    if( $mode == 'F' ) {
        if( !($pdfname = $raParms['filename']) )  goto done;
    }


    $pdf = new PDF_Invoice( 'P', 'mm', 'letter' );
    $pdf->AddPage();
    $pdf->addSociete( "CATS",
                      "Collaborative Approach Therapy Services\n" .
                      "68 Dunbar Road South\n".
                      "Waterloo ON N2L2E3\n" );
    $pdf->fact_dev( "INVOICE", "" );
    //$pdf->temporaire( "Devis temporaire" );
    $pdf->addDate( date("Y-M-d" ) ); //03/12/2003");
    $pdf->addClient("CL01");
    $pdf->addPageNumber("1");
    $pdf->addClientAdresse("68 Dunbar Rd South\nWaterloo ON N2L 2E3");
    $pdf->addReglement("Payment by cheque");
    $pdf->addEcheance( date( 'Y-M-d', time() + 3600*24*30) );
    $pdf->addNumTVA( $kfrAppt->Key() );
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

?>