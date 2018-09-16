<?php
require_once "client-modal.php" ;
require_once 'Clinics.php';
class ClientList
{
    private $oApp;
    public $kfdb;

    public $oPeopleDB, $oClients_ProsDB, $oClinicsDB;

    private $pro_fields    = array("P_first_name","P_last_name","pro_role","P_address","P_city","P_postal_code","P_phone_number","fax_number","P_email");
    //Computer Valid Keys for Roles
    public $pro_roles_key = array("GP","Paediatrician", "Psychologist", "SLP", "PT", "OT", "Specialist_Dr", "Resource_Teacher", "Teacher_Tutor", "Other");
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

        $oFormClient = new KeyframeForm( $this->oPeopleDB->KFRel("C"), "A", array("fields"=>array("parents_separate"=>array("control"=>"checkbox"))));
        $oFormPro = new KeyframeForm( $this->oPeopleDB->KFRel("PE"), "A" );

        // Put this before the GetClients call so the changes are shown in the list
        $cmd = SEEDInput_Str('cmd');
        switch( $cmd ) {
            case "update_client":
                $oFormClient->Update();
                $this->updatePeople( $oFormClient );
                break;
            case "update_pro":
                $kfr = $this->oPeopleDB->GetKFR("PE", $this->pro_key );
                foreach( $this->pro_fields as $field ) {
                    $kfr->SetValue( $field, SEEDInput_Str($field) );
                }
                $kfr->PutDBRow();
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
                $kfr = $this->oPeopleDB->KFRel("C")->CreateRecord();
                $kfr->SetValue("P_first_name",$name);
                $kfr->SetValue("clinic",$this->clinics->GetCurrentClinic());
                $kfr->PutDBRow();
                $this->client_key = $kfr->Key();
                break;
            case "new_pro":
                $name = SEEDInput_Str("new_pro_name");
                $kfr = $this->oPeopleDB->KFRel("PE")->CreateRecord();
                $kfr->SetValue("P_first_name", $name);
                $kfr->SetValue("clinic",$this->clinics->GetCurrentClinic());
                $kfr->PutDBRow();
                $this->pro_key = $kfr->Key();
                break;
        }

        /* Set the form to use the selected client.
         */
        if( $this->client_key && ($kfrClient = $this->oPeopleDB->GetKFR("C", $this->client_key )) ) {
            $oFormClient->SetKFR( $kfrClient );
        }
        if( $this->pro_key && ($kfr = $this->oPeopleDB->GetKFR("PE", $this->pro_key )) ) {
            $oFormPro->SetKFR( $kfr );
        }

        $clientPros = array();
        $proClients = array();
        $myPros = array();
        $myClients = array();
        if( $this->client_key ) {
            // A client has been clicked. Who are their pros?
            $myPros = $this->oClients_ProsDB->KFRel()->GetRecordSetRA("Clients._key='{$this->client_key}'" );
        }
        if( $this->pro_key ) {
            // A pro has been clicked. Who are their clients?
            $myClients = $this->oClients_ProsDB->KFRel()->GetRecordSetRA("Pros._key='{$this->pro_key}'" );
        }

        $oPeople = new PeopleDB( $this->oApp );
        $raClients = $oPeople->GetList('C', "" );
        $raProsInt = $oPeople->GetList('PI', "");
        $raProsExt = $oPeople->GetList('PE', "");
        $s .= "<div>Clients are: <ul>".SEEDCore_ArrayExpandRows( $raClients, "<li>[[_key]] : [[P_first_name]] [[P_last_name]]</li>" )."</ul></div>";
        $s .= "<div>Internal pros are: <ul>".SEEDCore_ArrayExpandRows( $raProsInt, "<li>[[_key]] : [[P_first_name]] [[P_last_name]]</li>" )."</ul></div>";


        $condClinic = $this->clinics->isCoreClinic() ? "" : ("clinic = ".$this->clinics->GetCurrentClinic());
        $raClients = $this->oPeopleDB->GetList('C', $condClinic);
        $raTherapists = $this->oPeopleDB->GetList('PI', $condClinic);
        $raPros = $this->oPeopleDB->GetList('PE', $condClinic);

        $s .= "<div class='container-fluid'><div class='row'>"
             ."<div class='col-md-4'>"
                 ."<h3>Clients</h3>"
                 ."<button onclick='add_new();'>Add Client</button>"
                 ."<script>function add_new(){var value = prompt('Enter Clients First Name');
                 if(!value){return;}
                 document.getElementById('new_client_name').value = value;
                 document.getElementById('new_client').submit();
                 }</script><form id='new_client'><input type='hidden' value='' name='new_client_name' id='new_client_name'><input type='hidden' name='cmd' value='new_client'/>
                 <input type='hidden' name='screen' value='therapist-clientlist'/></form>"
                 .SEEDCore_ArrayExpandRows( $raClients, "<div style='padding:5px;'><a href='?client_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a>%[[clinic]]</div>" )
                 .($this->client_key ? $this->drawClientForm( $oFormClient, $raClients, $myPros, $raPros) : "")
             ."</div>"
             ."<div class='col-md-4'>"
                 ."<h3>Therapists</h3>"
                 ."<button onclick='add_new();'>Add Therapist</button>"
                 ."<script>function add_new(){var value = prompt('Enter Therapist\'s First Name');
                 if(!value){return;}
                 document.getElementById('new_therapist_name').value = value;
                 document.getElementById('new_therapist').submit();
                 }</script><form id='new_therapist'><input type='hidden' value='' name='new_therapist_name' id='new_therapist_name'><input type='hidden' name='cmd' value='new_therapist'/>
                 <input type='hidden' name='screen' value='therapist-clientlist'/></form>"
                 .SEEDCore_ArrayExpandRows( $raTherapists, "<div style='padding:5px;'><a href='?therapist_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a>%[[clinic]]</div>" )
                 .($this->therapist_key ? $this->drawProForm( $oFormPro, $raTherapists, $myClients, $raClients) : "")
             ."</div>"
             ."<div class='col-md-4'>"
                 ."<h3>External Providers</h3>"
                 ."<button onclick='add_new_pro();'>Add Professional</button>"
                 ."<script>function add_new_pro(){var value = prompt('Enter Professionals Name');
                 if(!value){return;}
                 document.getElementById('new_pro_name').value = value;
                 document.getElementById('new_pro').submit();
                 }</script><form id='new_pro'><input type='hidden' value='' name='new_pro_name' id='new_pro_name'><input type='hidden' name='cmd' value='new_pro'/>
                 <input type='hidden' name='screen' value='therapist-clientlist'/></form>"
                 .SEEDCore_ArrayExpandRows( $raPros, "<div style='padding:5px;'><a href='?pro_key=[[_key]]'>[[P_first_name]] [[P_last_name]]</a> is a [[pro_role]]%[[clinic]]</div>" )
                 .($this->pro_key ? $this->drawProForm( $oFormPro, $raPros, $myClients, $raClients ) : "")
             ."</div>"
             ."</div></div>";

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
        if( ($kP = $oForm->Value('P__key')) && ($kfr = $this->oPeopleDB->GetKFR('P', $kP)) ) {
            foreach( array('first_name', 'last_name', 'address', 'city', 'province', 'postal_code', 'dob', 'phone_number', 'email') as $v ) {
                $kfr->SetValue( $v, $oForm->Value("P_$v") );
            }
            $kfr->PutDBRow();
        }
    }

    function drawClientForm( $oFormClient, $raClients, $myPros, $raPros )
    {
        $s = "";

        // The user clicked on a client name so show their form
        foreach( $raClients as $ra ) {
            //var_dump($ra);
            if($ra['clinic'] != $this->clinics->GetCurrentClinic()){
                continue;
            }
            if( $ra['_key'] == $this->client_key ) {
                $sPros = "<div style='padding:10px;border:1px solid #888'>"
                        .SEEDCore_ArrayExpandRows( $myPros, "[[Pros_pro_name]] is my [[Pros_pro_role]]<br />" )
                        ."</div>";
                $sPros .= drawModal($ra, $this->oPeopleDB,$this->pro_roles_name);
                $oFormClient->SetStickyParms( array( 'raAttrs' => array( 'maxlength'=>'200' ) ) );
                $sForm =
                      "<form>"
                     ."<input type='hidden' name='cmd' value='update_client'/>"
                     ."<input type='hidden' name='client_key' id='clientId' value='{$this->client_key}'/>"
                     .$oFormClient->HiddenKey()
                     ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
                     .($this->clinics->isCoreClinic()?"<p>Client # {$this->client_key}</p>":"")
                     ."<table class='container-fluid table table-striped table-sm'>"
                     .$this->drawFormRow( "First Name", $oFormClient->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name'") ) )
                     .$this->drawFormRow( "Last Name", $oFormClient->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) )
                     .$this->drawFormRow( "Parents Name", $oFormClient->Text('parents_name',"",array("attrs"=>"placeholder='Parents Name'") ) )
                     .$this->drawFormRow( "Parents Separate", $oFormClient->Checkbox('parents_separate') )
                     .$this->drawFormRow( "Address", $oFormClient->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) )
                     .$this->drawFormRow( "City", $oFormClient->Text('P_city',"",array("attrs"=>"placeholder='City'") ) )
                     .$this->drawFormRow( "Province", $oFormClient->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) )
                     .$this->drawFormRow( "Postal Code", $oFormClient->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) )
                     .$this->drawFormRow( "Date Of Birth", $oFormClient->Date('P_dob',"",array("attrs"=>"style='border:1px solid gray'")) )
                     .$this->drawFormRow( "Phone Number", $oFormClient->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) )
                     .$this->drawFormRow( "Email", $oFormClient->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) )
                     .$this->drawFormRow("Clinic", $this->getClinicList($ra))
                     ."<tr>"
                        ."<td class='col-md-12'><input type='submit' value='Save' style='margin:auto' /></td>"
                        .($ra['P_email']?"<tdclass='col-md-12'><div id='credsDiv'><button onclick='sendcreds(event)'>Send Credentials</button></div></td>":"")
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
                     ."</tr>"
                     ."</table>"
                     ."</form>";


                $s .= "<div class='container-fluid' style='border:1px solid #aaa;padding:20px;margin:20px'>"
                     ."<div class='row'>"
                         ."<div class='col-md-9'>".$sForm."</div>"
                         ."<div class='col-md-3'>".$sPros."</div>"
                     ."</div>"
                     ."</div>";
            }
        }
        return( $s );
    }

    private function drawFormRow( $label, $control )
    {
        return( "<tr>"
                   ."<td class='col-md-4'><p>$label</p></td>"
                   ."<td class='col-md-8'>$control</td>"
               ."</tr>" );
    }

    function drawProForm( SEEDCoreForm $oForm, $raPros, $myClients, $raClients )
    {
        $s = "";

        // The user clicked on a professionals name so show their form
        foreach( $raPros as $ra ) {
            if( $ra['clinic'] != $this->clinics->GetCurrentClinic()){
                continue;
            }
            if( $ra['_key'] == $this->pro_key ) {
                if($ra['clinic'] != $this->clinics->GetCurrentClinic()){
                    continue;
                }
                $sClients = "<div style='padding:10px;border:1px solid #888'>"
                    .SEEDCore_ArrayExpandRows( $myClients, "[[client_first_name]] [[client_last_name]]<br />" )
                    ."</div>";
                $sClients .= "<form>"
                        ."<input type='hidden' name='cmd' value='update_pro_add_client'/>"
                        ."<input type='hidden' name='pro_key' value='{$this->pro_key}'/>"
                        ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
                        ."<select name='add_client_key'><option value='0'> Choose a client</option>"
                        .SEEDCore_ArrayExpandRows( $raClients, "<option value='[[_key]]'>[[client_first_name]] [[client_last_name]]</option>" )
                        ."</select><input type='submit' value='add'></form>";

                $selRoles = "<select name='pro_role' id='mySelect' onchange='doUpdateForm();'>";
                foreach ($this->pro_roles_name as $role) {
                    if($ra['pro_role'] == $role){
                        $selRoles .= "<option selected />".$role;
                    } elseif($role == "Other" && !in_array($ra['pro_role'], $this->pro_roles_name)){
                        $selRoles .= "<option selected />".$role;
                    } else{
                        $selRoles .= "<option />".$role;
                    }
                }
                $selRoles .= "</select>"
                            ."<input type='text' ".(in_array($ra['pro_role'], $this->pro_roles_name)?"style='display:none' disabled ":"")."required id='other' name='pro_role' maxlength='200' value='".(in_array($ra['pro_role'], $this->pro_roles_name)?"":htmlspecialchars($ra['pro_role']))."' placeholder='Role' />";

                $sForm =
                      "<form>"
                     ."<input type='hidden' name='cmd' value='update_pro'/>"
                     ."<input type='hidden' name='pro_key' id='proId' value='{$this->pro_key}'/>"
                     .$oForm->HiddenKey()
                     ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
                     .($this->clinics->isCoreClinic() ? "<p>Pro # {$this->pro_key}</p>":"")
                     ."<table class='container-fluid table table-striped table-sm'>"
                     .$this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name'") ) )
                     .$this->drawFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) )
                     .$this->drawFormRow( "Address", $oForm->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) )
                     .$this->drawFormRow( "City", $oForm->Text('P_city',"",array("attrs"=>"placeholder='City'") ) )
                     .$this->drawFormRow( "Province", $oForm->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) )
                     .$this->drawFormRow( "Postal Code", $oForm->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) )
                     .$this->drawFormRow( "Phone Number", $oForm->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) )
                     .$this->drawFormRow( "Fax Number", $oForm->Text('fax_number', "", array("attrs"=>"placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) )
                     .$this->drawFormRow( "Email", $oForm->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) )
                     .$this->drawFormRow( "Role", $selRoles )
                     .$this->drawFormRow( "Rate", "<input type='number' name='rate' value='".htmlspecialchars($ra['rate'])."' placeholder='Rate' step='1' min='0' />" )
                     .$this->drawFormRow("Clinic", $this->getClinicList($ra))
                     ."<tr>"
                         ."<td class='col-md-12'><input type='submit' value='Save'/></td>"
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
                     ."<div class='row'>"
                     ."<div class='col-md-9'>".$sForm."</div>"
                     ."<div class='col-md-3'>".$sClients."</div>"
                     ."</div>"
                     ."</div>";
            }
        }
        return( $s );
    }

    private function getClinicList($ra){
        $s = "<select name='clinic' ".($this->clinics->isCoreClinic()?"":"disabled ").">";
        $raClinics = $this->oClinicsDB->KFRel()->GetRecordSetRA("");
        foreach($raClinics as $clinic){
            if($ra['clinic'] == $clinic['_key']){
                $s .= "<option selected value='".$clinic['_key']."' >".$clinic['clinic_name']."</option>";
            }
            else{
                $s .= "<option value='".$clinic['_key']."' >".$clinic['clinic_name']."</option>";
            }
        }
        $s .= "</select>";
        return $s;
    }

}

?>
