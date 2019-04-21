<?php

/* assessments_mabc
 *
 * Movement ABC assessment
 */

class AssessmentData_MABC extends AssessmentData
{
    private $age = 0.0;
    private $ageBand = 0;     // 1,2,3 denotes the test used for the client's age

    function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oA, $oAsmt, $kAsmt );
    }

    function GetAgeInfo()   { return( [$this->age, $this->ageBand] ); }

    function SetAgeBand( $age, $ageBand )
    {
        $this->age = $age;
        $this->ageBand = $ageBand;
    }

    public function GetItemLabel( string $item ) : string
    {
        //$scores = Assessment_MABC_Scores::GetScores( $this->age );
        //return( @$scores[$item]['label'] ?: "" );
        return( Assessment_MABC_Scores::GetLabels( $this->age, $item )[1] );
    }

    public function ComputeScore( string $item ) : int
    {
        $ret = 0;

        $scores = Assessment_MABC_Scores::GetScores( $this->age );
        if( ($raS = @$scores[$item]) && ($raw = $this->GetRaw($item)) ) {
            // this is a basic item
            if( isset($raS['ceiling']) && $raw > $raS['ceiling'] ) {
                $ret = $raS['map'][$raS['ceiling']];
            } else if( isset($raS['floor']) && $raw < $raS['floor'] ) {
                $ret = $raS['map'][$raS['floor']];
            } else if( isset($raS['map'][$raw]) ) {
                $ret = $raS['map'][$raw];
            }
        } else {
            // this could be a computed score
            switch($item) {
                case 'md1avg':  $ret = $this->getAverage( 'md1a', 'md1b' );     break;
                case 'ac1avg':  $ret = $this->getAverage( 'ac1a', 'ac1b' );     break;
                case 'bal1avg': $ret = $this->getAverage( 'bal1a', 'bal1b' );   break;
                case 'bal3avg': $ret = $this->getAverage( 'bal3a', 'bal3b' );   break;

                // Component score totals
                case 'md_cmp':
                    // ageBand 1,2,3 add up the same way
                    $ret = $this->ComputeScore('md1avg') + $this->ComputeScore('md2') + $this->ComputeScore('md3');
                    break;
                case 'ac_cmp':
                    if( $this->ageBand == 1 || $this->ageBand == 2 ) {
                        $ret = $this->ComputeScore('ac1') + $this->ComputeScore('ac2');
                    } else {
                        // ageBand 3
                        $ret = $this->ComputeScore('ac1avg') + $this->ComputeScore('ac2');
                    }
                    break;
                case 'bal_cmp':
                    if( $this->ageBand == 1 ) {
                        $ret = $this->ComputeScore('bal1avg') + $this->ComputeScore('bal2') + $this->ComputeScore('bal3');
                    } else if( $this->ageBand == 2 ) {
                        $ret = $this->ComputeScore('bal1avg') + $this->ComputeScore('bal2') + $this->ComputeScore('bal3avg');
                    } else {
                        // ageBand 3
                        $ret = $this->ComputeScore('bal1') + $this->ComputeScore('bal2') + $this->ComputeScore('bal3avg');
                    }
                    break;

                // Standard scores and percentiles for component totals
                case 'md_std':
                case 'md_pct':
                case 'ac_std':
                case 'ac_pct':
                case 'bal_std':
                case 'bal_pct':
                    $component = strtok($item,"_"); // md, ac, bal
                    list($std,$pct) = Assessment_MABC_Scores::GetComponentTotalScore( $component, $this->ComputeScore("{$component}_cmp") );
                    $ret = (substr($item,-3)=='std') ? $std : $pct;
                    break;

                // Standard score and percentile for total score
                case 'total_score':
                    $ret = $this->ComputeScore('md_cmp') + $this->ComputeScore('ac_cmp') + $this->ComputeScore('bal_cmp');
                    break;
                case 'total_std':
                case 'total_pct':
                    list($std,$pct,$zone) = Assessment_MABC_Scores::GetTotalScore( $this->ComputeScore('total_score') );
                    $ret = ($item == 'total_std' ? $std : $pct);
                    break;
            }
        }

        return( $ret );
    }

    public function ComputePercentile( string $item ) : int
    {
        return( 0 );
    }

    private function getAverage( string $item1, string $item2 ) : int
    {
        // Get the average of the scores of two raw items
        $avg = 0;

        if( $this->GetRaw($item1) && $this->GetRaw($item2) ) {
            $avg = $this->mabcAvg( $this->ComputeScore($item1), $this->ComputeScore($item2) );
        }
        return( $avg );
    }

    private function mabcAvg( int $a, int $b ) : int
    {
        // in MABC we average integer scores to integers by rounding down if the total is <10 and rounding up if total >10

        if( $a + $b > 10 ) {
            $avg = intval( ($a + $b)/2 );      // odd totals will round down; even totals are correct
        } else {
            $avg = intval( ($a + $b + 1)/2 );  // this rounds up odd totals and leaves even totals correct
        }
        return( $avg );
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


class AssessmentUI_MABC extends AssessmentUIColumns
{
    function __construct( AssessmentData_MABC $oData )
    {
        if( ($age = $oData->GetRaw('metaClientAge')) ) {

        }

        // Other methods have to set columnsDef before they do anything
        parent::__construct( $oData, ['dummyColumnsDef'] );
    }

    function DrawScoreResults() : string
    {
        $s = "";

        list($age,$ageBand) = $this->oData->GetAgeInfo();

        $s .= "<p>Age at test date: $age</p>";

        if( CATS_DEBUG ) {
            $raResults = [];

            foreach( array_merge($this->raBasicItems, $this->raComputedItems) as $item ) {
                $raResults[$item] = $this->oData->ComputeScore($item);
            }

            // All the code below will be replaced by this template
            $oTmpl = SEEDTemplateMaker2( ['fTemplates'=> [CATSLIB."templates/assessments_mabc.twig"]] );
            $s .= $oTmpl->ExpandTmpl( "assessments_mabc_results_$ageBand", $raResults );
        }

// Put all this html into twig but don't get rid of the code below because it's used in the live version.
// Then when the new section works the same as the old one, get rid of this code and take away the CATS_DEBUG.
        switch( $ageBand ) {
            case 1:
            default:
                $raSum = $this->scoreSummary1;
                break;
            case 2:
                $raSum = $this->scoreSummary2;
                break;
            case 3:
                $raSum = $this->scoreSummary3;
                break;
        }
        $s .= $this->scoreSummary;
        $s = str_replace( "{mdForm}",  $raSum[0], $s );
        $s = str_replace( "{acForm}",  $raSum[1], $s );
        $s = str_replace( "{balForm}", $raSum[2], $s );


        // subst {label:X}
        foreach( $this->raBasicItems as $item ) {
            $s = str_replace( "{label:$item}",  $this->oData->GetItemLabel($item), $s );
        }

        // subst {raw:X}
        foreach( $this->raBasicItems as $item ) {
            $s = str_replace( "{raw:$item}",  "<strong style='border:1px solid #aaa; padding:0px 4px;background-color:#eee'>".$this->oData->GetRaw($item)."</strong>", $s );
        }

        // subst {score:X}
        foreach( array_merge($this->raBasicItems, $this->raComputedItems) as $item ) {
            $s = str_replace( "{score:$item}", $this->oData->ComputeScore($item), $s );
        }

        // oops, can't get zone directly from ComputeScore because it only returns int
        list($std,$pct,$zone) = Assessment_MABC_Scores::GetTotalScore( $this->oData->ComputeScore('total_score') );
        switch( $zone ) {
            case 'green':
                $zone = "<span style='color:green;background-color:#aaddaa'>&nbsp; Green &nbsp;</span>";
                break;
            case 'yellow':
                $zone = "<span style='color:brown;background-color:#ddddaa'>&nbsp; Yellow &nbsp;</span>";
                break;
            case 'red':
                $zone = "<span style='color:red;background-color:#ddaaaa'>&nbsp; Red &nbsp;</span>";
                break;
        }
        $s = str_replace( "{score:total_zone}", $zone, $s );

        return( $s );
    }

    private $raBasicItems =
                ['md1','md2','md3','md1a','md1b','md2a','md2b','md3a','md3b',
                 'ac1','ac2','ac3','ac1a','ac1b','ac2a','ac2b','ac3a','ac3b',
                 'bal1','bal2','bal3','bal1a','bal1b','bal2a','bal2b','bal3a','bal3b'];
    private $raComputedItems =
                ['md1avg', 'ac1avg', 'bal1avg', 'bal3avg',
                 'md_cmp', 'md_std', 'md_pct', 'ac_cmp', 'ac_std', 'ac_pct', 'bal_cmp', 'bal_std', 'bal_pct',
                 'total_score', 'total_std', 'total_pct'];

private $scoreSummary1 = [
    "<tr><td class='label0'>{label:md1a}</td><td>{raw:md1a}</td><td class='score0'>{score:md1a}</td><td><div class='score1'>{score:md1avg}</div></td></tr>
     <tr><td class='label0'>{label:md1b}</td><td>{raw:md1b}</td><td class='score0'>{score:md1b}</td><td>&nbsp;</td></tr>
     <tr><td class='label0'>{label:md2}</td><td>{raw:md2}</td><td>&nbsp;</td><td><div class='score1'>{score:md2}</div></td></tr>
     <tr><td class='label0'>{label:md3}</td><td>{raw:md3}</td><td>&nbsp;</td><td><div class='score1'>{score:md3}</div></td></tr>",

    "<tr><td class='label0'>{label:ac1}</td><td>{raw:ac1}</td><td>&nbsp;</td><td><div class='score1'>{score:ac1}</div></td></tr>
     <tr><td class='label0'>{label:ac2}</td><td>{raw:ac2}</td><td>&nbsp;</td><td><div class='score1'>{score:ac2}</div></td></tr>",

    "<tr><td class='label0'>{label:bal1a}</td><td>{raw:bal1a}</td><td class='score0'>{score:bal1a}</td><td><div class='score1'>{score:bal1avg}</div></td></tr>
     <tr><td class='label0'>{label:bal1b}</td><td>{raw:bal1b}</td><td class='score0'>{score:bal1b}</td><td>&nbsp;</td></tr>
     <tr><td class='label0'>{label:bal2}</td><td>{raw:bal2}</td><td>&nbsp;</td><td><div class='score1'>{score:bal2}</div></td></tr>
     <tr><td class='label0'>{label:bal3}</td><td>{raw:bal3}</td><td>&nbsp;</td><td><div class='score1'>{score:bal3}</div></td></tr>"
];

private $scoreSummary2 = [
    "<tr><td class='label0'>{label:md1a}</td><td>{raw:md1a}</td><td class='score0'>{score:md1a}</td><td><div class='score1'>{score:md1avg}</div></td></tr>
     <tr><td class='label0'>{label:md1b}</td><td>{raw:md1b}</td><td class='score0'>{score:md1b}</td><td>&nbsp;</td></tr>
     <tr><td class='label0'>{label:md2}</td><td>{raw:md2}</td><td>&nbsp;</td><td><div class='score1'>{score:md2}</div></td></tr>
     <tr><td class='label0'>{label:md3}</td><td>{raw:md3}</td><td>&nbsp;</td><td><div class='score1'>{score:md3}</div></td></tr>",

    "<tr><td class='label0'>{label:ac1}</td><td>{raw:ac1}</td><td>&nbsp;</td><td><div class='score1'>{score:ac1}</div></td></tr>
     <tr><td class='label0'>{label:ac2}</td><td>{raw:ac2}</td><td>&nbsp;</td><td><div class='score1'>{score:ac2}</div></td></tr>",

    "<tr><td class='label0'>{label:bal1a}</td><td>{raw:bal1a}</td><td class='score0'>{score:bal1a}</td><td><div class='score1'>{score:bal1avg}</div></td></tr>
     <tr><td class='label0'>{label:bal1b}</td><td>{raw:bal1b}</td><td class='score0'>{score:bal1b}</td><td>&nbsp;</td></tr>
     <tr><td class='label0'>{label:bal2}</td><td>{raw:bal2}</td><td>&nbsp;</td><td><div class='score1'>{score:bal2}</div></td></tr>
     <tr><td class='label0'>{label:bal3a}</td><td>{raw:bal3a}</td><td class='score0'>{score:bal3a}</td><td><div class='score1'>{score:bal3avg}</div></td></tr>
     <tr><td class='label0'>{label:bal3b}</td><td>{raw:bal3b}</td><td class='score0'>{score:bal3b}</td><td>&nbsp;</td></tr>"
];

private $scoreSummary3 = [
    "<tr><td class='label0'>{label:md1a}</td><td>{raw:md1a}</td><td class='score0'>{score:md1a}</td><td><div class='score1'>{score:md1avg}</div></td></tr>
     <tr><td class='label0'>{label:md1b}</td><td>{raw:md1b}</td><td class='score0'>{score:md1b}</td><td>&nbsp;</td></tr>
     <tr><td class='label0'>{label:md2}</td><td>{raw:md2}</td><td>&nbsp;</td><td><div class='score1'>{score:md2}</div></td></tr>
     <tr><td class='label0'>{label:md3}</td><td>{raw:md3}</td><td>&nbsp;</td><td><div class='score1'>{score:md3}</div></td></tr>",

    "<tr><td class='label0'>{label:ac1a}</td><td>{raw:ac1a}</td><td class='score0'>{score:ac1a}</td><td><div class='score1'>{score:ac1avg}</div></td></tr>
     <tr><td class='label0'>{label:ac1b}</td><td>{raw:ac1b}</td><td class='score0'>{score:ac1b}</td><td>&nbsp;</td></tr>
     <tr><td class='label0'>{label:ac2}</td><td>{raw:ac2}</td><td>&nbsp;</td><td><div class='score1'>{score:ac2}</div></td></tr>",

    "<tr><td class='label0'>{label:bal1}</td><td>{raw:bal1}</td><td>&nbsp;</td><td><div class='score1'>{score:bal1}</div></td></tr>
     <tr><td class='label0'>{label:bal2}</td><td>{raw:bal2}</td><td>&nbsp;</td><td><div class='score1'>{score:bal2}</div></td></tr>
     <tr><td class='label0'>{label:bal3a}</td><td>{raw:bal3a}</td><td class='score0'>{score:bal3a}</td><td><div class='score1'>{score:bal3avg}</div></td></tr>
     <tr><td class='label0'>{label:bal3b}</td><td>{raw:bal3b}</td><td class='score0'>{score:bal3b}</td><td>&nbsp;</td></tr>"
];

private $scoreSummary = "
    <style>
    .label0 {font-size:9pt}
    .score0 {font-size:9pt}
    .score1 {font-weight:bold; border:1px solid #aaa; border-radius:10px;padding:0px 5px;text-align:center}
    </style>
    <br/>
    <table width='100%'>
    <tr><th style='width:33%'>Manual Dexterity</th><th style='width:33%'>Aiming & Catching</th><th style='width:33%'>Balance</th></tr>
    <tr><td style='width:33%;padding:0px 5px;border-right:1px solid #ccc' valign='top'>
          <table>{mdForm}</table>
        </td>
        <td style='width:33%;padding:0px 5px;border-right:1px solid #ccc' valign='top'>
          <table width='100%'>{acForm}</table>
        </td>
        <td style='width:33%;padding:0px 5px' valign='top'>
          <table width='100%'>{balForm}</table>
        </td>
    </tr>
    </table>

    <p>&nbsp;</p>

    <table style='border:1px solid black;width:100%'>
    <tr><th colspan='3'>Manual Dexterity</th></tr>
    <tr><td style='width:33%'>Component score <p>{score:md_cmp}</p></td>
        <td style='width:33%'>Standard score <p><span class='score1'>{score:md_std}</span></p></td>
        <td style='width:33%'>Percentile <p>{score:md_pct} %</p></td></tr>
    </table>
    <p>&nbsp;</p>

    <table style='border:1px solid black;width:100%'>
    <tr><th colspan='3'>Aiming & Catching</th></tr>
    <tr><td style='width:33%'>Component score <p>{score:ac_cmp}</p></td>
        <td style='width:33%'>Standard score <p><span class='score1'>{score:ac_std}</span></p></td>
        <td style='width:33%'>Percentile <p>{score:ac_pct} %</p></td></tr>
    </table>
    <p>&nbsp;</p>

    <table style='border:1px solid black;width:100%'>
    <tr><th colspan='3'>Balance</th></tr>
    <tr><td style='width:33%'>Component score <p>{score:bal_cmp}</p></td>
        <td style='width:33%'>Standard score <p><span class='score1'>{score:bal_std}</span></p></td>
        <td style='width:33%'>Percentile <p>{score:bal_pct} %</p></td></tr>
    </table>
    <p>&nbsp;</p>

    <table style='border:1px solid black;width:100%'>
    <tr><th colspan='3'>Total</th></tr>
    <tr><td style='width:33%'>Total test score <p>{score:total_score}</p></td>
        <td style='width:33%'>Standard score <p><span class='score1'>{score:total_std}</span></p></td>
        <td style='width:33%'>Percentile <p>{score:total_pct} % &nbsp;&nbsp;<strong>{score:total_zone}</strong></p></td></tr>
    </table>
";
}


class Assessment_MABC extends Assessments
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        $oData = new AssessmentData_MABC( $this, $oAsmt, $kAsmt );
        $oUI = new AssessmentUI_MABC( $oData );

        parent::__construct( $oAsmt, 'mabc', $oData, $oUI );
    }

    function DrawAsmtForm( int $kClient )
    {
        $s = "";

        if( !($age = $this->getClientAge($kClient)) ) {
            $s = "Please enter the client's date of birth on the client list";
            goto done;
        }

        // Set the AssessmentData_MABC and AssessmentUI_MABC to use the appropriate test level
        $this->setColumnRangesByAge( $age );

        $s .= $this->oUI->DrawColumnForm( $kClient, ['hiddenParms'=> ['metaClientAge'=>$age]] );

        done:
        return( $s );

    }

    private function setColumnRangesByAge( $age )
    {
        // 3-6 years
        $def_ageBand1 = array(
            'md'  => ['label'=>"MD",  'cols' => ['1a'=>'md1a',  '1b'=>'md1b', '2'=>'md2',   '3'=>'md3'] ],
            'ac'  => ['label'=>"A&C", 'cols' => ['1'=>'ac1',    '2'=>'ac2'] ],
            'bal' => ['label'=>"Bal", 'cols' => ['1a'=>'bal1a', '1b'=>'bal1b', '2'=>'bal2', '3'=>'bal3'] ],
            );
        // 7-10 years
        $def_ageBand2 = array(
            'md'  => ['label'=>"MD",  'cols' => ['1a'=>'md1a',  '1b'=>'md1b',  '2'=>'md2',  '3'=>'md3'] ],
            'ac'  => ['label'=>"A&C", 'cols' => ['1'=>'ac1',    '2'=>'ac2'] ],
            'bal' => ['label'=>"Bal", 'cols' => ['1a'=>'bal1a', '1b'=>'bal1b', '2'=>'bal2', '3a'=>'bal3a', '3b'=>'bal3b'] ],
        );
        // 11-16 years
        $def_ageBand3 = array(
            'md'  => ['label'=>"MD",  'cols' => ['1a'=>'md1a', '1b'=>'md1b', '2'=>'md2',    '3'=>'md3'] ],
            'ac'  => ['label'=>"A&C", 'cols' => ['1a'=>'ac1a', '1b'=>'ac1b', '2'=>'ac2'] ],
            'bal' => ['label'=>"Bal", 'cols' => ['1'=>'bal1',  '2'=>'bal2',  '3a'=>'bal3a', '3b'=>'bal3b'] ],
        );

        if( $age < 7.0 ) {
            $ageBand = 1;   $cr = $this->raColumnRanges_ageBand1;   $colDef = $def_ageBand1;
        } else if( $age < 11.0 ) {
            $ageBand = 2;   $cr = $this->raColumnRanges_ageBand2;   $colDef = $def_ageBand2;
        } else {
            $ageBand = 3;   $cr = $this->raColumnRanges_ageBand3;   $colDef = $def_ageBand3;
        }
//      var_dump("Age Band $ageBand");

// eliminate raColumnRanges
        $this->raColumnRanges = $cr;
// deprecate this->raColumnDef and just use the one in oUI
        $this->raColumnDef = $colDef;
// SetAgeBand could just do everything that's in this method
        $this->oUI->SetColumnsDef( $colDef );
        $this->oData->SetAgeBand( $age, $ageBand );
    }

    function DrawAsmtResult()
    {
        $s = "";
//$this->oData->DebugDumpKfr();
        if( !$this->oData->GetAsmtKey() ) goto done;

        if( !($age = $this->oData->GetRaw('metaClientAge')) ) {
            $s .= "<p class='alert alert-warning'>Client age was not recorded. This result might not be valid.</p>";
        }
        $this->setColumnRangesByAge( $age );

        $s .= $this->drawResult();

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

class Assessment_MABC_Scores
{
    static function GetScores( $age )
    {
        if( $age >= 3.0 && $age < 3.5 )     return( self::$scores['age3:0-3:5'] );
        if( $age >= 3.5 && $age < 4.0 )     return( self::$scores['age3:6-3:11'] );
        if( $age >= 4.0 && $age < 4.5 )     return( self::$scores['age4:0-4:5'] );
        if( $age >= 4.5 && $age < 5.0 )     return( self::$scores['age4:6-4:11'] );

        $age = intval( $age );

        return( @self::$scores["age{$age}:0-{$age}:11"] );
    }

    static function GetLabels( $age, $item )
    {
        $code = $label = "";

        if( $age < 7.0 ) {
            $ageband = 1;
        } else if( $age < 11.0 ) {
            $ageband = 2;
        } else {
            $ageband = 3;
        }

        if( ($ra = @self::$labels[$ageband][$item]) ) {
            $code = $ra['code'];
            $label = $ra['label'];
        }

        return( [$code,$label] );
    }

private static $scores = array(
    // Age Band 1
    "age3:0-3:5" => array(
        'ac1' => array('map' => array(0 => 5,1 => 6,2 => 7,3 => 8,4 => 9,5 => 11,6 => 12,7 => 15,8 => 19,9 => 19,10 => 19)),
        'ac2' => array('map' => array(0 => 6,1 => 7,2 => 8,3 => 9,4 => 11,5 => 14,6 => 15,7 => 19,8 => 19,9 => 19,10 => 19)),
        'bal1a' => array('map' => array(0 => 4,1 => 6,2 => 7,3 => 8,4 => 10,5 => 11,6 => 12,7 => 13,8 => 13,9 => 14,10 => 14,11 => 14,12 => 14,13 => 14,14 => 15,15 => 16,16 => 16,17 => 19,18 => 19,19 => 19,20 => 19,21 => 19,22 => 19,23 => 19,24 => 19,25 => 19,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
        'bal1b' => array('map' => array(0 => 5,1 => 7,2 => 9,3 => 11,4 => 12,5 => 13,6 => 14,7 => 14,8 => 14,9 => 15,10 => 16,11 => 19,12 => 19,13 => 19,14 => 19,15 => 19,16 => 19,17 => 19,18 => 19,19 => 19,20 => 19,21 => 19,22 => 19,23 => 19,24 => 19,25 => 19,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
        'bal2' => array('map' => array(0 => 5,1 => 5,2 => 6,3 => 7,4 => 8,5 => 8,6 => 9,7 => 11,8 => 12,9 => 12,10 => 13,11 => 13,12 => 14,13 => 14,14 => 14,15 => 17)),
        'bal3' => array('map' => array(0 => 4,1 => 6,2 => 7,3 => 9,4 => 11,5 => 14)),
        'md1a' => array('map' => array(10 => 14,11 => 13,12 => 12,13 => 11,14 => 10,15 => 9,16 => 8,17 => 6,18 => 1),'ceiling' => 18,'floor' => 10),
        'md1b' => array('map' => array(10 => 14,11 => 14,12 => 12,13 => 11,14 => 11,15 => 10,16 => 10,17 => 9,18 => 9,19 => 8,20 => 7,21 => 7,22 => 6,23 => 6,24 => 5,25 => 5,26 => 5,27 => 5,28 => 5,29 => 1),'ceiling' => 29,'floor' => 10),
        'md2' => array('map' => array(27 => 15,28 => 15,29 => 15,30 => 15,31 => 15,32 => 15,33 => 14,34 => 14,35 => 14,36 => 13,37 => 13,38 => 13,39 => 13,40 => 13,41 => 12,42 => 12,43 => 12,44 => 12,45 => 12,46 => 12,47 => 12,48 => 11,49 => 11,50 => 11,51 => 11,52 => 11,53 => 10,54 => 10,55 => 10,56 => 10,57 => 9,58 => 9,59 => 9,60 => 9,61 => 9,62 => 9,63 => 9,64 => 9,65 => 9,66 => 8,67 => 8,68 => 8,69 => 8,70 => 8,71 => 7,72 => 7,73 => 7,74 => 7,75 => 7,76 => 7,77 => 7,78 => 7,79 => 6,80 => 6,81 => 6,82 => 6,83 => 6,84 => 5,85 => 5,86 => 5,87 => 5,88 => 4,89 => 4,90 => 4,91 => 4,92 => 4,93 => 4,94 => 4,95 => 4,96 => 1),'ceiling' => 96,'floor' => 27),
        'md3' => array('map' => array(2 => 14,3 => 13,4 => 12,5 => 11,6 => 10,7 => 9,8 => 9,9 => 8,10 => 7,11 => 7,12 => 6,13 => 6,14 => 6,15 => 5,16 => 4,17 => 1),'ceiling' => 17,'floor' => 2)
    ),
    "age3:6-3:11" => array(
        'ac1' => array('map' => array(0 => 3,1 => 4,2 => 5,3 => 6,4 => 7,5 => 8,6 => 10,7 => 12,8 => 12,9 => 15,10 => 19)),
        'ac2' => array('map' => array(0 => 5,1 => 7,2 => 8,3 => 9,4 => 11,5 => 12,6 => 14,7 => 15,8 => 17,9 => 19,10 => 19)),
        'bal1a' => array('map' => array(0 => 4,1 => 6,2 => 7,3 => 8,4 => 10,5 => 11,6 => 11,7 => 12,8 => 12,9 => 12,10 => 13,11 => 13,12 => 13,13 => 13,14 => 14,15 => 14,16 => 14,17 => 14,18 => 15,19 => 15,20 => 16,21 => 17,22 => 17,23 => 17,24 => 19,25 => 19,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
        'bal1b' => array('map' => array(0 => 5,1 => 7,2 => 8,3 => 11,4 => 11,5 => 12,6 => 13,7 => 14,8 => 14,9 => 15,10 => 15,11 => 15,12 => 16,13 => 17,14 => 17,15 => 19,16 => 19,17 => 19,18 => 19,19 => 19,20 => 19,21 => 19,22 => 19,23 => 19,24 => 19,25 => 19,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
        'bal2' => array('map' => array(0 => 5,1 => 5,2 => 5,3 => 6,4 => 7,5 => 7,6 => 8,7 => 9,8 => 9,9 => 9,10 => 9,11 => 9,12 => 11,13 => 11,14 => 12,15 => 13)),
        'bal3' => array('map' => array(0 => 3,1 => 4,2 => 5,3 => 6,4 => 8,5 => 12)),
        'md1a' => array('map' => array(7 => 15,8 => 15,9 => 13,10 => 12,11 => 11,12 => 10,13 => 9,14 => 8,15 => 7,16 => 6,17 => 5,18 => 1),'ceiling' => 18,'floor' => 7),
        'md1b' => array('map' => array(10 => 13,11 => 12,12 => 12,13 => 11,14 => 10,15 => 9,16 => 8,17 => 7,18 => 7,19 => 6,20 => 6,21 => 5,22 => 4,23 => 3,24 => 3,25 => 3,26 => 1),'ceiling' => 26,'floor' => 10),
        'md2' => array('map' => array(24 => 14,25 => 14,26 => 14,27 => 14,28 => 14,29 => 13,30 => 13,31 => 13,32 => 13,33 => 13,34 => 13,35 => 13,36 => 12,37 => 12,38 => 12,39 => 11,40 => 11,41 => 10,42 => 10,43 => 10,44 => 10,45 => 10,46 => 10,47 => 10,48 => 9,49 => 9,50 => 9,51 => 9,52 => 9,53 => 9,54 => 9,55 => 9,56 => 9,57 => 8,58 => 8,59 => 8,60 => 8,61 => 8,62 => 8,63 => 8,64 => 8,65 => 8,66 => 7,67 => 7,68 => 7,69 => 7,70 => 7,71 => 7,72 => 7,73 => 7,74 => 6,75 => 6,76 => 6,77 => 6,78 => 6,79 => 5,80 => 5,81 => 5,82 => 4,83 => 4,84 => 4,85 => 4,86 => 4,87 => 4,88 => 4,89 => 4,90 => 4,91 => 4,92 => 4,93 => 4,94 => 4,95 => 4,96 => 1),'ceiling' => 96,'floor' => 24),
        'md3' => array('map' => array(0 => 14,1 => 13,2 => 13,3 => 12,4 => 12,5 => 11,6 => 10,7 => 9,8 => 8,9 => 7,10 => 6,11 => 6,12 => 6,13 => 5,14 => 5,15 => 5,16 => 4,17 => 1),'ceiling' => 17)
    ),
    "age4:0-4:5" => array(
        'ac1' => array('map' => array(0 => 3,1 => 4,2 => 5,3 => 6,4 => 7,5 => 8,6 => 9,7 => 10,8 => 11,9 => 12,10 => 17)),
        'ac2' => array('map' => array(0 => 5,1 => 6,2 => 7,3 => 8,4 => 9,5 => 11,6 => 12,7 => 14,8 => 17,9 => 19,10 => 19)),
        'bal1a' => array('map' => array(0 => 4,1 => 5,2 => 6,3 => 6,4 => 7,5 => 8,6 => 9,7 => 9,8 => 10,9 => 11,10 => 11,11 => 12,12 => 12,13 => 12,14 => 12,15 => 13,16 => 13,17 => 13,18 => 13,19 => 14,20 => 14,21 => 14,22 => 14,23 => 14,24 => 14,25 => 14,26 => 14,27 => 16,28 => 16,29 => 16,30 => 16)),
        'bal1b' => array('map' => array(0 => 6,1 => 6,2 => 7,3 => 8,4 => 10,5 => 10,6 => 11,7 => 11,8 => 12,9 => 12,10 => 12,11 => 13,12 => 13,13 => 14,14 => 14,15 => 14,16 => 14,17 => 14,18 => 15,19 => 15,20 => 15,21 => 15,22 => 15,23 => 15,24 => 17,25 => 17,26 => 19,27 => 19,28 => 19,29 => 19,30 => 19)),
        'bal2' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 6,4 => 7,5 => 7,6 => 8,7 => 8,8 => 8,9 => 9,10 => 9,11 => 9,12 => 9,13 => 9,14 => 10,15 => 13)),
        'bal3' => array('map' => array(0 => 2,1 => 3,2 => 3,3 => 4,4 => 6,5 => 12)),
        'md1a' => array('map' => array(7 => 15,8 => 14,9 => 12,10 => 10,11 => 9,12 => 8,13 => 7,14 => 6,15 => 5,16 => 5,17 => 3,18 => 1),'ceiling' => 18,'floor' => 7),
        'md1b' => array('map' => array(9 => 14,10 => 13,11 => 12,12 => 10,13 => 8,14 => 7,15 => 6,16 => 5,17 => 5,18 => 5,19 => 5,20 => 4,21 => 4,22 => 3,23 => 3,24 => 3,25 => 1),'ceiling' => 25,'floor' => 9),
        'md2' => array('map' => array(22 => 14,23 => 14,24 => 14,25 => 13,26 => 13,27 => 12,28 => 12,29 => 12,30 => 11,31 => 11,32 => 10,33 => 10,34 => 10,35 => 10,36 => 10,37 => 9,38 => 9,39 => 9,40 => 8,41 => 8,42 => 8,43 => 8,44 => 8,45 => 8,46 => 8,47 => 8,48 => 8,49 => 7,50 => 7,51 => 7,52 => 7,53 => 7,54 => 7,55 => 7,56 => 6,57 => 6,58 => 6,59 => 6,60 => 6,61 => 6,62 => 6,63 => 6,64 => 5,65 => 5,66 => 5,67 => 5,68 => 5,69 => 5,70 => 5,71 => 5,72 => 5,73 => 5,74 => 5,75 => 5,76 => 5,77 => 5,78 => 4,79 => 4,80 => 3,81 => 3,82 => 3,83 => 3,84 => 3,85 => 3,86 => 1),'ceiling' => 86,'floor' => 22),
        'md3' => array('map' => array(0 => 13,1 => 12,2 => 11,3 => 10,4 => 10,5 => 9,6 => 9,7 => 8,8 => 8,9 => 7,10 => 6,11 => 6,12 => 6,13 => 5,14 => 5,15 => 4,16 => 4,17 => 1),'ceiling' => 17)
    ),
    "age4:6-4:11" => array(
        'ac1' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 5,5 => 7,6 => 8,7 => 9,8 => 10,9 => 12,10 => 16)),
        'ac2' => array('map' => array(0 => 4,1 => 6,2 => 6,3 => 7,4 => 8,5 => 10,6 => 12,7 => 13,8 => 14,9 => 17,10 => 19)),
        'bal1a' => array('map' => array(0 => 4,1 => 4,2 => 4,3 => 5,4 => 6,5 => 6,6 => 7,7 => 7,8 => 8,9 => 8,10 => 9,11 => 9,12 => 9,13 => 9,14 => 10,15 => 10,16 => 10,17 => 10,18 => 11,19 => 11,20 => 11,21 => 11,22 => 12,23 => 12,24 => 12,25 => 12,26 => 13,27 => 13,28 => 13,29 => 14,30 => 15)),
        'bal1b' => array('map' => array(0 => 3,1 => 3,2 => 6,3 => 7,4 => 8,5 => 8,6 => 9,7 => 9,8 => 9,9 => 10,10 => 10,11 => 10,12 => 10,13 => 11,14 => 11,15 => 12,16 => 12,17 => 13,18 => 13,19 => 13,20 => 14,21 => 14,22 => 14,23 => 14,24 => 14,25 => 14,26 => 14,27 => 15,28 => 15,29 => 18,30 => 18)),
        'bal2' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 4,4 => 4,5 => 4,6 => 5,7 => 7,8 => 8,9 => 8,10 => 8,11 => 9,12 => 9,13 => 9,14 => 10,15 => 13)),
        'bal3' => array('map' => array(0 => 1,1 => 3,2 => 3,3 => 4,4 => 6,5 => 12)),
        'md1a' => array('map' => array(7 => 16,8 => 15,9 => 12,10 => 11,11 => 10,12 => 8,13 => 6,14 => 6,15 => 6,16 => 2,18 => 1),'ceiling' => 18,'floor' => 7),
        'md1b' => array('map' => array(9 => 14,10 => 13,11 => 11,12 => 10,13 => 9,14 => 7,15 => 6,16 => 5,17 => 4,18 => 4,19 => 4,20 => 4,21 => 4,22 => 3,26 => 1),'ceiling' => 26,'floor' => 9),
        'md2' => array('map' => array(18 => 15,19 => 15,20 => 15,21 => 15,22 => 14,23 => 14,24 => 13,25 => 13,26 => 12,27 => 12,28 => 11,29 => 11,30 => 11,31 => 11,32 => 11,33 => 10,34 => 10,35 => 10,36 => 10,37 => 9,38 => 9,39 => 9,40 => 8,41 => 8,42 => 7,43 => 7,44 => 6,45 => 6,46 => 6,47 => 5,48 => 5,49 => 5,50 => 5,51 => 5,52 => 5,53 => 5,54 => 5,55 => 5,56 => 5,57 => 5,58 => 5,59 => 5,60 => 5,61 => 5,62 => 5,63 => 2,96 => 1),'ceiling' => 96,'floor' => 18),
        'md3' => array('map' => array(0 => 13,1 => 12,2 => 9,3 => 7,4 => 6,5 => 4,6 => 1),'ceiling' => 6)
    ),
    "age5:0-5:11" => array(
        'ac1' => array('map' => array(0 => 5,1 => 5,2 => 7,3 => 7,4 => 8,5 => 8,6 => 9,7 => 9,8 => 10,9 => 12,10 => 16)),
        'ac2' => array('map' => array(0 => 1,1 => 3,2 => 4,3 => 5,4 => 8,5 => 10,6 => 11,7 => 12,8 => 13,9 => 14,10 => 19)),
        'bal1a' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 6,6 => 7,7 => 7,8 => 8,9 => 8,10 => 8,11 => 9,12 => 9,13 => 9,14 => 9,15 => 10,16 => 10,17 => 10,18 => 10,19 => 10,20 => 10,21 => 11,22 => 11,23 => 11,24 => 11,25 => 11,26 => 11,27 => 11,28 => 11,29 => 11,30 => 13)),
        'bal1b' => array('map' => array(0 => 3,1 => 3,2 => 5,3 => 6,4 => 7,5 => 8,6 => 8,7 => 9,8 => 9,9 => 9,10 => 10,11 => 10,12 => 10,13 => 10,14 => 11,15 => 11,16 => 12,17 => 12,18 => 12,19 => 12,20 => 12,21 => 13,22 => 13,23 => 13,24 => 13,25 => 14,26 => 14,27 => 15,28 => 15,29 => 15,30 => 15)),
        'bal2' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 3,4 => 3,5 => 4,6 => 4,7 => 5,8 => 5,9 => 5,10 => 5,11 => 6,12 => 6,13 => 8,14 => 10,15 => 12)),
        'bal3' => array('map' => array(0 => 1,1 => 3,2 => 3,3 => 4,4 => 6,5 => 12)),
        'md1a' => array('map' => array(10 => 16,11 => 16,12 => 16,13 => 15,14 => 14,15 => 13,16 => 12,17 => 12,18 => 11,19 => 10,20 => 9,21 => 8,22 => 7,23 => 6,24 => 5,25 => 4,26 => 1),'ceiling' => 26,'floor' => 10),
        'md1b' => array('map' => array(12 => 16,13 => 15,14 => 15,15 => 15,16 => 14,17 => 13,18 => 13,19 => 12,20 => 12,21 => 11,22 => 10,23 => 9,24 => 7,25 => 6,26 => 6,27 => 5,28 => 4,29 => 4,30 => 1),'ceiling' => 30,'floor' => 12),
        'md2' => array('map' => array(25 => 16,26 => 16,27 => 16,28 => 16,29 => 16,30 => 15,31 => 15,32 => 15,33 => 15,34 => 15,35 => 15,36 => 14,37 => 14,38 => 14,39 => 13,40 => 13,41 => 12,42 => 12,43 => 12,44 => 11,45 => 11,46 => 11,47 => 11,48 => 10,49 => 10,50 => 9,51 => 9,52 => 9,53 => 9,54 => 8,55 => 8,56 => 7,57 => 7,58 => 7,59 => 7,60 => 7,61 => 5,62 => 5,63 => 5,64 => 5,65 => 5,66 => 5,67 => 4,68 => 4,69 => 4,70 => 4,71 => 4,72 => 4,73 => 4,74 => 4,75 => 4,76 => 4,77 => 4,78 => 4,79 => 4,80 => 4,81 => 4,82 => 4,83 => 4,84 => 4,85 => 4,86 => 4,87 => 4,88 => 4,89 => 4,90 => 4,91 => 4,92 => 4,93 => 4,94 => 4,95 => 4,96 => 4,97 => 3,98 => 3,99 => 3,100 => 3,101 => 3,102 => 3,103 => 3,104 => 3,105 => 3,106 => 3,107 => 3,108 => 3,109 => 3,110 => 3,111 => 3,112 => 3,113 => 3,114 => 3,115 => 3,116 => 3,117 => 3,118 => 3,119 => 3,120 => 3,121 => 1),'ceiling' => 121,'floor' => 25),
        'md3' => array('map' => array(0 => 11,1 => 11,2 => 9,3 => 4,4 => 1),'ceiling' => 4)
    ),
    "age6:0-6:11" => array(
        'ac1' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 5,5 => 6,6 => 7,7 => 7,8 => 8,9 => 9,10 => 14)),
        'ac2' => array('map' => array(0 => 1,1 => 3,2 => 4,3 => 5,4 => 7,5 => 8,6 => 9,7 => 11,8 => 13,9 => 14,10 => 16)),
        'bal1a' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 6,8 => 6,9 => 6,10 => 6,11 => 7,12 => 7,13 => 7,14 => 7,15 => 8,16 => 8,17 => 8,18 => 8,19 => 8,20 => 8,21 => 8,22 => 8,23 => 8,24 => 9,25 => 9,26 => 9,27 => 9,28 => 10,29 => 10,30 => 13)),
        'bal1b' => array('map' => array(0 => 3,1 => 3,2 => 5,3 => 6,4 => 7,5 => 7,6 => 7,7 => 8,8 => 8,9 => 8,10 => 8,11 => 8,12 => 8,13 => 8,14 => 9,15 => 9,16 => 9,17 => 9,18 => 10,19 => 10,20 => 10,21 => 10,22 => 10,23 => 10,24 => 11,25 => 11,26 => 11,27 => 11,28 => 11,29 => 14,30 => 14)),
        'bal2' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 1,6 => 4,7 => 4,8 => 4,9 => 5,10 => 5,11 => 6,12 => 6,13 => 8,14 => 10,15 => 11)),
        'bal3' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 2,4 => 6,5 => 11)),
        'md1a' => array('map' => array(14 => 14,15 => 13,16 => 12,17 => 11,18 => 10,19 => 9,20 => 7,21 => 6,22 => 6,23 => 5,24 => 4,25 => 1),'ceiling' => 25,'floor' => 14),
        'md1b' => array('map' => array(12 => 16,13 => 15,14 => 15,15 => 15,16 => 14,17 => 13,18 => 12,19 => 11,20 => 9,21 => 7,22 => 6,23 => 6,24 => 5,25 => 5,26 => 5,27 => 1),'ceiling' => 27,'floor' => 12),
        'md2' => array('map' => array(25 => 16,26 => 16,27 => 16,28 => 16,29 => 15,30 => 15,31 => 15,32 => 14,33 => 14,34 => 13,35 => 13,36 => 12,37 => 12,38 => 11,39 => 11,40 => 11,41 => 11,42 => 11,43 => 10,44 => 10,45 => 10,46 => 9,47 => 9,48 => 8,49 => 8,50 => 7,51 => 7,52 => 7,53 => 7,54 => 7,55 => 6,56 => 6,57 => 6,58 => 6,59 => 5,60 => 5,61 => 5,62 => 5,63 => 5,64 => 4,65 => 3,66 => 3,67 => 3,68 => 3,69 => 3,70 => 3,71 => 3,72 => 3,73 => 1),'ceiling' => 73,'floor' => 25),
        'md3' => array('map' => array(0 => 11,1 => 7,2 => 1),'ceiling' => 2)
    ),

    // Age Band 2
    "age7:0-7:11" => array(
        'ac1' => array('map' => array(0 => 5,1 => 6,2 => 7,3 => 7,4 => 8,5 => 9,6 => 10,7 => 10,8 => 11,9 => 15,10 => 17)),
        'ac2' => array('map' => array(0 => 3,1 => 3,2 => 5,3 => 5,4 => 7,5 => 9,6 => 9,7 => 11,8 => 12,9 => 15,10 => 17)),
        'bal1a' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 4,4 => 5,5 => 6,6 => 7,7 => 7,8 => 8,9 => 8,10 => 8,11 => 9,12 => 9,13 => 9,14 => 9,15 => 9,16 => 10,17 => 10,18 => 10,19 => 10,20 => 11,21 => 11,22 => 11,23 => 11,24 => 11,25 => 12,26 => 12,27 => 12,28 => 14,29 => 14,30 => 14)),
        'bal1b' => array('map' => array(0 => 4,1 => 4,2 => 5,3 => 7,4 => 8,5 => 9,6 => 10,7 => 10,8 => 11,9 => 11,10 => 11,11 => 11,12 => 12,13 => 12,14 => 12,15 => 12,16 => 13,17 => 13,18 => 13,19 => 13,20 => 14,21 => 14,22 => 14,23 => 15,24 => 15,25 => 15,26 => 15,27 => 15,28 => 15,29 => 15,30 => 16)),
        'bal2' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 3,4 => 3,5 => 3,6 => 3,7 => 3,8 => 4,9 => 4,10 => 5,11 => 6,12 => 6,13 => 8,14 => 10,15 => 12)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 3,3 => 3,4 => 4,5 => 11)),
        'bal3b' => array('map' => array(0 => 3,1 => 4,2 => 5,3 => 6,4 => 9,5 => 13)),
        'md1a' => array('map' => array(21 => 16,22 => 15,23 => 15,24 => 14,25 => 14,26 => 13,27 => 12,28 => 12,29 => 11,30 => 10,31 => 9,32 => 9,33 => 8,34 => 8,35 => 8,36 => 7,37 => 6,38 => 6,39 => 6,40 => 6,41 => 6,42 => 6,43 => 5,44 => 5,45 => 5,46 => 5,47 => 4),'ceiling' => 47),
        'md1b' => array('map' => array(21 => 16,22 => 15,23 => 15,24 => 15,25 => 15,26 => 15,27 => 14,28 => 13,29 => 13,30 => 13,31 => 12,32 => 12,33 => 11,34 => 11,35 => 10,36 => 10,37 => 9,38 => 8,39 => 8,40 => 8,41 => 8,42 => 7,43 => 7,44 => 7,45 => 7,46 => 7,47 => 7,48 => 6,49 => 6,50 => 6,51 => 4),'ceiling' => 51,'floor' => 21),
        'md2' => array('map' => array(21 => 15,22 => 14,23 => 13,24 => 13,25 => 12,26 => 12,27 => 11,28 => 11,29 => 11,30 => 10,31 => 9,32 => 9,33 => 8,34 => 8,35 => 7,36 => 7,37 => 6,38 => 6,39 => 6,40 => 6,41 => 5,42 => 5,43 => 5,44 => 5,45 => 5,46 => 5,47 => 5,48 => 3),'ceiling' => 48,'floor' => 21),
        'md3' => array('map' => array(0 => 12,1 => 10,2 => 8,3 => 5,4 => 3,5 => 1),'ceiling' => 5)
    ),
    "age8:0-8:11" => array(
        'ac1' => array('map' => array(0 => 4,1 => 5,2 => 5,3 => 6,4 => 7,5 => 7,6 => 8,7 => 9,8 => 10,9 => 12,10 => 15)),
        'ac2' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 5,5 => 6,6 => 8,7 => 11,8 => 11,9 => 14,10 => 17)),
        'bal1a' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 6,8 => 6,9 => 7,10 => 7,11 => 8,12 => 8,13 => 9,14 => 9,15 => 9,16 => 9,17 => 9,18 => 9,19 => 10,20 => 10,21 => 10,22 => 11,23 => 11,24 => 11,25 => 11,26 => 12,27 => 12,28 => 12,29 => 13,30 => 13)),
        'bal1b' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 5,4 => 7,5 => 8,6 => 9,7 => 10,8 => 10,9 => 11,10 => 11,11 => 11,12 => 11,13 => 11,14 => 11,15 => 12,16 => 12,17 => 12,18 => 12,19 => 13,20 => 13,21 => 13,22 => 13,23 => 13,24 => 13,25 => 14,26 => 14,27 => 15,28 => 15,29 => 15,30 => 15)),
        'bal2' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 3,4 => 3,5 => 3,6 => 3,7 => 3,8 => 4,9 => 4,10 => 5,11 => 6,12 => 6,13 => 7,14 => 7,15 => 11)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 3,3 => 3,4 => 4,5 => 11)),
        'bal3b' => array('map' => array(0 => 2,1 => 3,2 => 4,3 => 5,4 => 6,5 => 12)),
        'md1a' => array('map' => array(32 => 7,33 => 6,34 => 6,35 => 5,36 => 3,20 => 15,21 => 14,22 => 13,23 => 12,24 => 12,25 => 11,26 => 11,27 => 10,28 => 9,29 => 8,30 => 7,31 => 7),'ceiling' => 36,'floor' => 20),
        'md1b' => array('map' => array(22 => 15,23 => 15,24 => 14,25 => 14,26 => 13,27 => 12,28 => 11,29 => 11,30 => 10,31 => 10,32 => 10,33 => 9,34 => 8,35 => 8,36 => 7,37 => 7,38 => 7,39 => 7,40 => 6,41 => 6,42 => 6,43 => 6,44 => 4),'ceiling' => 44,'floor' => 22),
        'md2' => array('map' => array(18 => 14,19 => 13,20 => 13,21 => 12,22 => 12,23 => 12,24 => 12,25 => 11,26 => 10,27 => 9,28 => 9,29 => 8,30 => 8,31 => 8,32 => 7,33 => 7,34 => 6,35 => 6,36 => 5,37 => 5,38 => 5,39 => 5,40 => 5,41 => 5,42 => 4,43 => 4,44 => 4,45 => 3),'ceiling' => 45,'floor' => 18),
        'md3' => array('map' => array(0 => 12,1 => 6,2 => 5,3 => 1),'ceiling' => 3)
    ),
    "age9:0-9:11" => array(
        'ac1' => array('map' => array(0 => 5,1 => 8,2 => 9,3 => 9,4 => 9,5 => 9,6 => 10,7 => 10,8 => 10,9 => 12,10 => 15)),
        'ac2' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 5,5 => 6,6 => 8,7 => 11,8 => 11,9 => 12,10 => 14)),
        'bal1a' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 5,8 => 6,9 => 6,10 => 7,11 => 7,12 => 7,13 => 7,14 => 7,15 => 8,16 => 8,17 => 8,18 => 8,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 11,27 => 11,28 => 11,29 => 12,30 => 13)),
        'bal1b' => array('map' => array(0 => 3,1 => 3,2 => 4,3 => 5,4 => 7,5 => 8,6 => 9,7 => 9,8 => 10,9 => 10,10 => 11,11 => 11,12 => 11,13 => 11,14 => 11,15 => 11,16 => 11,17 => 12,18 => 12,19 => 12,20 => 12,21 => 12,22 => 12,23 => 12,24 => 12,25 => 13,26 => 13,27 => 13,28 => 13,29 => 13,30 => 13)),
        'bal2' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 1,6 => 1,7 => 1,8 => 2,9 => 2,10 => 2,11 => 3,12 => 3,13 => 4,14 => 7,15 => 11)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 2,4 => 3,5 => 11)),
        'bal3b' => array('map' => array(0 => 2,1 => 3,2 => 4,3 => 5,4 => 6,5 => 12)),
        'md1a' => array('map' => array(32 => 6,33 => 6,34 => 6,35 => 5,36 => 3,19 => 16,20 => 15,21 => 14,22 => 13,23 => 12,24 => 12,25 => 11,26 => 10,27 => 9,28 => 8,29 => 7,30 => 7,31 => 6),'ceiling' => 36,'floor' => 19),
        'md1b' => array('map' => array(21 => 16,22 => 15,23 => 15,24 => 14,25 => 14,26 => 13,27 => 12,28 => 11,29 => 11,30 => 10,31 => 9,32 => 9,33 => 8,34 => 8,35 => 7,36 => 7,37 => 6,38 => 6,39 => 6,40 => 6,41 => 5,42 => 5,43 => 5,44 => 4),'ceiling' => 44,'floor' => 21),
        'md2' => array('map' => array(16 => 15,17 => 14,18 => 13,19 => 13,20 => 12,21 => 11,22 => 10,23 => 10,24 => 10,25 => 9,26 => 9,27 => 8,28 => 7,29 => 7,30 => 6,31 => 6,32 => 6,33 => 5,34 => 4,35 => 4,36 => 4,37 => 3,38 => 3,39 => 3,40 => 3,41 => 3,42 => 3,43 => 3,44 => 2),'ceiling' => 44,'floor' => 16),
        'md3' => array('map' => array(0 => 11,1 => 6,2 => 4,3 => 1),'ceiling' => 3)
    ),
    "age10:0-10:11" => array(
        'ac1' => array('map' => array(0 => 5,1 => 6,2 => 6,3 => 7,4 => 7,5 => 8,6 => 8,7 => 9,8 => 9,9 => 12,10 => 14)),
        'ac2' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 5,5 => 5,6 => 7,7 => 8,8 => 11,9 => 12,10 => 14)),
        'bal1a' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 5,8 => 5,9 => 5,10 => 6,11 => 6,12 => 6,13 => 6,14 => 7,15 => 7,16 => 8,17 => 8,18 => 8,19 => 9,20 => 9,21 => 9,22 => 9,23 => 9,24 => 9,25 => 9,26 => 9,27 => 10,28 => 10,29 => 10,30 => 13)),
        'bal1b' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 6,5 => 7,6 => 8,7 => 9,8 => 9,9 => 9,10 => 10,11 => 10,12 => 10,13 => 10,14 => 10,15 => 10,16 => 10,17 => 11,18 => 11,19 => 11,20 => 11,21 => 11,22 => 11,23 => 11,24 => 12,25 => 13,26 => 13,27 => 13,28 => 13,29 => 13,30 => 13)),
        'bal2' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 1,6 => 1,7 => 1,8 => 2,9 => 2,10 => 2,11 => 3,12 => 3,13 => 3,14 => 4,15 => 11)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
        'bal3b' => array('map' => array(0 => 2,1 => 3,2 => 4,3 => 5,4 => 6,5 => 12)),
        'md1a' => array('map' => array(32 => 5,33 => 3,18 => 16,19 => 15,20 => 14,21 => 14,22 => 13,23 => 12,24 => 11,25 => 10,26 => 9,27 => 8,28 => 8,29 => 7,30 => 6,31 => 5),'ceiling' => 33,'floor' => 18),
        'md1b' => array('map' => array(32 => 7,33 => 7,34 => 6,35 => 6,36 => 6,37 => 6,38 => 6,39 => 5,40 => 4,20 => 16,21 => 15,22 => 15,23 => 14,24 => 13,25 => 12,26 => 12,27 => 11,28 => 10,29 => 9,30 => 8,31 => 7),'ceiling' => 40,'floor' => 20),
        'md2' => array('map' => array(15 => 15,16 => 14,17 => 13,18 => 13,19 => 12,20 => 11,21 => 11,22 => 10,23 => 9,24 => 8,25 => 7,26 => 7,27 => 7,28 => 6,29 => 6,30 => 6,31 => 6,32 => 6,33 => 5,34 => 4,35 => 4,36 => 4,37 => 3,38 => 3,39 => 3,40 => 3,41 => 3,42 => 3,43 => 3,44 => 2),'ceiling' => 44,'floor' => 15),
        'md3' => array('map' => array(0 => 11,1 => 6,2 => 4,3 => 1),'ceiling' => 3)
    ),

    // Age Band 3
    "age11:0-11:11" => array(
        'ac1a' => array('map' => array(0 => 4,1 => 5,2 => 6,3 => 6,4 => 7,5 => 7,6 => 8,7 => 10,8 => 11,9 => 11,10 => 14)),
        'ac1b' => array('map' => array(0 => 4,1 => 6,2 => 7,3 => 8,4 => 9,5 => 10,6 => 11,7 => 12,8 => 12,9 => 13,10 => 15)),
        'ac2' => array('map' => array(0 => 3,1 => 5,2 => 6,3 => 6,4 => 7,5 => 10,6 => 11,7 => 13,8 => 15,9 => 17,10 => 17)),
        'bal1' => array('map' => array(0 => 2,1 => 3,2 => 3,3 => 4,4 => 5,5 => 5,6 => 6,7 => 7,8 => 7,9 => 7,10 => 7,11 => 8,12 => 8,13 => 8,14 => 8,15 => 8,16 => 8,17 => 8,18 => 9,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 10,27 => 11,28 => 11,29 => 13,30 => 13)),
        'bal2' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 4,5 => 5,6 => 7,7 => 8,8 => 9,9 => 9,10 => 9,11 => 10,12 => 10,13 => 11,14 => 12,15 => 12)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
        'bal3b' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 4,4 => 6,5 => 11)),
        'md1a' => array('map' => array(16 => 14,17 => 12,18 => 11,19 => 11,20 => 10,21 => 10,22 => 9,23 => 8,24 => 7,25 => 6,26 => 5,27 => 2),'ceiling' => 27,'floor' => 16),
        'md1b' => array('map' => array(15 => 16,16 => 15,17 => 14,18 => 13,19 => 12,20 => 12,21 => 11,22 => 10,23 => 10,24 => 9,25 => 9,26 => 8,27 => 8,28 => 7,29 => 7,30 => 7,31 => 6,32 => 6,33 => 5,34 => 5,35 => 5,36 => 5,37 => 5,38 => 4,39 => 3,40 => 1),'ceiling' => 40,'floor' => 15),
        'md2' => array('map' => array(24 => 16,25 => 15,26 => 15,27 => 14,28 => 13,29 => 13,30 => 13,31 => 12,32 => 12,33 => 12,34 => 12,35 => 11,36 => 11,37 => 11,38 => 11,39 => 10,40 => 10,41 => 10,42 => 10,43 => 9,44 => 9,45 => 8,46 => 8,47 => 8,48 => 7,49 => 7,50 => 7,51 => 7,52 => 7,53 => 7,54 => 6,55 => 6,56 => 6,57 => 6,58 => 6,59 => 6,60 => 5,61 => 5,62 => 5,63 => 5,64 => 5,65 => 5,66 => 5,67 => 4,68 => 4,69 => 4,70 => 4,71 => 4,72 => 4,73 => 4,74 => 4,75 => 4,76 => 4,77 => 4,78 => 4,79 => 4,80 => 4,81 => 4,82 => 4,83 => 4,84 => 4,85 => 4,86 => 4,87 => 4,88 => 4,89 => 4,90 => 4,91 => 1),'ceiling' => 91,'floor' => 24),
        'md3' => array('map' => array(0 => 13,1 => 10,2 => 8,3 => 8,4 => 7,5 => 6,6 => 5,7 => 4,8 => 4,9 => 4,10 => 1),'ceiling' => 10)
    ),
    "age12:0-12:11" => array(
        'ac1a' => array('map' => array(0 => 4,1 => 5,2 => 5,3 => 6,4 => 6,5 => 7,6 => 7,7 => 8,8 => 9,9 => 11,10 => 14)),
        'ac1b' => array('map' => array(0 => 3,1 => 4,2 => 6,3 => 7,4 => 8,5 => 9,6 => 10,7 => 11,8 => 12,9 => 13,10 => 15)),
        'ac2' => array('map' => array(0 => 3,1 => 4,2 => 6,3 => 6,4 => 7,5 => 9,6 => 10,7 => 13,8 => 14,9 => 16,10 => 16)),
        'bal1' => array('map' => array(0 => 2,1 => 3,2 => 3,4 => 5,5 => 5,6 => 6,7 => 7,8 => 7,9 => 7,10 => 7,11 => 8,12 => 8,13 => 8,14 => 8,15 => 8,16 => 8,17 => 8,18 => 9,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 10,27 => 11,28 => 11,29 => 13,30 => 13)),
        'bal2' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 3,5 => 5,6 => 7,7 => 8,8 => 9,9 => 9,10 => 9,11 => 10,12 => 10,13 => 11,14 => 12,15 => 12)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
        'bal3b' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 3,4 => 10,5 => 11)),
        'md1a' => array('map' => array(14 => 15,15 => 14,16 => 13,17 => 11,18 => 11,19 => 10,20 => 9,21 => 8,22 => 7,23 => 6,24 => 6,25 => 5,26 => 5,27 => 3,28 => 2,29 => 1),'ceiling' => 29,'floor' => 14),
        'md1b' => array('map' => array(15 => 16,16 => 14,17 => 13,18 => 12,19 => 11,20 => 11,21 => 10,22 => 9,23 => 8,24 => 8,25 => 7,26 => 6,27 => 6,28 => 5,29 => 5,30 => 5,31 => 4,32 => 4,33 => 4,34 => 3,35 => 3,36 => 3,37 => 3,38 => 3,39 => 1),'ceiling' => 39,'floor' => 15),
        'md2' => array('map' => array(22 => 15,23 => 14,24 => 14,25 => 14,26 => 14,27 => 13,28 => 12,29 => 12,30 => 11,31 => 11,32 => 11,33 => 11,34 => 11,35 => 10,36 => 10,37 => 10,38 => 10,39 => 10,40 => 10,41 => 9,42 => 9,43 => 8,44 => 8,45 => 7,46 => 7,47 => 7,48 => 6,49 => 6,50 => 6,51 => 6,52 => 5,53 => 5,54 => 5,55 => 5,56 => 5,57 => 5,58 => 5,59 => 5,60 => 5,61 => 5,62 => 5,63 => 4,64 => 4,65 => 4,66 => 4,67 => 4,68 => 4,69 => 3,70 => 1),'ceiling' => 70,'floor' => 22),
        'md3' => array('map' => array(0 => 13,1 => 9,2 => 8,3 => 7,4 => 6,5 => 5,6 => 3,7 => 3,8 => 3,9 => 3,10 => 1),'ceiling' => 10)
    ),
    "age13:0-13:11" => array(
        'ac1a' => array('map' => array(0 => 4,1 => 5,2 => 5,3 => 5,4 => 6,5 => 6,6 => 7,7 => 8,8 => 9,9 => 11,10 => 13)),
        'ac1b' => array('map' => array(0 => 3,1 => 4,2 => 6,3 => 7,4 => 8,5 => 9,6 => 10,7 => 10,8 => 11,9 => 12,10 => 14)),
        'ac2' => array('map' => array(1 => 4,2 => 4,3 => 6,4 => 7,5 => 9,6 => 10,7 => 11,8 => 12,9 => 15,10 => 16)),
        'bal1' => array('map' => array(0 => 2,1 => 3,2 => 3,3 => 4,4 => 5,5 => 5,6 => 6,7 => 7,8 => 7,9 => 7,10 => 7,11 => 8,12 => 8,13 => 8,14 => 8,15 => 8,16 => 8,17 => 8,18 => 9,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 10,27 => 11,28 => 11,29 => 13,30 => 13)),
        'bal2' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 3,5 => 5,6 => 6,7 => 7,8 => 9,9 => 9,10 => 9,11 => 10,12 => 10,13 => 11,14 => 12,15 => 12)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
        'bal3b' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 3,4 => 10,5 => 11)),
        'md1a' => array('map' => array(14 => 14,15 => 13,16 => 12,17 => 11,18 => 11,19 => 10,20 => 9,21 => 7,22 => 6,23 => 6,24 => 6,25 => 4,26 => 1),'ceiling' => 26,'floor' => 14),
        'md1b' => array('map' => array(16 => 14,17 => 13,18 => 12,19 => 11,20 => 10,21 => 10,22 => 9,23 => 8,24 => 8,25 => 7,26 => 6,27 => 6,28 => 5,29 => 4,30 => 1),'ceiling' => 30,'floor' => 16),
        'md2' => array('map' => array(20 => 16,21 => 16,22 => 15,23 => 14,24 => 14,25 => 14,26 => 13,27 => 13,28 => 12,29 => 12,30 => 11,31 => 11,32 => 11,33 => 11,34 => 11,35 => 10,36 => 10,37 => 10,38 => 10,39 => 9,40 => 9,41 => 8,42 => 8,43 => 8,44 => 8,45 => 7,46 => 7,47 => 7,48 => 6,49 => 6,50 => 6,51 => 6,52 => 5,53 => 5,54 => 5,55 => 5,56 => 5,57 => 5,58 => 5,59 => 5,60 => 5,61 => 4,62 => 4,63 => 3,64 => 1),'ceiling' => 64,'floor' => 20),
        'md3' => array('map' => array(0 => 13,1 => 9,2 => 7,3 => 7,4 => 6,5 => 5,6 => 3,7 => 1),'ceiling' => 7)
    ),
    "age14:0-14:11" => array(
        'ac1a' => array('map' => array(0 => 2,1 => 2,2 => 4,3 => 4,4 => 6,5 => 6,6 => 7,7 => 8,8 => 8,9 => 9,10 => 13)),
        'ac1b' => array('map' => array(0 => 3,1 => 3,2 => 5,3 => 5,4 => 6,5 => 6,6 => 9,7 => 10,8 => 11,9 => 12,10 => 14)),
        'ac2' => array('map' => array(0 => 3,1 => 4,2 => 4,3 => 5,4 => 7,5 => 8,6 => 10,7 => 11,8 => 12,9 => 15,10 => 16)),
        'bal1' => array('map' => array(0 => 2,1 => 3,2 => 3,3 => 4,4 => 5,5 => 5,6 => 5,7 => 6,8 => 7,9 => 7,10 => 7,11 => 8,12 => 8,13 => 8,14 => 8,15 => 8,16 => 8,17 => 8,18 => 9,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 10,27 => 10,28 => 11,29 => 13,30 => 13)),
        'bal2' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 3,4 => 3,5 => 4,6 => 5,7 => 5,8 => 6,9 => 6,10 => 6,11 => 7,12 => 7,13 => 8,14 => 8,15 => 12)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
        'bal3b' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 3,4 => 10,5 => 11)),
        'md1a' => array('map' => array(14 => 14,15 => 13,16 => 12,17 => 11,18 => 10,19 => 10,20 => 9,21 => 7,22 => 6,23 => 6,24 => 5,25 => 1),'ceiling' => 25,'floor' => 14),
        'md1b' => array('map' => array(15 => 16,16 => 14,17 => 13,18 => 11,19 => 11,20 => 10,21 => 10,22 => 9,23 => 8,24 => 8,25 => 7,26 => 6,27 => 6,28 => 1),'ceiling' => 28,'floor' => 15),
        'md2' => array('map' => array(20 => 16,21 => 16,22 => 15,23 => 14,24 => 13,25 => 12,26 => 12,27 => 12,28 => 11,29 => 11,30 => 10,31 => 10,32 => 10,33 => 10,34 => 10,35 => 10,36 => 10,37 => 9,38 => 9,39 => 8,40 => 8,41 => 8,42 => 7,43 => 7,44 => 7,45 => 7,46 => 7,47 => 7,48 => 6,49 => 6,50 => 6,51 => 6,52 => 5,53 => 5,54 => 5,55 => 5,56 => 5,57 => 5,58 => 5,59 => 4,60 => 4,61 => 3,62 => 3,63 => 3,64 => 1),'ceiling' => 64,'floor' => 20),
        'md3' => array('map' => array(0 => 12,1 => 8,2 => 7,3 => 1),'ceiling' => 3)
    ),
    "age15:0-15:11" => array(
        'ac1a' => array('map' => array(0 => 2,1 => 2,2 => 4,3 => 4,4 => 6,5 => 6,6 => 6,7 => 7,8 => 8,9 => 9,10 => 13)),
        'ac1b' => array('map' => array(0 => 3,1 => 3,2 => 5,3 => 5,4 => 6,5 => 6,6 => 9,7 => 10,8 => 11,9 => 12,10 => 14)),
        'ac2' => array('map' => array(0 => 3,1 => 4,2 => 4,3 => 5,4 => 7,5 => 8,6 => 10,7 => 11,8 => 12,9 => 15,10 => 16)),
        'bal1' => array('map' => array(0 => 2,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 6,8 => 7,9 => 7,10 => 7,11 => 8,12 => 8,13 => 8,14 => 8,15 => 8,16 => 8,17 => 8,18 => 9,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 10,27 => 10,28 => 11,29 => 13,30 => 13)),
        'bal2' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 3,4 => 3,5 => 4,6 => 5,7 => 5,8 => 6,9 => 6,10 => 6,11 => 7,12 => 7,13 => 8,14 => 8,15 => 12)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
        'bal3b' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 3,4 => 10,5 => 11)),
        'md1a' => array('map' => array(14 => 14,15 => 13,16 => 12,17 => 11,18 => 10,19 => 9,20 => 8,21 => 7,22 => 6,23 => 6,24 => 5,25 => 1),'ceiling' => 25,'floor' => 14),
        'md1b' => array('map' => array(15 => 14,16 => 13,17 => 13,18 => 11,19 => 11,20 => 10,21 => 10,22 => 8,23 => 7,24 => 7,25 => 6,26 => 6,27 => 6,28 => 1),'ceiling' => 28,'floor' => 15),
        'md2' => array('map' => array(21 => 15,22 => 14,23 => 14,24 => 13,25 => 12,26 => 12,27 => 12,28 => 11,29 => 11,30 => 10,31 => 10,32 => 10,33 => 9,34 => 9,35 => 8,36 => 8,37 => 7,38 => 7,39 => 6,40 => 6,41 => 6,42 => 6,43 => 6,44 => 6,45 => 6,46 => 6,47 => 6,48 => 6,49 => 6,50 => 5,51 => 5,52 => 5,53 => 5,54 => 5,55 => 5,56 => 5,57 => 5,58 => 5,59 => 4,60 => 4,61 => 3,62 => 3,63 => 3,64 => 1),'ceiling' => 64,'floor' => 21),
        'md3' => array('map' => array(0 => 12,1 => 7,2 => 7,3 => 1),'ceiling' => 3)
    ),
    "age16:0-16:11" => array(
        'ac1a' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 3,4 => 3,5 => 4,6 => 5,7 => 7,8 => 8,9 => 9,10 => 13)),
        'ac1b' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 4,4 => 4,5 => 6,6 => 9,7 => 9,8 => 10,9 => 12,10 => 13)),
        'ac2' => array('map' => array(0 => 3,1 => 3,2 => 3,3 => 3,4 => 5,5 => 7,6 => 9,7 => 10,8 => 12,9 => 14,10 => 15)),
        'bal1' => array('map' => array(0 => 2,1 => 3,2 => 3,3 => 3,4 => 4,5 => 4,6 => 5,7 => 6,8 => 7,9 => 7,10 => 7,11 => 8,12 => 8,13 => 8,14 => 8,15 => 8,16 => 8,17 => 8,18 => 9,19 => 9,20 => 9,21 => 9,22 => 9,23 => 10,24 => 10,25 => 10,26 => 10,27 => 10,28 => 11,29 => 13,30 => 13)),
        'bal2' => array('map' => array(0 => 2,1 => 2,2 => 3,3 => 3,4 => 3,5 => 4,6 => 5,7 => 5,8 => 6,9 => 6,10 => 6,11 => 7,12 => 7,13 => 8,14 => 8,15 => 12)),
        'bal3a' => array('map' => array(0 => 1,1 => 1,2 => 1,3 => 1,4 => 1,5 => 11)),
        'bal3b' => array('map' => array(0 => 1,1 => 1,2 => 2,3 => 2,4 => 4,5 => 11)),
        'md1a' => array('map' => array(14 => 13,15 => 13,16 => 12,17 => 10,18 => 9,19 => 8,20 => 7,21 => 6,22 => 6,23 => 6,24 => 5,25 => 1),'ceiling' => 25,'floor' => 14),
        'md1b' => array('map' => array(15 => 14,16 => 13,17 => 12,18 => 11,19 => 10,20 => 9,21 => 9,22 => 8,23 => 7,24 => 7,25 => 5,26 => 5,27 => 4,28 => 1),'ceiling' => 28,'floor' => 15),
        'md2' => array('map' => array(21 => 15,22 => 14,23 => 14,24 => 13,25 => 12,26 => 12,27 => 11,28 => 11,29 => 10,30 => 10,31 => 10,32 => 9,33 => 9,34 => 9,35 => 8,36 => 8,37 => 7,38 => 7,39 => 6,40 => 6,41 => 6,42 => 6,43 => 6,44 => 5,45 => 5,46 => 5,47 => 5,48 => 5,49 => 4,50 => 4,51 => 4,52 => 3,53 => 3,54 => 3,55 => 3,56 => 1),'ceiling' => 56,'floor' => 21),
        'md3' => array('map' => array(0 => 12,1 => 6,2 => 6,3 => 1),'ceiling' => 3)
    )
);

static private $labels = array(
    // ageband => [code and label]
    1 => ['md1a'=>  ['code'=>'MD 1',  'label'=> 'Posting Coins pref hand'],
          'md1b'=>  ['code'=>'',      'label'=> 'Posting Coins non-pref hand'],
          'md2'=>   ['code'=>'MD 2',  'label'=> 'Threading Beads'],
          'md3'=>   ['code'=>'MD 3',  'label'=> 'Drawing Trail 1'],
          'ac1'=>   ['code'=>'A&C 1', 'label'=> 'Catching Beanbag'],
          'ac2'=>   ['code'=>'A&C 2', 'label'=> 'Throwing Beanbag onto Mat'],
          'bal1a'=> ['code'=>'Bal 1', 'label'=> 'One-Leg Balance best leg'],
          'bal1b'=> ['code'=>'',      'label'=> 'One-Leg Balance other leg'],
          'bal2'=>  ['code'=>'Bal 2', 'label'=> 'Walking Heels Raised'],
          'bal3'=>  ['code'=>'Bal 3', 'label'=> 'Jumping on Mats']
         ],
    2 => ['md1a'=>  ['code'=>'MD 1',  'label'=> 'Placing Pegs pref hand'],
          'md1b'=>  ['code'=>'',      'label'=> 'Placing Pegs non-pref hand'],
          'md2'=>   ['code'=>'MD 2',  'label'=> 'Threading Lace'],
          'md3'=>   ['code'=>'MD 3',  'label'=> 'Drawing Trail 2'],
          'ac1'=>   ['code'=>'A&C 1', 'label'=> 'Catching with Two Hands'],
          'ac2'=>   ['code'=>'A&C 2', 'label'=> 'Throwing Beanbag onto Mat'],
          'bal1a'=> ['code'=>'Bal 1', 'label'=> 'One-Leg Balance best leg'],
          'bal1b'=> ['code'=>'',      'label'=> 'One-Leg Balance other leg'],
          'bal2'=>  ['code'=>'Bal 2', 'label'=> 'Walking Heel-to-Toe Forwards'],
          'bal3a'=> ['code'=>'Bal 3', 'label'=> 'Hopping on Mats best leg'],
          'bal3b'=> ['code'=>'',      'label'=> 'Hopping on Mats other leg'],
         ],
    3 => ['md1a'=>  ['code'=>'MD 1',  'label'=> 'Turning Pegs pref hand'],
          'md1b'=>  ['code'=>'',      'label'=> 'Turning Pegs non-pref hand'],
          'md2'=>   ['code'=>'MD 2',  'label'=> 'Triangle with Nuts and Bolts'],
          'md3'=>   ['code'=>'MD 3',  'label'=> 'Drawing Trail 3'],
          'ac1a'=>  ['code'=>'A&C 1', 'label'=> 'Catching with One Hand - best hand'],
          'ac1b'=>  ['code'=>'',      'label'=> 'Catching with One Hand - other hand'],
          'ac2'=>   ['code'=>'A&C 2', 'label'=> 'Throwing at Wall Target'],
          'bal1'=>  ['code'=>'Bal 1', 'label'=> 'Two-Board Balance'],
          'bal2'=>  ['code'=>'Bal 2', 'label'=> 'Walking Heel-to-Toe Backwards'],
          'bal3a'=> ['code'=>'Bal 3', 'label'=> 'Zig-Zag Hopping best leg'],
          'bal3b'=> ['code'=>'',      'label'=> 'Zig-Zag Hopping other leg'],
         ],
);

    static function GetComponentTotalScore( string $component, int $score )
    {
        $stdScore = 0;
        $percentile = 0;

        // ceiling cases
        if( $component == 'md' && $score > 43  )  $score = 43;
        if( $component == 'ac' && $score > 33  )  $score = 33;
        if( $component == 'bal' && $score > 44  )  $score = 44;

        foreach( self::$raComponentTotals as $ra ) {
            $raCompScores = SEEDCore_ParseRangeStr( $ra[$component] );
            if( in_array($score, $raCompScores[0]) ) {
                $stdScore = $ra['std'];
                $percentile = $ra['pct'];
                break;
            }
        }

        return( [$stdScore,$percentile] );
    }

    static function GetTotalScore( int $total )
    {
        $stdScore = 0;
        $percentile = 0;
        $zone = "";

        // floor and ceiling cases
        if( $total < 29  )   $total = 29;
        if( $total > 108  )  $total = 108;

        foreach( self::$raTestTotals as $ra ) {
            $raTotals = SEEDCore_ParseRangeStr( $ra['total'] );
            if( in_array($total, $raTotals[0]) ) {
                $stdScore = $ra['std'];
                $percentile = $ra['pct'];
                $zone = $ra['zone'];
                break;
            }
        }

        return( [$stdScore,$percentile,$zone] );
    }


// Map component totals to standard totals and percentiles
static private $raComponentTotals = array(
    ['md'=>"43",    'ac'=>"33",    'bal'=>"44",    'std'=>19, 'pct'=>99.9 ],    // these are all ceilings
    ['md'=>"42",    'ac'=>"31-32", 'bal'=>"42-43", 'std'=>18, 'pct'=>99.5 ],
    ['md'=>"41",    'ac'=>"30",    'bal'=>"40-41", 'std'=>17, 'pct'=>99 ],
    ['md'=>"40",    'ac'=>"29",    'bal'=>"38-39", 'std'=>16, 'pct'=>98 ],
    ['md'=>"38-39", 'ac'=>"27-28", 'bal'=>"37",    'std'=>15, 'pct'=>95 ],
    ['md'=>"37",    'ac'=>"26",    'bal'=>"36",    'std'=>14, 'pct'=>91 ],
    ['md'=>"35-36", 'ac'=>"24-25", 'bal'=>"",      'std'=>13, 'pct'=>84 ],
    ['md'=>"33-34", 'ac'=>"22-23", 'bal'=>"35",    'std'=>12, 'pct'=>75 ],
    ['md'=>"31-32", 'ac'=>"21",    'bal'=>"33-34", 'std'=>11, 'pct'=>63 ],
    ['md'=>"29-30", 'ac'=>"19-20", 'bal'=>"31-32", 'std'=>10, 'pct'=>50 ],
    ['md'=>"26-28", 'ac'=>"17-18", 'bal'=>"28-30", 'std'=>9,  'pct'=>37 ],
    ['md'=>"24-25", 'ac'=>"15-16", 'bal'=>"25-27", 'std'=>8,  'pct'=>25 ],
    ['md'=>"22-23", 'ac'=>"14",    'bal'=>"23-24", 'std'=>7,  'pct'=>16 ],
    ['md'=>"19-21", 'ac'=>"13",    'bal'=>"19-22", 'std'=>6,  'pct'=>9 ],
    ['md'=>"16-18", 'ac'=>"11-12", 'bal'=>"15-18", 'std'=>5,  'pct'=>5 ],
    ['md'=>"13-15", 'ac'=>"10",    'bal'=>"13-14", 'std'=>4,  'pct'=>2 ],
    ['md'=>"9-12",  'ac'=>"9",     'bal'=>"11-12", 'std'=>3,  'pct'=>1 ],
    ['md'=>"4-8",   'ac'=>"7-8",   'bal'=>"9-10",  'std'=>2,  'pct'=>0.5 ],
    ['md'=>"0-4",   'ac'=>"0-7",   'bal'=>"0-9",   'std'=>1,  'pct'=>0.1 ]
);

// Map total test score to standard totals and percentiles
static private $raTestTotals = array(
    ['total'=>"108",     'std'=>19, 'pct'=>99.9, 'zone'=>"green" ],
    ['total'=>"105-107", 'std'=>18, 'pct'=>99.5, 'zone'=>"green" ],
    ['total'=>"102-104", 'std'=>17, 'pct'=>99,   'zone'=>"green" ],
    ['total'=>"99-101",  'std'=>16, 'pct'=>98,   'zone'=>"green" ],
    ['total'=>"96-98",   'std'=>15, 'pct'=>95,   'zone'=>"green" ],
    ['total'=>"93-95",   'std'=>14, 'pct'=>91,   'zone'=>"green" ],
    ['total'=>"90-92",   'std'=>13, 'pct'=>84,   'zone'=>"green" ],
    ['total'=>"86-89",   'std'=>12, 'pct'=>75,   'zone'=>"green" ],
    ['total'=>"82-85",   'std'=>11, 'pct'=>63,   'zone'=>"green" ],
    ['total'=>"78-81",   'std'=>10, 'pct'=>50,   'zone'=>"green" ],
    ['total'=>"73-77",   'std'=>9,  'pct'=>37,   'zone'=>"green" ],
    ['total'=>"68-72",   'std'=>8,  'pct'=>25,   'zone'=>"green" ],
    ['total'=>"63-67",   'std'=>7,  'pct'=>16,   'zone'=>"green" ],
    ['total'=>"57-62",   'std'=>6,  'pct'=>9,    'zone'=>"yellow" ],
    ['total'=>"50-56",   'std'=>5,  'pct'=>5,    'zone'=>"yellow" ],
    ['total'=>"44-49",   'std'=>4,  'pct'=>2,    'zone'=>"red" ],
    ['total'=>"38-43",   'std'=>3,  'pct'=>1,    'zone'=>"red" ],
    ['total'=>"30-37",   'std'=>2,  'pct'=>0.5,  'zone'=>"red" ],
    ['total'=>"29",      'std'=>1,  'pct'=>0.1,  'zone'=>"red" ]
);
}
