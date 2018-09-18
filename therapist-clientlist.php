<?php
require_once "client-modal.php" ;
require_once 'Clinics.php';
require_once "therapist-clientlistxls.php";

class ClientList
{
    private $oApp;
    public $kfdb;

    public $oPeopleDB, $oClients_ProsDB, $oClinicsDB;

    private $pro_fields    = array("P_first_name","P_last_name","pro_role","P_address","P_city","P_postal_code","P_phone_number","fax_number","P_email");
    //map of computer keys to human readable text
    public $pro_roles_name = array("GP"=>"GP","Paediatrician"=>"Paediatrician", "Psychologist"=>"Psychologist", "SLP"=>"SLP", "PT"=>"PT", "OT"=>"OT", "Specialist_Dr"=>"Specialist Dr", "Resource_Teacher"=>"Resource Teacher", "Teacher_Tutor"=>"Teacher/Tutor", "Other"=>"Other");

    private $client_key;
    private $therapist_key;
    private $pro_key;
    private $clinics;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->kfdb = $oApp->kfdb;

        $this->oPeopleDB = new PeopleDB( $this->oApp );
        $this->oClients_ProsDB = new Clients_ProsDB( $oApp->kfdb );
        $this->oClinicsDB = new ClinicsDB($oApp->kfdb);

        $clinics = new Clinics($oApp);
        $clinics->GetCurrentClinic();

        $this->client_key = SEEDInput_Int( 'client_key' );
        $this->therapist_key = SEEDInput_Int( 'therapist_key' );
        $this->pro_key = SEEDInput_Int( 'pro_key' );
        $this->clinics = new Clinics($oApp);
    }

    function DrawClientList()
    {
        $s = "";

        $s .= "<div style='clear:both;float:right; border:1px solid #aaa;border-radius:5px;padding:10px'>"
                 ."<a href='jx.php?cmd=therapist-clientlistxls'><button>Download</button></a>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
                 ."<img src='".W_CORE_URL."img/icons/xls.png' height='30'/>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
                 ."<form style='display:inline-block;' action='${_SERVER['PHP_SELF']}' method='post' enctype='multipart/form-data'>"
                 ."<input type='submit' value='Upload'/>&nbsp;&nbsp;&nbsp;"
                 ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
                 ."<input type='hidden' name='cmd' value='uploadxls' />"
                 ."<input type='file' name='uploadfile' style='font-size:9pt'/>"
                 ."</form>"
             ."</div>";

        $oFormClient    = new KeyframeForm( $this->oPeopleDB->KFRel("C"), "A", array("fields"=>array("parents_separate"=>array("control"=>"checkbox"))));
        $oFormTherapist = new KeyframeForm( $this->oPeopleDB->KFRel("PI"), "A" );
        $oFormPro       = new KeyframeForm( $this->oPeopleDB->KFRel("PE"), "A" );

        // Put this before the GetClients call so the changes are shown in the list
        $cmd = SEEDInput_Str('cmd');
        switch( $cmd ) {
            case "update_client":
                $oFormClient->Update();
                $this->updatePeople( $oFormClient );
                break;
            case "update_therapist":
                $oFormTherapist->Update();
                $this->updatePeople( $oFormTherapist );
                break;
            case "update_pro":
                $oFormPro->Update();
                $this->updatePeople( $oFormPro );
                break;
            case "update_pro_add_client":
                $kfr = $this->oClients_ProsDB->KFRelBase()->CreateRecord();
                $kfr->SetValue("fk_professionals", $this->pro_key);
                $kfr->SetValue("fk_clients", SEEDInput_Int("add_client_key"));
                $kfr->PutDBRow();
                break;
            case "new_client":
                $name = SEEDInput_Str("new_client_name");
                $kfrPeople = $this->oPeopleDB->KFRel("P")->CreateRecord();
                $kfrPeople->SetValue("first_name", $name);
                $kfrPeople->PutDBRow();
                $kfr = $this->oPeopleDB->KFRel("C")->CreateRecord();
                $kfr->SetValue("fk_people",$kfrPeople->Key());
                $kfr->SetValue("clinic",$this->clinics->GetCurrentClinic());
                $kfr->PutDBRow();
                $this->client_key = $kfr->Key();
                break;
            case "new_pro":
                $name = SEEDInput_Str("new_pro_name");
                $kfrPeople = $this->oPeopleDB->KFRel("P")->CreateRecord();
                $kfrPeople->SetValue("first_name", $name);
                $kfrPeople->PutDBRow();
                $kfr = $this->oPeopleDB->KFRel("PE")->CreateRecord();
                $kfr->SetValue("fk_people",$kfrPeople->Key());
                $kfr->SetValue("clinic",$this->clinics->GetCurrentClinic());
                $kfr->PutDBRow();
                $this->pro_key = $kfr->Key();
                break;
            case "new_therapist":
                $name = SEEDInput_Str("new_therapist_name");
                $kfrPeople = $this->oPeopleDB->KFRel("P")->CreateRecord();
                $kfrPeople->SetValue("first_name", $name);
                $kfrPeople->PutDBRow();
                $kfr = $this->oPeopleDB->KFRel("PI")->CreateRecord();
                $kfr->SetValue("fk_people",$kfrPeople->Key());
                $kfr->SetValue("clinic",$this->clinics->GetCurrentClinic());
                $kfr->PutDBRow();
                $this->therapist_key = $kfr->Key();
                break;
            case 'uploadxls':
                $s .= $this->uploadSpreadsheet();
                break;
        }

        $clientPros = array();
        $proClients = array();
        $myPros = array();
        $myClients = array();

        /* Set the form to use the selected client.
         */
        if( $this->client_key && ($kfr = $this->oPeopleDB->GetKFR("C", $this->client_key )) ) {
            $oFormClient->SetKFR( $kfr );
            // A client has been clicked. Who are their pros?
            $myPros = $this->oPeopleDB->GetList('CX', "fk_clients2='{$this->client_key}'" );
        }
        if( $this->therapist_key && ($kfr = $this->oPeopleDB->GetKFR("PI", $this->therapist_key )) ) {
            $oFormTherapist->SetKFR( $kfr );
            // A therapist has been clicked. Who are their clients?
            $myClients = $this->oPeopleDB->GetList('CX', "fk_pros_internal='{$this->therapist_key}'" );
        }
        if( $this->pro_key && ($kfr = $this->oPeopleDB->GetKFR("PE", $this->pro_key )) ) {
            $oFormPro->SetKFR( $kfr );
            // A pro has been clicked. Who are their clients?
            $myClients = $this->oPeopleDB->GetList('CX', "fk_pros_external='{$this->therapist_key}'" );
        }

        $condClinic = $this->clinics->isCoreClinic() ? "" : ("clinic = ".$this->clinics->GetCurrentClinic());
        $raClients    = $this->oPeopleDB->GetList('C', $condClinic);
        $raTherapists = $this->oPeopleDB->GetList('PI', $condClinic);
        $raPros       = $this->oPeopleDB->GetList('PE', $condClinic);

        $s .= "<div style='clear:both' class='container-fluid'><div class='row'>"
             ."<div class='col-md-4'>"
                 ."<h3>Clients</h3>"
                 ."<button onclick='add_new_client();'>Add Client</button>"
                 ."<script>function add_new_client(){var value = prompt('Enter Client\'s First Name');
                 if(!value){return;}
                 document.getElementById('new_client_name').value = value;
                 document.getElementById('new_client').submit();
                 }</script><form id='new_client'><input type='hidden' value='' name='new_client_name' id='new_client_name'><input type='hidden' name='cmd' value='new_client'/>
                 <input type='hidden' name='screen' value='therapist-clientlist'/></form>"
                 .SEEDCore_ArrayExpandRows( $raClients, "<div id='client-[[_key]]' style='padding:5px;'><a href='?client_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a>%[[clinic]]</div>" )
             ."</div>"
             ."<div class='col-md-4'>"
                 ."<h3>CATS Staff</h3>"
                 ."<button onclick='add_new_staff();'>Add Staff Member</button>"
                 ."<script>function add_new_staff(){var value = prompt('Enter Staff Member\'s First Name');
                 if(!value){return;}
                 document.getElementById('new_therapist_name').value = value;
                 document.getElementById('new_therapist').submit();
                 }</script><form id='new_therapist'><input type='hidden' value='' name='new_therapist_name' id='new_therapist_name'><input type='hidden' name='cmd' value='new_therapist'/>
                 <input type='hidden' name='screen' value='therapist-clientlist'/></form>"
                 .SEEDCore_ArrayExpandRows( $raTherapists, "<div id='therapist-[[_key]]' style='padding:5px;'><a href='?therapist_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a> is a [[pro_role]]%[[clinic]]</div>" )
             ."</div>"
             ."<div class='col-md-4'>"
                 ."<h3>External Providers</h3>"
                 ."<button onclick='add_new_pro();'>Add External Provider</button>"
                 ."<script>function add_new_pro(){var value = prompt('Enter External Provider\'s Name');
                 if(!value){return;}
                 document.getElementById('new_pro_name').value = value;
                 document.getElementById('new_pro').submit();
                 }</script><form id='new_pro'><input type='hidden' value='' name='new_pro_name' id='new_pro_name'><input type='hidden' name='cmd' value='new_pro'/>
                 <input type='hidden' name='screen' value='therapist-clientlist'/></form>"
                 .SEEDCore_ArrayExpandRows( $raPros, "<div id='pro-[[_key]]' style='padding:5px;'><a href='?pro_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a> is a [[pro_role]]%[[clinic]]</div>" )
             ."</div>"
             ."</div></div>"
             ."<style>"
                 ."#client-{$this->client_key}, #therapist-{$this->therapist_key}, #pro-{$this->pro_key} "
                     ." { font-weight:bold;color:green;background-color:#dfd; }"
             ."</style>";


             $s .= "<div class='container'><div class='row'>";
             if( $this->client_key ) {
                 $s .= $this->drawClientForm( $oFormClient, $myPros, $raPros );
             }
             if( $this->therapist_key ) {
                 $s .= $this->drawProForm( $oFormTherapist, $myClients, $raClients, true );
             }
             if( $this->pro_key ) {
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
        $peopleFields = array( 'first_name', 'last_name', 'address', 'city', 'province', 'postal_code', 'dob', 'phone_number', 'email' );

        if( ($kP = $oForm->Value('P__key')) && ($kfr = $this->oPeopleDB->GetKFR('P', $kP)) ) {
            foreach( $peopleFields as $v ) {
                $kfr->SetValue( $v, $oForm->Value("P_$v") );
            }
            $kfr->PutDBRow();
        }
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
        $sPros       .= "</div>".drawModal($oForm->GetValuesRA(), $this->oPeopleDB, $this->pro_roles_name );

        $oForm->SetStickyParms( array( 'raAttrs' => array( 'maxlength'=>'200', 'style'=>'width:100%' ) ) );
        $sForm =
              "<form>"
             ."<input type='hidden' name='cmd' value='update_client'/>"
             ."<input type='hidden' name='client_key' id='clientId' value='{$this->client_key}'/>"
             .$oForm->HiddenKey()
             ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
             .($this->clinics->isCoreClinic()?"<p>Client # {$this->client_key}</p>":"")
             ."<table class='container-fluid table table-striped table-sm'>"
             .$this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name'") ) )
             .$this->drawFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) )
             .$this->drawFormRow( "Parents Name", $oForm->Text('parents_name',"",array("attrs"=>"placeholder='Parents Name'") ) )
             .$this->drawFormRow( "Parents Separate", $oForm->Checkbox('parents_separate') )
             .$this->drawFormRow( "Address", $oForm->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) )
             .$this->drawFormRow( "City", $oForm->Text('P_city',"",array("attrs"=>"placeholder='City'") ) )
             .$this->drawFormRow( "Province", $oForm->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) )
             .$this->drawFormRow( "Postal Code", $oForm->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) )
             .$this->drawFormRow( "Date Of Birth", $oForm->Date('P_dob',"",array("attrs"=>"style='border:1px solid gray'")) )
             .$this->drawFormRow( "Phone Number", $oForm->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) )
             .$this->drawFormRow( "Email", $oForm->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) )
             .$this->drawFormRow( "Clinic", $this->getClinicList($oForm->Value('clinic') ) )
             ."<tr class='row'>"
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
                 ."<div class='col-md-8'>".$sForm."</div>"
                 ."<div class='col-md-4'>".$sTherapists.$sPros."</div>"
             ."</div>"
             ."</div>";

         return( $s );
    }

    private function drawFormRow( $label, $control )
    {
        return( "<tr class='row'>"
                   ."<td class='col-md-5'><p>$label</p></td>"
                   ."<td class='col-md-7'>$control</td>"
               ."</tr>" );
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
            if( $ra['fk_pros_external'] && ($kfr = $this->oPeopleDB->GetKFR( 'PE', $ra['fk_pros_external'] )) ) {
                $sPros .= $kfr->Expand( "[[P_first_name]] [[P_last_name]] is my [[pro_role]]<br />" );
            }
        }
        $sClients .=
                 "</div>"
                ."<form>"
                ."<input type='hidden' name='cmd' value='update_pro_add_client'/>"
                ."<input type='hidden' name='pro_key' value='{$this->pro_key}'/>"
                ."<select name='add_client_key'><option value='0'> Choose a client</option>"
                .SEEDCore_ArrayExpandRows( $raClients, "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>" )
                ."</select><input type='submit' value='add'></form>";

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

        $oForm->SetStickyParms( array( 'raAttrs' => array( 'maxlength'=>'200', 'style'=>'width:100%' ) ) );
        $sForm =
              "<form>"
             .($bTherapist ? ("<input type='hidden' name='therapist_key' id='therapistId' value='{$this->therapist_key}'/>"
                             ."<input type='hidden' name='cmd' value='update_therapist'/>"
                             .($this->clinics->isCoreClinic() ? "<p>Therapist # {$this->therapist_key}</p>":"")
                                 )
                           : ("<input type='hidden' name='pro_key' id='proId' value='{$this->pro_key}'/>"
                             ."<input type='hidden' name='cmd' value='update_pro'/>"
                             .($this->clinics->isCoreClinic() ? "<p>Provider # {$this->pro_key}</p>":"")
                           ))
             .$oForm->HiddenKey()
             ."<table class='container-fluid table table-striped table-sm'>"
             .$this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name'") ) )
             .$this->drawFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) )
             .$this->drawFormRow( "Address", $oForm->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) )
             .$this->drawFormRow( "City", $oForm->Text('P_city',"",array("attrs"=>"placeholder='City'") ) )
             .$this->drawFormRow( "Province", $oForm->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) )
             .$this->drawFormRow( "Postal Code", $oForm->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) )
             .$this->drawFormRow( "Phone Number", $oForm->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) )
             .$this->drawFormRow( "Fax Number", $oForm->Text('fax_number', "", array("attrs"=>"placeholder='Fax Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) )
             .$this->drawFormRow( "Email", $oForm->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) )
             .$this->drawFormRow( "Role", $selRoles )
             .$this->drawFormRow( "Rate", "<input type='number' name='rate' value='".$oForm->ValueEnt('rate')."' placeholder='Rate' step='1' min='0' />" )
             .$this->drawFormRow( "Clinic", $this->getClinicList($oForm->Value('clinic') ) )
             ."<tr class='row'>"
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
             ."<div class='col-md-8'>".$sForm."</div>"
             ."<div class='col-md-4'>".$sClients."</div>"
             ."</div>"
             ."</div>";

         return( $s );
    }

    private function getClinicList( $clinicId )
    {
        $s = "<select name='clinic' ".($this->clinics->isCoreClinic()?"":"disabled ").">";
        $raClinics = $this->oClinicsDB->KFRel()->GetRecordSetRA("");
        foreach($raClinics as $clinic){
            $sSelected = ($clinicId == $clinic['_key']) ? "selected" : "";
            $s .= "<option $sSelected value='{$clinic['_key']}'>{$clinic['clinic_name']}</option>";
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
