<?php
require_once "client-modal.php" ;
require_once 'Clinics.php';
require_once "therapist-clientlistxls.php";
require_once 'client_code_generator.php';

class ClientList
{
    private $oApp;
    public $kfdb;

    public $oPeopleDB, $oClinicsDB;

    private $pro_fields    = array("P_first_name","P_last_name","pro_role","P_address","P_city","P_postal_code","P_phone_number","fax_number","P_email");
    //map of computer keys to human readable text
    public $pro_roles_name = array("GP"=>"GP","Paediatrician"=>"Paediatrician", "Psychologist"=>"Psychologist", "SLP"=>"SLP", "PT"=>"PT", "OT"=>"OT", "Specialist_Dr"=>"Specialist Dr", "Resource_Teacher"=>"Resource Teacher", "Teacher_Tutor"=>"Teacher/Tutor", "Other"=>"Other");

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
        $s = "<style>
                .client-normal{
                    display:".(@$this->oApp->sess->VarGet("clientlist-normal") || @$this->oApp->sess->VarGet("clientlist-normal") === NULL?"block":"none").";
                }
                .client-discharged{
                    display:".(@$this->oApp->sess->VarGet("clientlist-discharged")?"block":"none").";
                }
                .client-discharged a{
                    color: #007bffa8;
                }
              </style>
             ";

        $s .= "<div style='clear:both;float:right; border:1px solid #aaa;border-radius:5px;padding:10px'>"
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

        $oFormClient    = new KeyframeForm( $this->oPeopleDB->KFRel("C"), "A", array("fields"=>array("parents_separate"=>array("control"=>"checkbox"))));
        $oFormTherapist = new KeyframeForm( $this->oPeopleDB->KFRel("PI"), "A" );
        $oFormPro       = new KeyframeForm( $this->oPeopleDB->KFRel("PE"), "A" );

        // Put this before the GetClients call so the changes are shown in the list
        $cmd = SEEDInput_Str('cmd');
        $overrideCheck = FALSE;
        if(isset($_SESSION["cmdData"])){
            $type = SEEDInput_Str("type");
            switch ($type){
                case "C":
                    $kfrelID = $type;
                    $type = "client";
                    break;
                case "PI":
                    $kfrelID = $type;
                    $type = "therapist";
                    break;
                case "PE":
                    $kfrelID = $type;
                    $type = "pro";
                    break;
                case "client":
                    $kfrelID = "C";
                    break;
                case "therapist":
                    $kfrelID = "PI";
                    break;
                case "pro":
                    $kfrelID = "PE";
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
                            break;
                        case "pro":
                            $oFormPro->SetKFR($kfr);
                            break;
                    }
                    unset($_SESSION["cmdData"]);
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
                            break;
                        case "pro":
                            $oFormPro->SetKFR($kfr);
                            break;
                    }
                    unset($_SESSION["cmdData"]);
                    break;
                case "keep":
                default:
                    unset($_SESSION["cmdData"]);
                    break;
            }
        }

        $existsWarning = <<<ExistsWarning
        <div class='alert alert-warning' style='width: 85%;justify-content: space-around;'>
            A [[type]] with this name is already exists in this clinic<br />
            <div style='justify-content: space-around;display: flex'>
                <form style='display:inline'>
                    <input type='hidden' name='cmd' value='overwrite' />
                    <input type='hidden' name='type' value='[[type]]' />
                    <input type='submit' value='Replace:' />
                    <select name='key'>
                        [[options]]
                    </select>
                </form>
                <a href='?cmd=keep'><button>Use Original</button></a>
                <a href='?cmd=new&type=[[type]]'><button>Save as new [[type]]</button></a>
            </div>
        </div>
ExistsWarning;

        switch( $cmd ) {
            case "update_client":
                $exists = $this->checkExists($oFormClient, "C");
                if($exists && !$overrideCheck){
                    $_SESSION["cmdData"] = $oFormClient->GetKFR()->ValuesRA();
                    $options = "";
                    $ra = $this->oPeopleDB->GetList("C", "P.first_name='".$oFormClient->Value("P_first_name")."' AND P.last_name='".$oFormClient->Value("P_last_name")."' AND clinic=".$oFormClient->Value("clinic"),array_merge($this->queryParams,array("iStatus" => -1)));
                    foreach ($ra as $option){
                        $options .= "<option value='".$option['_key']."'>".$this->oCCG->getClientCode($option['_key']).($option['_status'] == 1?"(Deleted)":"").($option['_status'] == 2?"(Discharged)":"")."</option>";
                    }
                    $s .= str_replace(array("[[type]]","[[options]]"), array("client",$options), $existsWarning);
                }
                else{
                    $oFormClient->Update();
                    $this->updatePeople( $oFormClient );
                    $this->client_key = $oFormClient->GetKey();
                    if($oFormClient->Value("P_first_name") && $oFormClient->Value("P_last_name")){
                        // Only create client code once first and last name are set
                        $this->oCCG->getClientCode($this->client_key);
                    }
                }
                break;
            case "discharge_client":
                $kfr = $this->oPeopleDB->GetKFR("C", $this->client_key);
                $kfr->StatusSet("Hidden");
                $kfr->PutDBRow();
                $s .= "<div class='alert alert-success alert-dismissible'>Client Discharged</div>";
                break;
            case "admit_client":
                $kfr = $this->oPeopleDB->GetKFR("C", $this->client_key);
                $kfr->StatusSet("Normal");
                $kfr->PutDBRow();
                $s .= "<div class='alert alert-success alert-dismissible'>Client Admitted</div>";
                break;
            case "regenerate_client_code":
                /* WARNING this will overwrite the existing code.
                 * This action should only be preformed by a developer
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
                $exists = $this->checkExists($oFormTherapist, "PI");
                if($exists && !$overrideCheck){
                    $_SESSION["cmdData"] = $oFormTherapist->GetKFR()->ValuesRA();
                    $options = "";
                    $ra = $this->oPeopleDB->GetList("PI", "P.first_name='".$oFormTherapist->Value("P_first_name")."' AND P.last_name='".$oFormTherapist->Value("P_last_name")."' AND clinic=".$oFormTherapist->Value("clinic"),$this->queryParams);
                    foreach ($ra as $option){
                        $options .= "<option value='".$option['_key']."'>".$option["P_first_name"]." ".$option["P_last_name"]."(".$option["_key"].")</option>";
                    }
                    $s .= str_replace(array("[[type]]","[[options]]"), array("therapist",$options), $existsWarning);
                }
                else{
                    $oFormTherapist->Update();
                    $this->updatePeople( $oFormTherapist );
                    $this->therapist_key = $oFormTherapist->GetKey();
                }
                break;
            case "update_pro":
                $exists = $this->checkExists($oFormPro, "PE");
                if($exists && !$overrideCheck){
                    $_SESSION["cmdData"] = $oFormPro->GetKFR()->ValuesRA();
                    $options = "";
                    $ra = $this->oPeopleDB->GetList("PE", "P.first_name='".$oFormPro->Value("P_first_name")."' AND P.last_name='".$oFormPro->Value("P_last_name")."' AND clinic=".$oFormPro->Value("clinic"),$this->queryParams);
                    foreach ($ra as $option){
                        $options .= "<option value='".$option['_key']."'>".$option["P_first_name"]." ".$option["P_last_name"]."(".$option["_key"].")</option>";
                    }
                    $s .= str_replace(array("[[type]]","[[options]]"), array("pro",$options), $existsWarning);
                }
                else{
                    $oFormPro->Update();
                    $this->updatePeople( $oFormPro );
                    $this->pro_key = $oFormPro->GetKey();
                }
                break;
            case "link":
                $kfr = $this->oPeopleDB->KFRel("CX")->CreateRecord();
                $kfr->SetValue("fk_pros_external", SEEDInput_Int("add_external_key"));
                $kfr->SetValue("fk_clients2", SEEDInput_Int("add_client_key"));
                $kfr->SetValue("fk_pros_internal", SEEDInput_Int("add_internal_key"));
                $kfr->PutDBRow();
                break;
            case 'uploadxls':
                $s .= $this->uploadSpreadsheet();
                break;
        }

        $clientPros = array();
        $proClients = array();
        $myPros = array();
        $myClients = array();

        $sNew = "";

        if($this->client_key == -1){
            $this->client_key = 0;
            $oFormClient->SetKFR($this->oPeopleDB->KFRel("C")->CreateRecord());
            $sNew = "client";
        }
        elseif ($this->therapist_key == -1){
            $this->therapist_key = 0;
            $oFormTherapist->SetKFR($this->oPeopleDB->KFRel("PI")->CreateRecord());
            $sNew = "therapist";
        }
        elseif ($this->pro_key == -1){
            $this->pro_key = 0;
            $oFormPro->SetKFR($this->oPeopleDB->KFRel("PE")->CreateRecord());
            $sNew = "pro";
        }

        /* Set the form to use the selected client.
         */
        if( $this->client_key && ($kfr = $this->oPeopleDB->GetKFR("C", $this->client_key )) ) {
            $oFormClient->SetKFR( $kfr );
            // A client has been clicked. Who are their pros?
            $myPros = $this->oPeopleDB->GetList('CX', "fk_clients2='{$this->client_key}'");
        }
        if( $this->therapist_key && ($kfr = $this->oPeopleDB->GetKFR("PI", $this->therapist_key )) ) {
            $oFormTherapist->SetKFR( $kfr );
            // A therapist has been clicked. Who are their clients?
            $myClients = $this->oPeopleDB->GetList('CX', "fk_pros_internal='{$this->therapist_key}'" );
        }
        if( $this->pro_key && ($kfr = $this->oPeopleDB->GetKFR("PE", $this->pro_key )) ) {
            $oFormPro->SetKFR( $kfr );
            // A pro has been clicked. Who are their clients?
            $myClients = $this->oPeopleDB->GetList('CX', "fk_pros_external='{$this->pro_key}'" );
        }

        $condClinic = $this->clinics->isCoreClinic() ? "" : ("clinic = ".$this->clinics->GetCurrentClinic());
        $raClients    = $this->oPeopleDB->GetList('C', $condClinic, array_merge($this->queryParams,array("iStatus" => -1)));
        $raTherapists = $this->oPeopleDB->GetList('PI', $condClinic, $this->queryParams);
        $raPros       = $this->oPeopleDB->GetList('PE', $condClinic, $this->queryParams);

        $s .= "<div style='clear:both' class='container-fluid'><div class='row'>"
             ."<div class='col-md-4'>"
                 ."<h3>Clients</h3>"
                 ."<button onclick=\"window.location.href='?client_key=-1'\">Add Client</button><br />"
                 ."<form id='filterForm' action='".CATSDIR."jx.php' style='display:inline'>
                    <input type='checkbox' name='clientlist-normal' value='checked' ".(@$this->oApp->sess->VarGet("clientlist-normal") || @$this->oApp->sess->VarGet("clientlist-normal") === NULL?"checked":"").">Normal</input>
                    <input type='checkbox' name='clientlist-discharged' value='checked' ".(@$this->oApp->sess->VarGet("clientlist-discharged")?"checked":"").">Discharged</input>
                    <input type='hidden' name='cmd' value='therapist-clientList-sort' />
                    <button onclick='filterClients(event);'>Filter</button>
                   </form>"
                 .SEEDCore_ArrayExpandRows( $raClients, "<div id='client-[[_key]]' class='client-[[_status]]' style='padding:5px;'><a href='?client_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a>%[[clinic]]</div>" )
             ."</div>"
             ."<div class='col-md-4'>"
                 ."<h3>CATS Staff</h3>"
                 ."<button onclick=\"window.location.href='?therapist_key=-1'\">Add Staff Member</button>"
                 .SEEDCore_ArrayExpandRows( $raTherapists, "<div id='therapist-[[_key]]' style='padding:5px;'><a href='?therapist_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a> is a [[pro_role]]%[[clinic]]</div>" )
             ."</div>"
             ."<div class='col-md-4'>"
                 ."<h3>External Providers</h3>"
                 ."<button onclick=\"window.location.href='?pro_key=-1'\">Add External Provider</button>"
                 .SEEDCore_ArrayExpandRows( $raPros, "<div id='pro-[[_key]]' style='padding:5px;'><a href='?pro_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a> is a [[pro_role]]%[[clinic]]</div>" )
             ."</div>"
             ."</div></div>"
             ."<style>"
                 ."#client-{$this->client_key}, #therapist-{$this->therapist_key}, #pro-{$this->pro_key} "
                     ." { font-weight:bold;color:green;background-color:#dfd; }"
             ."</style>"
             .$this->filterJS();

             //fix up status classes
             $s = str_replace(array("class='client-0'","class='client-2'"), array("class='client-normal'","class='client-discharged'"), $s);

             $s .= "<div class='container'><div class='row'>";
             if( $this->client_key || $sNew == "client") {
                 $s .= $this->drawClientForm( $oFormClient, $myPros, $raPros );
             }
             if( $this->therapist_key || $sNew == "therapist") {
                 $s .= $this->drawProForm( $oFormTherapist, $myClients, $raClients, true );
             }
             if( $this->pro_key || $sNew == "pro") {
                 $s .= $this->drawProForm( $oFormPro, $myClients, $raClients, false );
             }
             $s .= "</div></div>";

             foreach($this->oClinicsDB->KFRel()->GetRecordSetRA("") as $clinic){
                 if($this->clinics->isCoreClinic()){
                     $s = str_replace("%".$clinic['_key'], " @ the ".$clinic['clinic_name']." clinic", $s);
                 }
                 else {
                     $s = str_replace("%".$clinic['_key'], "", $s);
                 }
             }
        return( $s );
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

    function drawClientForm( $oForm, $myPros, $raPros )
    /**************************************************
        The user clicked on a client name so show their form
     */
    {
        $s = "";

        $sTherapists = "<div style='padding:10px;border:1px solid #888'>";
        $sPros       = "<div style='padding:10px;border:1px solid #888'>";
        foreach( $myPros as $ra ) {
            if( $ra['fk_pros_internal'] && ($kfr = $this->oPeopleDB->GetKFR( 'PI', $ra['fk_pros_internal'] )) ) {
                $sTherapists .= $kfr->Expand( "[[P_first_name]] [[P_last_name]] is my [[pro_role]]<br />" );
            }
            if( $ra['fk_pros_external'] && ($kfr = $this->oPeopleDB->GetKFR( 'PE', $ra['fk_pros_external'] )) ) {
                $sPros .= $kfr->Expand( "[[P_first_name]] [[P_last_name]] is my [[pro_role]]<br />" );
            }
        }
        $sTherapists .= "</div>";
        $sPros       .= "</div>".($oForm->Value('_key')?drawModal($oForm->GetValuesRA(), $this->oPeopleDB, $this->pro_roles_name ):"");

        $oForm->SetStickyParms( array( 'raAttrs' => array( 'maxlength'=>'200', 'style'=>'width:100%' ) ) );
        $age = date_diff(date_create($oForm->Value("P_dob")), date_create('now'))->format("%y Years %m Months");
        $this->sForm =
              "<form>"
             ."<input type='hidden' name='cmd' value='update_client'/>"
             .($oForm->Value('_key')?"<input type='hidden' name='client_key' id='clientId' value='{$this->client_key}'/>":"")
             .$oForm->HiddenKey()
             ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
             .($oForm->Value('_key')?($this->clinics->isCoreClinic()?"<p>Client # {$this->client_key}</p>":""):"<p>New Client</p>")
             ."<table class='container-fluid table table-striped table-sm'>";
             $this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name' autofocus") ) );
             $this->drawFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) );
             $this->drawFormRow( "Pronouns", $this->getPronounList($oForm));
             $this->drawFormRow( "Parents Name", $oForm->Text('parents_name',"",array("attrs"=>"placeholder='Parents Name'") ) );
             $this->drawFormRow( "Parents Separate", $oForm->Checkbox('parents_separate') );
             $this->drawFormRow( "Address", $oForm->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) );
             $this->drawFormRow( "City", $oForm->Text('P_city',"",array("attrs"=>"placeholder='City'") ) );
             $this->drawFormRow( "Province", $oForm->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) );
             $this->drawFormRow( "Postal Code", $oForm->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) );
             $this->drawFormRow( "School" , str_replace("[name]", $oForm->Name("school"), $this->schoolField($oForm->Value("school"))));
             $this->drawPartialFormRow( "Date Of Birth", $oForm->Date('P_dob',"",array("attrs"=>"style='border:1px solid gray'")) );
             $this->drawPartialFormRow( "Age", $age);
             $this->drawFormRow( "Phone Number", $oForm->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' maxlength='200'") ) );
             $this->drawFormRow( "Email", $oForm->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) );
             $this->drawFormRow( "Clinic", $this->getClinicList($oForm) );
             $this->drawFormRow( "Code", ($oForm->Value('_key')?$this->oCCG->getClientCode($oForm->Value('_key')):"Code generated once first and last name are set"));
             $this->endRowDraw();
             $this->sForm .= "<tr class='row'>"
                ."<td class='col-md-12'><input type='submit' value='Save' style='margin:auto' /></td>"
             ."</tr>"
             ."<tr class='row'>"
                 .($oForm->Value('P_email')
                     ?"<td class='col-md-12'><div id='credsDiv'><button onclick='sendcreds(event)'>Send Credentials</button></div></td>":"")
             ."</tr>"
             ."</tr>"
             ."</table>"
             ."<script>"
                 ."function sendcreds(e){
                     e.preventDefault();
                     var credsDiv = document.getElementById('credsDiv');
                     var cid = document.getElementById('clientId').value;
                     $.ajax({
                         type: 'POST',
                         data: { cmd: 'therapist---credentials', client: cid },
                         url: 'jx.php',
                         success: function(data, textStatus, jqXHR) {
                             var jsData = JSON.parse(data);
                             var sSpecial = jsData.bOk ? jsData.sOut : 'Failed to send Email';
                             credsDiv.innerHTML =  sSpecial;
                         },
                         error: function(jqXHR, status, error) {
                             console.log(status + \": \" + error);
                             debugger;
                         }
                     });
                 }
               </script>"
             ."</form>";

        $s .= "<div class='container-fluid' style='border:1px solid #aaa;padding:20px;margin:20px'>"
             ."<h3>Client : ".$oForm->Value('P_first_name')." ".$oForm->Value('P_last_name')."</h3>"
             ."<div class='row'>"
                 ."<div class='col-md-8'>".$this->sForm."</div>"
                 ."<div class='col-md-4'>".$sTherapists.$sPros
                 ."<br /><br />"
                 .($oForm->Value("_key")?"<button onclick=\"window.location='?cmd=".($oForm->Value("_status")==0?"discharge":"admit")."_client&client_key=".$oForm->Value("_key")."';event.preventDefault();\">".($oForm->Value("_status")==0?"Discharge":"Admit")." Client</button>":"")
                 ."</div>"
             ."</div>"
             ."</div>";
             $s .= $this->clinicJS($oForm);
         return( $s );
    }

    private function schoolField( $value )
    {
        $s = "<input type='checkbox' id='schoolBox' onclick='inSchool()' [[checked]]>In School</input>
         <input type='text' style='display:[[display]]' name='[name]' id='schoolField' value='[[value]]' [[disabled]] required placeholder='School' />
         <input type='hidden' value='' id='schoolHidden' name='[name]' [[!disabled]] />
         <script>
	       function inSchool() {
		      var checkBox = document.getElementById('schoolBox');
		      var text = document.getElementById('schoolField');
              var hidden = document.getElementById('schoolHidden');
		      if (checkBox.checked == true){
			     text.style.display = 'block';
			     text.disabled = false;
                 hidden.disabled = true;
		      } else {
			     text.style.display = 'none';
			     text.disabled = true;
                 hidden.disabled = false;
		      }
           }
         </script>";
        $s = str_replace("[[checked]]", ($value?"checked":""), $s);
        $s = str_replace(array("[[disabled]]","[[!disabled]]"), ($value?array("","disabled"):array("disabled","")), $s);
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
        else{
            $this->sForm = str_replace(array("[[label]]","[[control]]"), array("<br />[[label]]","<br />[[control]]"), $this->sForm);
        }
        $this->sForm = str_replace(array("[[label]]","[[control]]"), array($label."[[label]]",$control."[[control]]"), $this->sForm);
    }
    
    private function beginRowDraw(){
         $this->sForm .= "<tr class='row' [[style]]>"
            ."<td class='col-md-5'><p>[[label]]</p></td>"
            ."<td class='col-md-7'>[[control]]</td>"
            ."</tr>";
    }
    
    private function endRowDraw(){
        $this->sForm = str_replace(array("[[label]]","[[control]]", "[[style]]"), "", $this->sForm);
    }
    
    function drawProForm( SEEDCoreForm $oForm, $myClients, $raClients, $bTherapist )
    /*******************************************************************************
        The user clicked on a therapist / external provider's name so show their form
     */
    {
        $s = "";

        $sClients = "<div style='padding:10px;border:1px solid #888'>Clients:<br/>";
        foreach( $myClients as $ra ) {
            if( $ra['fk_clients2'] && ($kfr = $this->oPeopleDB->GetKFR( 'C', $ra['fk_clients2'] )) ) {
                $sClients .= $kfr->Expand( "[[P_first_name]] [[P_last_name]]<br />" );
            }
        }
        $sClients .=
                 "</div>"
                ."<form>"
                ."<input type='hidden' name='cmd' value='link'/>"
                ."<input type='hidden' name='".($bTherapist?"add_internal_key":"add_external_key")."' value='".($bTherapist?$this->therapist_key:$this->pro_key)."'/>"
                .($oForm->Value('_key')?"<select name='add_client_key'><option value='0'> Choose a client</option>"
                .SEEDCore_ArrayExpandRows( $raClients, "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>")
                ."</select><input type='submit' value='add'></form>":"");

        $myRole = $oForm->Value('pro_role');
        $myRoleIsNormal = in_array($myRole, $this->pro_roles_name);
        $selRoles = "<select name='".$oForm->Name('pro_role')."' id='mySelect' onchange='doUpdateForm();'>";
        foreach ($this->pro_roles_name as $role) {
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
              "<form>"
                  .($bTherapist ? (($oForm->Value('_key')?"<input type='hidden' name='therapist_key' id='therapistId' value='{$this->therapist_key}'/>":"")
                             ."<input type='hidden' name='cmd' value='update_therapist'/>"
                                 .(($oForm->Value('_key')?($this->clinics->isCoreClinic() ? "<p>Therapist # {$this->therapist_key}</p>":""):"New Therapist")
                                 ))
                             : (($oForm->Value('_key')?"<input type='hidden' name='pro_key' id='proId' value='{$this->pro_key}'/>":"")
                             ."<input type='hidden' name='cmd' value='update_pro'/>"
                             .($oForm->Value('_key')?($this->clinics->isCoreClinic() ? "<p>Provider # {$this->pro_key}</p>":""):"New Professional")
                           ))
             .$oForm->HiddenKey()
             ."<table class='container-fluid table table-striped table-sm'>";
             $this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name' autofocus") ) );
             $this->drawFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) );
             $this->drawFormRow( "Address", $oForm->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) );
             $this->drawFormRow( "City", $oForm->Text('P_city',"",array("attrs"=>"placeholder='City'") ) );
             $this->drawFormRow( "Province", $oForm->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) );
             $this->drawFormRow( "Postal Code", $oForm->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) );
             $this->drawFormRow( "Phone Number", $oForm->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' maxlength='200'") ) );
             $this->drawFormRow( "Fax Number", $oForm->Text('fax_number', "", array("attrs"=>"placeholder='Fax Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) );
             $this->drawFormRow( "Email", $oForm->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) );
             $this->drawFormRow( "Pronouns", $this->getPronounList($oForm) );
             $this->drawFormRow( "Role", $selRoles );
             $this->drawFormRow( "Credentials", $oForm->Text('P_extra_credentials',"",array("attrs"=>"placeholder='To be shown after name'")));
             $this->drawFormRow( "Registration number", $oForm->Text('P_extra_regnumber',"",array("attrs"=>"placeholder='Registration number'")));
             $this->drawFormRow( "Rate","<input type='number' name='".$oForm->Name('rate')."' value='".$oForm->ValueEnt('rate')."' placeholder='Hourly rate' step='1' min='0' />" );
             $this->drawFormRow( "Clinic", $this->getClinicList($oForm) );
             $this->endRowDraw();
             $this->sForm .= "<tr class='row'>"
                ."<td class='col-md-12'><input type='submit' value='Save' style='margin:auto' /></td>"
             ."</tr>"
             ."</table>"
             ."</form>"
            ."<script>function doUpdateForm() {
                var sel = document.getElementById('mySelect').value;
                if( sel == 'Other' ) {
                    document.getElementById('other').style.display = 'inline';
                    document.getElementById('other').disabled = false;
                } else {
                    document.getElementById('other').style.display = 'none';
                    document.getElementById('other').disabled = true;
                }
            }
            </script>";

        $s .= "<div class='container-fluid' style='border:1px solid #aaa;padding:20px;margin:20px'>"
             ."<h3>".($bTherapist ? "CATS Staff" : "External Provider")." : ".$oForm->Value('P_first_name')." ".$oForm->Value('P_last_name')."</h3>"
             ."<div class='row'>"
             ."<div class='col-md-8'>".$this->sForm."</div>"
             ."<div class='col-md-4'>".$sClients."</div>"
             ."</div>"
             ."</div>";
             $s .= $this->clinicJS($oForm);
         return( $s );
    }

    private function getClinicList( $oForm)
    {
        $clinicId = $oForm->Value("clinic");
        $s = "<select id='".$oForm->Name('clinic')."' name='".$oForm->Name('clinic')."' ".($this->clinics->isCoreClinic()?"":"disabled ").">";
        $raClinics = $this->oClinicsDB->KFRel()->GetRecordSetRA("");
        foreach($raClinics as $clinic){
            $sSelected = (($oForm->Value("_key") == 0 && $this->clinics->GetCurrentClinic() == $clinic['_key']) || $clinicId == $clinic['_key']) ? " selected" : "";
            $s .= "<option$sSelected value='{$clinic['_key']}'>{$clinic['clinic_name']}</option>";
        }
        $s .= "</select>";
        return $s;
    }

    private function clinicJS($oForm){
        $s = "<script>"
            ."addEventListener('DOMContentLoaded', function() {
                document.getElementById('".$oForm->Name("clinic")."').form.addEventListener('submit', function(){
                    document.getElementById('".$oForm->Name("clinic")."').disabled = false;
                });
            });
            </script>";
        return $s;
    }

    private function filterJS(){
        return <<<FilterJS
               <script>
                function filterClients(e){
                    var filterForm = document.getElementById('filterForm');
                    var postData = $(filterForm).serializeArray();
                    var formURL = $(filterForm).attr("action");
                    $.ajax({
                        type: "POST",
                        data: postData,
                        url: formURL,
                        success: function(data, textStatus, jqXHR) {
                            doFilterUpdate();
                        },
                        error: function(jqXHR, status, error) {
                            console.log(status + ": " + error);
                        }
                    });
                    e.preventDefault();
                }
                function doFilterUpdate(){
                    var normal = document.getElementById('filterForm')[0];
                    var discharged = document.getElementById('filterForm')[1];
                    var normalClients = document.getElementsByClassName('client-normal');
                    var dischargedClients = document.getElementsByClassName('client-discharged');
                    var i;
                    for(i=0;i<normalClients.length;i++){
                        normalClients[i].style.display = normal.checked?"block":"none";
                    }
                    for(i=0;i<dischargedClients.length;i++){
                        dischargedClients[i].style.display = discharged.checked?"block":"none";
                    }
                }
               </script>
FilterJS;
    }

    private function getPronounList($oForm){

        $pronouns = array("M" => "He/Him/His", "F" => "She/Her/Her", "O" => "They/Them/Their");
        $s = "<select name='".$oForm->Name("P_pronouns")."' required >";
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
                            ($kfrC = $this->oPeopleDB->GetKFR( 'C', $ra[0])) &&
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
}

?>