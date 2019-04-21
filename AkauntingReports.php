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

        $raRows = $this->GetLedgerRA( $this->LedgerParmsFromRequest() );

        if($clinics = $this->clinics->getClinicsWithAkaunting()){
            $clinic = $this->oApp->sess->SmartGPC('Akaunting_clinic', array($this->clinics->GetCurrentClinic()));
            $clinicsDB = new ClinicsDB($this->oApp->kfdb);
            $sForm = "<form style='display:inline' id='companyForm'><select name='Akaunting_clinic' onChange=\"document.getElementById('companyForm').submit()\">";
            foreach($clinics as $option){
                $raData = $clinicsDB->GetClinic($option);
                if($option == $clinic){
                    $sForm .= SEEDCore_ArrayExpand($raData, "<option selected value='[[akaunting_company]]'>[[clinic_name]]</option>");
                }
                else{
                    $sForm .= SEEDCore_ArrayExpand($raData, "<option value='[[akaunting_company]]'>[[clinic_name]]</option>");
                }
            }
            $sForm .= "</select></form>";
        }
        
        $s = "<div id='companyFormContainer'>".$sForm."</div>"
            ."<table cellpadding='10' border='1'>"
            ."<tr><th><a href='{$_SERVER['PHP_SELF']}?Akaunting_sort=date'>Date</a></th><th><a href='{$_SERVER['PHP_SELF']}?Akaunting_sort=name'>Account</a></th><th>Debit</th><th>Credit</th></tr>"
            .($raRows?SEEDCore_ArrayExpandRows( $raRows, "<tr><td>[[date]]</td><td>[[code]] : [[name]]</td><td> [[d]]</td><td> [[c]]</tr>"):"Could not get Accounting Data" )
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


}

?>