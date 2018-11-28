<?php

$raAssessments = array(
    'spm' => "Sensory Processing Measure (SPM)",
    'aasp' => "Adolescent/Adult Sensory Profile (AASP)"
);


class Assessments
{
    protected $oApp;
    protected $asmtCode;

    // protected constructors are a way to enforce that this class cannot be instantiated by itself -- only a derived class can be used
    protected function __construct( SEEDAppConsole $oApp, $asmtCode )
    {
        $this->oApp = $oApp;
        $this->asmtCode = $asmtCode;
    }

    function ScoreUI()
    {
        $s = "";


        $s .= "<script>
                var raPercentilesSPM = ".json_encode($this->raPercentiles).";
                var cols = ".json_encode($this->Columns()).";
                var chars = ".json_encode($this->Inputs("script")).";
                </script>
                <link rel='stylesheet' href='w/css/asmt-overview.css' />";

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
            $oForm->Store();
            $kAsmt = $oForm->GetKey();
        }

        $s .= "<style>
               .score-table {}
               .score-table th { height:60px; }
               .score-num   { width:1em; }
               .score-item  { width:3em; }
               .score { padding-left: 5px; }
               </style>";

        $raColumns = $this->raColumnRanges;

        $raClients = $oPeopleDB->GetList( 'C', $clinics->isCoreClinic() ? "" : ("clinic= '".$clinics->GetCurrentClinic()."'") );

        $sAsmt = $sList = "";

        /* Draw the list of assessments
         */
        $sList = "<form action='{$_SERVER['PHP_SELF']}' method='post'><input type='hidden' name='new' value='1'/><input type='submit' value='New'/></form>";
        $raAssessments = $oAssessmentsDB->GetList( "AxCxP", "" );
        foreach( $raAssessments as $ra ) {
            $sStyle = $kAsmt == $ra['_key'] ? "font-weight:bold;color:green" : "";
            $sList .= "<div class='assessment-link'><a  style='$sStyle' href='{$_SERVER['PHP_SELF']}?kA={$ra['_key']}'>{$ra['P_first_name']} {$ra['P_last_name']}</a></div>";
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

            // Put the results in a js array for processing on the client
            $s .= "<script>
                   var raResultsSPM = ".json_encode($raResults).";
                   </script>";
        }


        $s .= "<div class='container-fluid'><div class='row'>"
                 ."<div class='col-md-2' style='border-right:1px solid #bbb'>$sList</div>"
                 ."<div class='col-md-10'>$sAsmt</div>"
             ."</div>";

        return( $s );
    }

    private function drawAsmt( SEEDCoreForm $oForm, $raColumns )
    {
        global $raAssessments;
        $sAsmt = "<h2>".$raAssessments[$this->asmtCode]."</h2>
                    <span style='margin-left: 20%' id='name'> Name: </span>
                        <span style='margin-left: 40%' id='DoB'> Date of Birth: </span><br />
                    <table id='results'>
                        <tr><th> Results </th><th> Score </th><th> Interpretation </th>
                            <th> Percentile </th><th> Reverse Percentile </th></tr>

                    </table>
                    <template id='rowtemp'>
                        <tr><td class='section'> </td><td class='score'> </td><td class='interp'> </td>
                            <td class='per'> </td><td class='rev'> </td></tr>

                    </template>
                    <script src='w/js/asmt-overview.js'></script>";

        /*$sReports = "";
        foreach( explode( "\n", $this->Reports ) as $sReport ) {
            if( !$sReport ) continue;
            $n = intval(substr($sReport,0,2));
            $report = substr($sReport,3);
            $v = $oForm->Value( "i$n" );
            if( $this->getScore( $n, $v ) > 2 ) $sReports .= $report."<br/>";
        }
        if( $sReports ) {
            $sAsmt .= "<div style='border:1px solid #aaa;margin:20px 30px;padding:10px'>$sReports</div>";
        }*/
        $sAsmt .= <<<spmChart
        <link rel='stylesheet' href='w/css/spmChart.css'>
                    <script src='w/js/spmChart.js'></script>
                    <svg id='chart' class='hidden'
	xmlns="http://www.w3.org/2000/svg"
	viewBox="0 0 263.19417 183.18527">
	<defs>
		<clipPath id='cutOff'>
			<rect width="250.43593" height="153.06314" x="18.888107" y="91.020195" />
		</clipPath>
	</defs>
  <g id="spmChart" transform="translate(-6.4124658,-89.416847)">
	<text class='percent' x="11.870281" y="92.607399">
		<tspan x="11.870281" y="92.607399">100%</tspan>
	</text>
	<text class='percent' x="11.870281" y="130.95422">
		<tspan x="11.870281" y="130.95422">90%</tspan>
	</text>
	<text class='percent' x="11.870281" y="169.30106">
		<tspan x="11.870281"y="169.30106">80%</tspan>
	</text>
	<text class='percent' x="11.870281" y="207.64787">
		<tspan x="11.870281" y="207.64787">70%</tspan>
	</text>
	<text class='percent' x="11.870281" y="245.99472">
		<tspan x="11.870281" y="245.99472">60%</tspan>
	</text>
	<path class='percLine' d="M 18.852836,205.81657 H 269.60663" />
	<path class='percLine' d="M 18.852836,167.51418 H 269.60663" />
	<path class='percLine' d="M 18.852836,129.40811 H 269.60663" />
	<path
		id="dd"
		class="guideline"
		d="M 18.852836,102.49993057250973 H 269.60663"
		onmouseover='grow(this); tip(event, "Definite Dysfunction (&ge;97%)")'
		onmouseout='shrink(this)' />
	<path
		id="sp"
		class="guideline"
		d="M 18.852836,156.07202987670894 H 269.60663"
		onmouseover='grow(this); tip(event, "Some Problems (&ge;83%)")'
		onmouseout='shrink(this)' />
	<rect
		id="box"
		width="250.43593"
		height="153.06314"
		x="18.888107"
		y="91.020195"
		class='percLine' />
	<text class='xText' x="-139.54134" y="206.36235">
		<tspan x="-139.54134" y="206.36235">Social Participation</tspan>
	</text>
	<text class='xText' x="-119.62378" y="226.27992">
		<tspan x="-119.62378" y="226.27992">Vision</tspan>
	</text>
	<text class='xText' y="246.19751" x="-99.706169">
		<tspan y="246.19751" x="-99.706169">Hearing</tspan>
	</text>
	<text class='xText' x="-79.788582" y="266.11511">
		<tspan x="-79.788582" y="266.11511">Touch</tspan>
	</text>
	<text class='xText' y="286.03268" x="-59.870987">
		<tspan y="286.03268" x="-59.870987">Body Awareness</tspan>
	</text>
	<text class='xText' x="-39.9534" y="305.95029">
		<tspan x="-39.9534" y="305.95029">Balance and Motion</tspan>
	</text>
	<text class='xText' y="325.86783" x="-20.03581">
		<tspan y="325.86783" x="-20.03581">Planning</tspan>
	</text>
	<text class='xText' x="-0.1182246" y="345.78546">
		<tspan x="-0.11822455" y="345.78546">Total</tspan>
	</text>
	<path d="m 46.682496,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 74.512158,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 102.34182,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 130.17149,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 158.00114,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 185.8308,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 213.66046,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 241.49012,242.34399 v 3.4787" class='tickmark percLine' />
	<path id='line'
		class='hidden'
		onmouseover='scoreGrow(); tip(event, "Scores", this)'
		onmouseout='shrink(this)'
		clip-path='url(#cutOff)' />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
  </g>
</svg>
<div id='info'>
	<span id='info-text'></span>
</div>
spmChart;

        $sAsmt .= "<table width='100%'><tr>";
        foreach( $raColumns as $label => $sRange ) {
            $sAsmt .= "<td valign='top' width='12%'>".$this->column( $oForm, $label, $sRange, false )."</td>";
        }
        $sAsmt .= "</tr><tr>";
        foreach( $raColumns as $label => $sRange ) {
            $sAsmt .= "<td valign='top' width='12%'>".$this->column_total( $oForm, $label, $sRange, false )."</td>";
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

        $sAsmt .= $this->getDataList($oForm,$this->Inputs("datalist"))
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
        //$s .= "<tr><td></td><td><span class='sectionTotal'>".($bEditable ? "" : "<br/>Total: $total")."</span></td></tr>";
        $s .= "</table>";

        return( $s );
    }

    private function column_total( SEEDCoreForm $oForm, $heading, $sRange, $bEditable )
    {
        $s = "";

        $total = 0;
        foreach( SEEDCore_ParseRangeStrToRA( $sRange ) as $n ) {
            $sDummy = $this->item( $oForm, $n, $bEditable, $total );
        }
        $s .= "<span class='sectionTotal'>".($bEditable ? "" : "<br/>Total: $total")."</span>";

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

    protected function Columns()
    {
        // Override to provide the column names
        return( array() );
    }

    private function Inputs($type){
        switch($type){
            case "datalist":
                return $this->InputOptions();
            case "script":
                $raOptions = array();
                foreach($this->InputOptions() as $option){
                    array_push($raOptions, substr($option, 0,1));
                }
        }
    }

    protected function InputOptions(){
        // Override to provide custom input options
        return array("1","2","3","4","5");
    }

}

class Assessment_SPM extends Assessments
{
    function __construct( SEEDAppConsole $oApp, $asmtCode )
    {
        parent::__construct( $oApp, $asmtCode );
    }

    protected function Columns()
    {
        return( array_keys($this->raPercentiles[8]) );
    }

    protected function InputOptions(){
        return array("never","occasionally","frequently","always");
    }
    
    protected $raColumnRanges = array(
            "Social<br/>participation" => "1-10",
            "Vision"                   => "11-21",
            "Hearing"                  => "22-29",
            "Touch"                    => "30-40",
            "Taste /<br/>Smell"        => "41-45",
            "Body<br/>Awareness"       => "46-55",
            "Balance<br/>and Motion"   => "56-66",
            "Planning<br/>and Ideas"   => "67-75"
        );


    /* The percentiles that apply to each score, per column
     */
    protected $raPercentiles =
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
         '44' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' )
        );

    protected $Reports = "
11	Seems bothered by light, especially bright lights (blinks, squints, cries, closes eyes, etc.)
12	Has trouble finding an object when it is part of a group of other things
13	Closes one eye or tips his/her head back when looking at something or someone
14	Becomes distressed in unusual visual environments
15	Has difficulty controlling eye movement when following objects like a ball with his/her eyes
16	Has difficulty recognizing how objects are similar or different based on their colours, shapes or sizes
17	Enjoys watching objects spin or move more than most kids his/her age
18	Walks into objects or people as if they were not there
19	Likes to flip light switches on and off repeatedly
20	Dislikes certain types of lighting, such as midday sun, strobe lights, flickering lights or fluorescent lights
21	Enjoys looking at moving objects out of the corner of his/her eye
22	Seems bothered by ordinary household sounds, such as the vacuum cleaner, hair dryer or toilet flushing
23	Responds negatively to loud noises by running away, crying, or holding hands over ears
24	Appears not to hear certain sounds
25	Seems disturbed by or intensely interested in sounds not usually noticed by other people
26	Seems frightened of sounds that do not usually cause distress in other kids her/her age
27	Seems easily distracted by background noises such as lawn mower outside, an air conditioner, a refrigerator, or fluorescent lights
28	Likes to cause certain sounds to happen over and over again, such as by repeatedly flushing the toilet
29	Shows distress at shrill or brassy sounds, such as whistles, party noisemakers, flutes and trumpets
30	Pulls away from being touched lightly
31	Seems to lack normal awareness of being touched
32	Becomes distressed by the feel of new clothes
33	Prefers to touch rather than to be touched
34	Becomes distressed by having his/her fingernails or toenails cut
35	Seems bothered when someone touches his/her face
36	Avoids touching or playing with finger paint, paste, sand, clay, mud, glue, or other messy things
37	Has an unusually high tolerance for pain
38	Dislikes teeth brushing, more than other kids his/her age
39	Seems to enjoy sensations that should be painful, such as crashing onto the floor or hitting his/her own body
40	Has trouble finding things in a pocket, bag or backpack using touch only (without looking)
41	Likes to taste nonfood items, such as glue or paint
42	Gags at the thought of an unappealing food, such as cooked spinach
43	Likes to smell nonfood objects and people
44	Shows distress at smell that other children do not notice
45	Seems to ignore or not notice strong odors that other children react to
46	Grasps objects so tightly that it is difficult to use the object
47	Seems driven to seek activities such as pushing, pulling, dragging, lifting and jumping
48	Seems unsure of how far to raise or lower the body during movement such as sitting down or stepping over an object
49	Grasps objects so loosely that it is difficult to use the object
50	Seems to exert too much pressure for the task, such as walking heavily, slamming doors, or pressing too hard when using pencils or crayons
51	Jumps a lot
52	Tends to pet animals with too much force
53	Bumps or pushes other children
54	Chews on toys, clothes or other objects more than other children
55	Breaks things from pressing or pushing too hard on them
56	Seems excessively fearful of movement such as going up and down stairs, riding swings, teeter-totters, slides or other playground equipment
57	Doesn't seem to have good balance
58	Avoids balance activities, such as walking on curbs or uneven ground
59	Falls out of a chair when shifting his/her body
60	Fails to catch self when falling
61	Seems not to get dizzy when others usually do
62	Spins and whirls his/her body more than other children
63	Shows distress when his/her head is tilted away from vertical position
64	Shows poor coordination and appears to be clumsy
65	Seems afraid of riding in elevators or escalators
66	Leans on other people or furniture when sitting or when trying to stand up
67	Performs inconsistently in daily tasks
68	Has trouble figuring out how to carry multiple objects at the same time
69	Seems confused about how to put away materials and belongings in their correct places
70	Fails to perform tasks in proper sequence, such as getting dressed or setting the table
71	Fails to complete tasks with multiple steps
72	Has difficulty imitating demonstrated actions, such as movement games or songs with motions
73	Has difficulty building to copy a model, such as using Legos or blocks to build something that matches a model
74	Has trouble coming up with ideas for new games and activities
75	Tends to play the same activities over and over, rather than shift to new activities when given the chance
";
}

class Assessment_AASP extends Assessments {

    function __construct( SEEDAppConsole $oApp, $asmtCode )
    {
        parent::__construct( $oApp, $asmtCode );
    }

    protected $raColumnRanges = array(
        "Taste/Smell"           => "1-8",
        "Movement"              => "9-16",
        "Visual"                => "17-26",
        "Touch"                 => "27-39",
        "Activity<br/>Level"    => "40-49",
        "Auditory"              => "50-60"
    );

}

class Assesment_MABC extends Assessments {
    
    protected $sAssesmentTitle = "Movement Assessment Battery for Children";
    
    protected $raColumnRanges = array(
        "MD"  => "1-4",
        "A&C" => "5-7",
        "Bal" => "8-11"
    );

}

function AssessmentsScore( SEEDAppConsole $oApp )
{
    global $raAssessments;

    $asmtType = $oApp->sess->SmartGPC( 'asmtType', array('','spm', 'aasp') );

    $s = "<form method='post'><select name='asmtType' onchange='submit();'>"
        ."<option value=''".($asmtType=='' ? 'selected' : '').">-- Choose Assessment Type --</option>";
    foreach( $raAssessments as $code => $title ) {
        $s .= "<option value='$code'".($asmtType==$code ? 'selected' : '').">$title</option>";
    }
    $s .= "</select></form>"
         ."<br/<br/>";

    switch( $asmtType ) {
        case 'spm':  $o = new Assessment_SPM($oApp, $asmtType);  break;
        case 'aasp': $o = new Assessment_AASP($oApp, $asmtType); break;
        default:     goto done;
    }

    $s .= $o->ScoreUI();

    done:
    return( $s );
}

?>