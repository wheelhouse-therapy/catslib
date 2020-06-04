<?php

/* People
 *
 * Copyright 2018 Collaborative Approach Therapy Services
 *
 * Manage information about people (clients, staff, external professionals)
 */

class People
{
    private $oApp;
    private $oPeopleDB;

    // Clients, Staff, Ext are stored in one array to make it easy to multiplex the code that manages them (just change the first index)
    private $raPeople = array();   // array( 'C' => array( client_id1 => array('P_first_name'=>"", ...), client_id2 => array( ...
                                   //        'S' => array( staff_id1 => array('P_first_name' ...
                                   //        'E' => array( ext_id1 => array('P_first_name' ...

    private $extra_fields = array( 'C' => array(),
                                   'S' => array( 'credentials', 'regnumber' ),
                                   'E' => array() );

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB( $oApp );
    }

    function GetClient( $k )  { return( $this->getCSE( 'C', $k ) ); }
    function GetStaff( $k )   { return( $this->getCSE( 'S', $k ) ); }
    function GetExt( $k )     { return( $this->getCSE( 'E', $k ) ); }

    function GetAge( $sCSE, $k, $atDate = "" )
    {
        $age = 0.0;
        $raC = $this->getCSE( $sCSE, $k );

        if( $raC['P_dob'] ) {
            $date1 = new DateTime( $raC['P_dob'] );
            $date2 = new DateTime( $atDate ?: "now" );

            $interval = $date2->diff($date1);
            $age = $interval->days / 365.25;
        }

        return( $age );
    }

    function GetAgeString( $sCSE, $k, $atDate = "" )
    {
        $age = "";
        $raC = $this->getCSE( $sCSE, $k );
        
        if( $raC['P_dob'] ) {
            $date1 = new DateTime( $raC['P_dob'] );
            $date2 = new DateTime( $atDate ?: "now" );
            
            $age = date_diff($date1, $date2)->format("%y Years %m Months");
        }
        
        return( $age );
    }
    
    private function getCSE( $c, $kCSE )
    /***********************************
       Look up the person and return their array of values. If not in the array, try to load them.
     */
    {
        if( !isset($this->raPeople[$c][$kCSE]) ) { $this->loadCSE( $c, $kCSE ); }
        return( isset($this->raPeople[$c][$kCSE]) ? $this->raPeople[$c][$kCSE] : array() );
    }

    private function loadCSE( $c, $kCSE )
    /************************************
       Load the given person from the PeopleDB
     */
    {
        switch( $c ) {
            case 'C':   $kfr = $this->oPeopleDB->GetKFR(ClientList::CLIENT, $kCSE);      break;
            case 'S':   $kfr = $this->oPeopleDB->GetKFR(ClientList::INTERNAL_PRO, $kCSE);     break;
            case 'E':   $kfr = $this->oPeopleDB->GetKFR(ClientList::EXTERNAL_PRO, $kCSE);     break;
            default:    return;
        }

        if( $kfr ) {
            $this->raPeople[$c][$kCSE] = $kfr->ValuesRA();
            // unpack the P_extra fields into the array
            $raExtra = SEEDCore_ParmsURL2RA( $kfr->Value('P_extra') );
            unset($this->raPeople[$c][$kCSE]['P_extra']);
            foreach( $this->extra_fields[$c] as $k ) {
                $this->raPeople[$c][$kCSE]["P_extra_$k"] = @$raExtra[$k];
            }
        }
    }
}

?>