<?php

/* assessments_mabc
 *
 * Movement ABC assessment
 */

class Assessment_MABC extends Assessments {

    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oAsmt, $kAsmt, 'mabc' );
    }

    function DrawAsmtForm( int $kClient )
    {
        return( $this->DrawColumnForm2( $kClient ) );
    }

    function DrawAsmtResult()
    {
        return( $this->drawResult2() );
    }

    protected function GetScore( $n, $v ):int
    {
        return( 0 );
    }

    public function getTags(): array{
        //TODO Return Array of valid tags
    }

    protected function getTagField(String $tag):String{
        //TODO Return Values for valid tags
    }

    protected $raColumnRanges = array(
        "MD"  => ['1a'=>'md1a', '1b'=>'md1b', '2'=>'md2', '3'=>'md3'],
        "A&C" => ['1a'=>'ac1a', '1b'=>'ac1b', '2'=>'md2'],
        "Bal" => ['1'=>'bal1',  '2'=>'bal2',  '3a'=>'bal3a', '3b'=>'bal3b'],
    );

    protected $raPercentiles = array();
}

