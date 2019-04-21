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
                    $raParms['sort'] = 'name,date,d,c';
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

        $s = "<table cellpadding='10' border='1'>"
            ."<tr><th>Company</th><th><a href='{$_SERVER['PHP_SELF']}?sort=date'>Date</a></th><th><a href='{$_SERVER['PHP_SELF']}?sort=name'>Account</a></th><th>Debit</th><th>Credit</th></tr>"
            .SEEDCore_ArrayExpandRows( $raRows, "<tr><td>[[company_id]]</td><td>[[date]]</td><td>[[name]]</td><td> [[d]]</td><td> [[c]]</tr>" )
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


?>