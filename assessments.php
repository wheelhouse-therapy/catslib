<?php

include( "assessments_mabc.php" );


$raGlobalAssessments = array(
    'spm'  => array( 'code'=>'spm',  'title'=>"Sensory Processing Measure (SPM)" ),
    'aasp' => array( 'code'=>'aasp', 'title'=>"Adolescent/Adult Sensory Profile (AASP)" ),
    'mabc' => array( 'code'=>'mabc', 'title'=>"Movement Assessment Battery for Children (MABC)" ),
    'spmc' => array( 'code'=>'spmc', 'title'=>"Sensory Processing Measure for Classroom (SPMC)")
);


class AssessmentsCommon
{
    public  $oApp;
    private $oAsmtDB;
    public  $raAssessments;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oAsmtDB = new AssessmentsDB( $this->oApp );
        global $raGlobalAssessments;
        $this->raAssessments = $raGlobalAssessments;
    }

    function KFRelAssessment() { return( $this->oAsmtDB->Kfrel('A') ); }

    function GetAsmtObject( int $kAsmt )
    {
        $oRet = null;

        if( ($kfr = $this->KFRelAssessment()->GetRecordFromDBKey( $kAsmt )) ) {
            $oRet = $this->getObjectByType( $kfr->value('testType'), $kAsmt );
        }

        return( $oRet );
    }

    function GetAsmtObjectByClient( int $kClient, string $asmtType )
    {
        $oRet = null;

        if( ($kfr = $this->KFRelAssessment()->GetRecordFromDBCond( "fk_clients2='$kClient' AND testType='$asmtType'" )) ) {
            $oRet = $this->getObjectByType( $kfr->value('testType'), $kfr->Key() );
        }

        return( $oRet );
    }

    function GetNewAsmtObject( string $sAsmtType )
    {
        return( $this->getObjectByType( $sAsmtType, 0 ) );
    }

    private function getObjectByType( string $asmtType, int $kA )
    {
        $o = null;

        switch( $asmtType ) {
            case 'spm':  $o = new Assessment_SPM( $this, $kA );  break;
            case 'aasp': $o = new Assessment_AASP( $this, $kA ); break;
            case 'mabc': $o = new Assessment_MABC( $this, $kA ); break;
            case 'spmc': $o = new Assessment_SPM_Classroom($this, $kA); break;
            default:     break;
        }
        return( $o );
    }

    function GetSummaryTable( $kAsmtCurr )
    /*************************************
        Draw a table of assessments, highlight the given one
     */
    {
        $s = "";

        $raA = $this->oAsmtDB->GetList( "AxCxP", "" );
        $s .= "<table style='border:none'>";
        foreach( $raA as $ra ) {
            $date = substr( $ra['_created'], 0, 10 );
            $sStyle = $kAsmtCurr == $ra['_key'] ? "font-weight:bold;color:green" : "";
            $s .= "<tr><td>$date</td>"
                     ."<td><a style='$sStyle' href='{$_SERVER['PHP_SELF']}?kA={$ra['_key']}'>{$ra['P_first_name']} {$ra['P_last_name']}</a></td>"
                     ."<td>{$ra['testType']}</td></tr>";
        }
        $s .= "</table>";

        return( $s );
    }

    /**
     * Output a list of assesments for a client for inclusion in a report
     * An empty array is returned if there are no assesments for the client
     * @param int $client - client id to list assmts for
     * @return array of html output to be placed in model
     */
    function listAssessments(int $client):array{
        $raOut = array("header"=>"<h4 class='modal-title'>Please Select Assessments to be included in this report</h4>","body"=>"<form id='assmt_form' onsubmit='modalSubmit(event)'>","footer"=>"<input type='submit' id='submitVal' value='Download' form='assmt_form' />");
        $bData = false;

        // propagate the previous modal dialog's parameters into the next modal dialog
        foreach( $_POST as $k => $v ) {
            if( $k == 'submitVal' ) continue;   // this is 'Next', should not be encoded because the next submitVal is Download
            if( $k == 'cmd' )       continue;   // this is 'therapist-resourcemodal', should not be encoded because the next cmd is 'download'
            $raOut['body'] .= "<input type='hidden' name='$k' value='".htmlspecialchars($v)."' />";
        }
        $raOut['body'] .= "<input type='hidden' name='cmd' value='download'/>";

        foreach ($this->raAssessments as $assmt){
            $raOut['body'] .= $assmt['title'].":<select>";
            $raA = $this->oAsmtDB->GetList( "AxCxP", "fk_clients2='$client' and testType='{$assmt['code']}'", array("sSortCol"=>"_created", "bSortDown"=> true) );
            $raOut['body'] .= "<select name='".$assmt['code']."'".(count($raA) == 0 ? " readonly":"").">";
            if(count($raA) == 0){
                $raOut['body'] .= "<option>No Data Recorded</option>";
            }
            else{
                $raOut['body'] .= "<option value='0' selected>Do Not Include</option>";
                foreach ($raA as $ra){
                    $date = substr( $ra['_created'], 0, 10 );
                    $raOut['body'] .= "<option value='".$ra['_key']."'>".$date."</option>";
                }
                // Some data has been put in the form lets be sure to send this to the user
                $bData = true;
            }
            $raOut['body'] .= "</select>";
        }
        $raOut['body'] .="</form>";

        return $bData ? $raOut : array();
    }

    function GetClientSelect( SEEDCoreForm $oForm )
    {
        $clinics = new Clinics($this->oApp);
        $clinics->GetCurrentClinic();
        $oPeopleDB = new PeopleDB( $this->oApp );
        $raClients = $oPeopleDB->GetList( 'C', $clinics->isCoreClinic() ? "" : ("clinic= '".$clinics->GetCurrentClinic()."'") );
        $opts = array( '--- Choose Client ---' => 0 );
        foreach( $raClients as $ra ) {
            $opts["{$ra['P_first_name']} {$ra['P_last_name']} ({$ra['_key']})"] = $ra['_key'];
        }

        return( "<div>".$oForm->Select( 'fk_clients2', $opts, "" )."</div>" );
    }
    function LookupProblemItems( int $kClient, string $asmtType, string $section )
    {
        if( ($asmtType = @array( 'SPMH'=>'spm', 'SPMC'=>'spmc', 'AASP'=>'aasp', 'MABC'=>'mabc')[$asmtType]) &&
            ($oAsmt = $this->GetAsmtObjectByClient( $kClient, $asmtType )) )
        {
            $oAsmt->GetProblemItems( $section );
        }
    }

    function LookupPercentile( int $kClient, string $asmtType, string $section )
    {
        if( ($asmtType = @array( 'SPMH'=>'spm', 'SPMC'=>'spmc', 'AASP'=>'aasp', 'MABC'=>'mabc')[$asmtType]) &&
            ($oAsmt = $this->GetAsmtObjectByClient( $kClient, $asmtType )) )
        {
            $oAsmt->GetPercentile( $section );
        }
    }
}

abstract class Assessments
{
    protected $oAsmt;
    protected $asmtCode;
    protected $kfrAsmt;
    protected $bUseDataList = false;    // the data entry form uses <datalist>

    function __construct( AssessmentsCommon $oAsmt, int $kAsmt, string $asmtCode )
    {
        $this->oAsmt = $oAsmt;
        $this->asmtCode = $asmtCode;

        $this->kfrAsmt = $kAsmt ? $oAsmt->KFRelAssessment()->GetRecordFromDBKey($kAsmt) : $oAsmt->KFRelAssessment()->CreateRecord();
    }

    function GetAsmtKey()   { return( $this->kfrAsmt->Key() ); }

    function StyleScript()
    {
        $s = "<script>
              var raPercentilesSPM = ".json_encode($this->raPercentiles).";
              var cols = ".json_encode($this->Columns()).";
              var chars = ".json_encode($this->Inputs("script")).";
              </script>";

        return( $s );
    }

    abstract function DrawAsmtForm( int $kClient );
    abstract function DrawAsmtResult();

    function UpdateAsmt()
    {
        $ok = false;

        $oForm = new KeyFrameForm( $this->oAsmt->KFRelAssessment(), "A" );
        $oForm->SetKFR( $this->kfrAsmt );
        $oForm->Load();

        $raItems = array();
        foreach( $oForm->GetValuesRA() as $k => $v ) {
            if( substr($k,0,1) == 'i' &&  ($item = substr($k,1)) ) {  //($item = intval(substr($k,1))) ) {
                $raItems[$item] = $v;
            }
        }
        ksort($raItems);
        $oForm->SetValue( 'results', SEEDCore_ParmsRA2URL( $raItems ) );
        $oForm->SetValue( 'testType', $this->asmtCode );
        $oForm->Store();

        // Though it seems as if the kfr we set above should have been updated by reference, it isn't. So get the updated kfr out of the oForm.
//        $kAsmt = $oForm->GetKey();
        $this->kfrAsmt = $oForm->GetKFR();

        $ok = true;

        return( $ok );
    }

    function ScoreUI()
    {
        $s = "";


        $clinics = new Clinics($this->oApp);
        $clinics->GetCurrentClinic();
        $oPeopleDB = new PeopleDB( $this->oApp );
        $oAssessmentsDB = new AssessmentsDB( $this->oApp );
        $oForm = new KeyFrameForm( $oAssessmentsDB->Kfrel('A'), "A" );

        /* 1: bNew from the button in the assessments list (kA is irrelevant)
         * 2: kA non-zero from a link in the assessments list
         * 3: saved a new form so oForm-Key==0
         * 4: updated a form so oForm-Key<>0
         */
        $kAsmt = 0;
        if( !($bNew = SEEDInput_Int('new')) &&
            !($kAsmt = SEEDInput_Int('kA')) &&
            SEEDInput_Int('assessmentSave') )
        {
            $oForm->Load();

            $raItems = array();
            foreach( $oForm->GetValuesRA() as $k => $v ) {
                if( substr($k,0,1) == 'i' && ($item = intval(substr($k,1))) ) {
                    $raItems[$item] = $v;
                }
            }
            ksort($raItems);
            $oForm->SetValue( 'results', SEEDCore_ParmsRA2URL( $raItems ) );
            $oForm->Store();
            $kAsmt = $oForm->GetKey();
        }

        $raColumns = $this->raColumnRanges;

        $raClients = $oPeopleDB->GetList( 'C', $clinics->isCoreClinic() ? "" : ("clinic= '".$clinics->GetCurrentClinic()."'") );

        $sAsmt = $sList = "";

        /* Draw the list of assessments
         */
        $sList = "<form action='{$_SERVER['PHP_SELF']}' method='post'><input type='hidden' name='new' value='1'/><input type='submit' value='New'/></form>";
        $raA = $oAssessmentsDB->GetList( "AxCxP", "" );
        foreach( $raA as $ra ) {
            $sStyle = $kAsmt == $ra['_key'] ? "font-weight:bold;color:green" : "";
            $sList .= "<div class='assessment-link'><a  style='$sStyle' href='{$_SERVER['PHP_SELF']}?kA={$ra['_key']}'>{$ra['P_first_name']} {$ra['P_last_name']}</a></div>";
        }


        /* Draw the current assessment
         */
        if( $bNew ) {
            $sAsmt = $this->drawNewAsmtForm( $oForm, $raClients, $raColumns );
        } else if( $kAsmt ) {
            if( !$oForm->GetKey() ) {
                $oForm->SetKFR( $oAssessmentsDB->GetKFR( 'A', $kAsmt ) );
            }
            $raResults = SEEDCore_ParmsURL2RA( $oForm->Value('results') );
            foreach( $raResults as $k => $v ) {
                $oForm->SetValue( "i$k", $v );
            }
            $sAsmt = $this->drawAsmt( $oForm, $raColumns );

            // Put the results in a js array for processing on the client
            $s .= "<script>
                   var raResultsSPM = ".json_encode($raResults).";
                   </script>";
        }


        $s .= "<div class='container-fluid'><div class='row'>"
                 ."<div class='col-md-2' style='border-right:1px solid #bbb'>$sList</div>"
                 ."<div id='assessment' class='col-md-10'>$sAsmt</div>"
             ."</div>";

        return( $s );
    }

    protected function drawAsmt( SEEDCoreForm $oForm, $raColumns )
    {
        $oPeopleDB = new PeopleDB( $this->oAsmt->oApp );
        $client = $oPeopleDB->getKFR('C', $oForm->Value("fk_clients2"));
        $sAsmt = "<h2>".$this->oAsmt->raAssessments[$this->asmtCode]['title']."</h2>
                    <span style='margin-left: 10%' id='name'> Name: ".$client->Expand("[[P_first_name]] [[P_last_name]]")."</span>
                        <span style='margin-left: 10%' id='DoB'> Date of Birth: ".$client->Value("P_dob")."</span><br />
                    <table id='results'>
                        <tr><th> Results </th><th> Score </th><th> Interpretation </th>
                            <th> Percentile </th><th> Reverse Percentile </th></tr>

                    </table>
                    <template id='rowtemp'>
                        <tr><td class='section'> </td><td class='score'> </td><td class='interp'> </td>
                            <td class='per'> </td><td class='rev'> </td></tr>

                    </template>
                    <script src='w/js/asmt-overview.js'></script>";

        /*$sReports = "";
        foreach( explode( "\n", $this->Reports ) as $sReport ) {
            if( !$sReport ) continue;
            $n = intval(substr($sReport,0,2));
            $report = substr($sReport,3);
            $v = $oForm->Value( "i$n" );
            if( $this->getScore( $n, $v ) > 2 ) $sReports .= $report."<br/>";
        }
        if( $sReports ) {
            $sAsmt .= "<div style='border:1px solid #aaa;margin:20px 30px;padding:10px'>$sReports</div>";
        }*/

        $sAsmt .= SPMChart();

        $sAsmt .= $this->drawColFormTable( $oForm, $this->raColumnRanges, false );

        return( $sAsmt );
    }

    function drawResult()
    {
        $s = "";

        if( !($kfr = $this->kfrAsmt) )  goto done;

        $oForm = new KeyFrameForm( $kfr->KFRel(), "A" );
        $oForm->SetKFR( $kfr );


        $raColumns = $this->raColumnRanges;


        $raResults = SEEDCore_ParmsURL2RA( $oForm->Value('results') );
        foreach( $raResults as $k => $v ) {
            $oForm->SetValue( "i$k", $v );
        }
        $s .= $this->drawAsmt( $oForm, $raColumns );

        // Put the results in a js array for processing on the client
        $s .= "<script>
               var raResultsSPM = ".json_encode($raResults).";
               var raTotalsSPM = ".json_encode($this->raTotals).";
               </script>";

        done:
        return( $s );
    }

    function drawResult2()
    {
        $s = "";

        if( !($kfr = $this->kfrAsmt) )  goto done;

        $oForm = new KeyFrameForm( $kfr->KFRel(), "A" );
        $oForm->SetKFR( $kfr );

        $raColumns = $this->raColumnRanges;

        $raResults = SEEDCore_ParmsURL2RA( $oForm->Value('results') );
        foreach( $raResults as $k => $v ) {
            $oForm->SetValue( "i$k", $v );
        }
        $s .= $this->drawColFormTable2( $oForm, $this->raColumnRanges, false );

        done:
        return( $s );
    }


    function DrawColumnForm( int $kClient )
    /**************************************
        Draw a form composed of columns of values. Parameters must be defined in derived classes.
            raColumnRanges : [ col-label => 1-6, col-label => 7-15, ... ]
     */
    {
        $s = "<h2>".@$this->oAsmt->raAssessments[$this->asmtCode]['title']."</h2>";

        $oForm = new KeyframeForm( $this->oAsmt->KFRelAssessment(), "A" );

        $s .= "<form method='post'>"
             .$oForm->Hidden( 'fk_clients2', ['value'=>$kClient] );

        $s .= $this->drawColFormTable( $oForm, $this->raColumnRanges, true );

        if( $this->bUseDataList ) {
            $s .= $this->getDataList( $oForm, $this->Inputs("datalist") )
                 ."<script src='w/js/assessments.js'></script>";
        }

        $s .= "<input hidden name='sAsmtAction' value='save'/>"
             ."<input hidden name='sAsmtType' value='{$this->asmtCode}'/>"
             .$oForm->HiddenKey()
             ."<input type='submit'>&nbsp;&nbsp;&nbsp;<a href='.'>Cancel</a></form>"
             ."<span id='total'></span>";
        return( $s );
    }

    function DrawColumnForm2( int $kClient )
    /***************************************
        Draw a form composed of columns of values. Parameters must be defined in derived classes.
            raColumnRanges : [ col-label => [item-label=>item-key,...], col-label => [...], ... ]
     */
    {
        $s = "<h2>".@$this->oAsmt->raAssessments[$this->asmtCode]['title']."</h2>";

        $oForm = new KeyframeForm( $this->oAsmt->KFRelAssessment(), "A" );

        $s .= "<form method='post'>"
             .$oForm->Hidden( 'fk_clients2', ['value'=>$kClient] );

        $s .= $this->drawColFormTable2( $oForm, $this->raColumnRanges, true );

        if( $this->bUseDataList ) {
            $s .= $this->getDataList( $oForm, $this->Inputs("datalist") )
                 ."<script src='w/js/assessments.js'></script>";
        }

        $s .= "<input hidden name='sAsmtAction' value='save'/>"
             ."<input hidden name='sAsmtType' value='{$this->asmtCode}'/>"
             .$oForm->HiddenKey()
             ."<input type='submit'>&nbsp;&nbsp;&nbsp;<a href='.'>Cancel</a></form>"
             ."<span id='total'></span>";
        return( $s );
    }

    private function drawColFormTable( $oForm, $raColumns, $bEditable )
    {
        $colwidth = (100/count($raColumns))."%";

        $s = "<table width='100%'><tr>";
        foreach( $raColumns as $label => $sRange ) {
            $s .= "<th valign='top'>$label<br/><br/></th>";
        }
        $s .= "</tr><tr>";
        foreach( $raColumns as $label => $sRange ) {
            $s .= "<td valign='top' width='$colwidth'>".$this->column( $oForm, $sRange, $bEditable )."</td>";
        }
        if( !$bEditable ) {
            $s .= "</tr><tr>";
            foreach( $raColumns as $label => $sRange ) {
                $s .= "<td valign='top' width='$colwidth'>".$this->column_total( $oForm, $sRange, false )."</td>";
            }
        }
        $s .= "</tr></table>";

        return( $s );
    }

    private function drawColFormTable2( $oForm, $raColumns, $bEditable )
    {
        $colwidth = (100/count($raColumns))."%";

        $s = "<table width='100%'><tr>";
        foreach( $raColumns as $label => $raItems ) {
            $s .= "<th valign='top'>$label<br/><br/></th>";
        }
        $s .= "</tr><tr>";
        foreach( $raColumns as $label => $raItems ) {
            $s .= "<td valign='top' width='$colwidth'>".$this->column2( $oForm, $raItems, $bEditable )."</td>";
        }
        if( !$bEditable ) {
            $s .= "</tr><tr>";
            foreach( $raColumns as $label => $raItems ) {
//                $s .= "<td valign='top' width='$colwidth'>".$this->column_total( $oForm, $sRange, false )."</td>";
            }
        }
        $s .= "</tr></table>";

        return( $s );
    }

    private function column( SEEDCoreForm $oForm, $sRange, $bEditable )
    {
        $s = "<table class='score-table'>";
        $total = 0;
        foreach( SEEDCore_ParseRangeStrToRA( $sRange ) as $n ) {
            $s .= $this->item( $oForm, $n, $bEditable, $total );
        }
        //$s .= "<tr><td></td><td><span class='sectionTotal'>".($bEditable ? "" : "<br/>Total: $total")."</span></td></tr>";
        $s .= "</table>";

        return( $s );
    }

    private function column2( SEEDCoreForm $oForm, $raItems, $bEditable )
    {
        $s = "<table class='score-table'>";
        $total = 0;
        foreach( $raItems as $itemLabel => $itemKey ) {
            $s .= $this->item2( $oForm, $itemLabel, $itemKey, $bEditable, $total );
        }
        //$s .= "<tr><td></td><td><span class='sectionTotal'>".($bEditable ? "" : "<br/>Total: $total")."</span></td></tr>";
        $s .= "</table>";

        return( $s );
    }

    private function column_total( SEEDCoreForm $oForm, $sRange, $bEditable )
    {
        $s = "";

        $total = 0;
        foreach( SEEDCore_ParseRangeStrToRA( $sRange ) as $n ) {
            $sDummy = $this->item( $oForm, $n, $bEditable, $total );
        }
        $s .= "<span class='sectionTotal'>".($bEditable ? "" : "<br/>Total: $total")."</span>";

        return( $s );
    }

    private function item( SEEDCoreForm $oForm, $n, $bEditable, &$total )
    {
        if( $bEditable ) {
            $score = "";
            $s = $oForm->Text("i$n","",array('attrs'=>"class='score-item s-i-$n' data-num='$n' list='options' required"));
        } else {
            $v = $oForm->Value( "i$n" );
            $score = $this->GetScore( $n, $v );
            $s = "<strong style='border:1px solid #aaa; padding:0px 4px;background-color:#eee'>".$v."</strong>";
        }

        $total += intval($score);
        $s = "<tr><td class='score-num'>$n&nbsp;</td><td>".$s."<span class='score'>$score</span></td></tr>";
        return( $s );
    }

    private function item2( SEEDCoreForm $oForm, $itemLabel, $itemKey, $bEditable, &$total )
    {
        if( $bEditable ) {
            $score = "";
            $s = $oForm->Text("i$itemKey","",array('attrs'=>"class='score-item s-i-$itemKey' data-num='$itemKey' list='options' required"));
        } else {
            $v = $oForm->Value( "i$itemKey" );
            $score = $this->GetScore( $itemKey, $v );
            $s = "<strong style='border:1px solid #aaa; padding:0px 4px;background-color:#eee'>".$v."</strong>";
        }

        $total += intval($score);
        $s = "<tr><td class='score-num'>$itemKey&nbsp;</td><td>".$s."<span class='score'>$score</span></td></tr>";
        return( $s );
    }

    private function getDataList(SEEDCoreForm $oForm,$raOptions = NULL){
        $s ="<datalist id='options'>";
        if($raOptions != NULL){
            foreach($raOptions as $option){
                $s .= $oForm->Option("", substr($option, 0,1), $option);
            }
        }
        $s .= "</datalist>";
        return $s;
    }

    protected function Columns()
    {
        // Override to provide the column names
        return( array() );
    }

    protected function Inputs($type){
        switch($type){
            case "datalist":
                return $this->InputOptions();
            case "script":
                $raOptions = array();
                foreach($this->InputOptions() as $option){
                    array_push($raOptions, substr($option, 0,1));
                }
                return( $raOptions );
        }
    }

    protected function InputOptions(){
        // Override to provide custom input options
        return array("1","2","3","4","5");
    }

    /**
     * Get the score for an assessment question when that item has a given value
     * @return int
     */
    abstract protected function GetScore( $item, $value ):int;

    /**
     * Get a list of tags availible for this assesment type
     * Tags in the returned array should return a value when passed to getTagValue()
     * @return array of availible tags.
     * @see getTagValue($tag)
     */
    abstract public function getTags():array;

    /**
     * Get value for a given tag
     * Tags which can be used as a parameter should be returned by getTags()
     * This method checks the parameter tag against the list returned by getTags() to enusure consistancy
     * @param String $tag - tag to get the value for
     * @return String - value of the passed tag for this assesment
     * @see getTags()
     */
    public final function getTagValue(String $tag):String{
        if(in_array($tag, $this->getTags())){
            return $this->getTagField($tag);
        }
        throw new Exception("Invalid Tag:".$tag);
    }

    /**
     * Get value for the given tag
     * Impementations do not have to be concerned with invalid tags as getTagValue($tag) checks for consistancy against the list returned by getTags()
     * before it calls this method
     * @param String $tag - tag to get the value for
     * @return String - value of the passed tag for this assesment
     * @see getTags()
     * @see getTagValue($tag)
     */
    abstract protected function getTagField(String $tag):String;

    /**
     * Return a string containing the problematic results for the given section
     */
    abstract function GetProblemItems( string $section ) : string;
    /**
     * Return the percentile score for the given section
     */
    abstract function GetPercentile( string $section ) : string;
}

class Assessment_SPM extends Assessments
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt, String $subclass = "" )
    {
        //Use subclass when defining a sub class of this such as the classroom spm
        //Subclass ensures that scoreing does not break when subclassing
        parent::__construct( $oAsmt, $kAsmt, 'spm'.$subclass );
        $this->bUseDataList = true;     // the data entry form uses <datalist>
    }

    function DrawAsmtResult()
    {
        return( $this->drawResult() );
    }


    function DrawAsmtForm( int $kClient )
    {
        return( $this->DrawColumnForm( $kClient ) );
    }


    protected function Columns()
    {
        return( array_keys($this->raPercentiles[8]) );
    }

    protected function InputOptions(){
        return array("never","occasionally","frequently","always");
    }

    protected function GetScore( $n, $v ):int
    /************************************
        Return the score for item n when it has the value v
     */
    {
        $score = "0";

        if(!$v){
            return 0;
        }

        if( ($n >= 1 && $n <= 10) || $n == 57 ) {
            $score = array( 'n'=>4, 'o'=>3, 'f'=>2, 'a'=>1 )[$v];
        } else {
            $score = array( 'n'=>1, 'o'=>2, 'f'=>3, 'a'=>4 )[$v];
        }
        return( $score );
    }

    public function getTags(): array{
        //TODO Return Array of valid tags
    }

    protected function getTagField(String $tag):String{
        //TODO Return Values for valid tags
    }

    function GetProblemItems( string $section ) : string
    {
        $s = "";

        if( $this->kfrAsmt && $range = @$this->raColumnDef[$section]['range'] ) {var_dump($range);
            $range = SEEDCore_ParseRangeStrToRA( $range );
            $raResults = SEEDCore_ParmsURL2RA( $this->kfrAsmt->Value('results') );
            foreach( $raResults as $k => $v ) {
                if( in_array( $k, $range ) ) {
                    if( $this->GetScore( $k, $v ) >=3 ) {
                        $s .= $k." ";
                    }
                }
            }
        }

        return( $s );
    }

    function GetPercentile( string $section ) : string
    {
        $s = "";

        if( $this->kfrAsmt && $range = @$this->raColumnDef[$section]['range'] ) {

        }

        return( $s );
    }

    protected $raColumnRanges = array(  // deprecated, use raColumnDef instead
            "Social<br/>participation" => "1-10",
            "Vision"                   => "11-21",
            "Hearing"                  => "22-29",
            "Touch"                    => "30-40",
            "Taste /<br/>Smell"        => "41-45",
            "Body<br/>Awareness"       => "46-55",
            "Balance<br/>and Motion"   => "56-66",
            "Planning<br/>and Ideas"   => "67-75"
        );

    protected $raColumnDef = array(
            'social'   => [ 'label'=>"Social<br/>participation", 'range'=>"1-10" ],
            'vision'   => [ 'label'=>"Vision",                   'range'=>"11-21" ],
            'hearing'  => [ 'label'=>"Hearing",                  'range'=>"22-29" ],
            'touch'    => [ 'label'=>"Touch",                    'range'=>"30-40" ],
            'taste'    => [ 'label'=>"Taste /<br/>Smell",        'range'=>"41-45" ],
            'body'     => [ 'label'=>"Body<br/>Awareness",       'range'=>"46-55" ],
            'balance'  => [ 'label'=>"Balance<br/>and Motion",   'range'=>"56-66" ],
            'planning' => [ 'label'=>"Planning<br/>and Ideas",   'range'=>"67-75" ]
        );

    /* The percentiles that apply to each score, per column
     */
    protected $raPercentiles =
        array(
         '8'  => array( 'social'=>'',     'vision'=>'',     'hearing'=>'24',   'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'' ),
         '9'  => array( 'social'=>'',     'vision'=>'',     'hearing'=>'58',   'touch'=>'',     'body'=>'',     'balance'=>'',     'planning'=>'16' ),
         '10' => array( 'social'=>'16',   'vision'=>'',     'hearing'=>'73',   'touch'=>'',     'body'=>'16',   'balance'=>'',     'planning'=>'31' ),
         '11' => array( 'social'=>'16',   'vision'=>'18',   'hearing'=>'82',   'touch'=>'16',   'body'=>'42',   'balance'=>'16',   'planning'=>'42' ),
         '12' => array( 'social'=>'24',   'vision'=>'50',   'hearing'=>'88',   'touch'=>'42',   'body'=>'58',   'balance'=>'38',   'planning'=>'54' ),
         '13' => array( 'social'=>'31',   'vision'=>'66',   'hearing'=>'90',   'touch'=>'58',   'body'=>'69',   'balance'=>'54',   'planning'=>'62' ),
         '14' => array( 'social'=>'38',   'vision'=>'76',   'hearing'=>'92',   'touch'=>'69',   'body'=>'76',   'balance'=>'66',   'planning'=>'69' ),
         '15' => array( 'social'=>'46',   'vision'=>'82',   'hearing'=>'95',   'touch'=>'76',   'body'=>'82',   'balance'=>'76',   'planning'=>'76' ),
         '16' => array( 'social'=>'54',   'vision'=>'86',   'hearing'=>'95.5', 'touch'=>'82',   'body'=>'84',   'balance'=>'82',   'planning'=>'79' ),
         '17' => array( 'social'=>'62',   'vision'=>'90',   'hearing'=>'96',   'touch'=>'86',   'body'=>'86',   'balance'=>'86',   'planning'=>'84' ),
         '18' => array( 'social'=>'69',   'vision'=>'92',   'hearing'=>'97',   'touch'=>'90',   'body'=>'90',   'balance'=>'90',   'planning'=>'86' ),
         '19' => array( 'social'=>'73',   'vision'=>'93',   'hearing'=>'97.5', 'touch'=>'92',   'body'=>'92',   'balance'=>'92',   'planning'=>'90' ),
         '20' => array( 'social'=>'79',   'vision'=>'95.5', 'hearing'=>'98',   'touch'=>'93',   'body'=>'93',   'balance'=>'93',   'planning'=>'92' ),
         '21' => array( 'social'=>'84',   'vision'=>'96',   'hearing'=>'98.5', 'touch'=>'95',   'body'=>'95',   'balance'=>'95',   'planning'=>'93' ),
         '22' => array( 'social'=>'88',   'vision'=>'96',   'hearing'=>'99.5', 'touch'=>'95.5', 'body'=>'95.5', 'balance'=>'96',   'planning'=>'95' ),
         '23' => array( 'social'=>'90',   'vision'=>'97',   'hearing'=>'99.5', 'touch'=>'96',   'body'=>'96',   'balance'=>'97',   'planning'=>'95.5' ),
         '24' => array( 'social'=>'92',   'vision'=>'97.5', 'hearing'=>'99.5', 'touch'=>'96',   'body'=>'97',   'balance'=>'98',   'planning'=>'97' ),
         '25' => array( 'social'=>'93',   'vision'=>'98',   'hearing'=>'99.5', 'touch'=>'97',   'body'=>'97.5', 'balance'=>'98.5', 'planning'=>'97.5' ),
         '26' => array( 'social'=>'95',   'vision'=>'98.5', 'hearing'=>'99.5', 'touch'=>'98',   'body'=>'98',   'balance'=>'99.5', 'planning'=>'98.5' ),
         '27' => array( 'social'=>'95.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'98.5', 'body'=>'98.5', 'balance'=>'99.5', 'planning'=>'99' ),
         '28' => array( 'social'=>'97',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99',   'body'=>'99',   'balance'=>'99.5', 'planning'=>'99.5' ),
         '29' => array( 'social'=>'97.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99',   'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '30' => array( 'social'=>'98',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '31' => array( 'social'=>'99',   'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '32' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'99.5', 'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '33' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '34' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '35' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '36' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'99.5' ),
         '37' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         '38' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         '39' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         '40' => array( 'social'=>'99.5', 'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'99.5', 'balance'=>'99.5', 'planning'=>'' ),
         '41' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         '42' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         '43' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' ),
         '44' => array( 'social'=>'',     'vision'=>'99.5', 'hearing'=>'',     'touch'=>'99.5', 'body'=>'',     'balance'=>'99.5', 'planning'=>'' )
        );
        protected $raTotals = array("56"=>"16","57"=>"16","58"=>"16","59"=>"21","60"=>"27","61"=>"34","62"=>"38","63"=>"42","64"=>"50","65"=>"54","66"=>"58",
            "67"=>"62","68"=>"62","69"=>"66","70"=>"69","71"=>"73","72"=>"73","73"=>"76","74"=>"76","75"=>"79","76"=>"79","77"=>"82","78"=>"82","79"=>"84",
            "80"=>"84","81"=>"86","82"=>"86","83"=>"86","84"=>"88","85"=>"88","86"=>"88","87"=>"88","88"=>"90","89"=>"90","90"=>"90","91"=>"90","92"=>"92",
            "93"=>"92","94"=>"93","95"=>"93","96"=>"93","97"=>"93","98"=>"93","99"=>"95","100"=>"95","101"=>"95","102"=>"95","103"=>"95.5","104"=>"95.5",
            "105"=>"95.5","106"=>"96","107"=>"96","108"=>"96","109"=>"96","110"=>"97","111"=>"97","112"=>"97","113"=>"97","114"=>"97","115"=>"97","116"=>"97",
            "117"=>"97","118"=>"97","119"=>"97.5","120"=>"97.5","121"=>"97.5","122"=>"98","123"=>"98","124"=>"98","125"=>"98","126"=>"98","127"=>"98","128"=>"98",
            "129"=>"98.5","130"=>"98.5","131"=>"99","132"=>"99","133"=>"99.5","134"=>"99.5","135"=>"99.5","136"=>"99.5","137"=>"99.5","138"=>"99.5","139"=>"99.5",
            "140"=>"99.5","141"=>"99.5","142"=>"99.5","143"=>"99.5","144"=>"99.5","145"=>"99.5","146"=>"99.5","147"=>"99.5","148"=>"99.5","149"=>"99.5",
            "150"=>"99.5","151"=>"99.5","152"=>"99.5","153"=>"99.5","154"=>"99.5","155"=>"99.5","156"=>"99.5","157"=>"99.5","158"=>"99.5","159"=>"99.5",
            "160"=>"99.5","161"=>"99.5","162"=>"99.5","163"=>"99.5","164"=>"99.5","165"=>"99.5","166"=>"99.5","167"=>"99.5","168"=>"99.5","169"=>"99.5",
            "170"=>"99.5","171"=>"99.5");

    protected $Reports = "
11	Seems bothered by light, especially bright lights (blinks, squints, cries, closes eyes, etc.)
12	Has trouble finding an object when it is part of a group of other things
13	Closes one eye or tips his/her head back when looking at something or someone
14	Becomes distressed in unusual visual environments
15	Has difficulty controlling eye movement when following objects like a ball with his/her eyes
16	Has difficulty recognizing how objects are similar or different based on their colours, shapes or sizes
17	Enjoys watching objects spin or move more than most kids his/her age
18	Walks into objects or people as if they were not there
19	Likes to flip light switches on and off repeatedly
20	Dislikes certain types of lighting, such as midday sun, strobe lights, flickering lights or fluorescent lights
21	Enjoys looking at moving objects out of the corner of his/her eye
22	Seems bothered by ordinary household sounds, such as the vacuum cleaner, hair dryer or toilet flushing
23	Responds negatively to loud noises by running away, crying, or holding hands over ears
24	Appears not to hear certain sounds
25	Seems disturbed by or intensely interested in sounds not usually noticed by other people
26	Seems frightened of sounds that do not usually cause distress in other kids her/her age
27	Seems easily distracted by background noises such as lawn mower outside, an air conditioner, a refrigerator, or fluorescent lights
28	Likes to cause certain sounds to happen over and over again, such as by repeatedly flushing the toilet
29	Shows distress at shrill or brassy sounds, such as whistles, party noisemakers, flutes and trumpets
30	Pulls away from being touched lightly
31	Seems to lack normal awareness of being touched
32	Becomes distressed by the feel of new clothes
33	Prefers to touch rather than to be touched
34	Becomes distressed by having his/her fingernails or toenails cut
35	Seems bothered when someone touches his/her face
36	Avoids touching or playing with finger paint, paste, sand, clay, mud, glue, or other messy things
37	Has an unusually high tolerance for pain
38	Dislikes teeth brushing, more than other kids his/her age
39	Seems to enjoy sensations that should be painful, such as crashing onto the floor or hitting his/her own body
40	Has trouble finding things in a pocket, bag or backpack using touch only (without looking)
41	Likes to taste nonfood items, such as glue or paint
42	Gags at the thought of an unappealing food, such as cooked spinach
43	Likes to smell nonfood objects and people
44	Shows distress at smell that other children do not notice
45	Seems to ignore or not notice strong odors that other children react to
46	Grasps objects so tightly that it is difficult to use the object
47	Seems driven to seek activities such as pushing, pulling, dragging, lifting and jumping
48	Seems unsure of how far to raise or lower the body during movement such as sitting down or stepping over an object
49	Grasps objects so loosely that it is difficult to use the object
50	Seems to exert too much pressure for the task, such as walking heavily, slamming doors, or pressing too hard when using pencils or crayons
51	Jumps a lot
52	Tends to pet animals with too much force
53	Bumps or pushes other children
54	Chews on toys, clothes or other objects more than other children
55	Breaks things from pressing or pushing too hard on them
56	Seems excessively fearful of movement such as going up and down stairs, riding swings, teeter-totters, slides or other playground equipment
57	Doesn't seem to have good balance
58	Avoids balance activities, such as walking on curbs or uneven ground
59	Falls out of a chair when shifting his/her body
60	Fails to catch self when falling
61	Seems not to get dizzy when others usually do
62	Spins and whirls his/her body more than other children
63	Shows distress when his/her head is tilted away from vertical position
64	Shows poor coordination and appears to be clumsy
65	Seems afraid of riding in elevators or escalators
66	Leans on other people or furniture when sitting or when trying to stand up
67	Performs inconsistently in daily tasks
68	Has trouble figuring out how to carry multiple objects at the same time
69	Seems confused about how to put away materials and belongings in their correct places
70	Fails to perform tasks in proper sequence, such as getting dressed or setting the table
71	Fails to complete tasks with multiple steps
72	Has difficulty imitating demonstrated actions, such as movement games or songs with motions
73	Has difficulty building to copy a model, such as using Legos or blocks to build something that matches a model
74	Has trouble coming up with ideas for new games and activities
75	Tends to play the same activities over and over, rather than shift to new activities when given the chance
";

}

class Assessment_AASP extends Assessments {

    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oAsmt, $kAsmt, 'aasp' );
    }

    function DrawAsmtForm( int $kClient )
    {
        return( "FORM" );
    }

    function DrawAsmtResult()
    {
        return( "RESULTS" );
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

    function GetProblemItems( string $section ) : string
    {}
    function GetPercentile( string $section ) : string
    {}

    protected $raColumnRanges = array(
        "Taste/Smell"           => "1-8",
        "Movement"              => "9-16",
        "Visual"                => "17-26",
        "Touch"                 => "27-39",
        "Activity<br/>Level"    => "40-49",
        "Auditory"              => "50-60"
    );

    protected $raPercentiles = array();
}

class Assessment_SPM_Classroom extends Assessment_SPM {

    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        parent::__construct( $oAsmt, $kAsmt, 'c' );
        $this->bUseDataList = true;     // the data entry form uses <datalist>
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

function AssessmentsScore( SEEDAppConsole $oApp )
{
    $s = "";

    $s .= "<link rel='stylesheet' href='w/css/asmt-overview.css' />";
    $s .= "<style>
           .score-table {}
           .score-table th { height:60px; }
           .score-num   { width:1em; }
           .score-item  { width:3em; }
           .score { padding-left: 5px; }
           </style>";


    /* kAsmt==0 && sAsmtAction==''       = landing screen
     * kAsmt==0 && sAsmtAction==edit     = show blank form for new assessment (requires sAsmtType)
     * kAsmt==0 && sAsmtAction==save     = save new assessment and show the result (requires sAsmtType)
     * kAsmt    && sAsmtAction==''       = show result for assessment kAsmt
     * kAsmt    && sAsmtAction=='edit'   = show filled form for editing assessment kAsmt
     * kAsmt    && sAsmtAction=='save'   = update assessment kAsmt and show the result
     */
    $p_kAsmt = SEEDInput_Int('kA');
    $p_sAsmtType = SEEDInput_Str('sAsmtType');
    $p_action = SEEDInput_Str('sAsmtAction');

//var_dump($p_kAsmt,$p_action,$p_sAsmtType);
//var_dump($_REQUEST);

    $oAC = new AssessmentsCommon( $oApp );
    if( $p_kAsmt ) {
        $oAsmt = $oAC->GetAsmtObject( $p_kAsmt );
    } else if( $p_sAsmtType ) {
        $oAsmt = $oAC->GetNewAsmtObject( $p_sAsmtType );
    } else {
        // Just show the landing screen.
        $oAsmt = null;
        $p_kAsmt = 0;
        $p_action = '';
    }

    // Output <style> and <script> for the current assessment if any
    $s .= $oAsmt ? $oAsmt->StyleScript() : "";


    switch( $p_action ) {
        case 'edit':
            /* Show the input form for the given assessment type.
             * The form is smart enough to do Edit or New depending on whether it was created with a kAsmt.
             * Don't show the summary list because it takes too much space.
             */
            if( ($kClient = SEEDInput_Int('fk_clients2')) ) {
                $s .= $oAsmt->DrawAsmtForm( $kClient );
            } else {
                goto do_default;
            }
            break;

        case 'save':
            /* New or Edit form submitted, save the record.  Then show the main page by falling through to the default case.
             */
            if( $oAsmt->UpdateAsmt() ) {
                $s .= "<div class='alert alert-success'>Saved assessment</div>";
                $p_kAsmt = $oAsmt->GetAsmtKey();    // if a new asmt was inserted show it below
            } else {
                $s .= "<div class='alert alert-danger'>Could not save assessment</div>";
            }
            goto do_default;
            // fall through to the default case to show the main page

        default:
            do_default:
            /* Show the landing page or a particular assessment.
             */
            $sLeft = $oAC->GetSummaryTable( $p_kAsmt );
            $sRight = $p_kAsmt ? $oAsmt->DrawAsmtResult() : "";
// uncomment to see the problem items in the vision column of an spm test
//$sRight .= $p_kAsmt ? $oAsmt->GetProblemItems('vision') : "";

            /* New button with a control to choose the assessment type
             */
            $oForm = new SEEDCoreForm( 'Plain' );
            $sControl =
                  "<form action='{$_SERVER['PHP_SELF']}' method='post'>"
                 ."<h4>New Assessment</h4>"
                 .$oAC->GetClientSelect( $oForm )
                 ."<select name='sAsmtType'>"
                 .SEEDCore_ArrayExpandRows( $oAC->raAssessments, "<option value='[[code]]'>[[title]]</option>" )
                 ."</select>"
                 ."<input type='hidden' name='sAsmtAction' value='edit'/>"   // this means 'new' if there is no kA
                 ."<br/><input type='submit' value='New'/>"
                 ."</form>";

            $s .= "<div style='float:right;'><div style='width:97%;margin:20px;padding-top:5%;padding-left:5%;padding-bottom:5%;border:1px solid #aaa; background-color:#eee; border-radius:3px'>$sControl</div>";
            if($sRight){
                $sRight = "<script> var AssmtType = '".$oAC->KFRelAssessment()->GetRecordFromDBKey($p_kAsmt)->Value("testType")."';</script>".$sRight;
                $s .= "<script src='w/js/printme/jquery-printme.js'></script>"
                    ."<div style='padding:5%;display:inline'>"
                        ."<button style='background: url(".CATSDIR_IMG."Print.png) 0px/24px no-repeat; width: 24px; height: 24px;border:  none;' data-tooltip='Print Assessment' onclick='$(\"#assessment\").printMe({ \"path\": [\"w/css/spmChart.css\"]});'></button>"
                    ."</div>";
            }
            $s .= "</div><div class='container-fluid'><div class='row'>"
                     ."<div class='col-md-3' style='border-right:1px solid #bbb'>$sLeft</div>"
                     ."<div id='assessment' class='col-md-9' style='border-right:1px solid #bbb'>$sRight</div>"
                 ."</div>";
    }

    done:
    return( $s );
}

function SPMChart()
{
    $s = <<<spmChart
<br />
<link rel='stylesheet' href='w/css/spmChart.css'>
<script src='w/js/spmChart.js'></script>
<svg id='chart' class='hidden'
	xmlns="http://www.w3.org/2000/svg"
	viewBox="0 0 263.19417 183.18527">
	<defs>
		<clipPath id='cutOff'>
			<rect width="250.43593" height="153.06314" x="18.888107" y="91.020195" />
		</clipPath>
	</defs>
  <g id="spmChart" transform="translate(-6.4124658,-89.416847)">
	<text class='percent' x="11.870281" y="92.607399">
		<tspan x="11.870281" y="92.607399">100%</tspan>
	</text>
	<text class='percent' x="11.870281" y="130.95422">
		<tspan x="11.870281" y="130.95422">90%</tspan>
	</text>
	<text class='percent' x="11.870281" y="169.30106">
		<tspan x="11.870281"y="169.30106">80%</tspan>
	</text>
	<text class='percent' x="11.870281" y="207.64787">
		<tspan x="11.870281" y="207.64787">70%</tspan>
	</text>
	<text class='percent' x="11.870281" y="245.99472">
		<tspan x="11.870281" y="245.99472">60%</tspan>
	</text>
	<path class='percLine' d="M 18.852836,205.81657 H 269.60663" />
	<path class='percLine' d="M 18.852836,167.51418 H 269.60663" />
	<path class='percLine' d="M 18.852836,129.40811 H 269.60663" />
	<path
		id="dd"
		class="guideline"
		d="M 18.852836,102.49993057250973 H 269.60663"
		onmouseover='grow(this); tip(event, "Definite Dysfunction (&ge;97%)")'
		onmouseout='shrink(this)' />
	<path
		id="sp"
		class="guideline"
		d="M 18.852836,156.07202987670894 H 269.60663"
		onmouseover='grow(this); tip(event, "Some Problems (&ge;83%)")'
		onmouseout='shrink(this)' />
	<rect
		id="box"
		width="250.43593"
		height="153.06314"
		x="18.888107"
		y="91.020195"
		class='percLine' />
	<text class='xText' x="-139.54134" y="206.36235">
		<tspan x="-139.54134" y="206.36235">Social Participation</tspan>
	</text>
	<text class='xText' x="-119.62378" y="226.27992">
		<tspan x="-119.62378" y="226.27992">Vision</tspan>
	</text>
	<text class='xText' y="246.19751" x="-99.706169">
		<tspan y="246.19751" x="-99.706169">Hearing</tspan>
	</text>
	<text class='xText' x="-79.788582" y="266.11511">
		<tspan x="-79.788582" y="266.11511">Touch</tspan>
	</text>
	<text class='xText' y="286.03268" x="-59.870987">
		<tspan y="286.03268" x="-59.870987">Body Awareness</tspan>
	</text>
	<text class='xText' x="-39.9534" y="305.95029">
		<tspan x="-39.9534" y="305.95029">Balance and Motion</tspan>
	</text>
	<text class='xText' y="325.86783" x="-20.03581">
		<tspan y="325.86783" x="-20.03581">Planning</tspan>
	</text>
	<text class='xText' x="-0.1182246" y="345.78546">
		<tspan x="-0.11822455" y="345.78546">Average</tspan>
	</text>
	<path d="m 46.682496,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 74.512158,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 102.34182,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 130.17149,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 158.00114,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 185.8308,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 213.66046,242.34399 v 3.4787" class='tickmark percLine' />
	<path d="m 241.49012,242.34399 v 3.4787" class='tickmark percLine' />
	<path id='line'
		class='hidden'
		onmouseover='scoreGrow(); tip(event, "Scores", this)'
		onmouseout='shrink(this)'
		clip-path='url(#cutOff)' />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
	<circle cx='1' cy='1' r='1' onmouseover='scoreGrow()' onmouseout='shrink(document.getElementById("line"))' class='point'  />
  </g>
</svg>
<div id='info'>
	<span id='info-text'></span>
</div>
spmChart;

    return( $s );

}