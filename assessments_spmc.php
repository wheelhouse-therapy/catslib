<?php

/* Sensory Processing Measure for the Classroom
 *
 * Similar to SPM but different assessment items.
 */

class Assessment_SPM_Classroom extends Assessment_SPMShared
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        $oData = new AssessmentData_SPMC( $this, $oAsmt, $kAsmt );
        $oUI = new AssessmentUI_SPMC( $oData );

        parent::__construct( $oAsmt, 'spmc', $oData, $oUI );
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

        if( $n >= 1 && $n <= 10 ) {
            $score = array( 'n'=>4, 'o'=>3, 'f'=>2, 'a'=>1 )[$v];
        } else {
            $score = array( 'n'=>1, 'o'=>2, 'f'=>3, 'a'=>4 )[$v];
        }
        return( $score );
    }

    protected $raColumnRanges = array(
            "Social<br/>participation" => "1-10",
            "Vision"                   => "11-17",
            "Hearing"                  => "18-24",
            "Touch"                    => "25-32",
            "Taste /<br/>Smell"        => "33-36",
            "Body<br/>Awareness"       => "37-43",
            "Balance<br/>and Motion"   => "44-52",
            "Planning<br/>and Ideas"   => "53-62"
    );
}


class AssessmentData_SPMC extends AssessmentData
{
    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
    }


    /* The percentiles that apply to each score, per column
     */
    public $raPercentiles =
    array(
        '7'  => array( 'social'=>'',     'vision'=>'',     'hearing'=>'24',   'touch'=>'',     'body'=>'21',   'balance'=>'',     'planning'=>''     ),
        '8'  => array( 'social'=>'',     'vision'=>'42',   'hearing'=>'58',   'touch'=>'27',   'body'=>'54',   'balance'=>'',     'planning'=>''     ),
        '9'  => array( 'social'=>'',     'vision'=>'62',   'hearing'=>'73',   'touch'=>'62',   'body'=>'66',   'balance'=>'16',   'planning'=>''     ),
        '10' => array( 'social'=>'16',   'vision'=>'76',   'hearing'=>'82',   'touch'=>'79',   'body'=>'76',   'balance'=>'38',   'planning'=>'16'   ),
        '11' => array( 'social'=>'18',   'vision'=>'82',   'hearing'=>'86',   'touch'=>'86',   'body'=>'82',   'balance'=>'54',   'planning'=>'38'   ),
        '12' => array( 'social'=>'27',   'vision'=>'88',   'hearing'=>'90',   'touch'=>'90',   'body'=>'86',   'balance'=>'62',   'planning'=>'50'   ),
        '13' => array( 'social'=>'31',   'vision'=>'92',   'hearing'=>'93',   'touch'=>'93',   'body'=>'90',   'balance'=>'73',   'planning'=>'58'   ),
        '14' => array( 'social'=>'38',   'vision'=>'93',   'hearing'=>'95.5', 'touch'=>'95.5', 'body'=>'93',   'balance'=>'79',   'planning'=>'66'   ),
        '15' => array( 'social'=>'46',   'vision'=>'95.5', 'hearing'=>'97',   'touch'=>'96',   'body'=>'95',   'balance'=>'84',   'planning'=>'69'   ),
        '16' => array( 'social'=>'50',   'vision'=>'97.5', 'hearing'=>'98.5', 'touch'=>'97.5', 'body'=>'95.5', 'balance'=>'86',   'planning'=>'76'   ),
        '17' => array( 'social'=>'58',   'vision'=>'98.5', 'hearing'=>'99.5', 'touch'=>'98.5', 'body'=>'96',   'balance'=>'90',   'planning'=>'79'   ),
        '18' => array( 'social'=>'62',   'vision'=>'99',   'hearing'=>'99.5', 'touch'=>'99',   'body'=>'97',   'balance'=>'92',   'planning'=>'82'   ),
        '19' => array( 'social'=>'66',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'97.5', 'balance'=>'95',   'planning'=>'84'   ),
        '20' => array( 'social'=>'73',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'98.5', 'balance'=>'95.5', 'planning'=>'86'   ),
        '21' => array( 'social'=>'76',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'97',   'planning'=>'88'   ),
        '22' => array( 'social'=>'82',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'97.5', 'planning'=>'88'   ),
        '23' => array( 'social'=>'84',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'98',   'planning'=>'90'   ),
        '24' => array( 'social'=>'86',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'98.5', 'planning'=>'92'   ),
        '25' => array( 'social'=>'88',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'98.5', 'planning'=>'95'   ),
        '26' => array( 'social'=>'90',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99',   'planning'=>'95.5' ),
        '27' => array( 'social'=>'92',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'96'   ),
        '28' => array( 'social'=>'93',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'97.5' ),
        '29' => array( 'social'=>'95',   'vision'=>'',     'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'98'   ),
        '30' => array( 'social'=>'96',   'vision'=>'',     'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'98.5' ),
        '31' => array( 'social'=>'97',   'vision'=>'',     'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'98.5' ),
        '32' => array( 'social'=>'97.5', 'vision'=>'',     'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'99'   ),
        '33' => array( 'social'=>'98.5', 'vision'=>'',     'hearing'=>'',     'touch'=>'',     'body'=>'',     'balance'=>'99.5', 'planning'=>'99'   ),
        '34' => array( 'social'=>'99',   'vision'=>'',     'hearing'=>'',     'touch'=>'',     'body'=>'',     'balance'=>'99.5', 'planning'=>'99.5' ),
        '35' => array( 'social'=>'99.5', 'vision'=>'',     'hearing'=>'',     'touch'=>'',     'body'=>'',     'balance'=>'99.5', 'planning'=>'99.5' ),
        '36' => array( 'social'=>'99.5', 'vision'=>'',     'hearing'=>'',     'touch'=>'',     'body'=>'',     'balance'=>'99.5', 'planning'=>'99.5' ),
        '37' => array( 'social'=>'99.5', 'vision'=>'',     'hearing'=>'',     'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'99.5' ),
        '38' => array( 'social'=>'99.5', 'vision'=>'',     'hearing'=>'',     'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'99.5' ),
        '39' => array( 'social'=>'99.5', 'vision'=>'',     'hearing'=>'',     'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'99.5' ),
        '40' => array( 'social'=>'99.5', 'vision'=>'',     'hearing'=>'',     'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'99.5' )
    );


    protected function columnsDef()
    {
        // these have slightly different colRanges than SPM
        return( [
            'social'   => [ 'label'=>"Social<br/>participation", 'colRange'=>"1-10" ],
            'vision'   => [ 'label'=>"Vision",                   'colRange'=>"11-17" ],
            'hearing'  => [ 'label'=>"Hearing",                  'colRange'=>"18-24" ],
            'touch'    => [ 'label'=>"Touch",                    'colRange'=>"25-32" ],
            'taste'    => [ 'label'=>"Taste /<br/>Smell",        'colRange'=>"33-36" ],
            'body'     => [ 'label'=>"Body<br/>Awareness",       'colRange'=>"37-43" ],
            'balance'  => [ 'label'=>"Balance<br/>and Motion",   'colRange'=>"44-52" ],
            'planning' => [ 'label'=>"Planning<br/>and Ideas",   'colRange'=>"53-62" ]
        ] );
    }

    public $raItemDescriptions = [
        '1'  => "Works as part of a team; is helpful with others",
        '2'  => "Resolves peer conflicts without teacher intervention",
        '3'  => "Handles frustration without otubursts or aggressive behaviour",
        '4'  => "Willingly plays with peers in a variety of games and activities",
        '5'  => "Enters into play with peers without distrupting ongoing activity",
        '6'  => "Has friends and chooses to be with them when possible",
        '7'  => "Uses and understands humour when playing with peers",
        '8'  => "Maintains appropriate \"personal space\" (doesn't stand too close to others during conversation)",
        '9'  => "Maintains appropriate eye contact during conversations.",
        '10' => "Shifts conversation topics in accordance with peer interests; doesn't stay stuck on one topic.",
        '11' => "Squints, covers eyes, or complains about classroom lighting or bright sunlight",
        '12' => "Shows distress at the sight of moving objects",
        '13' => "Becomes distracted by nearby visual stimuli (pictures, items on walls, window, other children)",
        '14' => "During instruction or announcements, student looks around at peers, rather than looking at the person speaking or at blackboard",
        '15' => "Spins or flicks objects in front of eyes",
        '16' => "Stares intensely at people or objects",
        '17' => "Shows distress when lights are dimmed for movies or assemblies",
        '18' => "Shows distress at loud sounds (slamming door, electric pencil sharpener, PA announcement, fire drill)",
        '19' => "Shows distress at the sounds of singing or musical instruments",
        '20' => "Does not respond to voices or new sounds",
        '21' => "Cannot determine the location of sounds or voices",
        '22' => "Makes noises, hums, sings, or yells during quiet class time",
        '23' => "Speaks too loudly or makes excessive noise during transitions",
        '24' => "Yells, screams, or makes unusual noises to self",
        '25' => "Shows distress when hands or face are dirty (with glue, finger paints, food, dirt, etc)",
        '26' => "Does not tolerate dirt on hands or clothing, even briefly",
        '27' => "Shows distress when touching certain textures (classroom materials, utensils, sports equipment, etc.)",
        '28' => "Is distressed by accidental touch of peers (may last hout or withdraw)",
        '29' => "Does not respond to another's touch",
        '30' => "Seeks hot or cold temperatures by touching windows, other surfaces",
        '31' => "Touches classmates inappropriately during class and when standing in line",
        '32' => "Does not clean saliva or food from face",
        '33' => "Shows distress at the tastes or odours of different foods",
        '34' => "Does not notice strong or unusual odours (glue, paint, markers, etc.)",
        '35' => "Cannot distinguish between odours; does not prefer good smells to bad smells",
        '36' => "Tries to taste or lick objects or people",
        '37' => "Spills contents when opening containers",
        '38' => "Chews or mouths clothing, pencils, crayons, or classroom materials",
        '39' => "Moves chair roughly (shoves chair under desk or pulls out chair with too much force)",
        '40' => "Runs, hops, or bounces instead of walking",
        '41' => "Stomps or slaps feet on the ground when walking",
        '42' => "Jumps or stomps on stairs",
        '43' => "Slams doors shut or opens doors with excessive force",
        '44' => "Runs hand along wall when walking",
        '45' => "Wraps legs around chair legs",
        '46' => "Rocks in chair while seated at desk or table",
        '47' => "Fidgets when seated at desk or table",
        '48' => "Falls out of chair when seated at desk or table",
        '49' => "Leans on walls, furniture, or other people for support when standing",
        '50' => "When seated on floor, cannot sit up without support",
        '51' => "Slumps, leans on desk, or holds head up in hands while seated at desk",
        '52' => "Has poor coordination; appears clumsy",
        '53' => "Does not perform consistently in daily tasks; quality of work varies widely",
        '54' => "Is unable to solve problems effectively",
        '55' => "Bobbles or drops items when attempting to carry multiple objects",
        '56' => "Does not perform tasks in proper sequence",
        '57' => "Fails to complete tasks with multiple steps",
        '58' => "Has difficulty correctly inimitating demonstrations (movement games, songs with motions)",
        '59' => "Has difficulty completing tasks from a presented model",
        '60' => "Demonstrates limited imagination and creativity in play and free time (such as being unable to create new games)",
        '61' => "Plays repetitively during free time, does not expand or alter actvitiy when given opportunity",
        '62' => "Shows poor organization of materials in, on, or around desk area",
    ];

    // these are the same in spm and spmc so they are in Assessments_SPMShared
    function GetTotals()  { return( $this->oA->GetTotals() ); }
    function ComputeScore( string $item ) : int { return( $this->oA->ComputeScore($item) ); }
    function ComputePercentile( string $item ) : float { return( $this->oA->ComputePercentile( $item ) ); }
    function MapRaw2Score( string $item, string $raw ) : int { return( $this->oA->MapRaw2Score( $item, $raw ) ); }
}


class AssessmentUI_SPMC extends AssessmentUIColumns
{
    function __construct( AssessmentData_SPMC $oData )
    {
        parent::__construct( $oData, $this->initColumnsDef() );
    }

    private function initColumnsDef()
    {
        $def = array(
            'social'   => [ 'label'=>"Social<br/>participation", 'colRange'=>"1-10" ],
            'vision'   => [ 'label'=>"Vision",                   'colRange'=>"11-17" ],
            'hearing'  => [ 'label'=>"Hearing",                  'colRange'=>"18-24" ],
            'touch'    => [ 'label'=>"Touch",                    'colRange'=>"25-32" ],
            'taste'    => [ 'label'=>"Taste /<br/>Smell",        'colRange'=>"33-36" ],
            'body'     => [ 'label'=>"Body<br/>Awareness",       'colRange'=>"37-43" ],
            'balance'  => [ 'label'=>"Balance<br/>and Motion",   'colRange'=>"44-52" ],
            'planning' => [ 'label'=>"Planning<br/>and Ideas",   'colRange'=>"53-62" ]
        );
        return( $def );
    }

    function DrawScoreResults() : string { return( $this->oData->oA->DrawScoreResults() ); }
}
