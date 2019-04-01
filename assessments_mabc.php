<?php

/* assessments_mabc
 *
 * Movement ABC assessment
 */

class AssessmentData_MABC extends AssessmentData
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oAsmt, $kAsmt );
    }

    public function ComputeScore( string $item ) : int
    {
        return( 0 );
    }

    public function ComputePercentile( string $item ) : int
    {
        return( 0 );
    }

    function MapRaw2Score( string $item, string $raw ) : int
    /*************************************************
        Map raw -> score for basic items
     */
    {
        $score = 0;

        return( $score );
    }
}


class AssessmentUI_MABC extends AssessmentUI
{
    function __construct( AssessmentData_MABC $oData )
    {
        parent::__construct( $oData );
    }

}


class Assessment_MABC extends Assessments {

    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oAsmt, $kAsmt, 'mabc' );
    }

    function DrawAsmtForm( int $kClient )
    {
        list($age,$sErr) = $this->setColumnRangesByAge( $kClient );

        return( $age ? $this->DrawColumnForm2( $kClient ) : $sErr );
    }

    private function setColumnRangesByAge( $kClient )
    {
        $sErr = "";

        $age = $this->getClientAge($kClient);

        if( !$age ) {
            $sErr = "Please enter the client's date of birth on the client list";
            goto done;
        }
        if( $age < 7.0 ) {
            $this->raColumnRanges = $this->raColumnRanges_ageBand1;
            $this->raColumnDef = $this->raColumnDef_ageBand1;
        } else if( $age < 11.0 ) {
            $this->raColumnRanges = $this->raColumnRanges_ageBand2;
            $this->raColumnDef = $this->raColumnDef_ageBand2;
        } else {
            $this->raColumnRanges = $this->raColumnRanges_ageBand3;
            $this->raColumnDef = $this->raColumnDef_ageBand3;
        }

        done:
        return( [$age,$sErr] );
    }

    function DrawAsmtResult()
    {
        $s = "";

        if( !$this->kfrAsmt ) goto done;

        $kClient = $this->kfrAsmt->Value('fk_clients2');
        list($age,$sErr) = $this->setColumnRangesByAge( $kClient );
        if( !$age ) {
            $s = $sErr;
            goto done;
        }

        $s = $this->drawResult2();

        done:
        return( $s );
    }

    protected function GetScore( $n, $v ):int
    {
        return( 0 );
    }

    private function getClientAge( int $kClient, $atDate = "" )
    {
        return( (new People( $this->oAsmt->oApp ))->GetAge( 'C', $kClient, $atDate ) );
    }

    public function getTags(): array{
        //TODO Return Array of valid tags
    }

    protected function getTagField(String $tag):String{
        //TODO Return Values for valid tags
    }

    function GetProblemItems( string $section ) : string
    {}
    function GetPercentile( string $section ) : string
    {}

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


    protected $raColumnDef = array();   // point this to one of the below
    // 3-6 years
    private $raColumnDef_ageBand1 = array(
        'md'  => ['label'=>"MD",  'cols' => ['1a'=>'md1a',  '1b'=>'md1b', '2'=>'md2',   '3'=>'md3'] ],
        'ac'  => ['label'=>"A&C", 'cols' => ['1'=>'ac1',    '2'=>'ac2'] ],
        'bal' => ['label'=>"Bal", 'cols' => ['1a'=>'bal1a', '1b'=>'bal1b', '2'=>'bal2', '3'=>'bal3'] ],
        );
    // 7-10 years
    private $raColumnDef_ageBand2 = array(
        'md'  => ['label'=>"MD",  'cols' => ['1a'=>'md1a',  '1b'=>'md1b',  '2'=>'md2',  '3'=>'md3'] ],
        'ac'  => ['label'=>"A&C", 'cols' => ['1'=>'ac1',    '2'=>'ac2'] ],
        'bal' => ['label'=>"Bal", 'cols' => ['1a'=>'bal1a', '1b'=>'bal1b', '2'=>'bal2', '3a'=>'bal3a', '3b'=>'bal3b'] ],
    );
    // 11-16 years
    private $raColumnDef_ageBand3 = array(
        'md'  => ['label'=>"MD",  'cols' => ['1a'=>'md1a', '1b'=>'md1b', '2'=>'md2',    '3'=>'md3'] ],
        'ac'  => ['label'=>"A&C", 'cols' => ['1a'=>'ac1a', '1b'=>'ac1b', '2'=>'ac2'] ],
        'bal' => ['label'=>"Bal", 'cols' => ['1'=>'bal1',  '2'=>'bal2',  '3a'=>'bal3a', '3b'=>'bal3b'] ],
    );


    protected $raPercentiles = array();
}

//TODO Rename keys and Move to correct spot
$scores = array(
    "age3:0-3:5" => array('one-Leg balance other leg' => array('map' => array(0 => 5,1 => 7,2 => 9,3 => 11,4 => 12,5 => 13,6 => 14,7 => 14,8 => 14,9 => 15,10 => 16,11 => 19,12 => 19,13 => 19,14 => 19,15 => 19,16 => 19,17 => 19,18 => 19,19 => 19,20 => 19,21 => 19,22 => 19,23 => 19,24 => 19,25 => 19,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
                          'Walking heels raised' => array('map' => array(0 => 5,1 => 5,2 => 6,3 => 7,4 => 8,5 => 8,6 => 9,7 => 11,8 => 12,9 => 12,10 => 13,11 => 13,12 => 14,13 => 14,14 => 14,15 => 17)),
                          'posting coins pref hand' => array('map' => array(10 => 14,11 => 13,12 => 12,13 => 11,14 => 10,15 => 9,16 => 8,17 => 6,18 => 1),'ceiling' => 19,'floor' => 10),
                          'Drawing Trail' => array('map' => array(2 => 14,3 => 13,4 => 12,5 => 11,6 => 10,7 => 9,8 => 9,9 => 8,10 => 7,11 => 7,12 => 6,13 => 6,14 => 6,15 => 5,16 => 4,17 => 1),'ceiling' => 18,'floor' => 2),
                          'posting coins non-pref hand' => array('map' => array(10 => 14,11 => 14,12 => 12,13 => 11,14 => 11,15 => 10,16 => 10,17 => 9,18 => 9,19 => 8,20 => 7,21 => 7,22 => 6,23 => 6,24 => 5,25 => 5,26 => 5,27 => 5,28 => 5,29 => 1),'ceiling' => 30,'floor' => 10),
                          'one-leg balance best leg' => array('map' => array(0 => 4,1 => 6,2 => 7,3 => 8,4 => 10,5 => 11,6 => 12,7 => 13,8 => 13,9 => 14,10 => 14,11 => 14,12 => 14,13 => 14,14 => 15,15 => 16,16 => 16,17 => 19,18 => 19,19 => 19,20 => 19,21 => 19,22 => 19,23 => 19,24 => 19,25 => 19,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
                          'threading beads' => array('map' => array(27 => 15,28 => 15,29 => 15,30 => 15,31 => 15,32 => 15,33 => 14,34 => 14,35 => 14,36 => 13,37 => 13,38 => 13,39 => 13,40 => 13,41 => 12,42 => 12,43 => 12,44 => 12,45 => 12,46 => 12,47 => 12,48 => 11,49 => 11,50 => 11,51 => 11,52 => 11,53 => 10,54 => 10,55 => 10,56 => 10,57 => 9,58 => 9,59 => 9,60 => 9,61 => 9,62 => 9,63 => 9,64 => 9,65 => 9,66 => 8,67 => 8,68 => 8,69 => 8,70 => 8,71 => 7,72 => 7,73 => 7,74 => 7,75 => 7,76 => 7,77 => 7,78 => 7,79 => 6,80 => 6,81 => 6,82 => 6,83 => 6,84 => 5,85 => 5,86 => 5,87 => 5,88 => 4,89 => 4,90 => 4,91 => 4,92 => 4,93 => 4,94 => 4,95 => 4,96 => 1),'ceiling' => 97,'floor' => 27),
                          'Jumping on mats' => array('map' => array(0 => 4,1 => 6,2 => 7,3 => 9,4 => 11,5 => 14)),
                          'Catching Beanbag' => array('map' => array(0 => 5,1 => 6,2 => 7,3 => 8,4 => 9,5 => 11,6 => 12,7 => 15,8 => 19,9 => 19,10 => 19)),
                          'Throwing beanbag on mat' => array('map' => array(0 => 6,1 => 7,2 => 8,3 => 9,4 => 11,5 => 14,6 => 15,7 => 19,8 => 19,9 => 19,10 => 19))),
    "age3:6-3:11" => array('one-Leg balance other leg' => array('map' => array(0 => 5,1 => 7,2 => 8,3 => 11,4 => 11,5 => 12,6 => 13,7 => 14,8 => 14,9 => 15,10 => 15,11 => 15,12 => 16,13 => 17,14 => 17,15 => 19,16 => 19,17 => 19,18 => 19,19 => 19,20 => 19,21 => 19,22 => 19,23 => 19,24 => 19,25 => 19,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
                           'Walking heels raised' => array('map' => array(0 => 5,1 => 5,2 => 5,3 => 6,4 => 7,5 => 7,6 => 8,7 => 9,8 => 9,9 => 9,10 => 9,11 => 9,12 => 11,13 => 11,14 => 12,15 => 13)),
                           'posting coins pref hand' => array('map' => array(7 => 15,8 => 15,9 => 13,10 => 12,11 => 11,12 => 10,13 => 9,14 => 8,15 => 7,16 => 6,17 => 5,18 => 1),'ceiling' => 19,'floor' => 7),
                           'Drawing Trail' => array('map' => array(0 => 14,1 => 13,2 => 13,3 => 12,4 => 12,5 => 11,6 => 10,7 => 9,8 => 8,9 => 7,10 => 6,11 => 6,12 => 6,13 => 5,14 => 5,15 => 5,16 => 4,17 => 1),'ceiling' => 18),
                           'posting coins non-pref hand' => array('map' => array(10 => 13,11 => 12,12 => 12,13 => 11,14 => 10,15 => 9,16 => 8,17 => 7,18 => 7,19 => 6,20 => 6,21 => 5,22 => 4,23 => 3,24 => 3,25 => 3,26 => 1),'ceiling' => 27,'floor' => 10),
                           'one-leg balance best leg' => array('map' => array(0 => 4,1 => 6,2 => 7,3 => 8,4 => 10,5 => 11,6 => 11,7 => 12,8 => 12,9 => 12,10 => 13,11 => 13,12 => 13,13 => 13,14 => 14,15 => 14,16 => 14,17 => 14,18 => 15,19 => 15,20 => 16,21 => 17,22 => 17,23 => 17,24 => 19,25 => 19,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
                           'threading beads' => array('map' => array(24 => 14,25 => 14,26 => 14,27 => 14,28 => 14,29 => 13,30 => 13,31 => 13,32 => 13,33 => 13,34 => 13,35 => 13,36 => 12,37 => 12,38 => 12,39 => 11,40 => 11,41 => 10,42 => 10,43 => 10,44 => 10,45 => 10,46 => 10,47 => 10,48 => 9,49 => 9,50 => 9,51 => 9,52 => 9,53 => 9,54 => 9,55 => 9,56 => 9,57 => 8,58 => 8,59 => 8,60 => 8,61 => 8,62 => 8,63 => 8,64 => 8,65 => 8,66 => 7,67 => 7,68 => 7,69 => 7,70 => 7,71 => 7,72 => 7,73 => 7,74 => 6,75 => 6,76 => 6,77 => 6,78 => 6,79 => 5,80 => 5,81 => 5,82 => 4,83 => 4,84 => 4,85 => 4,86 => 4,87 => 4,88 => 4,89 => 4,90 => 4,91 => 4,92 => 4,93 => 4,94 => 4,95 => 4,96 => 1),'ceiling' => 97,'floor' => 24),
                           'Jumping on mats' => array('map' => array(0 => 3,1 => 4,2 => 5,3 => 6,4 => 8,5 => 12)),
                           'Catching Beanbag' => array('map' => array(0 => 3,1 => 4,2 => 5,3 => 6,4 => 7,5 => 8,6 => 10,7 => 12,8 => 12,9 => 15,10 => 19)),
                           'Throwing beanbag on mat' => array('map' => array(0 => 5,1 => 7,2 => 8,3 => 9,4 => 11,5 => 12,6 => 14,7 => 15,8 => 17,9 => 19,10 => 19))),
    "age4:0-4:5" => array('one-Leg balance other leg' => array('map' => array(0 => 6,1 => 6,2 => 7,3 => 8,4 => 10,5 => 10,6 => 11,7 => 11,8 => 12,9 => 12,10 => 12,11 => 13,12 => 13,13 => 14,14 => 14,15 => 14,16 => 14,17 => 14,18 => 15,19 => 15,20 => 15,21 => 15,22 => 15,23 => 15,24 => 17,25 => 17,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
                          'Walking heels raised' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 6,4 => 7,5 => 7,6 => 8,7 => 8,8 => 8,9 => 9,10 => 9,11 => 9,12 => 9,13 => 9,14 => 10,15 => 13)),
                          'posting coins pref hand' => array('map' => array(7 => 15,8 => 14,9 => 12,10 => 10,11 => 9,12 => 8,13 => 7,14 => 6,15 => 5,16 => 5,17 => 3,18 => 1),'ceiling' => 19,'floor' => 7),
                          'Drawing Trail' => array('map' => array(0 => 13,1 => 12,2 => 11,3 => 10,4 => 10,5 => 9,6 => 9,7 => 8,8 => 8,9 => 7,10 => 6,11 => 6,12 => 6,13 => 5,14 => 5,15 => 4,16 => 4,17 => 1),'ceiling' => 18),
                          'posting coins non-pref hand' => array('map' => array(9 => 14,10 => 13,11 => 12,12 => 10,13 => 8,14 => 7,15 => 6,16 => 5,17 => 5,18 => 5,19 => 5,20 => 4,21 => 4,22 => 3,23 => 3,24 => 3,25 => 1),'ceiling' => 26,'floor' => 9),
                          'one-leg balance best leg' => array('map' => array(0 => 4,1 => 5,2 => 6,3 => 6,4 => 7,5 => 8,6 => 9,7 => 9,8 => 10,9 => 11,10 => 11,11 => 12,12 => 12,13 => 12,14 => 12,15 => 13,16 => 13,17 => 13,18 => 13,19 => 14,20 => 14,21 => 14,22 => 14,23 => 14,24 => 14,25 => 14,26 => 14,27 => 16,28 => 16,29 => 16,30 => 16)),
                          'threading beads' => array('map' => array(22 => 14,23 => 14,24 => 14,25 => 13,26 => 13,27 => 12,28 => 12,29 => 12,30 => 11,31 => 11,32 => 10,33 => 10,34 => 10,35 => 10,36 => 10,37 => 9,38 => 9,39 => 9,40 => 8,41 => 8,42 => 8,43 => 8,44 => 8,45 => 8,46 => 8,47 => 8,48 => 8,49 => 7,50 => 7,51 => 7,52 => 7,53 => 7,54 => 7,55 => 7,56 => 6,57 => 6,58 => 6,59 => 6,60 => 6,61 => 6,62 => 6,63 => 6,64 => 5,65 => 5,66 => 5,67 => 5,68 => 5,69 => 5,70 => 5,71 => 5,72 => 5,73 => 5,74 => 5,75 => 5,76 => 5,77 => 5,78 => 4,79 => 4,80 => 3,81 => 3,82 => 3,83 => 3,84 => 3,85 => 3,86 => 1),'ceiling' => 87,'floor' => 22),
                          'Jumping on mats' => array('map' => array(0 => 2,1 => 3,2 => 3,3 => 4,4 => 6,5 => 12)),
                          'Catching Beanbag' => array('map' => array(0 => 3,1 => 4,2 => 5,3 => 6,4 => 7,5 => 8,6 => 9,7 => 10,8 => 11,9 => 12,10 => 17)),
                          'Throwing beanbag on mat' => array('map' => array(0 => 5,1 => 6,2 => 7,3 => 8,4 => 9,5 => 11,6 => 12,7 => 14,8 => 17,9 => 19,10 => 19))),
    "age4:6-4:11" => array('one-Leg balance other leg' => array('map' => array(0 => 3,1 => 3,2 => 6,3 => 7,4 => 8,5 => 8,6 => 9,7 => 9,8 => 9,9 => 10,10 => 10,11 => 10,12 => 10,13 => 11,14 => 11,15 => 12,16 => 12,17 => 13,18 => 13,19 => 13,20 => 14,21 => 14,22 => 14,23 => 14,24 => 14,25 => 14,26 => 14,27 => 15,28 => 15,29 => 18,30 => 18)),
                           'Walking heels raised' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 4,4 => 4,5 => 4,6 => 5,7 => 7,8 => 8,9 => 8,10 => 8,11 => 9,12 => 9,13 => 9,14 => 10,15 => 13)),
                           'posting coins pref hand' => array('map' => array(7 => 16,8 => 15,9 => 12,10 => 11,11 => 10,12 => 8,13 => 6,14 => 6,15 => 6,16 => 2,18 => 1),'ceiling' => 19,'floor' => 7),
                           'Drawing Trail' => array('map' => array(0 => 13,1 => 12,2 => 9,3 => 7,4 => 6,5 => 4,6 => 1),'ceiling' => 7),
                           'posting coins non-pref hand' => array('map' => array(9 => 14,10 => 13,11 => 11,12 => 10,13 => 9,14 => 7,15 => 6,16 => 5,17 => 4,18 => 4,19 => 4,20 => 4,21 => 4,22 => 3,26 => 1),'ceiling' => 27,'floor' => 9),
                           'one-leg balance best leg' => array('map' => array(0 => 4,1 => 4,2 => 4,3 => 5,4 => 6,5 => 6,6 => 7,7 => 7,8 => 8,9 => 8,10 => 9,11 => 9,12 => 9,13 => 9,14 => 10,15 => 10,16 => 10,17 => 10,18 => 11,19 => 11,20 => 11,21 => 11,22 => 12,23 => 12,24 => 12,25 => 12,26 => 13,27 => 13,28 => 13,29 => 14,30 => 15)),
                           'threading beads' => array('map' => array(18 => 15,19 => 15,20 => 15,21 => 15,22 => 14,23 => 14,24 => 13,25 => 13,26 => 12,27 => 12,28 => 11,29 => 11,30 => 11,31 => 11,32 => 11,33 => 10,34 => 10,35 => 10,36 => 10,37 => 9,38 => 9,39 => 9,40 => 8,41 => 8,42 => 7,43 => 7,44 => 6,45 => 6,46 => 6,47 => 5,48 => 5,49 => 5,50 => 5,51 => 5,52 => 5,53 => 5,54 => 5,55 => 5,56 => 5,57 => 5,58 => 5,59 => 5,60 => 5,61 => 5,62 => 5,63 => 2,96 => 1),'ceiling' => 97,'floor' => 18),
                           'Jumping on mats' => array('map' => array(0 => 1,1 => 3,2 => 3,3 => 4,4 => 6,5 => 12)),
                           'Catching Beanbag' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 5,5 => 7,6 => 8,7 => 9,8 => 10,9 => 12,10 => 16)),
                           'Throwing beanbag on mat' => array('map' => array(0 => 4,1 => 6,2 => 6,3 => 7,4 => 8,5 => 10,6 => 12,7 => 13,8 => 14,9 => 17,10 => 19))),
    "age5:0-5:11" => array('one-Leg balance other leg' => array('map' => array(0 => 3,1 => 3,2 => 5,3 => 6,4 => 7,5 => 8,6 => 8,7 => 9,8 => 9,9 => 9,10 => 10,11 => 10,12 => 10,13 => 10,14 => 11,15 => 11,16 => 12,17 => 12,18 => 12,19 => 12,20 => 12,21 => 13,22 => 13,23 => 13,24 => 13,25 => 14,26 => 14,27 => 15,28 => 15,29 => 15,30 => 15)),
                           'Walking heels raised' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 3,4 => 3,5 => 4,6 => 4,7 => 5,8 => 5,9 => 5,10 => 5,11 => 6,12 => 6,13 => 8,14 => 10,15 => 12)),
                           'posting coins pref hand' => array('map' => array(10 => 16,11 => 16,12 => 16,13 => 15,14 => 14,15 => 13,16 => 12,17 => 12,18 => 11,19 => 10,20 => 9,21 => 8,22 => 7,23 => 6,24 => 5,25 => 4,26 => 1),'ceiling' => 27,'floor' => 10),
                           'Drawing Trail' => array('map' => array(0 => 11,1 => 11,2 => 9,3 => 4,4 => 1),'ceiling' => 5),
                           'posting coins non-pref hand' => array('map' => array(12 => 16,13 => 15,14 => 15,15 => 15,16 => 14,17 => 13,18 => 13,19 => 12,20 => 12,21 => 11,22 => 10,23 => 9,24 => 7,25 => 6,26 => 6,27 => 5,28 => 4,29 => 4,30 => 1),'ceiling' => 31,'floor' => 12),
                           'one-leg balance best leg' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 6,6 => 7,7 => 7,8 => 8,9 => 8,10 => 8,11 => 9,12 => 9,13 => 9,14 => 9,15 => 10,16 => 10,17 => 10,18 => 10,19 => 10,20 => 10,21 => 11,22 => 11,23 => 11,24 => 11,25 => 11,26 => 11,27 => 11,28 => 11,29 => 11,30 => 13)),
                           'threading beads' => array('map' => array(25 => 16,26 => 16,27 => 16,28 => 16,29 => 16,30 => 15,31 => 15,32 => 15,33 => 15,34 => 15,35 => 15,36 => 14,37 => 14,38 => 14,39 => 13,40 => 13,41 => 12,42 => 12,43 => 12,44 => 11,45 => 11,46 => 11,47 => 11,48 => 10,49 => 10,50 => 9,51 => 9,52 => 9,53 => 9,54 => 8,55 => 8,56 => 7,57 => 7,58 => 7,59 => 7,60 => 7,61 => 5,62 => 5,63 => 5,64 => 5,65 => 5,66 => 5,67 => 4,68 => 4,69 => 4,70 => 4,71 => 4,72 => 4,73 => 4,74 => 4,75 => 4,76 => 4,77 => 4,78 => 4,79 => 4,80 => 4,81 => 4,82 => 4,83 => 4,84 => 4,85 => 4,86 => 4,87 => 4,88 => 4,89 => 4,90 => 4,91 => 4,92 => 4,93 => 4,94 => 4,95 => 4,96 => 4,97 => 3,98 => 3,99 => 3,100 => 3,101 => 3,102 => 3,103 => 3,104 => 3,105 => 3,106 => 3,107 => 3,108 => 3,109 => 3,110 => 3,111 => 3,112 => 3,113 => 3,114 => 3,115 => 3,116 => 3,117 => 3,118 => 3,119 => 3,120 => 3,121 => 1),'ceiling' => 122,'floor' => 25),
                           'Jumping on mats' => array('map' => array(0 => 1,1 => 3,2 => 3,3 => 4,4 => 6,5 => 12)),
                           'Catching Beanbag' => array('map' => array(0 => 5,1 => 5,2 => 7,3 => 7,4 => 8,5 => 8,6 => 9,7 => 9,8 => 10,9 => 12,10 => 16)),
                           'Throwing beanbag on mat' => array('map' => array(0 => 1,1 => 3,2 => 4,3 => 5,4 => 8,5 => 10,6 => 11,7 => 12,8 => 13,9 => 14,10 => 19))),
    "age6:0-6:11" => array('one-Leg balance other leg' => array('map' => array(0 => 3,1 => 3,2 => 5,3 => 6,4 => 7,5 => 7,6 => 7,7 => 8,8 => 8,9 => 8,10 => 8,11 => 8,12 => 8,13 => 8,14 => 9,15 => 9,16 => 9,17 => 9,18 => 10,19 => 10,20 => 10,21 => 10,22 => 10,23 => 10,24 => 11,25 => 11,26 => 11,27 => 11,28 => 11,29 => 14,30 => 14)),
                           'Walking heels raised' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 1,6 => 4,7 => 4,8 => 4,9 => 5,10 => 5,11 => 6,12 => 6,13 => 8,14 => 10,15 => 11)),
                           'posting coins pref hand' => array('map' => array(14 => 14,15 => 13,16 => 12,17 => 11,18 => 10,19 => 9,20 => 7,21 => 6,22 => 6,23 => 5,24 => 4,25 => 1),'ceiling' => 26,'floor' => 14),
                           'Drawing Trail' => array('map' => array(0 => 11,1 => 7,2 => 1),'ceiling' => 3),
                           'posting coins non-pref hand' => array('map' => array(12 => 16,13 => 15,14 => 15,15 => 15,16 => 14,17 => 13,18 => 12,19 => 11,20 => 9,21 => 7,22 => 6,23 => 6,24 => 5,25 => 5,26 => 5,27 => 1),'ceiling' => 28,'floor' => 12),
                           'one-leg balance best leg' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 6,8 => 6,9 => 6,10 => 6,11 => 7,12 => 7,13 => 7,14 => 7,15 => 8,16 => 8,17 => 8,18 => 8,19 => 8,20 => 8,21 => 8,22 => 8,23 => 8,24 => 9,25 => 9,26 => 9,27 => 9,28 => 10,29 => 10,30 => 13)),
                           'threading beads' => array('map' => array(25 => 16,26 => 16,27 => 16,28 => 16,29 => 15,30 => 15,31 => 15,32 => 14,33 => 14,34 => 13,35 => 13,36 => 12,37 => 12,38 => 11,39 => 11,40 => 11,41 => 11,42 => 11,43 => 10,44 => 10,45 => 10,46 => 9,47 => 9,48 => 8,49 => 8,50 => 7,51 => 7,52 => 7,53 => 7,54 => 7,55 => 6,56 => 6,57 => 6,58 => 6,59 => 5,60 => 5,61 => 5,62 => 5,63 => 5,64 => 4,65 => 3,66 => 3,67 => 3,68 => 3,69 => 3,70 => 3,71 => 3,72 => 3,73 => 1),'ceiling' => 74,'floor' => 25),
                           'Jumping on mats' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 2,4 => 6,5 => 11)),
                           'Catching Beanbag' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 5,5 => 6,6 => 7,7 => 7,8 => 8,9 => 9,10 => 14)),
                           'Throwing beanbag on mat' => array('map' => array(0 => 1,1 => 3,2 => 4,3 => 5,4 => 7,5 => 8,6 => 9,7 => 11,8 => 13,9 => 14,10 => 16))),
    "age7:0-7:11" => array('walking heel-toe forwards' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 3,4 => 3,5 => 3,6 => 3,7 => 3,8 => 4,9 => 4,10 => 5,11 => 6,12 => 6,13 => 8,14 => 10,15 => 12)),
                           'Hopping on mats other leg' => array('map' => array(0 => 3,1 => 4,2 => 5,3 => 6,4 => 9,5 => 13)),
                           'Drawing Trail' => array('map' => array(0 => 12,1 => 10,2 => 8,3 => 5,4 => 3,5 => 1),'ceiling' => 6),
                           'Hopping on mats best leg' => array('map' => array(0 => 1,1 => 1,2 => 3,3 => 3,4 => 4,5 => 11)),
                           'Placing Pegs pref hand' => array('map' => array(21 => 16,22 => 15,23 => 15,24 => 14,25 => 14,26 => 13,27 => 12,28 => 12,29 => 11,30 => 10,31 => 9,32 => 9,33 => 8,34 => 8,35 => 8,36 => 7,37 => 6,38 => 6,39 => 6,40 => 6,41 => 6,42 => 6,43 => 5,44 => 5,45 => 5,46 => 5,47 => 4),'ceiling' => 48),
                           'one-Leg balance other leg' => array('map' => array(0 => 4,1 => 4,2 => 5,3 => 7,4 => 8,5 => 9,6 => 10,7 => 10,8 => 11,9 => 11,10 => 11,11 => 11,12 => 12,13 => 12,14 => 12,15 => 12,16 => 13,17 => 13,18 => 13,19 => 13,20 => 14,21 => 14,22 => 14,23 => 15,24 => 15,25 => 15,26 => 15,27 => 15,28 => 15,29 => 15,30 => 16)),
                           'Catching with 2 hands' => array('map' => array(0 => 5,1 => 6,2 => 7,3 => 7,4 => 8,5 => 9,6 => 10,7 => 10,8 => 11,9 => 15,10 => 17)),
                           'Threading lace' => array('map' => array(21 => 15,22 => 14,23 => 13,24 => 13,25 => 12,26 => 12,27 => 11,28 => 11,29 => 11,30 => 10,31 => 9,32 => 9,33 => 8,34 => 8,35 => 7,36 => 7,37 => 6,38 => 6,39 => 6,40 => 6,41 => 5,42 => 5,43 => 5,44 => 5,45 => 5,46 => 5,47 => 5,48 => 3),'ceiling' => 49,'floor' => 21),
                           'one-leg balance best leg' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 4,4 => 5,5 => 6,6 => 7,7 => 7,8 => 8,9 => 8,10 => 8,11 => 9,12 => 9,13 => 9,14 => 9,15 => 9,16 => 10,17 => 10,18 => 10,19 => 10,20 => 11,21 => 11,22 => 11,23 => 11,24 => 11,25 => 12,26 => 12,27 => 12,28 => 14,29 => 14,30 => 14)),
                           'Placing pegs non-pref hand' => array('map' => array(21 => 16,22 => 15,23 => 15,24 => 15,25 => 15,26 => 15,27 => 14,28 => 13,29 => 13,30 => 13,31 => 12,32 => 12,33 => 11,34 => 11,35 => 10,36 => 10,37 => 9,38 => 8,39 => 8,40 => 8,41 => 8,42 => 7,43 => 7,44 => 7,45 => 7,46 => 7,47 => 7,48 => 6,49 => 6,50 => 6,51 => 4),'ceiling' => 52,'floor' => 21),
                           'Throwing beanbag on mat' => array('map' => array(0 => 3,1 => 3,2 => 5,3 => 5,4 => 7,5 => 9,6 => 9,7 => 11,8 => 12,9 => 15,10 => 17))),
    "age8:0-8:11" => array('walking heel-toe forwards' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 3,4 => 3,5 => 3,6 => 3,7 => 3,8 => 4,9 => 4,10 => 5,11 => 6,12 => 6,13 => 7,14 => 7,15 => 11)),
                           'Hopping on mats other leg' => array('map' => array(0 => 2,1 => 3,2 => 4,3 => 5,4 => 6,5 => 12)),
                           'Drawing Trail' => array('map' => array(0 => 12,1 => 6,2 => 5,3 => 1),'ceiling' => 4),
                           'Hopping on mats best leg' => array('map' => array(0 => 1,1 => 1,2 => 3,3 => 3,4 => 4,5 => 11)),
                           'Placing Pegs pref hand' => array('map' => array(32 => 7,33 => 6,34 => 6,35 => 5,36 => 3,20 => 15,21 => 14,22 => 13,23 => 12,24 => 12,25 => 11,26 => 11,27 => 10,28 => 9,29 => 8,30 => 7,31 => 7),'ceiling' => 37,'floor' => 20),
                           'one-Leg balance other leg' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 5,4 => 7,5 => 8,6 => 9,7 => 10,8 => 10,9 => 11,10 => 11,11 => 11,12 => 11,13 => 11,14 => 11,15 => 12,16 => 12,17 => 12,18 => 12,19 => 13,20 => 13,21 => 13,22 => 13,23 => 13,24 => 13,25 => 14,26 => 14,27 => 15,28 => 15,29 => 15,30 => 15)),
                           'Catching with 2 hands' => array('map' => array(0 => 4,1 => 5,2 => 5,3 => 6,4 => 7,5 => 7,6 => 8,7 => 9,8 => 10,9 => 12,10 => 15)),
                           'Threading lace' => array('map' => array(18 => 14,19 => 13,20 => 13,21 => 12,22 => 12,23 => 12,24 => 12,25 => 11,26 => 10,27 => 9,28 => 9,29 => 8,30 => 8,31 => 8,32 => 7,33 => 7,34 => 6,35 => 6,36 => 5,37 => 5,38 => 5,39 => 5,40 => 5,41 => 5,42 => 4,43 => 4,44 => 4,45 => 3),'ceiling' => 46,'floor' => 18),
                           'one-leg balance best leg' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 6,8 => 6,9 => 7,10 => 7,11 => 8,12 => 8,13 => 9,14 => 9,15 => 9,16 => 9,17 => 9,18 => 9,19 => 10,20 => 10,21 => 10,22 => 11,23 => 11,24 => 11,25 => 11,26 => 12,27 => 12,28 => 12,29 => 13,30 => 13)),
                           'Placing pegs non-pref hand' => array('map' => array(22 => 15,23 => 15,24 => 14,25 => 14,26 => 13,27 => 12,28 => 11,29 => 11,30 => 10,31 => 10,32 => 10,33 => 9,34 => 8,35 => 8,36 => 7,37 => 7,38 => 7,39 => 7,40 => 6,41 => 6,42 => 6,43 => 6,44 => 4),'ceiling' => 45,'floor' => 22),
                           'Throwing beanbag on mat' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 5,5 => 6,6 => 8,7 => 11,8 => 11,9 => 14,10 => 17))),
    "age9:0-9:11" => array('walking heel-toe forwards' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 1,6 => 1,7 => 1,8 => 2,9 => 2,10 => 2,11 => 3,12 => 3,13 => 4,14 => 7,15 => 11)),
                           'Hopping on mats other leg' => array('map' => array(0 => 2,1 => 3,2 => 4,3 => 5,4 => 6,5 => 12)),
                           'Drawing Trail' => array('map' => array(0 => 11,1 => 6,2 => 4,3 => 1),'ceiling' => 4),
                           'Hopping on mats best leg' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 2,4 => 3,5 => 11)),
                           'Placing Pegs pref hand' => array('map' => array(32 => 6,33 => 6,34 => 6,35 => 5,36 => 3,19 => 16,20 => 15,21 => 14,22 => 13,23 => 12,24 => 12,25 => 11,26 => 10,27 => 9,28 => 8,29 => 7,30 => 7,31 => 6),'ceiling' => 37,'floor' => 19),
                           'one-Leg balance other leg' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 5,4 => 7,5 => 8,6 => 9,7 => 9,8 => 10,9 => 10,10 => 11,11 => 11,12 => 11,13 => 11,14 => 11,15 => 11,16 => 11,17 => 12,18 => 12,19 => 12,20 => 12,21 => 12,22 => 12,23 => 12,24 => 12,25 => 13,26 => 13,27 => 13,28 => 13,29 => 13,30 => 13)),
                           'Catching with 2 hands' => array('map' => array(0 => 5,1 => 8,2 => 9,3 => 9,4 => 9,5 => 9,6 => 10,7 => 10,8 => 10,9 => 12,10 => 15)),
                           'Threading lace' => array('map' => array(16 => 15,17 => 14,18 => 13,19 => 13,20 => 12,21 => 11,22 => 10,23 => 10,24 => 10,25 => 9,26 => 9,27 => 8,28 => 7,29 => 7,30 => 6,31 => 6,32 => 6,33 => 5,34 => 4,35 => 4,36 => 4,37 => 3,38 => 3,39 => 3,40 => 3,41 => 3,42 => 3,43 => 3,44 => 2),'ceiling' => 45,'floor' => 16),
                           'one-leg balance best leg' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 5,8 => 6,9 => 6,10 => 7,11 => 7,12 => 7,13 => 7,14 => 7,15 => 8,16 => 8,17 => 8,18 => 8,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 11,27 => 11,28 => 11,29 => 12,30 => 13)),
                           'Placing pegs non-pref hand' => array('map' => array(21 => 16,22 => 15,23 => 15,24 => 14,25 => 14,26 => 13,27 => 12,28 => 11,29 => 11,30 => 10,31 => 9,32 => 9,33 => 8,34 => 8,35 => 7,36 => 7,37 => 6,38 => 6,39 => 6,40 => 6,41 => 5,42 => 5,43 => 5,44 => 4),'ceiling' => 45,'floor' => 21),
                           'Throwing beanbag on mat' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 5,5 => 6,6 => 8,7 => 11,8 => 11,9 => 12,10 => 14))),
    "age10:0-10:11" => array('walking heel-toe forwards' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 1,6 => 1,7 => 1,8 => 2,9 => 2,10 => 2,11 => 3,12 => 3,13 => 3,14 => 4,15 => 11)),
                             'Hopping on mats other leg' => array('map' => array(0 => 2,1 => 3,2 => 4,3 => 5,4 => 6,5 => 12)),
                             'Drawing Trail' => array('map' => array(0 => 11,1 => 6,2 => 4,3 => 1),'ceiling' => 4),
                             'Hopping on mats best leg' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
                             'Placing Pegs pref hand' => array('map' => array(32 => 5,33 => 3,18 => 16,19 => 15,20 => 14,21 => 14,22 => 13,23 => 12,24 => 11,25 => 10,26 => 9,27 => 8,28 => 8,29 => 7,30 => 6,31 => 5),'ceiling' => 34,'floor' => 18),
                             'one-Leg balance other leg' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 6,5 => 7,6 => 8,7 => 9,8 => 9,9 => 9,10 => 10,11 => 10,12 => 10,13 => 10,14 => 10,15 => 10,16 => 10,17 => 11,18 => 11,19 => 11,20 => 11,21 => 11,22 => 11,23 => 11,24 => 12,25 => 13,26 => 13,27 => 13,28 => 13,29 => 13,30 => 13)),
                             'Catching with 2 hands' => array('map' => array(0 => 5,1 => 6,2 => 6,3 => 7,4 => 7,5 => 8,6 => 8,7 => 9,8 => 9,9 => 12,10 => 14)),
                             'Threading lace' => array('map' => array(15 => 15,16 => 14,17 => 13,18 => 13,19 => 12,20 => 11,21 => 11,22 => 10,23 => 9,24 => 8,25 => 7,26 => 7,27 => 7,28 => 6,29 => 6,30 => 6,31 => 6,32 => 6,33 => 5,34 => 4,35 => 4,36 => 4,37 => 3,38 => 3,39 => 3,40 => 3,41 => 3,42 => 3,43 => 3,44 => 2),'ceiling' => 45,'floor' => 15),
                             'one-leg balance best leg' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 5,8 => 5,9 => 5,10 => 6,11 => 6,12 => 6,13 => 6,14 => 7,15 => 7,16 => 8,17 => 8,18 => 8,19 => 9,20 => 9,21 => 9,22 => 9,23 => 9,24 => 9,25 => 9,26 => 9,27 => 10,28 => 10,29 => 10,30 => 13)),
                             'Placing pegs non-pref hand' => array('map' => array(32 => 7,33 => 7,34 => 6,35 => 6,36 => 6,37 => 6,38 => 6,39 => 5,40 => 4,20 => 16,21 => 15,22 => 15,23 => 14,24 => 13,25 => 12,26 => 12,27 => 11,28 => 10,29 => 9,30 => 8,31 => 7),'ceiling' => 41,'floor' => 20),
                             'Throwing beanbag on mat' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 5,5 => 5,6 => 7,7 => 8,8 => 11,9 => 12,10 => 14))),
    "age11:0-11:11" => array('Turning pegs non-pref hand' => array('map' => array(15 => 16,16 => 15,17 => 14,18 => 13,19 => 12,20 => 12,21 => 11,22 => 10,23 => 10,24 => 9,25 => 9,26 => 8,27 => 8,28 => 7,29 => 7,30 => 7,31 => 6,32 => 6,33 => 5,34 => 5,35 => 5,36 => 5,37 => 5,38 => 4,39 => 3,40 => 1),'ceiling' => 41,'floor' => 15),
                             'Catching non-preferred hand' => array('map' => array(0 => 4,1 => 6,2 => 7,3 => 8,4 => 9,5 => 10,6 => 11,7 => 12,8 => 12,9 => 13,10 => 15)),
                             'ZigZag Hopping on mats other leg' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 4,4 => 6,5 => 11)),
                             'Turning Pegs pref hand' => array('map' => array(16 => 14,17 => 12,18 => 11,19 => 11,20 => 10,21 => 10,22 => 9,23 => 8,24 => 7,25 => 6,26 => 5,27 => 2),'ceiling' => 28,'floor' => 16),
                             'Drawing Trail' => array('map' => array(0 => 13,1 => 10,2 => 8,3 => 8,4 => 7,5 => 6,6 => 5,7 => 4,8 => 4,9 => 4,10 => 1),'ceiling' => 11),
                             'walking heel-toe backwards' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 5,6 => 7,7 => 8,8 => 9,9 => 9,10 => 9,11 => 10,12 => 10,13 => 11,14 => 12,15 => 12)),
                             'Triangle with Nuts and Bolts' => array('map' => array(24 => 16,25 => 15,26 => 15,27 => 14,28 => 13,29 => 13,30 => 13,31 => 12,32 => 12,33 => 12,34 => 12,35 => 11,36 => 11,37 => 11,38 => 11,39 => 10,40 => 10,41 => 10,42 => 10,43 => 9,44 => 9,45 => 8,46 => 8,47 => 8,48 => 7,49 => 7,50 => 7,51 => 7,52 => 7,53 => 7,54 => 6,55 => 6,56 => 6,57 => 6,58 => 6,59 => 6,60 => 5,61 => 5,62 => 5,63 => 5,64 => 5,65 => 5,66 => 5,67 => 4,68 => 4,69 => 4,70 => 4,71 => 4,72 => 4,73 => 4,74 => 4,75 => 4,76 => 4,77 => 4,78 => 4,79 => 4,80 => 4,81 => 4,82 => 4,83 => 4,84 => 4,85 => 4,86 => 4,87 => 4,88 => 4,89 => 4,90 => 4,91 => 1),'ceiling' => 92,'floor' => 24),
                             'Throwing wall at target' => array('map' => array(0 => 3,1 => 5,2 => 6,3 => 6,4 => 7,5 => 10,6 => 11,7 => 13,8 => 15,9 => 17,10 => 17)),
                             'ZigZag Hopping on mats best leg' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
                             'Catching -preferred hand' => array('map' => array(0 => 4,1 => 5,2 => 6,3 => 6,4 => 7,5 => 7,6 => 8,7 => 10,8 => 11,9 => 11,10 => 14)),
                             'Two-board balance' => array('map' => array(0 => 2,1 => 3,2 => 3,3 => 4,4 => 5,5 => 5,6 => 6,7 => 7,8 => 7,9 => 7,10 => 7,11 => 8,12 => 8,13 => 8,14 => 8,15 => 8,16 => 8,17 => 8,18 => 9,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 10,27 => 11,28 => 11,29 => 13,30 => 13))),
    "age12:0-12:11" => array('Turning pegs non-pref hand' => array('map' => array(15 => 16,16 => 14,17 => 13,18 => 12,19 => 11,20 => 11,21 => 10,22 => 9,23 => 8,24 => 8,25 => 7,26 => 6,27 => 6,28 => 5,29 => 5,30 => 5,31 => 4,32 => 4,33 => 4,34 => 3,35 => 3,36 => 3,37 => 3,38 => 3,39 => 1),'ceiling' => 40,'floor' => 15),
                             'Catching non-preferred hand' => array('map' => array(0 => 3,1 => 4,2 => 6,3 => 7,4 => 8,5 => 9,6 => 10,7 => 11,8 => 12,9 => 13,10 => 15)),
                             'ZigZag Hopping on mats other leg' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 3,4 => 10,5 => 11)),
                             'Turning Pegs pref hand' => array('map' => array(14 => 15,15 => 14,16 => 13,17 => 11,18 => 11,19 => 10,20 => 9,21 => 8,22 => 7,23 => 6,24 => 6,25 => 5,26 => 5,27 => 3,28 => 2,29 => 1),'ceiling' => 30,'floor' => 14),
                             'Drawing Trail' => array('map' => array(0 => 13,1 => 9,2 => 8,3 => 7,4 => 6,5 => 5,6 => 3,7 => 3,8 => 3,9 => 3,10 => 1),'ceiling' => 11),
                             'walking heel-toe backwards' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 3,5 => 5,6 => 7,7 => 8,8 => 9,9 => 9,10 => 9,11 => 10,12 => 10,13 => 11,14 => 12,15 => 12)),
                             'Triangle with Nuts and Bolts' => array('map' => array(22 => 15,23 => 14,24 => 14,25 => 14,26 => 14,27 => 13,28 => 12,29 => 12,30 => 11,31 => 11,32 => 11,33 => 11,34 => 11,35 => 10,36 => 10,37 => 10,38 => 10,39 => 10,40 => 10,41 => 9,42 => 9,43 => 8,44 => 8,45 => 7,46 => 7,47 => 7,48 => 6,49 => 6,50 => 6,51 => 6,52 => 5,53 => 5,54 => 5,55 => 5,56 => 5,57 => 5,58 => 5,59 => 5,60 => 5,61 => 5,62 => 5,63 => 4,64 => 4,65 => 4,66 => 4,67 => 4,68 => 4,69 => 3,70 => 1),'ceiling' => 71,'floor' => 22),
                             'Throwing wall at target' => array('map' => array(0 => 3,1 => 4,2 => 6,3 => 6,4 => 7,5 => 9,6 => 10,7 => 13,8 => 14,9 => 16,10 => 16)),
                             'ZigZag Hopping on mats best leg' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
                             'Catching -preferred hand' => array('map' => array(0 => 4,1 => 5,2 => 5,3 => 6,4 => 6,5 => 7,6 => 7,7 => 8,8 => 9,9 => 11,10 => 14)),
                             'Two-board balance' => array('map' => array(0 => 2,1 => 3,2 => 3,4 => 5,5 => 5,6 => 6,7 => 7,8 => 7,9 => 7,10 => 7,11 => 8,12 => 8,13 => 8,14 => 8,15 => 8,16 => 8,17 => 8,18 => 9,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 10,27 => 11,28 => 11,29 => 13,30 => 13)))
);
