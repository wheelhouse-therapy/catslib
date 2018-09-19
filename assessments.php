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

        $oForm = new SEEDCoreForm( "A" );

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



        $s .= "<form method='post'>";

        $s .= "<table width='100%'><tr>";
        foreach( $raColumns as $label => $sRange ) {
            $s .= "<td valign='top' width='12%'>".$this->column( $oForm, $label, $sRange )."</td>";
        }
        $s .= "</tr></table>";
        $s .= $this->getDataList($oForm,array("never","occasionaly","frequently","always"));
        $s .= "<input type='submit'></form><span id='total'></span>";
        $s .= "<script src='w/js/assessments.js'></script>";
        return( $s );
    }

    private function column( SEEDCoreForm $oForm, $heading, $sRange )
    {
        $s = "<table class='score-table'>"
            ."<tr>"
            ."<th colspan='2'>$heading<br/><br/></th>"
            ."</tr>";
        foreach( SEEDCore_ParseRangeStrToRA( $sRange ) as $n ) {
            $s .= $this->item( $oForm, $n );
        }
        $s .= "<tr><td></td><td><span class='sectionTotal'></span></td></tr>";
        $s .= "</table>";

        return( $s );
    }

    private function item( SEEDCoreForm $oForm, $n )
    {
        $s = "<tr><td class='score-num'>$n</td><td>".$oForm->Text("i$n","",array('attrs'=>"class='score-item s-i-$n' data-num='$n' list='options' required"))."<span class='score'></span></td></tr>";
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