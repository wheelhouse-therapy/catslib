<?php

/* Sensory Processing Measure
 *     Basic items are numbers 1-75.
 *     Aggregate items are section/column totals e.g. vision_total
 */


class AssessmentData_SPM extends AssessmentData
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oAsmt, $kAsmt );
    }

    public function ComputeScore( string $item ) : int
    {
        $score = 0;

        // Basic scores were computed and scored by the constructor.
        // Aggregate scores are computed below and cached here.
        if( isset($this->raScores[$item]) ) { $score = $this->raScores[$item]; goto done; }

        // Look up aggregate / computed score
        switch( $item ) {
            case "social_total":
                $score = 123; // compute the score
                break;
            case "vision_total":
                $score = 123; // compute the score
                break;
        }

        $this->raScores[$item] = $score;    // cache for next lookup

        done:
        return( $score );
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

        if( in_array($raw, ['n','o','f','a']) && is_numeric($item) ) {
            $score = (($item >= 1 && $item <= 10) || $item == 57 )
                        ? array( 'n'=>4, 'o'=>3, 'f'=>2, 'a'=>1 )[$raw]
                        : array( 'n'=>1, 'o'=>2, 'f'=>3, 'a'=>4 )[$raw];
        }

        return( $score );
    }
}


class AssessmentUI_SPM extends AssessmentUI
{
    function __construct( AssessmentData_SPM $oData )
    {
        parent::__construct( $oData );
    }

}

