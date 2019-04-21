<?php
class AssessmentData_AASP extends AssessmentData
{
    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
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
        $s = "RESULTS";
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