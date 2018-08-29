<?php

class Assessments
{
    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function ScoreUI()
    {
        $s = "";
var_dump( $_REQUEST );
        $oForm = new SEEDCoreForm( "A" );

        $s .= "<form method='post'>";
        $s .= $oForm->HiddenKeyParm( 11 )
             ."<div>A ".$oForm->Text( 'fld', "" )."</div>";
        $oForm->IncRowNum();
        $s .= $oForm->HiddenKeyParm( 12 )
             ."<div>B ".$oForm->Text( 'fld', "" )."</div>";
        $oForm->IncRowNum();
        $s .= $oForm->HiddenKeyParm( 13 )
             ."<div>C ".$oForm->Text( 'fld', "" )."</div>";
        $s .= "<input type='submit'/>"
             ."</form>";

        return( $s );
    }
}



function AssessmentsScore( SEEDAppConsole $oApp )
{
    $o = new Assessments( $oApp );

    return( $o->ScoreUI() );
}