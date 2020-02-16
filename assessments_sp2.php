<?php
class AssessmentData_SP2 extends AssessmentData {
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
    
    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
    }
    
    protected function MapRaw2Score($item, $raw):int{
        return 0;
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
    
}