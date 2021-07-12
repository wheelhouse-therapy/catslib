<?php

include( "assessments_mabc.php" );
include( "assessments_spm.php"  );
include( "assessments_aasp.php" );
include( "assessments_spmc.php" );
include( "assessments_sp2.php"  );


$raGlobalAssessments = array(
    'spm'  => array( 'code'=>'spm',  'title'=>"Sensory Processing Measure (SPM)" ),
    'spmc' => array( 'code'=>'spmc', 'title'=>"Sensory Processing Measure for Classroom (SPMC)"),
    'aasp' => array( 'code'=>'aasp', 'title'=>"Adolescent/Adult Sensory Profile (AASP)" ),
    'mabc' => array( 'code'=>'mabc', 'title'=>"Movement Assessment Battery for Children (MABC)" ),
    'sp2'  => array( 'code'=>'sp2',  'title'=>"Sensory Profile 2 (SP2)" ),
);

//Remove the null key if present
unset($raGlobalAssessments['']);

/* Problem:
 *      The model for the Assessments class is based on the way SPM works, but it isn't easy to adapt it to other assessments.
 *
 * Proposal:
 *      Create an AssessmentDataInterface and AssessmentUIInterface for each assessment type.
 *      The Data interface hides all the computation and mapping between inputs and scores - see ComputeScore() described below.
 *      The UI interface encapsulates the details of how the different forms work e.g. SPM has javascript for typing certain
 *      characters, MABC has a complicated structure of aggregated scores.
 *
 *      Every assessment contains a set of inputs, which we currently call "items".
 *      Every item has a name. It could be "2", "4b", "Vision3", etc.
 *      The therapist enters a "raw" code into each input. It could be an integer or a string.
 *      The raw is stored in the db, and shown back on the assessment report.
 *      Every item has a score, which is a number (so far only integers, but could be a float?) mapped from the raw.
 *
 *      e.g. You can have an item called "Vision12" with raw "n" which corresponds to score "1".
 *           We store Vision12=n in the database and use a method GetScore( "Vision12" ) to get 1.
 *
 *      There are also aggregated / computed scores and percentiles. These are retrieved via ComputeScore() and ComputePercentile()
 *      using names for those aggregations.
 *
 *      e.g. ComputeScore("VisionTotal") could retrieve a sum of the scores for Vision-related items.
 *           ComputePercentile("VisionTotal") could retrieve the percentile for that aggregated score.
 *
 *      The important thing is that this interface will work for all assessments that:
 *          - Have items with distinct names and well-defined raw inputs.
 *          - Have numeric scores associated with the inputs (or the score is just the input).
 *          - Have aggregated / computed scores that can be named.
 */


/* Every assessment type should implement an extension to this class
 * e.g. AssessmentData_SPM
 */
class AssessmentData
{
    public    $oAsmt;
    public    $oA;

    protected $kfrAsmt;
    protected $raRaws;      // [item=>raw, ...]
    protected $raScores;    // [item=>score, ...]
    private $date = "";

    protected function __construct( Assessments $oA, AssessmentsCommon $oAsmt, int $kAsmt)
    {
        $this->oAsmt = $oAsmt;
        $this->oA = $oA;

        $this->LoadAsmt( $kAsmt );
    }

    public function GetScores()  { return( $this->raScores ); }
    public function SetScore( $item, $score )  { $this->raScores[$item] = $score; }

    public final function setDate(String $date){
        $this->date = $date;
    }

    public final function getDate():String{
        return $this->date;
    }

    public function LoadAsmt( int $kAsmt )
    {
        $this->kfrAsmt = $kAsmt ? $this->oAsmt->KFRelAssessment()->GetRecordFromDBKey($kAsmt)
                                : $this->oAsmt->KFRelAssessment()->CreateRecord();

        // Get all the raws
        $this->raRaws = SEEDCore_ParmsURL2RA( $this->kfrAsmt->Value('results') );

        // Map them to scores
        foreach( $this->raRaws as $item => $raw ) {
            $this->raScores[$item] = $this->MapRaw2Score( $item, $raw );
        }

        $this->date = $this->kfrAsmt->Value('date');

    }

    public function GetAsmtKey() : int                      { return( $this->kfrAsmt->Key() ); }
    public function GetValue( string $k ) : string          { return( $this->kfrAsmt->Value($k) ); }        // for verbatim db fields
    public function GetRaw( string $item ) : string         { return( strval(@$this->raRaws[$item]) ); }    // for values encoded in 'results'

    public function GetForm()
    /************************
        Make a SeedCoreForm using the current kfrAsmt
     */
    {
        $oForm = new KeyFrameForm( $this->kfrAsmt->KFRel(), "A" );
        $oForm->SetKFR( $this->kfrAsmt );

        // Load the values into i$k for result form
        $raResults = SEEDCore_ParmsURL2RA( $oForm->Value('results') );
        foreach( $raResults as $k => $v ) {
            $oForm->SetValue( "i$k", $v );
        }
        
        return( $oForm );
    }

    public function Columns()
    /************************
        Return the column names as an array.
        SPM and SPMC support columnsDef().
     */
    {
        return( method_exists($this, 'columnsDef') ? array_keys($this->columnsDef()) : [] );
    }

    public function GetRangeOfColumn( $col )
    /***************************************
        Return the colRange of the given column
     */
    {
        return( method_exists($this, 'columnsDef') ? @$this->columnsDef()[$col]['colRange'] : "" );
    }

    public function ComputeScore( string $item ) : int        { return(0); }
    public function ComputePercentile( string $item ) : float { return(0.0); }


    public function MapRaw2Score( string $item, string $raw ) : int { return(0); }

    public function DebugDumpKfr()
    {
        var_dump($this->kfrAsmt->ValuesRA(),$this->raRaws);
    }
}

/* Every assessment type should implement an extension to this class
 * e.g. AssessmentUI_SPM
 *
 * Note that if it uses a column-table format, it can extend from AssessmentUIColumns
 */
class AssessmentUI
{
    protected $oData;
    public    $oAsmt;
    public    $oA;

    protected function __construct( AssessmentData $oData )
    {
        $this->oData = $oData;
        $this->oAsmt = $oData->oAsmt;   // copy here for convenience
        $this->oA    = $oData->oA;      // copy here for convenience
    }

//    public function DrawInputForm() : string { return(""); }
//    public function DrawGraph() : string { return(""); }
//    public function DrawRawTable() : string { return(""); }
    public function DrawScoreResults() : string { return(""); }
//    public function DrawRecommendation() : string { return(""); }
}

class AssessmentUIColumns extends AssessmentUI
/************************
    If an assessment UI uses columns of items, extend it from this one instead of from AssessmentUI.
 */
{
    private $raColumnsDef;

    protected function __construct( AssessmentData $oData, $raColumnsDef )
    {
        parent::__construct( $oData );
        $this->SetColumnsDef( $raColumnsDef );
    }

    public function GetColumnDef() { return( $this->raColumnsDef ); }

    function SetColumnsDef( $raColumnsDef ) { $this->raColumnsDef = $raColumnsDef; }

    function DrawColumnForm( int $kClient, $raParms = array() )
    /**********************************************************
        Draw a form composed of columns of values. Parameters must be defined in derived classes.
            raColumnRanges : [ col-label => 1-6, col-label => 7-15, ... ]
     */
    {
        $s = "<h2>".@$this->oAsmt->raAssessments[$this->oA->GetAsmtCode()]['title']."</h2>";

        //$oForm = new KeyframeForm( $this->oAsmt->KFRelAssessment(), "A" );
        // oData already has the form you are looking for, with the kfr already loaded.
        $oForm = $this->oData->GetForm();
        if( !$oForm->GetKey() ) {
            // this is a new form with empty data so fill the basics before drawing it
            $oForm->SetValue('fk_clients2', $kClient);
            $oForm->SetValue('date', $this->oData->getDate());
        }
//var_dump($oForm->GetValuesRA());

        $s .= "<form method='post'>"
             .$oForm->Hidden( 'fk_clients2' )
             .$oForm->Hidden('date')
             .$oForm->HiddenKey();

        if( @$raParms['hiddenParms'] ) {
            foreach( $raParms['hiddenParms'] as $k => $v ) $s .= $oForm->Hidden( $k, ['value'=>$v] );
        }

        $s .= $this->DrawColFormTable( $oForm, true );

        if( $this->oA->bUseDataList ) {
            $s .= $this->getDataList( $oForm, $this->oA->Inputs("datalist") )
                 ."<script src='w/js/assessments.js'></script>";
        }

        $s .= "<input hidden name='sAsmtAction' value='save'/>"
             ."<input hidden name='sAsmtType' value='".$this->oA->GetAsmtCode()."'/>"
             ."<input type='submit'>&nbsp;&nbsp;&nbsp;<a href='.'>Cancel</a></form>"
             ."<span id='total'></span>";

        return( $s );
    }

    private function getDataList( SEEDCoreForm $oForm, $raOptions = NULL )
    {
        $s = "<datalist id='options'>";
        if( $raOptions != NULL ) {
            foreach( $raOptions as $key => $option ) {
                if(is_numeric($key)){
                    $s .= $oForm->Option("", substr($option, 0,1), $option);
                }
                else{
                    $s .= $oForm->Option("", $option, $key);
                }
            }
        }
        $s .= "</datalist>";

        return $s;
    }

    function DrawColFormTable( SEEDCoreForm $oForm, $bEditable )
    {
        $s = "<table style='width:100%;table-layout:fixed'><tr>";
        foreach( $this->raColumnsDef as $ra ) {
            $s .= "<th style='vertical-align:top'>{$ra['label']}<br/><br/></th>";
        }
        $s .= "</tr><tr>";
        foreach( $this->raColumnsDef as $ra ) {
            $s .= "<td style='vertical-align:top; border-right:1px solid #ccc;padding:0px 5px'>";
            if( isset($ra['cols']) ) {
                // columns are explicitly defined
                $s .= $this->column( $oForm, $ra['cols'], $bEditable );
            } else if( isset($ra['colRange']) ) {
                // columns are numeric, expressed as a range. Unpack this into an explicit array
                $raCols = array();
                foreach( SEEDCore_ParseRangeStrToRA($ra['colRange']) as $v ) {
                    $raCols[$v] = $v;
                }
                $s .= $this->column( $oForm, $raCols, $bEditable );
            }
            $s .= "</td>";

        }
        if( !$bEditable ) {
            $s .= "</tr><tr>";
            foreach( $this->raColumnsDef as $ra ) {
                if( isset($ra['colRange']) ) {
                    $s .= "<td style='vertical-align:top;text-align:right'>".$this->column_total( $oForm, $ra['colRange'], false )."</td>";
                }
            }
        }
        $s .= "</tr></table>";

        return( $s );
    }


    private function column( SEEDCoreForm $oForm, $raItems, $bEditable )
    {
        $s = "<table class='score-table'>";
        $total = 0;
        foreach( $raItems as $itemLabel => $itemKey ) {
            $s .= $this->item( $oForm, $itemLabel, $itemKey, $bEditable, $total );
        }
        $s .= "</table>";

        return( $s );
    }

    private function column_total( SEEDCoreForm $oForm, $sRange, $bEditable )
    {
        $s = "";

        $total = 0;
        foreach( SEEDCore_ParseRangeStrToRA( $sRange ) as $item ) {
            $score = $this->itemVal( $oForm, $item )[1];
            $total += $score;
        }
        $s .= "<div class='sectionTotal'>".($bEditable ? "" : "<br/>Total: $total")."</div>";

        return( $s );
    }

    private function itemVal( SEEDCoreForm $oForm, $item )
    {
        $v = $oForm->Value( "i$item" );
        $score = ($v !== null) ? $this->oData->MapRaw2Score( $item, $v ) : 0;
        return( [$v, $score] );
    }

    private function item( SEEDCoreForm $oForm, $itemLabel, $itemKey, $bEditable, &$total )
    {
        if( $bEditable ) {
            $score = "";
            $s = $oForm->Text("i$itemKey","",array('value'=>$this->oData->GetRaw($itemKey),'attrs'=>"class='score-item s-i-$itemKey' data-num='$itemKey' list='options' required"));
        } else {
            list($v,$score) = $this->itemVal( $oForm, $itemKey );
            $s = "<strong style='border:1px solid #aaa; padding:0px 4px;background-color:#eee'>".$v."</strong>";
        }

        $total += intval($score);
        $s = "<tr><td class='score-num'>$itemKey&nbsp;</td><td>".$s."<span class='score'>$score</span></td></tr>";
        return( $s );
    }
}


class AssessmentsCommon
{
    public  $oApp;
    private $oAsmtDB;
    public  $raAssessments;

    public const DO_NOT_INCLUDE = -1;
    public const NO_DATA = -2;

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
            case 'sp2':  $o = new Assessment_SP2($this, $kA); break;
            default:     break;
        }
        return( $o );
    }

    /**
     * Get the date when the assessment was recorded, falling back on the record creation date if necesary.
     * All new assessments should record the date they were recorded upon creation but in case its missing for some reason we fall back on the when the record was created.
     * @param array $ra - array containing assessment data
     * @return string - Date when the assessment was recorded.
     */
    static function GetAssessmentDate(array $ra):string{
        return $ra['date']?:"Entered: ".substr( $ra['_created'], 0, 10 );
    }

    function GetSummaryTable( $kAsmtCurr, $client_key=0 )
    /*************************************
        Draw a table of assessments, highlight the given one
     */
    {
        if(!$client_key && !CATS_SYSADMIN){
            //The user is not loading from a clients record and is not a System Admin, show this message instead of assessment results
            //System Admins can see a list of all the assessmtents while everyone else must use therapist-clientlist to access assessment results
            return "<strong>To view a client's results, open their profile and click the \"Assessment Results\" button.</strong><br /><a href='".CATSDIR."therapist-clientlist'>Go to your client list</a>";
        }
        $s = "";

        $clinics = new Clinics($this->oApp);
        $cond = $clinics->isCoreClinic() ? "" : ("C.clinic = ".$clinics->GetCurrentClinic());;
        if($client_key){
            if($cond){
                $cond .= " AND ";
            }
            $cond .= "fk_clients2=".$client_key;
        }

        $raA = $this->oAsmtDB->GetList( "AxCxP", $cond );
        $s .= "<table style='border:none'>";
        foreach( $raA as $ra ) {
            $date = self::GetAssessmentDate($ra);
            $sStyle = $kAsmtCurr == $ra['_key'] ? "font-weight:bold;color:green" : "";
            $s .= "<tr><td>$date</td>"
                     ."<td><a style='$sStyle' href='".CATSDIR."?kA={$ra['_key']}'>{$ra['P_first_name']} {$ra['P_last_name']} (".(new ClientCodeGenerator($this->oApp))->getClientCode($ra['C__key']).")</a></td>"
                     ."<td>{$ra['testType']}</td></tr>";
        }
        $s .= "</table>";

        $s .= "<a href='".CATSDIR."therapist-reports'><button>Print Reports</button></a>";
        
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
        $raOut['body'] .= "<input type='hidden' name='cmd' value='download'/>"; // Needed for reports to download

        foreach ($this->raAssessments as $assmt){
            $raOut['body'] .= $assmt['title'].":";
            $raA = $this->oAsmtDB->GetList( "AxCxP", "fk_clients2='$client' and testType='{$assmt['code']}'", array("sSortCol"=>"date,_created", "bSortDown"=> true) );
            $raOut['body'] .= "<select name='assessments[".$assmt['code']."]'".(count($raA) == 0 ? " disabled":"").">";
            if(count($raA) == 0){
                $raOut['body'] .= "<option value='".self::NO_DATA."'>No Data Recorded</option>";
            }
            else{
                $raOut['body'] .= "<option value='".self::DO_NOT_INCLUDE."'>Do Not Include</option>";
                $bFirst = true;
                foreach ($raA as $ra){
                    $date = self::GetAssessmentDate($ra);
                    $raOut['body'] .= "<option value='".$ra['_key']."' ".($bFirst?"selected":"").">".$date."</option>";
                    $bFirst = false;
                }
                // Some data has been put in the form lets be sure to send this to the user
                $bData = true;
            }
            $raOut['body'] .= "</select>";
        }
        $raOut['body'] .="</form>";
        
        if(SEEDInput_Str("resource-mode") == 'email'){
            $raOut['footer'] = "<input type='submit' id='submitVal' value='Email' form='assmt_form' />";
        }

        return $bData ? $raOut : array();
    }

    function GetClientSelect( SEEDCoreForm $oForm )
    {
        $clinics = new Clinics($this->oApp);
        $clinics->GetCurrentClinic();
        $clientlist = new ClientList($this->oApp);
        $raClients = $clientlist->getMyClients();
        $raParams = array("attrs"=>"required style='max-width:100%'");
        if($this->oApp->sess->SmartGPC('client_key')){
            $raParams = array_merge($raParams,['selected'=>$this->oApp->sess->SmartGPC('client_key')]);
        }
        $opts = array( '' => '' );
        foreach( $raClients as $ra ) {
            $opts["{$ra['P_first_name']} {$ra['P_last_name']} (".(new ClientCodeGenerator($this->oApp))->getClientCode($ra['_key']).")".($clinics->isCoreClinic() || CATS_SYSADMIN?" ({$ra['_key']})":"")] = $ra['_key'];
        }

        return( "<div>".$oForm->Select( 'fk_clients2', $opts, "", $raParams )."</div>" );
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
    protected $oData;
    protected $oUI;

// move this to an AssessmentsUI parm
public    $bUseDataList = false;    // the data entry form uses <datalist>

    function __construct( AssessmentsCommon $oAsmt, string $asmtCode, AssessmentData $oData, AssessmentUI $oUI )
    {
        $this->oAsmt = $oAsmt;
        $this->asmtCode = $asmtCode;
        $this->oData = $oData;
        $this->oUI = $oUI;
    }

    public function GetAsmtCode()  { return( $this->asmtCode ); }

    function StyleScript()
    {
        $s = "";

        if( $this->asmtCode == 'spm' || $this->asmtCode == 'spmc' ) {
            $s = "<script>
                  var raPercentilesSPM = ".json_encode($this->oData->raPercentiles).";
                  var cols = ".json_encode($this->oData->Columns()).";
                  var chars = ".json_encode($this->Inputs("script")).";
                  var raTotalsSPM = ".json_encode($this->oData->GetTotals()).";";
        }

        if($this->bUseDataList){
            if(substr($s, 0,8) !== "<script>"){
                $s .= "<script>";
            }
            $s .= "var chars = ".json_encode($this->Inputs("script")).";";
        }

        if(substr($s, 0,8) === "<script>"){
            $s .= "</script>";
        }

        return( $s );
    }

    abstract function DrawAsmtForm( int $kClient );
    public function DrawAsmtResult()
    {
        $s = "";

        if( !$this->oData->GetAsmtKey() )  goto done;

        $oPeopleDB = new PeopleDB( $this->oAsmt->oApp );
        $oForm = $this->oData->GetForm();
        $client = $oPeopleDB->getKFR(ClientList::CLIENT, $oForm->Value("fk_clients2"));
        $s .= "<h2 id='name'>".$client->Expand("[[P_first_name]] [[P_last_name]]")."</h2>
                    <span style='font-weight: bold; font-size: 15pt; display: inline-block; margin-bottom: 5px' id='asmt-type'>".$this->oAsmt->raAssessments[$this->asmtCode]['title']."</span>
                    <span style='margin-left: 10%' id='DoB'> Date of Birth: ".$client->Value("P_dob")."</span>
                    <span style='margin-left: 10%' id='DateRecorded'>Date Recorded: ".$this->oData->getDate()."</span><br />";
        $s .= $this->oUI->DrawScoreResults();

        done:
        return( $s );
    }


    public function UpdateAsmt()
    {
        $kAsmt = 0;

        $oForm = $this->oData->GetForm();
        $oForm->Load();

        $raItems = array();
        foreach( $oForm->GetValuesRA() as $k => $v ) {
            if( substr($k,0,1) == 'i' &&  ($item = substr($k,1)) ) {
                $raItems[$item] = $v;
            }
            if( SEEDCore_StartsWith($k,'meta') ) {
                // store metadata in the same place as raw input data (but they have to start with 'meta')
                $raItems[$k] = $v;
            }

        }
        ksort($raItems);
        $oForm->SetValue( 'results', SEEDCore_ParmsRA2URL( $raItems ) );
        $oForm->SetValue( 'testType', $this->asmtCode );
        if( $oForm->Store() ) {
            // oData caches things like the raw items so re-load it
            $this->oData->LoadAsmt( $oForm->GetKey() );

            $kAsmt = $oForm->GetKey();
        }

        return( $kAsmt );
    }


    function ScoreUI()
    {
        $s = "";

        $clinics = new Clinics($this->oApp);
        $clinics->GetCurrentClinic();
        $clientlist = new ClientList( $this->oApp );
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

        $raClients = $clientlist->getMyClients();

        $sAsmt = $sList = "";

        /* Draw the list of assessments
         */
        $sList = "<form action='".CATSDIR."' method='post'><input type='hidden' name='new' value='1'/><input type='submit' value='New'/></form>";
        $raA = $oAssessmentsDB->GetList( "AxCxP", "" );
        foreach( $raA as $ra ) {
            $sStyle = $kAsmt == $ra['_key'] ? "font-weight:bold;color:green" : "";
            $sList .= "<div class='assessment-link'><a  style='$sStyle' href='".CATSDIR."?kA={$ra['_key']}'>{$ra['P_first_name']} {$ra['P_last_name']}</a></div>";
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

    function Inputs($type){
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
        // An associative array can be used to define titles for the datalist options if used
        return array("1","2","3","4","5");
    }

    /**
     * Get the score for an assessment question when that item has a given value
     * @return int
     */
    abstract protected function GetScore( $item, $value ):int;

    public function getGlobalTags():array{
        return array("date", "respondent", "date_entered");
    }

    private final function getGlobalTagField(String $tag):String{
        $s = "";
        switch($tag){
            case "date":
                $s = AssessmentsCommon::GetAssessmentDate($this->oData->GetForm()->GetValuesRA());
                break;
            case "respondent":
                $s = $this->oData->GetValue("respondent");
                break;
            case "date_entered":
                $s = substr( $this->oData->GetValue("_created"), 0, 10 );
                break;
        }
        return $s;
    }

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
        else if(in_array($tag, $this->getGlobalTags())){
            return $this->getGlobalTagField($tag);
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
    abstract function GetPercentile( string $section ) : float;

    public final function GetData():AssessmentData{
        return $this->oData;
    }

    /** Check if a client is eligable for this assessment
     * or if we have the scores needed to properly handle an assessment of this client.
     *
     * If this message returns false a message will be shown to the user presenting them with the option to continue anyway.
     *
     * Assesments should override to add restrictions to the clients that can be assesed.
     *
     * Usefull for alerting users if they try to create a MABC for a client > 16 or <3 years of age
     *
     * @param int $kClient client to check eligability of
     * @return bool true if client is eligable and should procced with out notice, false otherwise
     */
    public function checkEligibility(int $kClient, $date = ""):bool{
        return true;
    }

    public function getIneligibleMessage():String{
        return "Would you like to proceed?";
    }

}


class Assessment_AASP extends Assessments
{
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        $oData = new AssessmentData_AASP( $this, $oAsmt, $kAsmt );
        $oUI = new AssessmentUI_AASP( $oData );

        parent::__construct( $oAsmt, 'aasp', $oData, $oUI );
        $this->bUseDataList = true;     // the data entry form uses <datalist>
    }

    function DrawAsmtForm( int $kClient )
    {
        return( $this->oUI->DrawColumnForm( $kClient ) );
    }

    protected function InputOptions(){
        // Override to provide custom input options
        return array("Almost Never"=>"1","Seldom"=>"2","Occasionally"=>"3","Frequently"=>"4","Almost Always"=>"5");
    }

    protected function GetScore( $n, $v ):int
    {
        return( 0 );
    }

    public function getTags(): array{
        return array(
            "visual_items","visual_items_never",
            "auditory_items", "auditory_items_never",
            "tactile_items", "tactile_items_never",
            "vestibular_items", "vestibular_items_never",
            "taste_items", "taste_items_never",
            "low_registration", "sensory_seeking", "sensory_sensativity", "sensory_sensitivity", "sensory_avoiding",
            "q1_interpretation", "q2_interpretation", "q3_interpretation", "q4_interpretation"
        );
    }

    protected function getTagField(String $tag):String{
        switch($tag){
            case "visual_items":
            case "auditory_items":
            case "tactile_items":
            case "vestibular_items":
            case "taste_items":
                return SEEDCore_ArrayExpandSeries($this->oData->getItems(explode("_", $tag)[0],4,5), "[[]]\n ",true,array("sTemplateLast"=>"[[]]"));
            case "visual_items_never":
            case "auditory_items_never":
            case "tactile_items_never":
            case "vestibular_items_never":
            case "taste_items_never":
                return SEEDCore_ArrayExpandSeries($this->oData->getItems(explode("_", $tag)[0],1), "[[]]\n ",true,array("sTemplateLast"=>"[[]]"));
            case "low_registration":
            case "sensory_seeking":
            case "sensory_sensativity":
            case "sensory_sensitivity":
            case"sensory_avoiding":
                $tag = array("low_registration" => "q1", "sensory_seeking" => "q2", "sensory_sensativity" => "q3", "sensory_sensitivity" => "q3", "sensory_avoiding" => "q4")[$tag];
            case "q1_interpretation":
            case "q2_interpretation":
            case "q3_interpretation":
            case "q4_interpretation":
                $ra = $this->raSectionBounds[ucfirst(explode("_", $tag)[0])];
                $score = $this->oData->ComputeScore(ucfirst(explode("_", $tag)[0])."_total");
                if($score <= $ra[0]){
                    return "Much Less than Most People";
                }
                if($score <= $ra[1]){
                    return "Less than Most People";
                }
                if($score <= $ra[2]){
                    return "Similar to Most People";
                }
                if($score <= $ra[3]){
                    return "More than Most People";
                }
                if($score <= $ra[4]){
                    return "Much More than Most People";
                }
        }
    }

    function GetProblemItems( string $section ) : string
    {}
    function GetPercentile( string $section ) : float
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

    private $raSectionBounds = array(
        "Q1" => array(18,26,40,51,75),
        "Q2" => array(27,41,58,65,75),
        "Q3" => array(19,25,40,48,75),
        "Q4" => array(18,25,40,48,75)
    );

}


abstract class Assessment_SPMShared extends Assessments
/******************************************************
    Stuff that is common to SPM and SPMC
 */
{
    protected function __construct( AssessmentsCommon $oAsmt, string $asmtCode, AssessmentData $oData, AssessmentUI $oUI )
    {
        parent::__construct( $oAsmt, $asmtCode, $oData, $oUI );
    }

    function DrawAsmtForm( int $kClient )
    {
        return( $this->oUI->DrawColumnForm( $kClient ) );
    }

    protected function InputOptions(){
        return array("never","occasionally","frequently","always");
    }

    public function getTags(): array{
        $raTags = array("social_percent","social_interpretation",
                        "vision_percent", "vision_interpretation", "vision_item",
                        "hearing_percent", "hearing_interpretation", "hearing_item",
                        "touch_percent", "touch_interpretation", "touch_item",
                        "taste_item",
                        "body_percent", "body_interpretation", "body_item",
                        "vestib_percent", "vestib_interpretation", "vestib_item",
                        "planning_percent", "planning_interpretation", "planning_item",
                        "total_percent", "total_interpretation"
        );
        return $raTags;
    }

    protected function getTagField(String $tag):String{

        //Array of section keys from tag keys
        $raSectionKeys = array("vestib" => "balance");

        $s = "Tag is Valid but Not implemented";
        $parts = explode("_", $tag,2);
        if(count($parts) == 2){
            switch($parts[1]){
                case "interpretation":
                    $percentile = $this->GetPercentile(@$raSectionKeys[$parts[0]]?:$parts[0]);
                    if ($percentile > 97){
                        $s = "Definite Dysfunction";
                    }
                    else if ($percentile < 84){
                        $s = "Typical";
                    }
                    else {
                        $s = "Some Problems";
                    }
                    break;
                case "percent":
                    $s = (1 - ($this->GetPercentile(@$raSectionKeys[$parts[0]]?:$parts[0])/100))*100 ."%";
                    break;
                case "item":
                    $s = $this->GetProblemItems(@$raSectionKeys[$parts[0]]?:$parts[0]);
                    break;
            }
        }
        return $s;
    }


    function GetProblemItems( string $section ) : string
    {
        $s = "";

        if($range = @$this->oUI->GetColumnDef()[$section]['colRange'] ) {
            $range = SEEDCore_ParseRangeStrToRA( $range );
            foreach( $range as $k ) {
                if( $this->oData->ComputeScore($k) >=3 ) {
                    $s .= $this->oData->raItemDescriptions[$k]."\n";
                }
            }
        }

        return( $s );
    }

    function GetPercentile( string $section ) : float
    {
        return( $this->oData->ComputePercentile($section) );
    }

/* These should probably not be the same for SPM and SPMC !
 */
    public function GetTotals() { return($this->raTotals); }
    private $raTotals = array("56"=>"16","57"=>"16","58"=>"16","59"=>"21","60"=>"27","61"=>"34","62"=>"38","63"=>"42","64"=>"50","65"=>"54","66"=>"58",
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


    public function ComputeScore( string $item ) : int
    {

        // Array of items not to be included when computing total.
        // Since we use array_sum to calculate total we need to exclude totals to produce accurate results
        $doNotInclude = array("total","social_total","vision_total","hearing_total","touch_total","taste_total","body_total","balance_total","planning_total");

        $score = 0;

        $raScores = $this->oData->GetScores();

        // Basic scores were computed and scored by the constructor.
        // Aggregate scores are computed below and cached here.
        if( isset($raScores[$item]) ) { $score = $raScores[$item]; goto done; }

        // Look up aggregate / computed score
        switch( $item ) {
            case "social_total":
            case "vision_total":
            case "hearing_total":
            case "touch_total":
            case "taste_total":
            case "body_total":
            case "balance_total":
            case "planning_total":
                $range = SEEDCore_ParseRangeStrToRA( $this->oData->GetRangeOfColumn(explode("_", $item)[0]) );
                $score = array_sum(array_intersect_key($raScores, array_flip($range)));
                break;
            case "total":
                $score = array_sum(array_diff_key($raScores, array_flip($doNotInclude)));
                break;
        }

        $this->oData->SetScore( $item, $score );    // cache for next lookup

        done:
        return( $score );
    }

    public function ComputePercentile( string $item ) : float
    {
        $percentile = 0.0;

        switch($item){
            case "social":
            case "vision":
            case "hearing":
            case "touch":
            case "taste":
            case "body":
            case "balance":
            case "planning":
                $score = $this->ComputeScore($item."_total");
                $percentile = floatval(@$this->oData->raPercentiles[$score][$item]);
                break;
            case 'total':
                $score = $this->ComputeScore("total") - $this->ComputeScore("balance_total") - $this->ComputeScore("social_total");
                $percentile = floatval(@$this->raTotals[$score]);
                break;
        }

        return( $percentile );

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


    function DrawScoreResults() : string
    {
        $s = "";

        $oForm = $this->oData->GetForm();

        $raResults = SEEDCore_ParmsURL2RA( $oForm->Value('results') );
        foreach( $raResults as $k => $v ) {
            $oForm->SetValue( "i$k", $v );
        }

        $s .= "<table id='results'>
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

        $s .= SPMChart();

        $s .= $this->oUI->DrawColFormTable( $oForm, false );

        // Put the results in a js array for processing on the client
        $s .= "<script>
               var raResultsSPM = ".json_encode($raResults).";
               var raTotalsSPM = ".json_encode($this->oData->GetTotals()).";
               </script>";

        return( $s );
    }

}

class Assessment_SP2 extends Assessments {
    
    function __construct( AssessmentsCommon $oAsmt, int $kAsmt )
    {
        $oData = new AssessmentData_SP2( $this, $oAsmt, $kAsmt );
        $oUI = new AssessmentUI_SP2( $oData );
        
        parent::__construct( $oAsmt, 'sp2', $oData, $oUI );
        $this->bUseDataList = true;     // the data entry form uses <datalist>
    }
    
    protected function GetScore($item, $value): int
    {
        return 0;
    }

    public function GetProblemItems(string $section): string
    {
        return "";
    }

    public function DrawAsmtForm( int $kClient )
    {
        return( $this->oUI->DrawColumnForm( $kClient ) );
    }

    public function getTags(): array
    {
        return array();
    }

    protected function getTagField(String $tag): String
    {
        return "";
    }

    public function GetPercentile(string $section): float
    {
        return 0.0;
    }

    protected function InputOptions(){
        return array("Almost Always"=>5,"Frequently"=>4,"Half the Time"=>3,"Occasionally"=>2,"Almost Never"=>1,"Does Not Apply"=>0);
    }
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
        $oAsmt->GetData()->setDate(SEEDInput_Str("date"));
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
            if($p_kAsmt && (!SEEDInput_Int('fk_clients2') || SEEDInput_Int('fk_clients2') == $oAsmt->GetData()->GetValue("fk_clients2"))){
                $s .= $oAsmt->DrawAsmtForm($oAsmt->GetData()->GetValue("fk_clients2"));
            }
            else if( !$p_kAsmt && ($kClient = SEEDInput_Int('fk_clients2')) ) {
                $s .= $oAsmt->DrawAsmtForm( $kClient );
            } else {
                $s .= "<div class='alert alert-danger'>Could not load assessment edit form</div>";
                goto do_default;
            }
            break;

        case 'save':
            /* New or Edit form submitted, save the record.  Then show the main page by falling through to the default case.
             */
            if( ($kUpdatedAsmt = $oAsmt->UpdateAsmt()) ) {
                $s .= "<div class='alert alert-success'>Saved assessment</div>";
                $p_kAsmt = $kUpdatedAsmt;    // if a new asmt was inserted show it below
                $oApp->sess->VarSet('client_key', $oAsmt->GetData()->GetValue("fk_clients2"));
            } else {
                $s .= "<div class='alert alert-danger'>Could not save assessment</div>";
            }
            goto do_default;
            // fall through to the default case to show the main page

        default:
            do_default:
            /* Show the landing page or a particular assessment.
             */
            $sList = $oAC->GetSummaryTable( $p_kAsmt,$oApp->sess->smartGPC('client_key') );
            $sResult = $p_kAsmt ? $oAsmt->DrawAsmtResult() : "";
// uncomment to see the problem items in the vision column of an spm test
//$sRight .= $p_kAsmt ? $oAsmt->GetProblemItems('vision') : "";

            /* New button with a control to choose the assessment type
             */
            $oForm = new SEEDCoreForm( 'Plain' );
            $sControl =
                  "<script>$(document).ready(function (){\$('#fk_clients2').select2({placeholder:'--- Choose Client ---',allowClear:true});\$('#sAsmtType').select2({width:'resolve'});});</script>"
                 ."<style>.asmt_controlform { width:97%; margin:20px; padding:5%; border:1px solid #aaa; background-color:#eee; border-radius:3px; }</style>"
                 ."<div class='asmt_controlform'>"
                 ."<form method='post' id='assmtForm'>"
                 ."<h4>New Assessment</h4>"
                 .$oAC->GetClientSelect( $oForm )
                 ."<select name='sAsmtType' id='sAsmtType' style='max-width:100%' required>"
                 .SEEDCore_ArrayExpandRows( $oAC->raAssessments, "<option value='[[code]]'>[[title]]</option>" )
                 ."</select>"
                 ."<input type='date' name='date' max='".date("Y-m-d")."' value='".date("Y-m-d")."' required>"
                 ."<input type='hidden' name='sAsmtAction' value='edit'/>"   // this means 'new' if there is no kA
                 ."</form>"
                 ."<button onclick='onAssementCreate()'>New</button>"
                 ."</div>";

            if($sResult){
                if((CATS_DEBUG)||CATS_SYSADMIN){
                    $sResult .= "Tags: <button style='width:50px' onclick='$(\"#tags\").slideDown(1000);'>Show</button>"
                               ."<div id='tags'b style='display:none'>";
                    foreach(array_merge($oAsmt->getTags(),$oAsmt->getGlobalTags()) as $tag){
                        $sResult .= "<strong>$tag:</strong>".$oAsmt->getTagValue($tag)."<br />";
                    }
                    $sResult .= "</div>";
                }
                $sResult = "<script> var AssmtType = '".$oAC->KFRelAssessment()->GetRecordFromDBKey($p_kAsmt)->Value("testType")."';</script>"
                          .$sResult;
                $sControl .= "<script src='w/js/printme/jquery-printme.js'></script>"
                    ."<div style='padding-left:5%;display:inline'>"
                        ."<button style='background: url(".CATSDIR_IMG."Print.png) 0px/24px no-repeat; width: 24px; height: 24px;border:  none;cursor:pointer;' data-tooltip='Print Assessment' onclick='$(\"#assessment\").printMe({ \"path\": [\"w/css/spmChart.css\",\"w/css/asmt-overview.css\"]});'></button>"
                    ."</div>"
                    ."<div style='padding-left:5px;display:inline'>"
                        ."<button style='background: url(".CATSDIR_IMG."invoice.png) 0px/24px no-repeat; width: 24px; height: 24px;border:  none;cursor:pointer;' data-tooltip='Edit Assessment' onclick='window.location=\"".CATSDIR."?sAsmtAction=edit&kA=$p_kAsmt\"'></button>"
                    ."</div>"
                    ."<div style='padding-left:5px;display:inline'>"
                        ."<button style='background: url(".CATSDIR_IMG."tags.png) 0px/24px no-repeat; width: 24px; height: 24px;border:  none;cursor:pointer;' data-tooltip='View Assessment Tags' onclick=\"$('#tags_dialog').modal('show')\"></button>"
                    ."</div>";
            }
            $s .= "</div>"
                 ."<div class='container-fluid'><div class='row'>"
                     ."<div id='assessment' class='col-md-8' style='border-right:1px solid #bbb'>$sResult</div>"
                     ."<div class='col-md-4'><div>$sControl</div><div>$sList</div></div>"
                 ."</div>";
            $s .= eligibilityScript();
            if($sResult){
                $s .= getAssessmentTags($oAsmt);
            }
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
		<tspan x="-0.11822455" y="345.78546">Total</tspan>
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

function getAssessmentTypes(){
    global $raGlobalAssessments;
    $assmts = array();
    foreach ($raGlobalAssessments as $assmt){
        array_push($assmts, $assmt['code']);
    }
    return $assmts;
}

function eligibilityScript(){
    return <<<eligibilityScript
            <!-- the div that represents the modal dialog -->
            <div class='modal fade' id='confirm_dialog' role='dialog'>
                <div class='modal-dialog'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h4 class='modal-title'>Ineligible client</h4>
                        </div>
                        <div class='modal-body'>
                            The Selected client is not eligible for this assessment.<br />
                            Results may not be correct.
                            <div style='display:inline' id='assmtMessage'></div>
                        </div>
                        <div class='modal-footer'>
                            <button onClick="document.getElementById('assmtForm').submit();">Yes</button>
                            <button onClick="$('#confirm_dialog').modal('hide');">No</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                function onAssementCreate() {
                    var target  = $('#assmtForm');
                    var postData = target.serializeArray();
                    postData.push({name: 'cmd', value: 'therapist-assessment-check'});
                    $.ajax({
                        type: "POST",
                        data: postData,
                        url: 'jx.php',
                        success: function(data, textStatus, jqXHR) {
                            var jsData = JSON.parse(data);
                            if(!jsData.bOk){
                                document.getElementById('assmtMessage').innerHTML = jsData.sOut;
                                $('#confirm_dialog').modal('show');
                            }
                            else{
                                document.getElementById('assmtForm').submit();
                            }
                        },
                        error: function(jqXHR, status, error) {
                            console.log(status + ": " + error);
                        }
                    });
                }
            </script>
eligibilityScript;
}

function getAssessmentTags(Assessments $oAsmt){
    $s = <<<TEMPLATE
<!-- the div that represents the modal dialog -->
<div class="modal fade" id="tags_dialog" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Available Tags for [[type]] Assessments</h4>
            </div>
            <div class="modal-body">
                <strong>Assessment Specific Tags:</strong><br />
                [[tags]]
                <div>
                    <strong>General Assessment Tags:</strong><br />
                    [[global]]
                </div>
            </div>
        </div>
    </div>
</div>
TEMPLATE;
    
    $tags = "";
    foreach ($oAsmt->getTags() as $tag){
        if($tags){
            $tags .= "<br />";
        }
        $tags .= "\${{$oAsmt->GetAsmtCode()}:$tag}";
    }
    if(!$tags){
        $tags = "No Tags Available for this Assessment Type";
    }
    
    $global = "";
    foreach ($oAsmt->getGlobalTags() as $tag){
        if($global){
            $global .= "<br />";
        }
        $global .= "\${{$oAsmt->GetAsmtCode()}:$tag}";
    }
    
    $s = str_replace(array("[[type]]","[[tags]]","[[global]]"), array(strtoupper($oAsmt->GetAsmtCode()),$tags,$global), $s);
    
    return $s;
}
