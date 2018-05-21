<?php
require_once "client-modal.php" ;
class ClientList
{
    public $kfdb;

    public $oClientsDB, $oProsDB, $oClients_ProsDB;

    private $client_fields = array("client_name","parents_name","address","city","postal_code","dob","phone_number","email","family_doc","paediatrician","slp","psychologist","referal","background_info");
    private $pro_fields    = array("pro_name","pro_role","address","city","postal_code","phone_number","fax_number","email");
    //Computer Valid Keys for Roles
    public $pro_roles_key = array("GP","Paediatrician", "Psychologist", "SLP", "PT", "OT", "Specialist_Dr", "Resource_Teacher", "Teacher_Tutor", "Other");
    //map of computer keys to human readable text
    public $pro_roles_name = array("GP"=>"GP","Paediatrician"=>"Paediatrician", "Psychologist"=>"Psychologist", "SLP"=>"SLP", "PT"=>"PT", "OT"=>"OT", "Specialist_Dr"=>"Specialist Dr", "Resource_Teacher"=>"Resource Teacher", "Teacher_Tutor"=>"Teacher/Tutor", "Other"=>"Other");

    private $client_key;
    private $pro_key;

    function __construct( KeyframeDatabase $kfdb )
    {
        $this->kfdb = $kfdb;

        $this->oClientsDB = new ClientsDB( $kfdb );
        $this->oProsDB = new ProsDB( $kfdb );
        $this->oClients_ProsDB = new Clients_ProsDB( $kfdb );

        $this->client_key = SEEDInput_Int( 'client_key' );
        $this->pro_key = SEEDInput_Int( 'pro_key' );

    }

    function DrawClientList()
    {
        $s = "";

        $oFormClient = new KeyframeForm( $this->oClientsDB->KFRel(), "A", array("fields"=>array("parents_separate"=>array("control"=>"checkbox"))));

        // Put this before the GetClients call so the changes are shown in the list
        $cmd = SEEDInput_Str('cmd');
        switch( $cmd ) {
            case "update_client":
                $oFormClient->Update();
                break;
            case "update_pro":
                $kfr = $this->oProsDB->GetPro( $this->pro_key );
                foreach( $this->pro_fields as $field ) {
                    $kfr->SetValue( $field, SEEDInput_Str($field) );
                }
                $kfr->PutDBRow();
                break;
            case "update_pro_add_client":
                $kfr = $this->oClients_ProsDB->KFRelBase()->CreateRecord();
                $kfr->SetValue("fk_professionals", $this->pro_key);
                $kfr->SetValue("fk_clients", SEEDInput_Int("add_client_key"));
                $kfr->PutDBRow();
                break;
            case "new_client":
                $name = SEEDInput_Str("new_client_name");
                $kfr = $this->oClientsDB->KFRel()->CreateRecord();
                $kfr->SetValue("client_name", $name);
                $kfr->PutDBRow();
                $this->client_key = $kfr->Key();
                break;
            case "new_pro":
                $name = SEEDInput_Str("new_pro_name");
                $kfr = $this->oProsDB->KFRel()->CreateRecord();
                $kfr->SetValue("pro_name", $name);
                $kfr->PutDBRow();
                $this->pro_key = $kfr->Key();
                break;
        }

        /* Set the form to use the selected client.
         */
        if( $this->client_key && ($kfrClient = $this->oClientsDB->GetClient( $this->client_key )) ) {
            $oFormClient->SetKFR( $kfrClient );
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

        $raClients = $this->oClientsDB->KFRel()->GetRecordSetRA("");
        $raPros = $this->oProsDB->KFRel()->GetRecordSetRA("");

        $s .= "<div class='container-fluid'><div class='row'>"
             ."<div class='col-md-6'>"
                 ."<h3>Clients</h3>"
                 ."<button onclick='add_new();'>Add Client</button>"
                 ."<script>function add_new(){var value = prompt('Enter Clients Name');
                 if(!value){return;}
                 document.getElementById('new_client_name').value = value;
                 document.getElementById('new_client').submit();
                 }</script><form id='new_client'><input type='hidden' value='' name='new_client_name' id='new_client_name'><input type='hidden' name='cmd' value='new_client'/>
                 <input type='hidden' name='screen' value='therapist-clientlist'/></form>"
                 .SEEDCore_ArrayExpandRows( $raClients, "<div style='padding:5px;'><a href='?client_key=[[_key]]&screen=therapist-clientlist'>[[client_name]]</a></div>" )
                 .($this->client_key ? $this->drawClientForm( $oFormClient, $raClients, $myPros, $raPros) : "")
             ."</div>"
             ."<div class='col-md-6'>"
                 ."<h3>External Providers</h3>"
                 ."<button onclick='add_new_pro();'>Add Professional</button>"
                 ."<script>function add_new_pro(){var value = prompt('Enter Professionals Name');
                 if(!value){return;}
                 document.getElementById('new_pro_name').value = value;
                 document.getElementById('new_pro').submit();
                 }</script><form id='new_pro'><input type='hidden' value='' name='new_pro_name' id='new_pro_name'><input type='hidden' name='cmd' value='new_pro'/>
                 <input type='hidden' name='screen' value='therapist-clientlist'/></form>"
                 .SEEDCore_ArrayExpandRows( $raPros, "<div style='padding:5px;'><a href='?pro_key=[[_key]]&screen=therapist-clientlist'>[[pro_name]]</a> is a [[pro_role]]</div>" )
                 .($this->pro_key ? $this->drawProForm( $raPros, $myClients, $raClients ) : "")
             ."</div>"
             ."</div></div>";

        return( $s );
    }


    function drawClientForm( $oFormClient, $raClients, $myPros, $raPros )
    {
        $s = "";

        // The user clicked on a client name so show their form
        foreach( $raClients as $ra ) {
            if( $ra['_key'] == $this->client_key ) {
                $sPros = "<div style='padding:10px;border:1px solid #888'>"
                        .SEEDCore_ArrayExpandRows( $myPros, "[[Pros_pro_name]] is my [[Pros_pro_role]]<br />" )
                        ."</div>";
                $sPros .= drawModal($ra, $this->oProsDB,$this->pro_roles_name);

                $oFormClient->SetStickyParms( array( 'raAttrs' => array( 'maxlength'=>'200' ) ) );
                $sForm =
                      "<form>"
                     ."<input type='hidden' name='cmd' value='update_client'/>"
                     ."<input type='hidden' name='client_key' value='{$this->client_key}'/>"
                     .$oFormClient->HiddenKey()
                     ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
                     ."<p>Client # {$this->client_key}</p>"
                     ."<table class='container-fluid table table-striped table-sm'>"
                     .$this->drawFormRow( "Name", $oFormClient->Text('client_name',"",array("attrs"=>"required placeholder='Name'") ) )
                     .$this->drawFormRow( "Parents Name", $oFormClient->Text('parents_name',"",array("attrs"=>"placeholder='Parents Name'") ) )
                     .$this->drawFormRow( "Parents Separate", $oFormClient->Checkbox('parents_separate') )
                     .$this->drawFormRow( "Address", $oFormClient->Text('address',"",array("attrs"=>"placeholder='Address'") ) )
                     .$this->drawFormRow( "City", $oFormClient->Text('city',"",array("attrs"=>"placeholder='City'") ) )
                     .$this->drawFormRow( "Postal Code", $oFormClient->Text('postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) )
                     .$this->drawFormRow( "Date Of Birth", $oFormClient->Date('dob',"",array("attrs"=>"style='border:1px solid gray'")) )
                     .$this->drawFormRow( "Phone Number", $oFormClient->Text('phone_number', "", array("attrs"=>"placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) )
                     .$this->drawFormRow( "Email", $oFormClient->Email('email',"",array("attrs"=>"placeholder='Email'") ) )
                     ."<tr>"
                        ."<td class='col-md-12'><input type='submit' value='Save' style='margin:auto' /></td>"
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

    function drawProForm( $raPros, $myClients, $raClients )
    {
        $s = "";

        // The user clicked on a professionals name so show their form
        foreach( $raPros as $ra ) {
            if( $ra['_key'] == $this->pro_key ) {

                $sClients = "<div style='padding:10px;border:1px solid #888'>"
                    .SEEDCore_ArrayExpandRows( $myClients, "[[client_name]]<br />" )
                    ."</div>";
                $sClients .= "<form>"
                        ."<input type='hidden' name='cmd' value='update_pro_add_client'/>"
                        ."<input type='hidden' name='pro_key' value='{$this->pro_key}'/>"
                        ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
                        ."<select name='add_client_key'><option value='0'> Choose a client</option>"
                        .SEEDCore_ArrayExpandRows( $raClients, "<option value='[[_key]]'>[[client_name]]</option>" )
                        ."</select><input type='submit' value='add'></form>";
                $sForm =
                    "<form>"
                    ."<input type='hidden' name='cmd' value='update_pro'/>"
                    ."<input type='hidden' name='pro_key' value='{$this->pro_key}'/>"
                    ."<input type='hidden' name='screen' value='therapist-clientlist'/>"
                    ."<p>Professional # {$this->pro_key}</p>"
                    ."<table class='container-fluid table table-striped'>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>Name</p>"
                        ."<td class='col-md-8'><input type='text' name='pro_name' required maxlength='200' value='".htmlspecialchars($ra['pro_name'])."' placeholder='Name' /></td>"
                    ."</tr>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>Address</p></td>"
                        ."<td class='col-md-8'><input type='text' name='address' maxlength='200' value='".htmlspecialchars($ra['address'])."' placeholder='Address' /></td>"
                    ."</tr>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>City</p></td>"
                        ."<td class='col-md-8'><input type='text' name='city' maxlength='200' value='".htmlspecialchars($ra['city'])."' placeholder='City' /></td>"
                    ."</tr>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>Postal Code</p></td>"
                        ."<td class='col-md-8'><input type='text' name='postal_code' maxlength='200' value='".htmlspecialchars($ra['postal_code'])."' placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$' /></td>"
                    ."</tr>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>Phone Number</p></td>"
                        ."<td class='col-md-8'><input type='text' name='phone_number' maxlength='200' value='".htmlspecialchars($ra['phone_number'])."' placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$' /></td>"
                    ."</tr>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>Fax Number</p></td>"
                        ."<td class='col-md-8'><input type='text' name='fax_number' maxlength='200' value='".htmlspecialchars($ra['fax_number'])."' placeholder='Fax Number' /></td>"
                    ."</tr>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>Email</p></td>"
                        ."<td class='col-md-8'><input type='email' name='email' maxlength='200' value='".htmlspecialchars($ra['email'])."' placeholder='Email' /></td>"
                    ."</tr>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>Role</p></td>"
                        ."<td class='col-md-8'><select name='pro_role' id='mySelect' onchange='doUpdateForm();'>";
                        foreach ($this->pro_roles_name as $role) {
                            if($ra['pro_role'] == $role){
                                $sForm .= "<option selected />".$role;
                            } elseif($role == "Other" && !in_array($ra['pro_role'], $this->pro_roles_name)){
                                $sForm .= "<option selected />".$role;
                            } else{
                                $sForm .= "<option />".$role;
                            }
                        }
                    $sForm .= "</select>"
                        ."<input type='text' ".(in_array($ra['pro_role'], $this->pro_roles_name)?"style='display:none' disabled ":"")."required id='other' name='pro_role' maxlength='200' value='".(in_array($ra['pro_role'], $this->pro_roles_name)?"":htmlspecialchars($ra['pro_role']))."' placeholder='Role' /></td>"
                    ."</tr>"
                    ."<tr>"
                        ."<td class='col-md-4'><p>Rate</p></td>"
                            ."<td class='col-md-8'><input type='number' name='rate' value='".htmlspecialchars($ra['rate'])."' placeholder='Rate' step='1' min='0' /></td>"
                    ."<tr>"
                        ."<td class='col-md-12'><input type='submit' value='Save'/></td>"
                    ."</tr>"
                    ."</table>"
                    ."</form>"
                    ."<script>function doUpdateForm()
                    {
                        var sel = document.getElementById('mySelect').value;
                        if( sel == 'Other' ) {
                            document.getElementById('other').style.display = 'inline';
                    		document.getElementById('other').disabled = false;
                        }
                    	else {
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
}

?>
