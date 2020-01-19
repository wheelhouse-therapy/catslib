<?php

/* Sensory Processing Measure
 *     Basic items are numbers 1-75.
 *     Aggregate items are section/column totals e.g. vision_total
 */

class Assessment_SPM extends Assessments
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt, String $subclass = "" )
    {
        $oData = new AssessmentData_SPM( $this, $oAsmt, $kAsmt );
        $oUI = new AssessmentUI_SPM( $oData );

        //Use subclass when defining a sub class of this such as the classroom spm
        //Subclass ensures that scoreing does not break when subclassing
        parent::__construct( $oAsmt, 'spm'.$subclass, $oData, $oUI );
        $this->bUseDataList = true;     // the data entry form uses <datalist>
    }

    function DrawAsmtForm( int $kClient )
    {
        return( $this->oUI->DrawColumnForm( $kClient ) );
    }

    protected function InputOptions(){
        return array("never","occasionally","frequently","always");
    }

    protected function GetScore( $n, $v ):int
    /************************************
        Return the score for item n when it has the value v
     */
    {
        $score = "0";

        if(!$v){
            return 0;
        }

        if( ($n >= 1 && $n <= 10) || $n == 57 ) {
            $score = array( 'n'=>4, 'o'=>3, 'f'=>2, 'a'=>1 )[$v];
        } else {
            $score = array( 'n'=>1, 'o'=>2, 'f'=>3, 'a'=>4 )[$v];
        }
        return( $score );
    }

    public function getTags(): array{
        $raTags = array("social_percent","social_interpretation",
                        "vision_percent", "vision_interpretation", "vision_item",
                        "hearing_percent", "hearing_interpretation", "hearing_item",
                        "touch_percent", "touch_interpretation", "touch_item",
                        "taste_item",
                        "body_percent", "body_interpretation", "body_item",
                        "vestib_percent", "vestib_interpretation", "vestib_item",
                        "planning_percent", "planning_interpretation", "planning_item",
                        "total_percent", "total_interpretation"
        );
        return $raTags;
    }

    protected function getTagField(String $tag):String{

        //Array of section keys from tag keys
        $raSectionKeys = array("vestib" => "balance");

        $s = "Tag is Valid but Not implemented";
        $parts = explode("_", $tag,2);
        if(count($parts) == 2){
            switch($parts[1]){
                case "interpretation":
                    $percentile = $this->GetPercentile(@$raSectionKeys[$parts[0]]?:$parts[0]);
                    if ($percentile > 97){
                        $s = "Definite Dysfunction";
                    }
                    else if ($percentile < 84){
                        $s = "Typical";
                    }
                    else {
                        $s = "Some Problems";
                    }
                    break;
                case "percent":
                    $s = (1 - ($this->GetPercentile(@$raSectionKeys[$parts[0]]?:$parts[0])/100))*100 ."%";
                    break;
                case "item":
                    $s = $this->GetProblemItems(@$raSectionKeys[$parts[0]]?:$parts[0]);
                    break;
            }
        }
        return $s;
    }

    protected $items = array(
        '1' => "Plays with friends cooperatively (without lots of arguments)",
       '11' => "Seems bothered by light, especially bright lights (blinks, squints, cries, closes eyes, etc.)",
       '12' => "Has trouble finding an object when it is part of a group of other things",
       '13' => "Closes one eye or tips his/her head back when looking at something or someone",
       '14' => "Becomes distressed in unusual visual environments",
       '15' => "Has difficulty controlling eye movement when following objects like a ball with his/her eyes",
       '16' => "Has difficulty recognizing how objects are similar or different based on their colours, shapes or sizes",
       '17' => "Enjoys watching objects spin or move more than most kids his/her age",
       '18' => "Walks into objects or people as if they were not there",
       '19' => "Likes to flip light switches on and off repeatedly",
       '20' => "Dislikes certain types of lighting, such as midday sun, strobe lights, flickering lights or fluorescent lights",
       '21' => "Enjoys looking at moving objects out of the corner of his/her eye",
       '22' => "Seems bothered by ordinary household sounds, such as the vacuum cleaner, hair dryer or toilet flushing",
       '23' => "Responds negatively to loud noises by running away, crying, or holding hands over ears",
       '24' => "Appears not to hear certain sounds",
       '25' => "Seems disturbed by or intensely interested in sounds not usually noticed by other people",
       '26' => "Seems frightened of sounds that do not usually cause distress in other kids her/her age",
       '27' => "Seems easily distracted by background noises such as lawn mower outside, an air conditioner, a refrigerator, or fluorescent lights",
       '28' => "Likes to cause certain sounds to happen over and over again, such as by repeatedly flushing the toilet",
       '29' => "Shows distress at shrill or brassy sounds, such as whistles, party noisemakers, flutes and trumpets",
       '30' => "Pulls away from being touched lightly",
       '31' => "Seems to lack normal awareness of being touched",
       '32' => "Becomes distressed by the feel of new clothes",
       '33' => "Prefers to touch rather than to be touched",
       '34' => "Becomes distressed by having his/her fingernails or toenails cut",
       '35' => "Seems bothered when someone touches his/her face",
       '36' => "Avoids touching or playing with finger paint, paste, sand, clay, mud, glue, or other messy things",
       '37' => "Has an unusually high tolerance for pain",
       '38' => "Dislikes teeth brushing, more than other kids his/her age",
       '39' => "Seems to enjoy ensasations that should be painful, such as crashing onto the floor or hitting his/her own body",
       '40' => "Has trouble finding things in a pocket, bag or backpack using touch only (without looking)",
       '41' => "Likes to taste nonfood items, such as glue or paint",
       '42' => "Gags at the thought of an unappealing food, such as cooked spinach",
       '43' => "Likes to smell nonfood objects and people",
       '44' => "Shows distress at smell that other children do not notice",
       '45' => "Seems to ignore or not notice strong odors that other children react to",
       '46' => "Grasps objects so tightly that it is difficult to use the object",
       '47' => "Seems driven to seek activities such as pushing, pulling, dragging, lifting and jumping",
       '48' => "Seems unsure of how far to raise or lower the body during movement such as sitting down or stepping over an object",
       '49' => "Grasps objects so loosely that it is difficult to use the object",
       '50' => "Seems to exert too much pressure for the task, such as walking heavily, slamming doors, or pressing too hard when using pencils or crayons",
       '51' => "Jumps a lot",
       '52' => "Tends to pet animals with too much force",
       '53' => "Bumps or pushes other children",
       '54' => "Chews on toys, clothes or other objects more than other children",
       '55' => "Breaks things from pressing or pushing too hard on them",
       '56' => "Seems excessively fearful of movement such as going up and down stairs, riding swings, teeter-totters, slides or other playground equipment",
       '57' => "Doesn't seem to have good balance",
       '58' => "Avoids balance activities, such as walking on curbs or uneven ground",
       '59' => "Falls out of a chair when shifting his/her body",
       '60' => "Fails to catch self when falling",
       '61' => "Seems not to get dizzy when others usually do",
       '62' => "Spins and whirls his/her body more than other children",
       '63' => "Shows distress when his/her head is tilted away from vertical position",
       '64' => "Shows poor coordination and appears to be clumsy",
       '65' => "Seems afraid of riding in elevators or escalators",
       '66' => "Leans on other people or furniture when sitting or when trying to stand up",
       '67' => "Performs inconsistently in daily tasks",
       '68' => "Has trouble figuring out how to carry multiple objects at the same time",
       '69' => "Seems confused about how to put away materials and belongings in their correct places",
       '70' => "Fails to perform tasks in proper sequence, such as getting dressed or setting the table",
       '71' => "Fails to complete tasks with multiple steps",
       '72' => "Has difficulty imitating demonstrated actions, such as movement games or songs with motions",
       '73' => "Has difficulty building to copy a model, such as using Legos or blocks to build something that matches a model",
       '74' => "Has trouble coming up with ideas for new games and activities",
       '75' => "Tends to play the same activities over and over, rather than shift to new activities when given the chance",
    );

    function GetProblemItems( string $section ) : string
    {
        $s = "";

        if($range = @$this->oUI->GetColumnDef()[$section]['colRange'] ) {
            $range = SEEDCore_ParseRangeStrToRA( $range );
            foreach( $range as $k ) {
                if( $this->oData->ComputeScore($k) >=3 ) {
                    $s .= $this->items[$k]."\n";
                }
            }
        }

        return( $s );
    }

    function GetPercentile( string $section ) : float
    {
        return( $this->oData->ComputePercentile($section) );
    }

    protected $raColumnRanges = array(  // deprecated, use raColumnDef instead
            "Social<br/>participation" => "1-10",
            "Vision"                   => "11-21",
            "Hearing"                  => "22-29",
            "Touch"                    => "30-40",
            "Taste /<br/>Smell"        => "41-45",
            "Body<br/>Awareness"       => "46-55",
            "Balance<br/>and Motion"   => "56-66",
            "Planning<br/>and Ideas"   => "67-75"
        );
}


class AssessmentData_SPM extends AssessmentData
{
    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
    }

    //FIXME Finish implementing the new system
    public $raRange = array(
        'social'   => "1-10",
        'vision'   => "11-21",
        'hearing'  => "22-29",
        'touch'    => "30-40",
        'taste'    => "41-45",
        'body'     => "46-55",
        'balance'  => "56-66",
        'planning' => "67-75"
    );

    /** The percentiles that apply to each score, per column
     */
    public $raPercentiles =
    array(
         8  => array( 'social'=>'',     'vision'=>'',     'hearing'=>'24',   'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'' ),
         9  => array( 'social'=>'',     'vision'=>'',     'hearing'=>'58',   'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'16' ),
         10 => array( 'social'=>'16',   'vision'=>'',     'hearing'=>'73',   'touch'=>'',     'body'=>'16',   'balance'=>'',     'planning'=>'31' ),
         11 => array( 'social'=>'16',   'vision'=>'18',   'hearing'=>'82',   'touch'=>'16',   'body'=>'42',   'balance'=>'16',   'planning'=>'42' ),
         12 => array( 'social'=>'24',   'vision'=>'50',   'hearing'=>'88',   'touch'=>'42',   'body'=>'58',   'balance'=>'38',   'planning'=>'54' ),
         13 => array( 'social'=>'31',   'vision'=>'66',   'hearing'=>'90',   'touch'=>'58',   'body'=>'69',   'balance'=>'54',   'planning'=>'62' ),
         14 => array( 'social'=>'38',   'vision'=>'76',   'hearing'=>'92',   'touch'=>'69',   'body'=>'76',   'balance'=>'66',   'planning'=>'69' ),
         15 => array( 'social'=>'46',   'vision'=>'82',   'hearing'=>'95',   'touch'=>'76',   'body'=>'82',   'balance'=>'76',   'planning'=>'76' ),
         16 => array( 'social'=>'54',   'vision'=>'86',   'hearing'=>'95.5', 'touch'=>'82',   'body'=>'84',   'balance'=>'82',   'planning'=>'79' ),
         17 => array( 'social'=>'62',   'vision'=>'90',   'hearing'=>'96',   'touch'=>'86',   'body'=>'86',   'balance'=>'86',   'planning'=>'84' ),
         18 => array( 'social'=>'69',   'vision'=>'92',   'hearing'=>'97',   'touch'=>'90',   'body'=>'90',   'balance'=>'90',   'planning'=>'86' ),
         19 => array( 'social'=>'73',   'vision'=>'93',   'hearing'=>'97.5', 'touch'=>'92',   'body'=>'92',   'balance'=>'92',   'planning'=>'90' ),
         20 => array( 'social'=>'79',   'vision'=>'95.5', 'hearing'=>'98',   'touch'=>'93',   'body'=>'93',   'balance'=>'93',   'planning'=>'92' ),
         21 => array( 'social'=>'84',   'vision'=>'96',   'hearing'=>'98.5', 'touch'=>'95',   'body'=>'95',   'balance'=>'95',   'planning'=>'93' ),
         22 => array( 'social'=>'88',   'vision'=>'96',   'hearing'=>'99.5', 'touch'=>'95.5', 'body'=>'95.5', 'balance'=>'96',   'planning'=>'95' ),
         23 => array( 'social'=>'90',   'vision'=>'97',   'hearing'=>'99.5', 'touch'=>'96',   'body'=>'96',   'balance'=>'97',   'planning'=>'95.5' ),
         24 => array( 'social'=>'92',   'vision'=>'97.5', 'hearing'=>'99.5', 'touch'=>'96',   'body'=>'97',   'balance'=>'98',   'planning'=>'97' ),
         25 => array( 'social'=>'93',   'vision'=>'98',   'hearing'=>'99.5', 'touch'=>'97',   'body'=>'97.5', 'balance'=>'98.5', 'planning'=>'97.5' ),
         26 => array( 'social'=>'95',   'vision'=>'98.5', 'hearing'=>'99.5', 'touch'=>'98',   'body'=>'98',   'balance'=>'99.5', 'planning'=>'98.5' ),
         27 => array( 'social'=>'95.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'98.5', 'body'=>'98.5', 'balance'=>'99.5', 'planning'=>'99' ),
         28 => array( 'social'=>'97',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99',   'body'=>'99',   'balance'=>'99.5', 'planning'=>'99.5' ),
         29 => array( 'social'=>'97.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99',   'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         30 => array( 'social'=>'98',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         31 => array( 'social'=>'99',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         32 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         33 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         34 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         35 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         36 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         37 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         38 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         39 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         40 => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         41 => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         42 => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         43 => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         44 => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' )
    );

    public $raTotals = array("56"=>"16","57"=>"16","58"=>"16","59"=>"21","60"=>"27","61"=>"34","62"=>"38","63"=>"42","64"=>"50","65"=>"54","66"=>"58",
        "67"=>"62","68"=>"62","69"=>"66","70"=>"69","71"=>"73","72"=>"73","73"=>"76","74"=>"76","75"=>"79","76"=>"79","77"=>"82","78"=>"82","79"=>"84",
        "80"=>"84","81"=>"86","82"=>"86","83"=>"86","84"=>"88","85"=>"88","86"=>"88","87"=>"88","88"=>"90","89"=>"90","90"=>"90","91"=>"90","92"=>"92",
        "93"=>"92","94"=>"93","95"=>"93","96"=>"93","97"=>"93","98"=>"93","99"=>"95","100"=>"95","101"=>"95","102"=>"95","103"=>"95.5","104"=>"95.5",
        "105"=>"95.5","106"=>"96","107"=>"96","108"=>"96","109"=>"96","110"=>"97","111"=>"97","112"=>"97","113"=>"97","114"=>"97","115"=>"97","116"=>"97",
        "117"=>"97","118"=>"97","119"=>"97.5","120"=>"97.5","121"=>"97.5","122"=>"98","123"=>"98","124"=>"98","125"=>"98","126"=>"98","127"=>"98","128"=>"98",
        "129"=>"98.5","130"=>"98.5","131"=>"99","132"=>"99","133"=>"99.5","134"=>"99.5","135"=>"99.5","136"=>"99.5","137"=>"99.5","138"=>"99.5","139"=>"99.5",
        "140"=>"99.5","141"=>"99.5","142"=>"99.5","143"=>"99.5","144"=>"99.5","145"=>"99.5","146"=>"99.5","147"=>"99.5","148"=>"99.5","149"=>"99.5",
        "150"=>"99.5","151"=>"99.5","152"=>"99.5","153"=>"99.5","154"=>"99.5","155"=>"99.5","156"=>"99.5","157"=>"99.5","158"=>"99.5","159"=>"99.5",
        "160"=>"99.5","161"=>"99.5","162"=>"99.5","163"=>"99.5","164"=>"99.5","165"=>"99.5","166"=>"99.5","167"=>"99.5","168"=>"99.5","169"=>"99.5",
        "170"=>"99.5","171"=>"99.5");


    protected function columnsDef()
    {
        return( [
            'social'   => [ 'label'=>"Social<br/>participation", 'colRange'=>"1-10" ],
            'vision'   => [ 'label'=>"Vision",                   'colRange'=>"11-21" ],
            'hearing'  => [ 'label'=>"Hearing",                  'colRange'=>"22-29" ],
            'touch'    => [ 'label'=>"Touch",                    'colRange'=>"30-40" ],
            'taste'    => [ 'label'=>"Taste /<br/>Smell",        'colRange'=>"41-45" ],
            'body'     => [ 'label'=>"Body<br/>Awareness",       'colRange'=>"46-55" ],
            'balance'  => [ 'label'=>"Balance<br/>and Motion",   'colRange'=>"56-66" ],
            'planning' => [ 'label'=>"Planning<br/>and Ideas",   'colRange'=>"67-75" ]
        ] );
    }


    public function ComputeScore( string $item ) : int
    {

        // Array of items not to be included when computing total.
        // Since we use array_sum to calculate total we need to exclude totals to produce accurate results
        $doNotInclude = array("total","social_total","vision_total","hearing_total","touch_total","taste_total","body_total","balance_total","planning_total");

        $score = 0;

        // Basic scores were computed and scored by the constructor.
        // Aggregate scores are computed below and cached here.
        if( isset($this->raScores[$item]) ) { $score = $this->raScores[$item]; goto done; }

        // Look up aggregate / computed score
        switch( $item ) {
            case "social_total":
            case "vision_total":
            case "hearing_total":
            case "touch_total":
            case "taste_total":
            case "body_total":
            case "balance_total":
            case "planning_total":
                $range = SEEDCore_ParseRangeStrToRA( $this->raRange[explode("_", $item)[0]] );
                $score = array_sum(array_intersect_key($this->raScores, array_flip($range)));
                break;
            case "total":
                $score = array_sum(array_diff_key($this->raScores, array_flip($doNotInclude)));
                break;
        }

        $this->raScores[$item] = $score;    // cache for next lookup

        done:
        return( $score );
    }

    public function ComputePercentile( string $item ) : float
    {
        $percentile = 0.0;

        switch($item){
            case "social":
            case "vision":
            case "hearing":
            case "touch":
            case "taste":
            case "body":
            case "balance":
            case "planning":
                $score = $this->ComputeScore($item."_total");
                $percentile = floatval(@$this->raPercentiles[$score][$item]);
                break;
            case 'total':
                $score = $this->ComputeScore("total") - $this->ComputeScore("balance_total") - $this->ComputeScore("social_total");
                $percentile = floatval(@$this->raTotals[$score]);
                break;
        }

        return( $percentile );

    }

    function MapRaw2Score( string $item, string $raw ) : int
    /*************************************************
        Map raw -> score for basic items
     */
    {
        $score = 0;

        if( in_array($raw, ['n','o','f','a']) && is_numeric($item) ) {
            $score = (($item >= 1 && $item <= 10) || $item == 57 )
                        ? array( 'n'=>4, 'o'=>3, 'f'=>2, 'a'=>1 )[$raw]
                        : array( 'n'=>1, 'o'=>2, 'f'=>3, 'a'=>4 )[$raw];
        }

        return( $score );
    }
}


class AssessmentUI_SPM extends AssessmentUIColumns
{
    function __construct( AssessmentData_SPM $oData )
    {
        parent::__construct( $oData, $this->initColumnsDef() );
    }

    private function initColumnsDef()
    {
        $def = array(
            'social'   => [ 'label'=>"Social<br/>participation", 'colRange'=>"1-10" ],
            'vision'   => [ 'label'=>"Vision",                   'colRange'=>"11-21" ],
            'hearing'  => [ 'label'=>"Hearing",                  'colRange'=>"22-29" ],
            'touch'    => [ 'label'=>"Touch",                    'colRange'=>"30-40" ],
            'taste'    => [ 'label'=>"Taste /<br/>Smell",        'colRange'=>"41-45" ],
            'body'     => [ 'label'=>"Body<br/>Awareness",       'colRange'=>"46-55" ],
            'balance'  => [ 'label'=>"Balance<br/>and Motion",   'colRange'=>"56-66" ],
            'planning' => [ 'label'=>"Planning<br/>and Ideas",   'colRange'=>"67-75" ]
        );
        return( $def );
    }

    function DrawScoreResults() : string
    {
        $s = "";

        $oForm = $this->oData->GetForm();

        $raResults = SEEDCore_ParmsURL2RA( $oForm->Value('results') );
        foreach( $raResults as $k => $v ) {
            $oForm->SetValue( "i$k", $v );
        }

        $s .= "<table id='results'>
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

        $s .= SPMChart();

        $s .= $this->DrawColFormTable( $oForm, false );

        // Put the results in a js array for processing on the client
        $s .= "<script>
               var raResultsSPM = ".json_encode($raResults).";
               var raTotalsSPM = ".json_encode($this->oData->raTotals).";
               </script>";

        return( $s );
    }
}
