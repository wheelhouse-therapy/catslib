<?php

/* Sensory Processing Measure
 *     Basic items are numbers 1-75.
 *     Aggregate items are section/column totals e.g. vision_total
 */


class AssessmentData_SPM extends AssessmentData
{
   //FIXME Finish implementing the new system 
    private $raRange = array(
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
    
    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
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
                $score = array_intersect_key($this->raScores, $range);
                break;
            case "total":
                $score = array_sum(array_diff_key($this->raScores, $doNotInclude));
                break;
        }

        $this->raScores[$item] = $score;    // cache for next lookup

        done:
        return( $score );
    }

    public function ComputePercentile( string $item ) : int
    {
        $percentile = 0;
        
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
                $percentile = $this->raPercentiles[$score][$item];
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
}

