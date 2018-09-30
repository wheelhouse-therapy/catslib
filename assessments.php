<?php

class Assessments
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function ScoreUI()
    {
        $s = "";

        $clinics = new Clinics($this->oApp);
        $clinics->GetCurrentClinic();
        $oPeopleDB = new PeopleDB( $this->oApp );
        $oAssessmentsDB = new AssessmentsDB( $this->oApp );
        $oForm = new KeyFrameForm( $oAssessmentsDB->Kfrel('A'), "A" );

        /* 1: bNew from the button in the assessments list (kA is irrelevant)
         * 2: kA non-zero from a link in the assessments list
         * 3: saved a new form so oForm-Key==0
         * 4: updated a form so oForm-Key<>0
         */
        $kAsmt = 0;
        if( !($bNew = SEEDInput_Int('new')) &&
            !($kAsmt = SEEDInput_Int('kA')) &&
            SEEDInput_Int('assessmentSave') )
        {
            $oForm->Load();

            $raItems = array();
            foreach( $oForm->GetValuesRA() as $k => $v ) {
                if( substr($k,0,1) == 'i' && ($item = intval(substr($k,1))) ) {
                    $raItems[$item] = $v;
                }
            }
            ksort($raItems);
            $oForm->SetValue( 'results', SEEDCore_ParmsRA2URL( $raItems ) );
            $this->oApp->kfdb->SetDebug(2);
            $oForm->Store();
            $this->oApp->kfdb->SetDebug(0);
            $kAsmt = $oForm->GetKey();
        }

        $s .= "<style>
               .score-table {}
               .score-table th { height:60px; }
               .score-num   { width:1em; }
               .score-item  { width:3em; }
               .score { padding-left: 5px; }
               </style>";


        $raColumns = array( "Social<br/>participation" => "1-10",
                            "Vision"                   => "11-21",
                            "Hearing"                  => "22-29",
                            "Touch"                    => "30-40",
                            "Taste /<br/>Smell"        => "41-45",
                            "Body<br/>Awareness"       => "46-55",
                            "Balance<br/>and Motion"   => "56-66",
                            "Planning<br/>and Ideas"   => "67-75",

        );

        $raClients = $oPeopleDB->GetList( 'C', $clinics->isCoreClinic() ? "" : ("clinic= '".$clinics->GetCurrentClinic()."'") );

        $sAsmt = $sList = "";

        /* Draw the list of assessments
         */
        $sList = "<form action='{$_SERVER['PHP_SELF']}' method='get'><input type='hidden' name='new' value='1'/><input type='submit' value='New'/></form>";
        $raAssessments = $oAssessmentsDB->GetList( "AxCxP", "" );
        foreach( $raAssessments as $ra ) {
            $sList .= "<div class='assessment-link'><a href='{$_SERVER['PHP_SELF']}?kA={$ra['_key']}'>{$ra['P_first_name']} {$ra['P_last_name']}</a></div>";
        }


        /* Draw the current assessment
         */
        if( $bNew ) {
            $sAsmt = $this->drawNewAsmtForm( $oForm, $raClients, $raColumns );
        } else if( $kAsmt ) {
            if( !$oForm->GetKey() ) {
                $oForm->SetKFR( $oAssessmentsDB->GetKFR( 'A', $kAsmt ) );
            }
            $raResults = SEEDCore_ParmsURL2RA( $oForm->Value('results') );
            foreach( $raResults as $k => $v ) {
                $oForm->SetValue( "i$k", $v );
            }
            $sAsmt = $this->drawAsmt( $oForm, $raColumns );
        }


        $s .= "<div class='container-fluid'><div class='row'>"
                 ."<div class='col-md-2' style='border-right:1px solid #bbb'>$sList</div>"
                 ."<div class='col-md-10'>$sAsmt</div>"
             ."</div>";

        return( $s );
    }

    private function drawAsmt( SEEDCoreForm $oForm, $raColumns )
    {
        $sAsmt = "<table width='100%'><tr>";
        foreach( $raColumns as $label => $sRange ) {
            $sAsmt .= "<td valign='top' width='12%'>".$this->column( $oForm, $label, $sRange, false )."</td>";
        }
        $sAsmt .= "</tr></table>";

        return( $sAsmt );
    }

    private function drawNewAsmtForm( SEEDCoreForm $oForm, $raClients, $raColumns )
    {
        $sAsmt = "";

        $opts = array();
        foreach( $raClients as $ra ) {
            $opts["{$ra['P_first_name']} {$ra['P_last_name']} ({$ra['_key']})"] = $ra['_key'];
        }
        $sAsmt .= "<form method='post'>"
                 ."<div>".$oForm->Select( 'fk_clients2', $opts, "" )." Choose a client</div>";

        $sAsmt .= "<table width='100%'><tr>";
        foreach( $raColumns as $label => $sRange ) {
            $sAsmt .= "<td valign='top' width='12%'>".$this->column( $oForm, $label, $sRange, true )."</td>";
        }
        $sAsmt .= "</tr></table>";

        $sAsmt .= $this->getDataList($oForm,array("never","occasionally","frequently","always"))
                 ."<input hidden name='assessmentSave' value='1'/>"
                 .$oForm->HiddenKey()
                 ."<input type='submit'></form>"
                 ."<span id='total'></span>"
                 ."<script src='w/js/assessments.js'></script>";
        return( $sAsmt );
    }


    private function column( SEEDCoreForm $oForm, $heading, $sRange, $bEditable )
    {
        $s = "<table class='score-table'>"
            ."<tr>"
            ."<th colspan='2'>$heading<br/><br/></th>"
            ."</tr>";
        foreach( SEEDCore_ParseRangeStrToRA( $sRange ) as $n ) {
            $s .= $this->item( $oForm, $n, $bEditable );
        }
        $s .= "<tr><td></td><td><span class='sectionTotal'></span></td></tr>";
        $s .= "</table>";

        return( $s );
    }

    private function item( SEEDCoreForm $oForm, $n, $bEditable )
    {
        if( $bEditable ) {
            $s = "<tr><td class='score-num'>$n</td>"
                ."<td>".$oForm->Text("i$n","",array('attrs'=>"class='score-item s-i-$n' data-num='$n' list='options' required"))."<span class='score'></span></td></tr>";
        } else {
            $s = "<tr><td class='score-num'>$n</td>"
                ."<td><strong style='border:1px solid #aaa; padding:0px 4px;background-color:#eee'>".$oForm->Value( "i$n" )."</strong><span class='score'></span></td></tr>";
        }
        return( $s );
    }

    private function getDataList(SEEDCoreForm $oForm,$raOptions = NULL){
        $s ="<datalist id='options'>";
        if($raOptions != NULL){
            foreach($raOptions as $option){
                $s .= $oForm->Option("", substr($option, 0,1), $option);
            }
        }
        $s .= "</datalist>";
        return $s;
    }

}



function AssessmentsScore( SEEDAppConsole $oApp )
{
    $o = new Assessments( $oApp );

    return( $o->ScoreUI() );
}