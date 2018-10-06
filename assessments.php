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


        $s .= "<script>
                var raPercentilesSPM = ".json_encode($this->raPercentilesSPM).";
                var cols = ".json_encode(array_keys($this->raPercentilesSPM[8])).";
                </script>";

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
        $total = 0;
        foreach( SEEDCore_ParseRangeStrToRA( $sRange ) as $n ) {
            $s .= $this->item( $oForm, $n, $bEditable, $total );
        }
        $s .= "<tr><td></td><td><span class='sectionTotal'>".($bEditable ? "" : "<br/>Total: $total")."</span></td></tr>";
        $s .= "</table>";

        return( $s );
    }

    private function item( SEEDCoreForm $oForm, $n, $bEditable, &$total )
    {
        if( $bEditable ) {
            $score = "";
            $s = $oForm->Text("i$n","",array('attrs'=>"class='score-item s-i-$n' data-num='$n' list='options' required"));
        } else {
            $v = $oForm->Value( "i$n" );
            $score = $this->getScore( $n, $v );
            $s = "<strong style='border:1px solid #aaa; padding:0px 4px;background-color:#eee'>".$v."</strong>";
        }

        $total += intval($score);
        $s = "<tr><td class='score-num'>$n</td><td>".$s."<span class='score'>$score</span></td></tr>";
        return( $s );
    }

    private function getScore( $n, $v )
    {
        $score = "0";

        if( ($n >= 1 && $n <= 10) || $n == 57 ) {
            $score = array( 'n'=>4, 'o'=>3, 'f'=>2, 'a'=>1 )[$v];
        } else {
            $score = array( 'n'=>1, 'o'=>2, 'f'=>3, 'a'=>4 )[$v];
        }
        return( $score );
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



    public $raPercentilesSPM =
        array(
         '8'  => array( 'social'=>'',     'vision'=>'',     'hearing'=>'24',   'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'' ),
         '9'  => array( 'social'=>'',     'vision'=>'',     'hearing'=>'58',   'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'16' ),
         '10' => array( 'social'=>'16',   'vision'=>'',     'hearing'=>'73',   'touch'=>'',     'body'=>'16',   'balance'=>'',     'planning'=>'31' ),
         '11' => array( 'social'=>'16',   'vision'=>'18',   'hearing'=>'82',   'touch'=>'16',   'body'=>'42',   'balance'=>'16',   'planning'=>'42' ),
         '12' => array( 'social'=>'24',   'vision'=>'50',   'hearing'=>'88',   'touch'=>'42',   'body'=>'58',   'balance'=>'38',   'planning'=>'54' ),
         '13' => array( 'social'=>'31',   'vision'=>'66',   'hearing'=>'90',   'touch'=>'58',   'body'=>'69',   'balance'=>'54',   'planning'=>'62' ),
         '14' => array( 'social'=>'38',   'vision'=>'76',   'hearing'=>'92',   'touch'=>'69',   'body'=>'76',   'balance'=>'66',   'planning'=>'69' ),
         '15' => array( 'social'=>'46',   'vision'=>'82',   'hearing'=>'95',   'touch'=>'76',   'body'=>'82',   'balance'=>'76',   'planning'=>'76' ),
         '16' => array( 'social'=>'54',   'vision'=>'86',   'hearing'=>'95.5', 'touch'=>'82',   'body'=>'84',   'balance'=>'82',   'planning'=>'79' ),
         '17' => array( 'social'=>'62',   'vision'=>'90',   'hearing'=>'96',   'touch'=>'86',   'body'=>'86',   'balance'=>'86',   'planning'=>'84' ),
         '18' => array( 'social'=>'69',   'vision'=>'92',   'hearing'=>'97',   'touch'=>'90',   'body'=>'90',   'balance'=>'90',   'planning'=>'86' ),
         '19' => array( 'social'=>'73',   'vision'=>'93',   'hearing'=>'97.5', 'touch'=>'92',   'body'=>'92',   'balance'=>'92',   'planning'=>'90' ),
         '20' => array( 'social'=>'79',   'vision'=>'95.5', 'hearing'=>'98',   'touch'=>'93',   'body'=>'93',   'balance'=>'93',   'planning'=>'92' ),
         '21' => array( 'social'=>'84',   'vision'=>'96',   'hearing'=>'98.5', 'touch'=>'95',   'body'=>'95',   'balance'=>'95',   'planning'=>'93' ),
         '22' => array( 'social'=>'88',   'vision'=>'96',   'hearing'=>'99.5', 'touch'=>'95.5', 'body'=>'95.5', 'balance'=>'96',   'planning'=>'95' ),
         '23' => array( 'social'=>'90',   'vision'=>'97',   'hearing'=>'99.5', 'touch'=>'96',   'body'=>'96',   'balance'=>'97',   'planning'=>'95.5' ),
         '24' => array( 'social'=>'92',   'vision'=>'97.5', 'hearing'=>'99.5', 'touch'=>'96',   'body'=>'97',   'balance'=>'98',   'planning'=>'97' ),
         '25' => array( 'social'=>'93',   'vision'=>'98',   'hearing'=>'99.5', 'touch'=>'97',   'body'=>'97.5', 'balance'=>'98.5', 'planning'=>'97.5' ),
         '26' => array( 'social'=>'95',   'vision'=>'98.5', 'hearing'=>'99.5', 'touch'=>'98',   'body'=>'98',   'balance'=>'99.5', 'planning'=>'98.5' ),
         '27' => array( 'social'=>'95.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'98.5', 'body'=>'98.5', 'balance'=>'99.5', 'planning'=>'99' ),
         '28' => array( 'social'=>'97',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99',   'body'=>'99',   'balance'=>'99.5', 'planning'=>'99.5' ),
         '29' => array( 'social'=>'97.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99',   'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '30' => array( 'social'=>'98',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '31' => array( 'social'=>'99',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '32' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '33' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '34' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '35' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '36' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '37' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         '38' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         '39' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         '40' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         '41' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         '42' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         '43' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         '44' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
        );
}



function AssessmentsScore( SEEDAppConsole $oApp )
{
    $o = new Assessments( $oApp );

    return( $o->ScoreUI() );
}