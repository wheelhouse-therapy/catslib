<?php

/* Sensory Processing Measure
 *     Basic items are numbers 1-75.
 *     Aggregate items are section/column totals e.g. vision_total
 */

class Assessment_SPM extends Assessment_SPMShared
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        $oData = new AssessmentData_SPM( $this, $oAsmt, $kAsmt );
        $oUI = new AssessmentUI_SPM( $oData );

        parent::__construct( $oAsmt, 'spm', $oData, $oUI );
        $this->bUseDataList = true;     // the data entry form uses <datalist>
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


    public $raItemDescriptions = [
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
       '26' => "Seems frightened of sounds that do not usually cause distress in other kids his/her age",
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
    ];


    // these are the same in spm and spmc so they are in Assessments_SPMShared
    function GetTotals()  { return( $this->oA->GetTotals() ); }
    function ComputeScore( string $item ) : int { return( $this->oA->ComputeScore($item) ); }
    function ComputePercentile( string $item ) : float { return( $this->oA->ComputePercentile( $item ) ); }
    function MapRaw2Score( string $item, string $raw ) : int { return( $this->oA->MapRaw2Score( $item, $raw ) ); }
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

    function DrawScoreResults() : string { return( $this->oData->oA->DrawScoreResults() ); }

}
