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
        $age = $this->getClientAge($kClient);

        if( !$age ) {
            return( "Please enter the client's date of birth on the client list" );
        }
        if( $age < 7.0 ) {
            $this->raColumnRanges = $this->raColumnRanges_ageBand1;
        } else if( $age < 11.0 ) {
            $this->raColumnRanges = $this->raColumnRanges_ageBand2;
        } else {
            $this->raColumnRanges = $this->raColumnRanges_ageBand3;
        }

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

    private function getClientAge( int $kClient, $atDate = "" )
    {
        // there's a better place to put a public GetClientAge() function, like in People()
        $age = 0.0;

        $oPeople = new People( $this->oAsmt->oApp );

        $raC = $oPeople->GetClient($kClient);

        if( $raC['P_dob'] ) {
            $date1 = new DateTime( $raC['P_dob'] );
            $date2 = new DateTime( $atDate ?: "now" );

            $interval = $date2->diff($date1);
            $age = $interval->days / 365.25;
        }

        return( $age );
    }

    public function getTags(): array{
        //TODO Return Array of valid tags
    }

    protected function getTagField(String $tag):String{
        //TODO Return Values for valid tags
    }

    protected $raColumnRanges = array();    // point this to one of the below
    // 3-6 years
    private $raColumnRanges_ageBand1 = array(
        "MD"  => ['1a'=>'md1a',  '1b'=>'md1b', '2'=>'md2',   '3'=>'md3'],
        "A&C" => ['1'=>'ac1',    '2'=>'ac2'],
        "Bal" => ['1a'=>'bal1a', '1b'=>'bal1b', '2'=>'bal2', '3'=>'bal3'],
        );
    // 7-10 years
    private $raColumnRanges_ageBand2 = array(
        "MD"  => ['1a'=>'md1a',  '1b'=>'md1b',  '2'=>'md2',  '3'=>'md3'],
        "A&C" => ['1'=>'ac1',    '2'=>'ac2'],
        "Bal" => ['1a'=>'bal1a', '1b'=>'bal1b', '2'=>'bal2', '3a'=>'bal3a', '3b'=>'bal3b'],
    );
    // 11-16 years
    private $raColumnRanges_ageBand3 = array(
        "MD"  => ['1a'=>'md1a', '1b'=>'md1b', '2'=>'md2',    '3'=>'md3'],
        "A&C" => ['1a'=>'ac1a', '1b'=>'ac1b', '2'=>'ac2'],
        "Bal" => ['1'=>'bal1',  '2'=>'bal2',  '3a'=>'bal3a', '3b'=>'bal3b'],
    );

    protected $raPercentiles = array();
}

