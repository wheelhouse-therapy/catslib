<?php

class AkauntingReports
{
    private $oApp;
    private $oAppAk = null;     // SEEDAppDB

    function __construct( SEEDAppConsole $oApp )
    {
        global $config_KFDB;

        $this->oApp = $oApp;

        if( isset($config_KFDB['akaunting']) ) {
            $this->oAppAk = new SEEDAppDB( $config_KFDB['akaunting'] );
        } else {
            $this->oApp->oC->AddErrMsg( "config_KFDB['akaunting'] not defined" );   // cats_page will show this at the top of the screen
        }

    }

    function DrawReport()
    {

        $s = "";

        if( !$this->oAppAk ) goto done;

        $raRows = $this->oAppAk->kfdb->QueryRowsRA( "select A.account_id,A.entry_type,A.debit as d,A.credit as c,B.name as name from d1r_double_entry_ledger A, d1r_double_entry_accounts B where A.account_id=B.id" );
        $s = "<table cellpadding='10' border='1'>".SEEDCore_ArrayExpandRows( $raRows, "<tr><td>[[name]]</td><td> [[d]]</td><td> [[c]]</tr>" )."</table>";




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


?>