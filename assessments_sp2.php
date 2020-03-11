<?php
class AssessmentData_SP2 extends AssessmentData {
    
    private const MUCH_LESS_THAN = 'ml';
    private const LESS_THAN = 'l';
    private const SAME_AS = 's';
    private const MORE_THAN = 'm';
    
    private $raRange = array(
      "SK"          => array(14,21,22,25,27,28,30,31,32,41,48,49,50,51,55,56,60,82,83),
      "AV"          => array(1,2,5,15,18,58,59,61,63,64,65,66,67,68,70,71,72,74,75,81),
      "SN"          => array(3,4,6,7,9,13,16,19,20,44,45,46,47,52,69,73,77,78,84),
      "RG"          => array(8,12,23,24,26,33,34,35,36,37,38,39,40,53,54,57,62,76,79,80,85,86),
      "auditory"    => array(1,2,3,4,5,6,7,8),
      "visual"      => array(9,10,11,12,13,14),
      "touch"       => array(16,17,18,19,20,21,22,23,24,25,26),
      "movement"    => array(27,28,29,30,31,32,33,34),
      "body"        => array(35,36,37,38,39,40,41,42),
      "oral"        => array(43,44,45,46,47,48,49,50,51,52),
      "conduct"     => array(53,54,55,56,57,58,59,60,61),
      "social"      => array(62,63,64,65,66,67,68,69,70,71,72,73,74,75),
      "attentional" => array(76,77,78,79,80,81,82,83,84,85)
    );
    
    private $interpretations = array(
        "SK"          => array(self::MUCH_LESS_THAN=>6,self::LESS_THAN=>20,self::SAME_AS=>48,self::MORE_THAN=>61),
        "AV"          => array(self::MUCH_LESS_THAN=>8,self::LESS_THAN=>21,self::SAME_AS=>47,self::MORE_THAN=>60),
        "SN"          => array(self::MUCH_LESS_THAN=>7,self::LESS_THAN=>18,self::SAME_AS=>43,self::MORE_THAN=>54),
        "RG"          => array(self::MUCH_LESS_THAN=>7,self::LESS_THAN=>19,self::SAME_AS=>44,self::MORE_THAN=>56),
        "auditory"    => array(self::MUCH_LESS_THAN=>3,self::LESS_THAN=>10,self::SAME_AS=>25,self::MORE_THAN=>32),
        "visual"      => array(self::MUCH_LESS_THAN=>5,self::LESS_THAN=>9, self::SAME_AS=>18,self::MORE_THAN=>22),
        "touch"       => array(self::MUCH_LESS_THAN=>1,self::LESS_THAN=>8, self::SAME_AS=>22,self::MORE_THAN=>29),
        "movement"    => array(self::MUCH_LESS_THAN=>2,self::LESS_THAN=>7, self::SAME_AS=>19,self::MORE_THAN=>25),
        "body"        => array(self::MUCH_LESS_THAN=>1,self::LESS_THAN=>5, self::SAME_AS=>16,self::MORE_THAN=>20),
        "oral"        => array(self::MUCH_LESS_THAN=>0,self::LESS_THAN=>8, self::SAME_AS=>25,self::MORE_THAN=>33),
        "conduct"     => array(self::MUCH_LESS_THAN=>2,self::LESS_THAN=>9, self::SAME_AS=>23,self::MORE_THAN=>30),
        "social"      => array(self::MUCH_LESS_THAN=>3,self::LESS_THAN=>13,self::SAME_AS=>32,self::MORE_THAN=>42),
        "attentional" => array(self::MUCH_LESS_THAN=>1,self::LESS_THAN=>9, self::SAME_AS=>25,self::MORE_THAN=>32)
    );
    
    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
    }
    
    public function MapRaw2Score( string $item, string $raw ) : int
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
    
    protected function columnsDef()
    {
        return( [
            'auditory'    => [ 'label'=>"Auditory<br/>Processing",            'colRange'=>"1-8"   ],
            'visual'      => [ 'label'=>"Visual<br/>Processing",              'colRange'=>"9-15"  ],
            'touch'       => [ 'label'=>"Touch<br/>Processing",               'colRange'=>"16-26" ],
            'movement'    => [ 'label'=>"Movement<br/>Processing",            'colRange'=>"27-34" ],
            'body'        => [ 'label'=>"Body<br/>Position<br/>Processing",   'colRange'=>"35-42" ],
            'oral'        => [ 'label'=>"Oral Sensory<br/>Processing",        'colRange'=>"43-52" ],
            'conduct'     => [ 'label'=>"Conduct",                            'colRange'=>"53-61" ],
            'social'      => [ 'label'=>"Social<br/>Emotional<br/>Responses", 'colRange'=>"62-75" ],
            'attentional' => [ 'label'=>"Attentional<br/>Responses",          'colRange'=>"76-86" ]
        ] );
    }
    
    public function ComputeScore( string $item ) : int
    {
        
        $score = 0;
        
        // Basic scores were computed and scored by the constructor.
        // Aggregate scores are computed below and cached here.
        if( isset($this->raScores[$item]) ) { $score = $this->raScores[$item]; goto done; }
        
        // Look up aggregate / computed score
        if(in_array($item, $this->getSections())){
            $score = array_sum(array_intersect_key($this->raScores, array_flip($this->raRange[$item])));
        }
        
        $this->raScores[$item] = $score;    // cache for next lookup
        
        done:
        return( $score );
    }
    
    public function getSections():array{
        return array_keys($this->raRange);
    }
    
    public function GetInterpretation(string $section):string{
        $ra = @$this->interpretations[$section]?:array(self::MUCH_LESS_THAN=>0,self::LESS_THAN=>0,self::SAME_AS=>0,self::MORE_THAN=>0);
        $score = $this->ComputeScore($section);
        $s = "";
        if($score<$ra[self::MUCH_LESS_THAN]){
            $s = "Much Less than others";
        }
        else if($score<$ra[self::LESS_THAN]){
            $s = "Less than others";
        }
        else if($score<$ra[self::SAME_AS]){
            $s = "Same as others";
        }
        else if($score<$ra[self::MORE_THAN]){
            $s = "More than others";
        }
        else{
            $s = "Much More than others";
        }
        
        return $s;
        
    }
    
}

class AssessmentUI_SP2 extends AssessmentUIColumns {
    
    function __construct( AssessmentData_SP2 $oData )
    {
        parent::__construct( $oData, $this->initColumnsDef() );
    }
    
    private function initColumnsDef()
    {
        $def = array(
            'auditory'    => [ 'label'=>"Auditory<br/>Processing",            'colRange'=>"1-8"   ],
            'visual'      => [ 'label'=>"Visual<br/>Processing",              'colRange'=>"9-15"  ],
            'touch'       => [ 'label'=>"Touch<br/>Processing",               'colRange'=>"16-26" ],
            'movement'    => [ 'label'=>"Movement<br/>Processing",            'colRange'=>"27-34" ],
            'body'        => [ 'label'=>"Body<br/>Position<br/>Processing",   'colRange'=>"35-42" ],
            'oral'        => [ 'label'=>"Oral Sensory<br/>Processing",        'colRange'=>"43-52" ],
            'conduct'     => [ 'label'=>"Conduct",                            'colRange'=>"53-61" ],
            'social'      => [ 'label'=>"Social<br/>Emotional<br/>Responses", 'colRange'=>"62-75" ],
            'attentional' => [ 'label'=>"Attentional<br/>Responses",          'colRange'=>"76-86" ]
        );
        return( $def );
    }
    
    function DrawScoreResults():string{
        $s = <<<Output
<table id='results'>
		<tr><th>Quadrants</th><th>Raw Score</th><th>Interpretation</th></tr>
		<tr><td>Seeking / Seeker</td><td>#SK</td><td>SK_interpretation</td></tr>
		<tr><td>Avoider</td><td>#AV</td><td>AV_interpretation</td></tr>
		<tr><td>Sensitivity</td><td>#SN</td><td>SN_interpretation</td></tr>
		<tr><td>Low Registration</td><td>#RG</td><td>RG_interpretation</td></tr>
		<tr><th style='border-top:2px solid black;'>Sensory Processing</th><th style='border-top:2px solid black;'>Raw Score</th><th style='border-top:2px solid black;'>Interpretation</th></tr>
		<tr><td>Auditory Processing</td><td>#auditory</td><td>auditory_interpretation</td></tr>
		<tr><td>Visual Processing</td><td>#visual</td><td>visual_interpretation</td></tr>
		<tr><td>Touch Processing</td><td>#touch</td><td>touch_interpretation</td></tr>
		<tr><td>Movement Processing</td><td>#movement</td><td>movement_interpretation</td></tr>
		<tr><td>Body Position Processing</td><td>#body</td><td>body_interpretation</td></tr>
		<tr><td>Oral Sensory Processing</td><td>#oral</td><td>oral_interpretation</td></tr>
		<tr><th style='border-top:2px solid black;'>Behavioural Sections</th><th style='border-top:2px solid black;'>Raw Score</th><th style='border-top:2px solid black;'>Interpretation</th></tr>
		<tr><td>Conduct</td><td>#conduct</td><td>conduct_interpretation</td></tr>
		<tr><td>Social Emotional</td><td>#social</td><td>social_interpretation</td></tr>
		<tr><td>Attentional</td><td>#attentional</td><td>attentional_interpretation</td></tr>
</table>
Output;
        foreach($this->oData->getSections() as $section){
            $s = str_replace(array("#".$section,$section."_interpretation"), array($this->oData->ComputeScore($section),$this->oData->GetInterpretation($section)), $s);
        }
        $s .= $this->DrawColFormTable( $this->oData->GetForm(), false );
        return $s;
    }
    
}