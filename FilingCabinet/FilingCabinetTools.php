<?php

/* FilingCabinetTools
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * See FilingCabinet.php for information
 */

class FilingCabinetTools
{
    private $oApp;
    private $oFC;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oFC = new FilingCabinet( $oApp );
    }




    /* Manage the open-closed status of trees in the Manage Resources screen
     */
    function TreeListGet():array
    {
        $raTrees = $this->oApp->sess->VarGet("open");
        if( !$raTrees || !is_array($raTrees) ) $raTrees = [];
        return $raTrees;
    }

    function TreeListSet( $p )
    {
        $this->oApp->sess->VarSet('open', $p);
    }

    function TreeClose( $id )
    {//var_dump($id);
        $ra = $this->TreeListGet();
        $ra = SEEDCore_ArrayRemoveValue($ra, $id);
        $this->TreeListSet( $ra );
    }

    function TreeCloseAll()
    {
        $this->oApp->sess->VarUnSet("open");
    }
}
