<?php

/* Sensory Processing Measure for the Classroom
 *
 * Similar to SPM but different assessment items.
 */


class Assessment_SPM_Classroom extends Assessment_SPM
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oAsmt, $kAsmt, 'c' );

        $this->bUseDataList = true;     // the data entry form uses <datalist>

// for now use the oData and oUI from SPM and overwrite raPercentiles, raRange, and raColumnsDef
//        // parent constructor does the right thing except it creates _SPM versions of these. So replace them.
//        $this->oData = new AssessmentData_SPMC( $this, $oAsmt, $kAsmt );
//        $this->oUI = new AssessmentUI_SPMC( $this->oData );


        //TODO Rework the data system to get rid of this dirty work arround
        $this->oData->raPercentiles = $this->raPercentiles;
        $this->oData->raRange = $this->raColumnRanges;
        $this->oUI->SetColumnsDef($this->initColumnsDef());

    // actually this array has to have these keys, not sure whether the array below is redundant
    $this->oData->raRange = array(
        'social'   => "1-10",
        'vision'   => "11-17",
        'hearing'  => "18-24",
        'touch'    => "25-32",
        'taste'    => "33-36",
        'body'     => "37-43",
        'balance'  => "44-52",
        'planning' => "53-62"
    );

    }

    protected function GetScore( $n, $v ):int
    /************************************
     Return the score for item n when it has the value v
     */
    {
        $score = "0";

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

    // move this to AssessmentUI_SPMC when it is created
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


    /* The percentiles that apply to each score, per column
     */
    protected $raPercentiles =
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

}


class AssessmentData_SPMC extends AssessmentData
{
    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
    }


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

}
