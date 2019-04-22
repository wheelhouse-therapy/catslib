<?php
class AssessmentData_AASP extends AssessmentData
{
    private $raRange = array(
        "Q1" => array("3","6","12","15","21","23","36","37","39","41","44","45","52","55","59"),
        "Q2" => array("2","4","8","10","14","17","19","28","30","32","40","42","47","50","58"),
        "Q3" => array("7","9","13","16","20","22","25","27","31","33","34","48","51","54","60"),
        "Q4" => array("1","5","11","18","24","26","29","35","38","43","46","49","53","56","57")
    );
    
    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
    }
    
    function MapRaw2Score( string $item, string $raw ) : int
    /*************************************************
     Map raw -> score for basic items
     */
    {
        $score = 0;
        
        if( in_array($raw, ['1','2','3','4','5']) && is_numeric($item) ) {
            $score = intval($raw);
        }
        
        return( $score );
    }
    
    public function ComputeScore( string $item ) : int
    {
        
        // Array of items not to be included when computing total.
        // Since we use array_sum to calculate total we need to exclude totals to produce accurate results
        $doNotInclude = array("total","Q1_total","Q2_total","Q3_total","Q4_total");
        
        $score = 0;
        
        // Basic scores were computed and scored by the constructor.
        // Aggregate scores are computed below and cached here.
        if( isset($this->raScores[$item]) ) { $score = $this->raScores[$item]; goto done; }
        
        // Look up aggregate / computed score
        switch( $item ) {
            case "Q1_total":
            case "Q2_total":
            case "Q3_total":
            case "Q4_total":
                $score = array_sum(array_intersect_key($this->raScores, array_flip($this->raRange[explode("_", $item)[0]])));
                break;
            case "total":
                $score = array_sum(array_diff_key($this->raScores, array_flip($doNotInclude)));
                break;
        }
        
        $this->raScores[$item] = $score;    // cache for next lookup
        
        done:
        return( $score );
    }
    
}

class AssessmentUI_AASP extends AssessmentUIColumns
{
    function __construct( AssessmentData_AASP $oData )
    {
        parent::__construct( $oData, $this->initColumnsDef() );
    }
    
    private function initColumnsDef()
    {
        $def = array(
            'taste'      => [ 'label'=>"Taste /<br/>Smell Processing", 'colRange'=>"1-8"  ],
            'movement'   => [ 'label'=>"Movement Processing",          'colRange'=>"9-16" ],
            'visual'     => [ 'label'=>"Visual Processing",            'colRange'=>"17-26" ],
            'touch'      => [ 'label'=>"Touch Processing",             'colRange'=>"27-39" ],
            'activity'   => [ 'label'=>"Activity Level",               'colRange'=>"40-49" ],
            'auditory'   => [ 'label'=>"Auditory Processing",          'colRange'=>"50-60" ],
        );
        return( $def );
    }
    
    function DrawScoreResults() : string
    {
        $s = "<table id='results'>
<thead><tr><th colspan='8' style='text-align:center'>Results of Adolescent Sensory Profile</th></tr></thead><tbody>
 <tr><td>Sensory Quadrant</td><td>Raw Score</td><td>Much Less than Most People</td><td>Less than Most People</td><td>Similar to Most People</td><td>More than Most People</td><td>Much More than Most People</td></tr>
 <tr><td>Low Registration</td><td>{{Q1_total}}&nbsp;/&nbsp;75</td><td>15&nbsp;-&nbsp;18</td><td>19&nbsp;-&nbsp;26</td><td>27&nbsp;-&nbsp;40</td><td>41&nbsp;-&nbsp;51</td><td>52&nbsp;-&nbsp;75</td></tr>
 <tr><td>Sensory Seeking</td><td>{{Q2_total}}&nbsp;/&nbsp;75</td><td>15&nbsp;-&nbsp;27</td><td>28&nbsp;-&nbsp;41</td><td>42&nbsp;-&nbsp;58</td><td>59&nbsp;-&nbsp;65</td><td>66&nbsp;-&nbsp;75</td></tr>
 <tr><td>Sensory Sensitvity</td><td>{{Q3_total}}&nbsp;/&nbsp;75</td><td>15&nbsp;-&nbsp;19</td><td>20&nbsp;-&nbsp;25</td><td>26&nbsp;-&nbsp;40</td><td>41&nbsp;-&nbsp;48</td><td>49&nbsp;-&nbsp;75</td></tr>
 <tr><td>Sensory Avoiding</td><td>{{Q4_total}}&nbsp;/&nbsp;75</td><td>15&nbsp;-&nbsp;18</td><td>19&nbsp;-&nbsp;25</td><td>26&nbsp;-&nbsp;40</td><td>41&nbsp;-&nbsp;48</td><td>49&nbsp;-&nbsp;75</td></tr>
</tbody></table>";
        $s = preg_replace_callback("/{{(.*?)}}/", function ($match){
            return $this->oData->ComputeScore($match[1]);
        }, $s);
        return( $s );
    }
    
    public function GetColumnDef() { return( $this->raColumnDef ); }
    
    protected $raColumnDef = array(
        'taste'      => [ 'label'=>"Taste /<br/>Smell Processing", 'colRange'=>"1-8"  ],
        'movement'   => [ 'label'=>"Movement Processing",          'colRange'=>"9-16" ],
        'visual'     => [ 'label'=>"Visual Processing",            'colRange'=>"17-26" ],
        'touch'      => [ 'label'=>"Touch Processing",             'colRange'=>"27-39" ],
        'activity'   => [ 'label'=>"Activity Level",               'colRange'=>"40-49" ],
        'auditory'   => [ 'label'=>"Auditory Processing",          'colRange'=>"50-60" ],
    );
}