<?php
require_once "client-modal.php" ;
require_once 'Clinics.php';
require_once "therapist-clientlistxls.php";
require_once 'client_code_generator.php';

class ClientList
{
    
    public const CLIENT = "C";
    public const INTERNAL_PRO = "PI";
    public const EXTERNAL_PRO = "PE";
    
    private $oApp;
    public $kfdb;

    public $oPeopleDB, $oClinicsDB;
    
    //map of computer keys to human readable text
    //Used to generate the connect modal and role select
    public static $pro_roles_name = array("GP"=>"GP","Paediatrician"=>"Paediatrician", "Psychologist"=>"Psychologist", "SLP"=>"SLP", "PT"=>"PT", "OT"=>"OT", "Specialist_Dr"=>"Specialist Dr", "Resource_Teacher"=>"Resource Teacher", "Teacher_Tutor"=>"Teacher/Tutor", "Other"=>"Other");
    
    public static $staff_roles_name = array("SLP"=>"SLP", "PT"=>"PT", "OT"=>"OT", "Office_Staff"=>"Office Staff", "Other"=>"Other");

    private static $raTemplate = ['header'=>'', 'tabs'=>['tab1'=>'','tab2'=>'','tab3'=>'','tab4'=>''], 'tabNames'=>['tab1', 'tab2', 'tab3','tab4']];
    
    private $client_key;
    private $therapist_key;
    private $pro_key;
    private $clinics;
    private $oCCG;

    private $queryParams = array("sSortCol" => "P.first_name,_key");
    
    //Variable used to store the form while its being generated.
    private $sForm = "";
    
    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->kfdb = $oApp->kfdb;

        $this->oPeopleDB = new PeopleDB( $this->oApp );
        $this->oClinicsDB = new ClinicsDB($oApp->kfdb);

        $clinics = new Clinics($oApp);
        $clinics->GetCurrentClinic();

        $this->client_key = SEEDInput_Int( 'client_key' );
        $this->therapist_key = SEEDInput_Int( 'therapist_key' );
        $this->pro_key = SEEDInput_Int( 'pro_key' );
        $this->clinics = new Clinics($oApp);
        $this->oCCG = new ClientCodeGenerator($this->oApp);
    }
    
    function DrawClientList()
    {
        if(@$_SESSION['clientListView']){
            ClientsAccess::getAccess(true,ClientsAccess::LIMITED);
        }
        else{
            ClientsAccess::getAccess(true,ClientsAccess::QUERY);
        }
        $s = "<div style='clear:both;float:right; border:1px solid #aaa;border-radius:5px;padding:10px'>"
                 ."<a href='jx.php?cmd=therapist-clientlistxls'><button>Download</button></a>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
                 ."<img src='".W_CORE_URL."img/icons/xls.png' height='30'/>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
/* TODO: upload isn't working
                 ."<form style='display:inline-block;' action='${_SERVER['PHP_SELF']}' method='post' enctype='multipart/form-data'>"
                 ."<input type='submit' value='Upload'/>&nbsp;&nbsp;&nbsp;"
                 ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
                 ."<input type='hidden' name='cmd' value='uploadxls' />"
                 ."<input type='file' name='uploadfile' style='font-size:9pt'/>"
                 ."</form>"
*/
             ."</div>";

        // Put this before the GetClients call so the changes are shown in the list
        $cmd = SEEDInput_Str('cmd');
        
        $s .= "<div id='messageBox'>";
        $s .= $this->proccessCommands($cmd)['message'];
        $s .= "</div>";
        
        //Legacy Code kept for support of direct Request control
        $sNew = "";
        if($this->client_key == -1){
            $this->client_key = 0;
            $sNew = self::CLIENT;
        }
        elseif ($this->therapist_key == -1){
            $this->therapist_key = 0;
            $sNew = self::INTERNAL_PRO;
        }
        elseif ($this->pro_key == -1){
            $this->pro_key = 0;
            $sNew = self::EXTERNAL_PRO;
        }

        $condClinic = $this->clinics->isCoreClinic() ? "" : ("clinic = ".$this->clinics->GetCurrentClinic());
        
        $s .= "<div style='clear:both' class='container-fluid'>"
             ."<div class='row'>"
                ."<div id='searchbar-wrapper' class='col-md-12'>"
                    ."<input type='text' id='searchbar' />"
                ."</div>"
             ."</div>"
             ."<div class='row'>"
             ."<div id='clients' class='col-md-4'>"
                 .$this->drawList(self::CLIENT,$condClinic)[0]
             ."</div>"
//              ."<div id='therapists' class='col-md-4'>"
//                  .$this->drawList(self::INTERNAL_PRO,$condClinic)[0]
//              ."</div>"
             ."<div id='pros' class='col-md-4'>"
                 .$this->drawList(self::EXTERNAL_PRO,$condClinic)[0]
             ."</div>"
             ."</div></div>";
             
             $s .= <<<Sidebar
<div id='sidebar'>
    <div id='sidebar-container'>
        <div class='close-sidebar' onclick='closeSidebar()'><i class='fas fa-times'></i></div>
        <div id='sidebar-header'>Name and stuff goes here.</div>
        <div id='tabs'>
    		[[TABS]]
	   </div><br/>
	   <div id='tab-content'></div>
    </div>
</div>
Sidebar;
             $sTabs = "";
             for($i=1;$i<=count(self::$raTemplate['tabs']);$i++){
                 if($i == 1){
                     $sTabs .= "<div id='tab{$i}' class='tab active-tab'>Tab{$i}</div>";
                 }
                 else{
                     $sTabs .= "<div id='tab{$i}' class='tab'>Tab{$i}</div>";
                 }
             }
             $s = str_replace("[[TABS]]", $sTabs, $s);
             
             $s .= "<script>$( document ).ready(function() {";
             if( $this->client_key || $sNew == self::CLIENT) {
                 $s .= "getForm('".self::createID(self::CLIENT, $this->client_key)."');";
             }
             if( $this->therapist_key || $sNew == self::INTERNAL_PRO) {
                 $s .= "getForm('".self::createID(self::INTERNAL_PRO, $this->therapist_key)."');";
             }
             if( $this->pro_key || $sNew == self::EXTERNAL_PRO) {
                 $s .= "getForm('".self::createID(self::EXTERNAL_PRO, $this->pro_key)."');";
             }
             $s .= "});</script>";

             $s .= "<div id='modalBox'></div>";
             if(isset($_SESSION['newLinks'])){
                 unset($_SESSION['newLinks']);
             }
        return( $s );
    }

    public function DrawAjaxForm(int $pid, String $type = self::CLIENT):array{
        $ra = self::$raTemplate;
        $kfr = $this->oPeopleDB->GetKFR($type, $pid );
        if(!$kfr){
            $kfr = $this->oPeopleDB->KFRel($type)->CreateRecord();
        }
        $condClinic = $this->clinics->isCoreClinic() ? "" : ("clinic = ".$this->clinics->GetCurrentClinic());
        $raClients    = $this->oPeopleDB->GetList(self::CLIENT, $condClinic, array_merge($this->queryParams,array("iStatus" => -1)));
        $raPros = $this->oPeopleDB->GetList(self::EXTERNAL_PRO, $condClinic, $this->queryParams);
        switch($type){
            case self::CLIENT:
                $oForm = new KeyframeForm( $this->oPeopleDB->KFRel(self::CLIENT), "A");
                $oForm->SetKFR($kfr);
                $myPros = ($pid?$this->oPeopleDB->GetList('CX', "fk_clients2='{$pid}'"):array());
                $ra = $this->drawClientForm($oForm, $myPros, $raPros);
                break;
            case self::INTERNAL_PRO:
                $oForm = new KeyframeForm( $this->oPeopleDB->KFRel(self::INTERNAL_PRO), "A" );
                $oForm->SetKFR($kfr);
                $myClients = ($pid?$this->oPeopleDB->GetList('CX', "fk_pros_internal='{$pid}'"):array());
                $ra = $this->drawProForm($oForm, $myClients, $raClients, true);
                break;
            case self::EXTERNAL_PRO:
                $oForm = new KeyframeForm( $this->oPeopleDB->KFRel(self::EXTERNAL_PRO), "A" );
                $oForm->SetKFR($kfr);
                $myClients = ($pid?$this->oPeopleDB->GetList('CX', "fk_pros_external='{$pid}'"):array());
                $ra = $this->drawProForm($oForm, $myClients, $raClients, false);
                break;
        }
        if(isset($_SESSION['newLinks'])){
            unset($_SESSION['newLinks']);
        }
        return $ra;
    }
    
    public function proccessCommands(String $cmd){
        $s = "";
        $id = "";
        $oFormClient    = new KeyframeForm( $this->oPeopleDB->KFRel(self::CLIENT), "A");
        $oFormTherapist = new KeyframeForm( $this->oPeopleDB->KFRel(self::INTERNAL_PRO), "A" );
        $oFormPro       = new KeyframeForm( $this->oPeopleDB->KFRel(self::EXTERNAL_PRO), "A" );
        
        $overrideCheck = FALSE;
        if(isset($_SESSION["cmdData"])){
            $type = SEEDInput_Str("type");
            switch ($type){
                case self::CLIENT:
                    $kfrelID = $type;
                    $type = "client";
                    break;
                case self::INTERNAL_PRO:
                    $kfrelID = $type;
                    $type = "therapist";
                    break;
                case self::EXTERNAL_PRO:
                    $kfrelID = $type;
                    $type = "pro";
                    break;
                case "client":
                    $kfrelID = self::CLIENT;
                    break;
                case "therapist":
                    $kfrelID = self::INTERNAL_PRO;
                    break;
                case "pro":
                    $kfrelID = self::EXTERNAL_PRO;
                    break;
                default:
                    $kfrelID = "";
            }
            switch ($cmd){
                case "new":
                    $overrideCheck = TRUE;
                    $cmd = "update_".$type;
                    $kfr = new KeyframeRecord($this->oPeopleDB->KFRel($kfrelID));
                    $kfr->LoadValuesFromRA($_SESSION["cmdData"]);
                    switch($type){
                        case "client":
                            $oFormClient->SetKFR($kfr);
                            break;
                        case "therapist":
                            $oFormTherapist->SetKFR($kfr);
                            $_FILES = $_SESSION['fileData'];
                            break;
                        case "pro":
                            $oFormPro->SetKFR($kfr);
                            break;
                    }
                    unset($_SESSION["cmdData"]);
                    unset($_SESSION['fileData']);
                    break;
                case "overwrite":
                    $overrideCheck = TRUE;
                    $cmd = "update_".$type;
                    $kfr = new KeyframeRecord($this->oPeopleDB->KFRel($kfrelID));
                    $kfr->LoadValuesFromRA($_SESSION["cmdData"]);
                    $kfr->SetKey(SEEDInput_Int("key"));
                    $kfr->StatusSet("Normal");
                    switch($type){
                        case "client":
                            $oFormClient->SetKFR($kfr);
                            break;
                        case "therapist":
                            $oFormTherapist->SetKFR($kfr);
                            $_FILES = $_SESSION['fileData'];
                            break;
                        case "pro":
                            $oFormPro->SetKFR($kfr);
                            break;
                    }
                    unset($_SESSION["cmdData"]);
                    unset($_SESSION['fileData']);
                    break;
                case "keep":
                default:
                    unset($_SESSION["cmdData"]);
                    unset($_SESSION['fileData']);
                    break;
            }
        }
        
        $existsWarning = <<<ExistsWarning
        <div class='alert alert-warning' style='width: 85%;justify-content: space-around;'>
            A [[type]] with this name is already exists in this clinic<br />
            <div style='justify-content: space-around;display: flex'>
                <form style='display:inline' onSubmit='event.preventDefault()'>
                    <input type='hidden' name='cmd' value='overwrite' />
                    <input type='hidden' name='type' value='[[type]]' />
                    <input type='submit' value='Replace:' onclick='submitForm(event)' />
                    <select name='key'>
                        [[options]]
                    </select>
                </form>
                <button onClick='sendCMD(event,"keep")'>Use Original</button>
                <form style='display:inline' onSubmit='submitForm(event)'><input type='hidden' name='cmd' value='new' /><input type='hidden' name='type' value='[[type]]' /><input type='submit' value='Save as new [[type]]' /></form>
            </div>
        </div>
ExistsWarning;
        
        switch( $cmd ) {
            case "update_client":
                $exists = $this->checkExists($oFormClient, self::CLIENT);
                if($exists && !$overrideCheck){
                    $_SESSION["cmdData"] = $oFormClient->GetKFR()->ValuesRA();
                    $options = "";
                    $ra = $this->oPeopleDB->GetList(self::CLIENT, "P.first_name='".$oFormClient->Value("P_first_name")."' AND P.last_name='".$oFormClient->Value("P_last_name")."' AND clinic=".$oFormClient->Value("clinic"),array_merge($this->queryParams,array("iStatus" => -1)));
                    foreach ($ra as $option){
                        $options .= "<option value='".$option['_key']."'>".$this->oCCG->getClientCode($option['_key']).($option['_status'] == 1?"(Deleted)":"").($option['_status'] == 2?"(Discharged)":"")."</option>";
                    }
                    $s .= str_replace(array("[[type]]","[[options]]"), array("client",$options), $existsWarning);
                }
                else{
                    // Check if the client is a new client
                    $oFormClient->Update(array("bNoStore" => TRUE));
                    $new = $oFormClient->GetKey() == 0;
                    // Perform regular update
                    $oFormClient->Update();
                    $this->updatePeople( $oFormClient );
                    $this->oApp->kfdb->Execute("UPDATE clients2 SET parents_separate = b'{$oFormClient->Value('parents_separate')}' WHERE _key={$oFormClient->GetKey()}");
                    $this->client_key = $oFormClient->GetKey();
                    if($oFormClient->Value("P_first_name") && $oFormClient->Value("P_last_name")){
                        // Only create client code once first and last name are set
                        $this->oCCG->getClientCode($this->client_key);
                    }
                    
                    if($new){
                        // A new client was entered connect them with the staff that entered them.
                        $kfr = $this->oPeopleDB->GetKfrel("CX")->CreateRecord();
                        $kfr->SetValue('fk_clients2', $this->client_key);
                        $staff = $this->oApp->kfdb->Query1("SELECT S._key FROM pros_internal as S, people as P WHERE S.fk_people = P._key AND P.uid = {$this->oApp->sess->GetUID()}");
                        $kfr->SetValue('fk_pros_internal', $staff);
                        $kfr->PutDBRow();
                    }
                    
                    $id = self::createID(self::CLIENT, $this->client_key);
                }
                break;
            case "discharge_client":
                $kfr = $this->oPeopleDB->GetKFR(self::CLIENT, $this->client_key);
                $kfr->StatusSet("Hidden");
                $kfr->PutDBRow();
                $s .= "<div class='alert alert-success alert-dismissible'>Client Discharged</div>";
                $id = self::createID(self::CLIENT, $this->client_key);
                break;
            case "admit_client":
                $kfr = $this->oPeopleDB->GetKFR(self::CLIENT, $this->client_key);
                $kfr->StatusSet("Normal");
                $kfr->PutDBRow();
                $s .= "<div class='alert alert-success alert-dismissible'>Client Admitted</div>";
                $id = self::createID(self::CLIENT, $this->client_key);
                break;
            case "regenerate_client_code":
                /* WARNING this will overwrite the existing code.
                 * This action should only be performed by a developer
                 * as it can affect the codes of other clients
                 */
                if($this->oCCG->regenerateCode($this->client_key)){
                    $s .= "<div class='alert alert-success alert-dismissible'>Code Regenerated</div>";
                }
                else{
                    $s .= "<div class='alert alert-danger alert-dismissible'>You Don't Have permission to perform this action</div>";
                }
                break;
            case "update_therapist":
                $exists = $this->checkExists($oFormTherapist, self::INTERNAL_PRO);
                if($exists && !$overrideCheck){
                    $_SESSION["cmdData"] = $oFormTherapist->GetKFR()->ValuesRA();
                    $_SESSION['fileData'] = $_FILES;
                    $options = "";
                    $ra = $this->oPeopleDB->GetList(self::INTERNAL_PRO, "P.first_name='".$oFormTherapist->Value("P_first_name")."' AND P.last_name='".$oFormTherapist->Value("P_last_name")."' AND clinic=".$oFormTherapist->Value("clinic"),$this->queryParams);
                    foreach ($ra as $option){
                        $options .= "<option value='".$option['_key']."'>".$option["P_first_name"]." ".$option["P_last_name"]."(".$option["_key"].")</option>";
                    }
                    $s .= str_replace(array("[[type]]","[[options]]"), array("therapist",$options), $existsWarning);
                }
                else{
                    $oFormTherapist->Update();
                    $this->updatePeople( $oFormTherapist );
                    $this->therapist_key = $oFormTherapist->GetKey();
                    //Handle Signature Upload
                    if(@$_FILES["new_signature"]["tmp_name"]){
                        $this->oApp->kfdb->Execute("UPDATE `pros_internal` SET `signature` = '".addslashes(file_get_contents($_FILES["new_signature"]["tmp_name"]))."' WHERE `pros_internal`.`_key` = ".$this->therapist_key);
                    }
                    if(SEEDInput_Int('linkClient')){
                        $kfr = $this->oPeopleDB->KFRel("CX")->CreateRecord();
                        $kfr->SetValue("fk_clients2", SEEDInput_Int("linkClient"));
                        $kfr->SetValue("fk_pros_internal", SEEDInput_Int($this->therapist_key));
                        $kfr->PutDBRow();
                    }
                }
                $id = self::createID(self::INTERNAL_PRO, $this->therapist_key);
                break;
            case "update_pro":
                $exists = $this->checkExists($oFormPro, self::EXTERNAL_PRO);
                if($exists && !$overrideCheck){
                    $_SESSION["cmdData"] = $oFormPro->GetKFR()->ValuesRA();
                    $options = "";
                    $ra = $this->oPeopleDB->GetList(self::EXTERNAL_PRO, "P.first_name='".$oFormPro->Value("P_first_name")."' AND P.last_name='".$oFormPro->Value("P_last_name")."' AND clinic=".$oFormPro->Value("clinic"),$this->queryParams);
                    foreach ($ra as $option){
                        $options .= "<option value='".$option['_key']."'>".$option["P_first_name"]." ".$option["P_last_name"]."(".$option["_key"].")</option>";
                    }
                    $s .= str_replace(array("[[type]]","[[options]]"), array("pro",$options), $existsWarning);
                }
                else{
                    $oFormPro->Update();
                    $this->updatePeople( $oFormPro );
                    $this->pro_key = $oFormPro->GetKey();
                    if(SEEDInput_Int('linkClient')){
                        $kfr = $this->oPeopleDB->KFRel("CX")->CreateRecord();
                        $kfr->SetValue("fk_clients2", SEEDInput_Int("linkClient"));
                        $kfr->SetValue("fk_pros_external", SEEDInput_Int($this->pro_key));
                        $kfr->PutDBRow();
                    }
                }
                $id = $id = self::createID(self::EXTERNAL_PRO, $this->pro_key);
                break;
            case "link":
                $kfr = $this->oPeopleDB->KFRel("CX")->CreateRecord();
                $kfr->SetValue("fk_pros_external", SEEDInput_Int("add_external_key"));
                $kfr->SetValue("fk_clients2", SEEDInput_Int("add_client_key"));
                $kfr->SetValue("fk_pros_internal", SEEDInput_Int("add_internal_key"));
                $kfr->PutDBRow();
                
                $id = SEEDInput_Str("id");
                
                break;
            case 'uploadxls':
                $s .= $this->uploadSpreadsheet();
                break;
            case 'linkAccount':
                $key = SEEDInput_Str('key');
                $people_key = SEEDInput_Int('people_id');
                $account = SEEDInput_Int('newAccount');
                $this->oApp->kfdb->Execute("UPDATE `people` SET `uid` = '$account' WHERE `people`.`_key` = $people_key;");
                list($type,$id) = self::parseID($key);
                switch($type){
                    case self::CLIENT:
                        $this->client_key = $id;
                        break;
                    case self::INTERNAL_PRO:
                        $this->therapist_key = $id;
                        break;
                    case self::EXTERNAL_PRO:
                        $this->pro_key = $id;
                        break;
                }
                $id = $key;
                break;
            case 'linkNew':
                $_SESSION['newLinks']['client_key'] = SEEDInput_Int('client_key');
                $id = self::createID(self::EXTERNAL_PRO, 0);
                break;
            case 'mailPros':
                $condClinic = $this->clinics->isCoreClinic() ? "" : (" AND clinic = ".$this->clinics->GetCurrentClinic());
                // Get the provider keys
                $raProKeys = array_column($this->oPeopleDB->GetList(self::EXTERNAL_PRO, 'P.address != "" AND P.city != "" AND P.province != "" AND P.postal_code != ""'.$condClinic),'_key');
                // Convert to IDs for template filler
                $raProKeys = array_map(function($value){return self::createID(self::EXTERNAL_PRO, $value);}, $raProKeys);
                // Split the keys into groups of 5 (for template)
                $chunks = array_chunk($raProKeys, 5);
                if(count($chunks) > 1){
                    // We need more than one file
                    $zip = new ZipArchive();
                    $filename = tempnam(sys_get_temp_dir(), "pro");
                    if ($zip->open($filename, ZIPARCHIVE::CREATE )!==TRUE) {
                        exit("cannot open zip archive\n");
                    }
                    foreach($chunks as $k=>$chunk)
                    {
                        // Create the template filler object with the proper settings (ie data)
                        $filler = new template_filler($this->oApp,[],$chunk);
                        $zip->addFile($filler->fill_resource(CATSLIB . "ReportsTemplates/Address Labels Template.docx",[],template_filler::RESOURCE_GROUP),"Address Label #".($k+1));
                    }
                    $zip->close();
                    header("Content-type: application/zip");
                    header("Content-Disposition: attachment; filename='Address Labels'");
                    header("Content-length: " . filesize($filename));
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    if( ($fp = fopen( $tempfile, "rb" )) ) {
                        fpassthru( $fp );
                        fclose( $fp );
                    }
                    exit;
                }
                else if(count($chunks) == 1){
                    // We can get away with one file
                    // Create the template filler object with the proper settings (ie data)
                    $filler = new template_filler($this->oApp,[],$chunks[0]);
                    $filler->fill_resource(CATSLIB . "ReportsTemplates/Address Labels Template.docx");
                }
                exit;
        }
        $list = $this->drawList((@$this->parseID($id)[0]?:""));
        
        if(SEEDInput_Str("action") == "Save and Close"){
            $id = "";
        }
        else if(SEEDInput_Str("action") == "Save and Print Consent forms"){
            $id = "";
            header("Location: ".CATSDIR."?screen=therapist-filing-cabinet&dir=clinic");
            header("HTTP/1.0 205 Reset Content");
            exit;
        }
        
        return array("message"=>$s,"id"=>$id, "list"=>$list[0],"listId" => $list[1]);
    }
    
    public function drawList(String $type, String $condClinic = ""):array{
        if(!$condClinic){
            $condClinic = $this->clinics->isCoreClinic() ? "" : ("clinic = ".$this->clinics->GetCurrentClinic());
        }
        $s = "";
        $id = "";
        switch($type){
            case self::CLIENT:
                $raClients = $this->getMyClientsInternal(-1);
                $s = "<h3 style='display:inline-block'>Clients</h3>"
                      .(ClientsAccess::QueryAccess()>= ClientsAccess::LEADER?"<input type='checkbox' style='margin-left:10px' ".(@$_SESSION['clientListView']?"checked ":"")."onchange='toggleView(event)' />Therapist View":"")
                      ."<br /><button onclick=\"getForm('".self::createID(self::CLIENT, 0)."');\">Add Client</button><br />"
                      ."<form id='filterForm' action='".CATSDIR."jx.php' style='display:inline'>
                            <input type='checkbox' name='clientlist-normal' id='normal-checkbox' ".(@$this->oApp->sess->VarGet("clientlist-normal") || @$this->oApp->sess->VarGet("clientlist-normal") === NULL?"checked":"").">Normal</input>
                            <input type='checkbox' name='clientlist-discharged' id='discharged-checkbox' ".(@$this->oApp->sess->VarGet("clientlist-discharged")?"checked":"").">Discharged</input>
                            <input type='hidden' name='cmd' value='therapist-clientList-sort' />
                            <button onclick='filterClients(event);'>Filter</button>
                        </form>"
                        .SEEDCore_ArrayExpandRows( $raClients, "<div id='client-[[_key]]' class='client client-%[[_status]]' style='padding:5px;' data-id='".self::CLIENT."[[_key]]' onclick='getForm(this.dataset.id)'><div class='name'>[[P_first_name]] [[P_last_name]]%[[clinic]]</div><div class='slider'><div class='text'>View/edit</div></div></div>");
                $id = "clients";
                //fix up status classes
                $s = str_replace(array("-%0","-%2"), array("-normal","-discharged"), $s);
                break;
            case self::INTERNAL_PRO:
                $condStaff = "P.uid in (SELECT fk_SEEDSession_users FROM users_clinics WHERE fk_clinics = {$this->clinics->GetCurrentClinic()})";
                if($this->clinics->isCoreClinic()){
                    $condStaff = "";
                }
                $raTherapists = $this->oPeopleDB->GetList(self::INTERNAL_PRO, $condStaff, $this->queryParams);
                $s = "<h3>CATS Staff</h3>"
                      .(CATS_SYSADMIN?"<button onclick=\"getForm('".self::createID(self::INTERNAL_PRO, 0)."');\">Add Staff Member</button>":"")
                      .SEEDCore_ArrayExpandRows( $raTherapists, "<div id='therapist-[[_key]]' class='therapist' style='padding:5px;' data-id='".self::INTERNAL_PRO."[[_key]]' onclick='getForm(this.dataset.id)'><div class='name'>[[P_first_name]] [[P_last_name]] is a [[pro_role]]%[[clinic]]</div><div class='slider'><div class='text'>View/edit</div></div></div>" );
                $id = "therapists";
                break;
            case self::EXTERNAL_PRO:
                $raPros = $this->oPeopleDB->GetList(self::EXTERNAL_PRO, $condClinic, $this->queryParams);
                $s = "<h3>External Providers</h3>"
                      ."<button onclick=\"getForm('".self::createID(self::EXTERNAL_PRO, 0)."');\">Add External Provider</button><a href='jx.php?cmd=therapist--clientlist-mailPros' style='margin-left:5px'><button>Get Address labels</button></a>"
                      .SEEDCore_ArrayExpandRows( $raPros, "<div id='pro-[[_key]]' class='pro' style='padding:5px;' data-id='".self::EXTERNAL_PRO."[[_key]]' onclick='getForm(this.dataset.id)'><div class='name'>[[P_first_name]] [[P_last_name]] is a [[pro_role]]%[[clinic]]</div><div class='slider'><div class='text'>View/edit</div></div></div>" );
                $id = "pros";
                break;
        }
        foreach($this->oClinicsDB->KFRel()->GetRecordSetRA("") as $clinic){
            if($this->clinics->isCoreClinic()){
                $s = str_replace("%".$clinic['_key'], " @ the ".$clinic['clinic_name']." clinic", $s);
            }
            else {
                $s = str_replace("%".$clinic['_key'], "", $s);
            }
        }
        return array($s,$id);
    }
    
    private function updatePeople( SEEDCoreForm $oForm )
    /***************************************************
        The relations C, PI, and PE join with P. When those are updated, the P_* fields have to be copied to table 'people'
     */
    {
        $peopleFields = array( 'pronouns','first_name','last_name','address','city','province','postal_code','dob','phone_number','email' );

        $kP = $oForm->Value('P__key');
        if(!$kP){
            $sCond = "";
            foreach($peopleFields as $field){
                if($sCond){
                    $sCond .= " AND ";
                }
                $sCond .= $field." = '".$oForm->Value("P_".$field)."'";
            }
            if(($kfr = $this->oPeopleDB->GetKFRCond("P",$sCond))){
                $kP = $kfr->Key();
            }
        }
        if(($kfr = ($kP?$this->oPeopleDB->GetKFR('P', $kP):$this->oPeopleDB->KFRel("P")->CreateRecord())) ) {
            foreach( $peopleFields as $v ) {
                $kfr->SetValue( $v, $oForm->Value("P_$v") );
            }
            $raExtra = array();
            if( $oForm->Value('P_extra_credentials') )  $raExtra['credentials'] = $oForm->Value('P_extra_credentials');
            if( $oForm->Value('P_extra_regnumber') )    $raExtra['regnumber'] = $oForm->Value('P_extra_regnumber');
            if( $oForm->Value('P_extra_referal') )  $raExtra['referal'] = $oForm->Value('P_extra_referal');
            if( $oForm->Value('P_extra_challenges') )    $raExtra['challenges'] = $oForm->Value('P_extra_challenges');
            if( $oForm->Value('P_extra_goals') )    $raExtra['goals'] = $oForm->Value('P_extra_goals');
            if( $oForm->Value('P_extra_address') )    $raExtra['address'] = $oForm->Value('P_extra_address');
            if( $oForm->Value('P_extra_city') )  $raExtra['city'] = $oForm->Value('P_extra_city');
            if( $oForm->Value('P_extra_province') )    $raExtra['province'] = $oForm->Value('P_extra_province');
            if( $oForm->Value('P_extra_postal_code') )    $raExtra['postal_code'] = $oForm->Value('P_extra_postal_code');
            if( $oForm->Value('P_extra_parents_name') )    $raExtra['parents_name'] = $oForm->Value('P_extra_parents_name');
            if( count($raExtra) )  $kfr->SetValue( 'extra', SEEDCore_ParmsRA2URL( $raExtra ) );
            $kfr->PutDBRow();
            $oForm->SetValue("fk_people", $kfr->Key());
            $oForm->Store();
        }
    }
    
    /**
     * This method checks if the person exists in the current clinic
     * @param KeyframeForm $oForm - Form containing the data
     * @param String $rel - the KeyframeRelation identifier to check
     * @return bool True if new person and someone exists, False otherwise
     */
    private function checkExists(KeyframeForm $oForm, String $rel):bool{
        $oForm->Update(array("bNoStore" => TRUE));
        if($oForm->GetKey()){
            return FALSE;
        }
        $ra = $this->oPeopleDB->GetList($rel, "P.first_name='".$oForm->Value("P_first_name")."' AND P.last_name='".$oForm->Value("P_last_name")."' AND clinic=".$oForm->Value("clinic"),array_merge($this->queryParams,array("iStatus" => -1)));
        return(!empty($ra));
    }
    
    private function drawClientForm( KeyframeForm $oForm, $myPros, $raPros )
    /**************************************************
        The user clicked on a client name so show their form
     */
    {
        $raOut = self::$raTemplate;

        $sTherapists = "<div style='padding:10px;border:1px solid #888'>";
        $sPros       = "<div style='padding:10px;border:1px solid #888'>";
        foreach( $myPros as $ra ) {
            if( $ra['fk_pros_internal'] && ($kfr = $this->oPeopleDB->GetKFR( self::INTERNAL_PRO, $ra['fk_pros_internal'] )) ) {
                if($this->clinics->isCoreClinic()){
                    $kfr->setValue("clinic_name"," (".$this->oClinicsDB->GetClinic($kfr->Value("clinic"))->Value('clinic_name').")");
                }
                else{
                    $kfr->setValue("clinic_name","");
                }
                $sTherapists .= $kfr->Expand( "[[P_first_name]] [[P_last_name]] is my [[pro_role]][[clinic_name]]<br />" );
            }
            if( $ra['fk_pros_external'] && ($kfr = $this->oPeopleDB->GetKFR( self::EXTERNAL_PRO, $ra['fk_pros_external'] )) ) {
                if($this->clinics->isCoreClinic()){
                    $kfr->setValue("clinic_name"," (".$this->oClinicsDB->GetClinic($kfr->Value("clinic"))->Value('clinic_name').")");
                }
                else{
                    $kfr->setValue("clinic_name","");
                }
                $sPros .= $kfr->Expand( "[[P_first_name]] [[P_last_name]] is my [[pro_role]][[clinic_name]]<br />" );
            }
        }
        if($sTherapists == "<div style='padding:10px;border:1px solid #888'>"){
            $sTherapists .= "No Staff Connected";
        }
        if($sPros == "<div style='padding:10px;border:1px solid #888'>"){
            $sPros .= "No External Providers Connected";
        }
        $sTherapists .= "</div>";
        $sPros       .= "</div>";

        $raExtra = SEEDCore_ParmsURL2RA( $oForm->Value('P_extra') );
        $oForm->SetValue( 'P_extra_referal', @$raExtra['referal'] );
        $oForm->SetValue( 'P_extra_challenges', @$raExtra['challenges'] );
        $oForm->SetValue( 'P_extra_goals', @$raExtra['goals'] );
        $oForm->SetValue( 'P_extra_address', @$raExtra['address'] );
        $oForm->SetValue( 'P_extra_city', @$raExtra['city'] );
        $oForm->SetValue( 'P_extra_province', @$raExtra['province'] );
        $oForm->SetValue( 'P_extra_postal_code', @$raExtra['postal_code'] );
        $oForm->SetValue( 'P_extra_parents_name', @$raExtra['parents_name'] );
        
        $oForm->SetStickyParms( array( 'raAttrs' => array( 'maxlength'=>'200', 'style'=>'width:100%',($oForm->Value("_status")==0?"":"disabled")=>"disabled" ) ) );
        $age = date_diff(date_create($oForm->Value("P_dob")), date_create('now'))->format("%y Years, %m Months");
        $this->sForm =
             ($oForm->Value("_status")==0?"<form id='clientForm' onSubmit='clinicHack(event);submitSidebarForm(event)'>":"")
             ."<input type='hidden' name='cmd' value='update_client'/>"
             .($oForm->Value('_key')?"<input type='hidden' name='client_key' id='clientId' value='{$oForm->Value('_key')}'/>":"")
             .$oForm->HiddenKey()
             ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
                 .($oForm->Value('_key')?($this->clinics->isCoreClinic()?"<p>Client # {$oForm->Value('_key')}</p>":""):"<p>New Client</p>")
             ."<table class='container-fluid table table-striped table-sm sidebar-table'>";
             $this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name' autofocus onchange='checkNameExists()'") ) );
             $this->drawPartialFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name' onchange='checkNameExists()'") ) );
             $this->drawPartialFormRow( "", "<span id='name-exists'>A client with this name already exists.</span>" );
             $this->drawFormRow( "Pronouns", $this->getPronounList($oForm));
             $this->drawFormRow( "Parents Name", $oForm->Text('parents_name',"",array("attrs"=>"placeholder='Parents Name'") ) );
             $this->drawFormRow( "Address", $oForm->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) );
             $this->drawFormRow( "City", $oForm->Text('P_city',"",array("attrs"=>"placeholder='City'") ) );
             $this->drawFormRow( "Province", $oForm->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) );
             $this->drawFormRow( "Postal Code", $oForm->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) );
             $this->parentsSeparateField($oForm);
             $this->drawFormRow( "School" , str_replace("[name]", $oForm->Name("school"), $this->schoolField($oForm->Value("school"),$oForm)));
             $this->drawPartialFormRow( "Date Of Birth", $oForm->Date('P_dob',"",array("attrs"=>"style='border:1px solid gray' pattern='\d{4}-((0\d)|(1[0-2]))-(([0-2]\d)|(3[01]))' title='yyyy-mm-dd' placeholder='yyyy-mm-dd' oninput='updateAge(event)'"))."<span class='date-description'>(YYYY-MM-DD)</span>" );
             $this->drawPartialFormRow( "Age", "<span id='age'>".$age."</span>");
             $this->drawFormRow( "Phone Number", $oForm->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' maxlength='200'") ) );
             $this->drawFormRow( "Email", $oForm->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) );
             $this->drawFormRow( "Clinic", $this->getClinicList($oForm) );
             $this->drawFormRow( "Code", ($oForm->Value('_key')?$this->oCCG->getClientCode($oForm->Value('_key')):"Code generated once first and last name are set"));
             $this->endRowDraw();
             if($oForm->Value("_status")==0){
             $this->sForm .= "<tr class='row'>"
                ."<td class='col-md-12'><input id='save-button' type='submit' value='Save' />"
                ."<input id='save-close-button' type='submit' value='Save and Close' />"
                ."<input id='save-print-button' type='submit' value='Save and Print Consent forms' /></td>"
             ."</tr>"
             ."</table>"
             ."</form>";
             }
             else{
                 $this->sForm .= "</table>";
             }

        $raOut['header'] = "<h3>Client : ".$oForm->Value('P_first_name')." ".$oForm->Value('P_last_name')."</h3>";
        $raOut['tabs']['tab1'] = $this->sForm;
        if($oForm->Value('_key')){
            $raOut['tabs']['tab3'] = $sTherapists.$sPros.($oForm->Value('_key')?drawModalButton($oForm->Value('_key')).drawStaffModalButton($oForm->Value('_key')):"");
            $raOut['tabs']['tab4'] = "<form onSubmit='event.preventDefault()'><input type='hidden' name='client_key' value='".$oForm->Value("_key")."' /><input type='hidden' name='cmd' value='".($oForm->Value("_status")==0?"discharge":"admit")."_client' /><button onclick='clientDischargeToggle();submitForm(event);'>".($oForm->Value("_status")==0?"Discharge":"Admit")." Client</button></form>"
                     .($oForm->Value("_status")!=0?"<br />Client Discharged @ ".$oForm->Value("_updated"):"")
                     ."<br /><a href='".CATSDIR."therapist-dr?client_key=".$oForm->Value("_key")."'><button>Doctors Letter</button></a>"
                     ."<br /><button onclick='loadAsmtList(".$oForm->Value("_key").")'>Assessment Results</button>"
                     ."<br />".$this->getLinkedUser($oForm, self::createID(self::CLIENT,$oForm->Value('_key')))
                     //."<br />".($oForm->Value('P_email')?"<div id='credsDiv'><button onclick='sendcreds(event)'>Send Credentials</button></div>":"")
                     ."</div>"
                 ."</div>"
                 ."</div>";
                 $raOut['tabs']['tab2'] = ($oForm->Value("_status")==0?"<form id='clientForm' onSubmit='clinicHack(event);submitSidebarForm(event)'>":"")
                                         ."<input type='hidden' name='cmd' value='update_client'/>"
                                         .($oForm->Value('_key')?"<input type='hidden' name='client_key' id='clientId' value='{$oForm->Value('_key')}'/>":"")
                                         .$oForm->HiddenKey()
                                         ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
                                         ."Reason for Referal:".$oForm->TextArea("P_extra_referal")
                                         ."Challenges:".$oForm->TextArea("P_extra_challenges")
                                         ."Goals:".$oForm->TextArea("P_extra_goals")
                                         ."<input id='save-button' type='submit' value='Save' /></form>";
                 $raOut['tabNames'] = ['Client','Additional Info', 'Providers', 'Assessments'];
        }
        else{
            $sUnavailible = <<<Unavailable
                            <div style='text-align:center'>
                                <i class='fas fa-lock fa-7x'></i><br />
                                This section is only available once this client has been saved.
                            </div>
Unavailable;
            $raOut['tabs']['tab2'] = $sUnavailible;
            $raOut['tabs']['tab3'] = $sUnavailible;
            $raOut['tabs']['tab4'] = $sUnavailible;
            $raOut['tabNames'] = ['Client','<i class="fas fa-lock"></i> Additional Info', '<i class="fas fa-lock"></i> Providers', '<i class="fas fa-lock"></i> Assessments'];
        }
         return( $raOut );
    }

    private function parentsSeparateField(SEEDCoreForm $oForm){
        if($oForm->Value("parents_separate") && $oForm->Value("_key")){
            $this->drawFormRow("Parents Separate", "<input type='hidden' name='{$oForm->Name('parents_separate')}' value='0' /><input type='checkbox' value='1' name='{$oForm->Name('parents_separate')}' id='separateBox' onclick='parentsSeparate()' checked />");
            $this->beginRowDraw();
            $this->sForm = str_replace("[[style]]", "id='additionalAddress'", $this->sForm);
        }
        else{
            $this->drawFormRow("Parents Separate", "<input type='hidden' name='{$oForm->Name('parents_separate')}' value='0' /><input type='checkbox' value='1' name='{$oForm->Name('parents_separate')}' id='separateBox' onclick='parentsSeparate()' />");
            $this->beginRowDraw();
            $this->sForm = str_replace("[[style]]", "style='display:none' id='additionalAddress'", $this->sForm);
        }
        $this->drawPartialFormRow("Parent 2", $oForm->Text('P_extra_parents_name',"",array("attrs"=>"placeholder='2nd Parents Name'") ));
        $this->drawPartialFormRow( "", $oForm->Text('P_extra_address',"",array("attrs"=>"placeholder='Address'") ) );
        $this->drawPartialFormRow( "", $oForm->Text('P_extra_city',"",array("attrs"=>"placeholder='City'") ) );
        $this->drawPartialFormRow( "", $oForm->Text('P_extra_province',"",array("attrs"=>"placeholder='Province'") ) );
        $this->drawPartialFormRow( "", $oForm->Text('P_extra_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) );
        $this->endRowDraw();
    }
    
    private function schoolField( $value, $oForm )
    {
        $s = "<input type='checkbox' id='schoolBox' onclick='inSchool()' [[checked]] ".($oForm->Value('_status')==0?'':'disabled').">In School</input>
         <input type='text' style='display:[[display]]' name='[name]' id='schoolField' value='[[value]]' [[disabled]] required placeholder='School' />
         <input type='hidden' value='' id='schoolHidden' name='[name]' [[!disabled]] />";
        $s = str_replace("[[checked]]", ($value?"checked":""), $s);
        $s = str_replace(array("[[disabled]]","[[!disabled]]"), ($oForm->Value('_status')==0&&$value?array("","disabled"):array("disabled","")), $s);
        $s = str_replace("[[display]]", ($value?"block":"none"), $s);
        $s = str_replace("[[value]]", $value, $s);
        return $s;
    }

    private function drawFormRow( $label, $control )
    {
        $this->endRowDraw();
        $this->beginRowDraw();
        $this->sForm = str_replace(array("[[label]]","[[control]]"), array($label,$control), $this->sForm);
        $this->endRowDraw();
    }

    private function drawPartialFormRow( $label, $control ){
        if(strpos($this->sForm, "[[label]]") === false){
            $this->beginRowDraw();
        }
        else if(substr($this->sForm, strpos($this->sForm, "[[label]]")-strlen("<td class='col-md-5'>"),strlen("<td class='col-md-5'>")) != "<td class='col-md-5'>"){
            $this->sForm = str_replace(array("[[label]]","[[control]]"), array("<br />[[label]]","<br />[[control]]"), $this->sForm);
        }
        $this->sForm = str_replace(array("[[label]]","[[control]]"), array($label."[[label]]",$control."[[control]]"), $this->sForm);
    }
    
    private function beginRowDraw(){
         $this->sForm .= "<tr class='row' [[style]]>"
            ."<td class='col-md-5'>[[label]]</td>"
            ."<td class='col-md-7'>[[control]]</td>"
            ."</tr>";
    }
    
    private function endRowDraw(){
        $this->sForm = str_replace(array("[[label]]","[[control]]", "[[style]]"), "", $this->sForm);
    }
    
    private function drawProForm( KeyframeForm $oForm, $myClients, $raClients, $bTherapist )
    /*******************************************************************************
        The user clicked on a therapist / external provider's name so show their form
     */
    {
        $raOut = self::$raTemplate;
        $sClients = "<div style='padding:10px;border:1px solid #888'>Clients:<br/>";
        foreach( $myClients as $ra ) {
            if( $ra['fk_clients2'] && ($kfr = $this->oPeopleDB->GetKFR( self::CLIENT, $ra['fk_clients2'] )) ) {
                $sClients .= $kfr->Expand( "[[P_first_name]] [[P_last_name]]<br />" );
            }   
        }
        if($sClients == "<div style='padding:10px;border:1px solid #888'>Clients:<br/>"){
            $sClients .= "No Clients Connected";
        }
        $sClients .=
                 "</div>"
                ."<form onSubmit='event.preventDefault()'>"
                ."<input type='hidden' name='id' value='".self::createID(($bTherapist?self::INTERNAL_PRO:self::EXTERNAL_PRO), $oForm->GetKey())."'/>"
                ."<input type='hidden' name='cmd' value='link'/>"
                ."<input type='hidden' name='".($bTherapist?"add_internal_key":"add_external_key")."' value='".$oForm->GetKey()."'/>"
                .($oForm->Value('_key')?"<select name='add_client_key'><option value='0'> Choose a client</option>"
                .SEEDCore_ArrayExpandRows( $raClients, "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>")
                ."</select><input type='submit' value='add' onclick='submitForm(event)'></form>":"");
        
        $roles = self::$pro_roles_name;
        if($bTherapist){
            $roles = self::$staff_roles_name;
        }
        $myRole = $oForm->Value('pro_role');
        $myRoleIsNormal = in_array($myRole, $roles);
        $selRoles = "<select name='".$oForm->Name('pro_role')."' id='mySelect' onchange='doUpdateForm();'>";
        foreach ($roles as $role) {
            if( $role == $myRole || ($role == "Other" && !$myRoleIsNormal)) {
                $selRoles .= "<option selected />".$role;
            } else{
                $selRoles .= "<option />".$role;
            }
        }
        $selRoles .= "</select>"
                    ."<input type='text' ".($myRoleIsNormal?"style='display:none' disabled ":"")
                    ."required id='other' name='".$oForm->Name('pro_role')."' maxlength='200' "
                        ."value='".($myRoleIsNormal?"":SEEDCore_HSC($myRole))."' placeholder='Role' />";

        $raExtra = SEEDCore_ParmsURL2RA( $oForm->Value('P_extra') );
        $oForm->SetValue( 'P_extra_credentials', @$raExtra['credentials'] );
        $oForm->SetValue( 'P_extra_regnumber', @$raExtra['regnumber'] );

        $oForm->SetStickyParms( array( 'raAttrs' => array( 'maxlength'=>'200', 'style'=>'width:100%' ) ) );
        $this->sForm =
              "<form onSubmit='clinicHack(event);submitSidebarForm(event);'>"
                  .($bTherapist ? (($oForm->Value('_key')?"<input type='hidden' name='therapist_key' id='therapistId' value='{$oForm->Value('_key')}'/>":"")
                             ."<input type='hidden' name='cmd' value='update_therapist'/>"
                                 .(($oForm->Value('_key')?($this->clinics->isCoreClinic() ? "<p>Therapist # {$oForm->Value('_key')}</p>":""):"New Therapist")
                                 ))
                                 : (($oForm->Value('_key')?"<input type='hidden' name='pro_key' id='proId' value='{$oForm->Value('_key')}'/>":"")
                             ."<input type='hidden' name='cmd' value='update_pro'/>"
                                 .($oForm->Value('_key')?($this->clinics->isCoreClinic() ? "<p>Provider # {$oForm->Value('_key')}</p>":""):"New Professional")
                           ))
             .$oForm->HiddenKey()
             .(isset($_SESSION['newLinks']['client_key'])?"<input type='hidden' name='linkClient' value='{$_SESSION['newLinks']['client_key']}' />":"")
             ."<table class='container-fluid table table-striped table-sm sidebar-table'>";
             $this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name' autofocus") ) );
             $this->drawPartialFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) );
             $this->drawPartialFormRow( "", "<span id='name-exists'>A provider with this name already exists.</span>" );
             $this->drawFormRow( "Address", $oForm->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) );
             $this->drawFormRow( "City", $oForm->Text('P_city',"",array("attrs"=>"placeholder='City'") ) );
             $this->drawFormRow( "Province", $oForm->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) );
             $this->drawFormRow( "Postal Code", $oForm->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) );
             $this->drawFormRow( "Phone Number", $oForm->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' maxlength='200'") ) );
             $this->drawFormRow( "Fax Number", $oForm->Text('fax_number', "", array("attrs"=>"placeholder='Fax Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) );
             $this->drawFormRow( "Email", $oForm->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) );
             $this->drawFormRow( "Pronouns", $this->getPronounList($oForm) );
             $this->drawFormRow( "Role", $selRoles );
             if($bTherapist){
                $this->drawFormRow( "Credentials", $oForm->Text('P_extra_credentials',"",array("attrs"=>"placeholder='To be shown after name'")));
                $this->drawFormRow( "Registration number", $oForm->Text('P_extra_regnumber',"",array("attrs"=>"placeholder='Registration number'")));
                $this->drawFormRow( "Rate","<input type='number' name='".$oForm->Name('rate')."' value='".$oForm->ValueEnt('rate')."' placeholder='Hourly rate' step='1' min='0' />" );
             }
             $this->drawFormRow("Organization", $oForm->Text("organization","",array("attrs"=>"placeholder='Organization'")));
             $this->drawFormRow( "Clinic", $this->getClinicList($oForm) );
             if($bTherapist){
                 $this->drawPartialFormRow("Signature", "<img src='data:image/jpg;base64,".base64_encode($oForm->Value("signature"))."' style='width:100%;padding-bottom:2px' />");
                 $this->drawPartialFormRow("", "<input type=\"file\" name=\"new_signature\" accept='.jpg' />");
             }
             $this->endRowDraw();
             $this->sForm .= "<tr class='row'>"
                ."<td class='col-md-12'><input id='save-button' type='submit' name='action' value='Save' />"
                ."<input id='save-close-button' type='submit' name='action' value='Save and Close' /></td>"
             ."</tr>"
             ."</table>"
             ."</form>";

        $raOut['header'] = "<h3>".($bTherapist ? "CATS Staff" : "External Provider")." : ".$oForm->Value('P_first_name')." ".$oForm->Value('P_last_name')."</h3>";
        $raOut['tabs']['tab1'] = $this->sForm;
        
         if(isset($_SESSION['newLinks']['client_key']) && $kfr = $this->oPeopleDB->GetKFR( self::CLIENT, $_SESSION['newLinks']['client_key'] )){
             
             $sSidebar = "<div style='padding:10px;border:1px solid #888'>Clients that will be connected:<br/>"
                            .$kfr->Expand( "<span>[[P_first_name]] [[P_last_name]]</span><br />" )
                        ."</div>";
             $raOut['tabs']['tab2'] = $sSidebar;
         }
         if($oForm->Value("_key")){
             $raOut['tabs']['tab2'] = $sClients;
             $raOut['tabs']['tab3'] = $this->getLinkedUser($oForm,($bTherapist?self::INTERNAL_PRO.$this->therapist_key:self::EXTERNAL_PRO.$this->pro_key));
             $raOut['tabNames'] = ['Provider', 'Clients', 'Linked Account'];
         }
         else{
             $sUnavailible = <<<Unavailable
                            <div style='text-align:center'>
                                <i class='fas fa-lock fa-7x'></i><br />
                                This section is only available once this [[role]] has been saved.
                            </div>
Unavailable;
             $sUnavailible = str_replace("[[role]]", $bTherapist?"staff member":"provider", $sUnavailible);
             $raOut['tabs']['tab2'] = $sUnavailible;
             $raOut['tabs']['tab3'] = $this->getLinkedUser($oForm,($bTherapist?self::INTERNAL_PRO.$this->therapist_key:self::EXTERNAL_PRO.$this->pro_key));
             if($raOut['tabs']['tab3']){
                 $raOut['tabs']['tab3'] = $sUnavailible;
             }
             $raOut['tabNames'] = ['Provider', '<i class="fas fa-lock"></i> Clients', '<i class="fas fa-lock"></i> Linked Account'];
         }
         return( $raOut );
    }

    private function getClinicList( $oForm)
    {
        $clinicId = $oForm->Value("clinic");
        $s = "<select id='".$oForm->Name('clinic')."' name='".$oForm->Name('clinic')."' ".($this->clinics->isCoreClinic()&&$oForm->Value('_status')==0?"":"disabled ").">";
        $raClinics = $this->oClinicsDB->KFRel()->GetRecordSetRA("");
        foreach($raClinics as $clinic){
            $sSelected = (($oForm->Value("_key") == 0 && $this->clinics->GetCurrentClinic() == $clinic['_key']) || $clinicId == $clinic['_key']) ? " selected" : "";
            $s .= "<option$sSelected value='{$clinic['_key']}'>{$clinic['clinic_name']}</option>";
        }
        $s .= "</select>";
        return $s;
    }

    private function getPronounList(KeyframeForm $oForm){

        $pronouns = array("M" => "He/Him/His", "F" => "She/Her/Her", "O" => "They/Them/Their");
        $s = "<select name='".$oForm->Name("P_pronouns")."' required ".($oForm->Value('_status')==0?"":"disabled").">";
        $s .= "<option value=''>Select Pronouns</option>";
        foreach($pronouns as $key => $name){
            if($oForm->Value("P_pronouns") == $key){
                $s .= "<option value='$key' selected >$name</option>";
            }
            else{
                $s .= "<option value='$key' >$name</option>";
            }
        }
        $s .= "</select>";
        return $s;
    }

    private function getLinkedUser(KeyframeForm $oForm, String $key):String{
        if(!CATS_SYSADMIN){
            // At this time to prevent confusion only show this form to System Admins, who likely know what it means
            return "";
        }
        $sUser = "<div style='padding:10px;border:1px solid #888'>Linked Account: [[account]]<br/>";
        if($this->oApp->sess->CanAdmin('admin')){ // Keep in case a non-admin sees this form. This will prevent them from makeing changes
            $sUser .= "<form onSubmit='event.preventDefault()'>"
                     ."<input type='hidden' name='cmd' value='linkAccount'/>"
                     ."<input type='hidden' name='key' value='$key'/>"
                     ."<input type='hidden' name='people_id' value='{$oForm->Value('P__key')}'/>"
                     ."<select name='newAccount' id='newAccount' class='noAccount' onChange='updateAccountStyle()'>"
                     ."<option value='0'>No Account</option>";
             //TODO Improve system
             $users = $this->kfdb->QueryRowsRA("SELECT * from SEEDSession_Users WHERE _key != {$this->oApp->sess->GetUID()} AND eStatus = 'ACTIVE'",KEYFRAMEDB_RESULT_ASSOC);
             $sUser .= SEEDCore_ArrayExpandRows($users, "<option value='[[_key]]'>[[realname]]</option>")
                     ."</select>"
                     ."<br /><input type='submit' value='Link' onclick='submitForm(event)' />";
        }
        $sUser .= "</div>";
        
        $account = ($raUser = $this->oApp->sess->oDB->GetUserInfo($oForm->Value('P_uid'),false,true)[1]) ? $raUser['realname'] : "";
        
        return str_replace("[[account]]", ($account?:"No Account"), $sUser);
        
    }
    
    private function uploadSpreadsheet()
    /***********************************
        Insert or update client / staff / providers from uploaded file
     */
    {
        $s = $sErr = "";

        $raRows = array();

        $f = @$_FILES['uploadfile'];
        if( $f && !@$f['error'] ) {
            $raSheets = Therapist_ClientList_LoadXLSX( $this->oApp, $f['tmp_name'] );

            /* There should be 3 arrays of arrays of rows: Clients, Staff, Providers
             */
            $nClients = $nStaff = $nProviders = 0;
            foreach( $raSheets as $sheetName => $raRows ) {
                if( $sheetName == 'Clients' ) {
                    foreach( $raRows as $ra ) {
                        if( $ra[0] &&
                            ($kfrC = $this->oPeopleDB->GetKFR( self::CLIENT, $ra[0])) &&
                            ($kfrP = $this->oPeopleDB->GetKFR( 'P', $kfrC->Value('fk_people'))) )
                        {
                            $kfrP->SetValue( 'first_name', $ra[1] );
                            $kfrP->SetValue( 'last_name',  $ra[2] );
                            $kfrP->SetValue( 'address',    $ra[3] );
                            $kfrP->SetValue( 'city',       $ra[4] );
                            $kfrP->PutDBRow();
                            ++$nClients;
                        }
                    }
                }
            }
            $s = "<div style='clear:both' class='alert alert-success'>Updated $nClients clients, $nStaff staff, $nProviders providers</div>";

        } else {
            $sErr = "The upload was not successful. ";
            if( $f['size'] == 0 ) {
                $sErr .= "No file was uploaded.  Please try again.";
            } else if( !isset($f['error']) ) {
                $sErr .= "No error was recorded.  Please tell Bob.";
            } else {
                $sErr .= "Please tell Bob that error # ${f['error']} was reported.";
            }
        }

        if( $sErr ) $s = "<div style='clear:both' class='alert alert-danger'>$sErr</div>";

        return( $s );
    }
    
    /**
     * Get array of clients that the user has access to see
     * @param int $status - status of the clients, -1 to get all a users clients
     * @param array $raParms - additional params to pass to oPeopleDB->GetList
     * @return array containing client data (Similar to oPeopleDB->GetList())
     */
    private function getMyClientsInternal(int $status = 0,array $raParms = array()):array{
        $condClinic = $this->clinics->isCoreClinic() ? "" : ("clinic = ".$this->clinics->GetCurrentClinic());
        $raClients = $this->oPeopleDB->GetList(self::CLIENT, $condClinic, array_merge($this->queryParams,array_merge(array("iStatus" => $status),$this->queryParams,$raParms)));
        $raOut = array();
        foreach ($raClients as $ra){
            if(ClientsAccess::getAccess() > ClientsAccess::LIMITED || $this->oApp->kfdb->Query1("SELECT C._key FROM clientsxpros as C, pros_internal as S, people as P WHERE C._status = 0 and C.fk_clients2 = {$ra['_key']} and C.fk_pros_internal = S._key and S.fk_people = P._key and P.uid = {$this->oApp->sess->GetUID()}")){
                array_push($raOut, $ra);
            }
        }
        return $raOut;
    }
    
    /**
     * Get array of clients that the user has access to see
     * @param int $status - status of the clients, -1 to get all a users clients
     * @param array $raParms - additional params to pass to oPeopleDB->GetList
     * @return array containing client data (Similar to oPeopleDB->GetList())
     */
    public function getMyClients(int $status = 0,array $raParms = array()):array{
        $access = ClientsAccess::getAccess();
        ClientsAccess::getAccess(true);
        $ra = $this->getMyClientsInternal($status,$raParms);
        ClientsAccess::getAccess(true,$access);
        return $ra;
    }
    
    /**
     * Convert a string ID to a record type and key
     * @param String $id - Id to parse
     */
    public static function parseID(String $id ):array{
        $type = substr($id, 0,strcspn($id, "1234567890"));
        $key = substr($id, strcspn($id, "1234567890"));
        if($type == self::CLIENT || $type == self::INTERNAL_PRO || $type == self::EXTERNAL_PRO){
            return array($type,$key);
        }
        return array();
    }
    
    /**
     * Convert a record type and key to a string ID
     * @param String $type - type of record the key is for
     * @param int $key - key unique to the record of the type specified
     */
    public static function createID(String $type,int $key):String{
        if($type == self::CLIENT || $type == self::INTERNAL_PRO || $type == self::EXTERNAL_PRO){
            return $type.$key;
        }
        return "";
    }
    
}

/**
 * This class handles users access with respect to clients
 * @author Eric
 *
 */
class ClientsAccess {
    
    //Variable to cache constants
    private static $constants = NULL;
    
    /* The numaric value of each access level is key for inheritance. eg. Full access inherits functions from leader access
     * permissions can be checked by useing >= X to check if a user has at least X access
     * See client case in drawList for an example of this.
     */
    
    /**
     * System will determine users access rights
     */
    public const QUERY = -1;
    
    /**
     * User can only see their connected clients.
     * Note: This is the default level for a user
     * Note 2: Attmpting to force a users access level to an improper level will revert their access to this level
     */
    public const LIMITED = 0;
    
    /**
     * User is an office staff and can see all clients in the clinic.
     * NOTE: This level unlockes the download as selector in the filing cabinet
     */
    public const OFFICE = 1;
    
    /**
     * User is the clinic leader and can see all clients in the clinic
     * Note: Users who lead a clinic are granted this level for the clinics they lead. Otherwise they revert to Limited access
     * Note 2: This level unlocks the toggle for the therapist view.
     */
    public const LEADER = 2;
    
    /**
     * User has access to core and therfore can see all clients
     * Note: This level takes effect everywhere.
     * Note 2: This level inherits features from Leader access and Office access
     */
    public const FULL = 3;
    
    // Cache of user access
    private static $access = null;
    
    public static function QueryAccess(){
        global $oApp;
        self::init();
        $clinics = new Clinics($oApp);
        $manageUsers = new ManageUsers($oApp);
        $access = self::LIMITED;
        if(in_array(Clinics::CORE, array_column($clinics->GetUserClinics(),'Clinics__key'))){
            // User can see Core, give them full access
            $access = self::FULL;
        }
        else if(in_array($clinics->GetCurrentClinic(), $clinics->getClinicsILead())){
            $access = self::LEADER;
        }
        else if($manageUsers->getClinicRecord($oApp->sess->GetUID())->Value('pro_role') == ClientList::$staff_roles_name['Office_Staff']){
            $access = self::OFFICE;
        }
        return $access;
    }
    
    /**
     * Get a users access rights
     * @param bool $force - force the system to revalidate access rights for a user. default false
     * @param int $access - when force is true force the access rights of the user to this value
     * @return int - access rights constant dictating the users access
     */
    public static function getAccess(bool $force = false, int $access = self::QUERY):int{
        self::init();
        if ((self::$access === null && $access == self::QUERY) || ($force && $access == self::QUERY)){
            self::$access = self::QueryAccess();
        }
        else if ($force && in_array($access, self::$constants)){
            self::$access = $access;
        }
        else if($force){
            // Force was true but $access was not valid
            self::$access = self::LIMITED;
        }
        return self::$access;
    }
    
    /**
     * Initialize the $constants variable for convienent checking if $access is valid in getAccess()
     * only initialized once regardless of number of times called
     */
    private static function init(){
        if(self::$constants == NULL){
            $refl = new ReflectionClass(ClientsAccess::class);
            self::$constants = $refl->getConstants();
        }
    }
    
}

?>
