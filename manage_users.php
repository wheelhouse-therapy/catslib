<?php

require_once( SEEDCORE."SEEDEmail.php" );

class ManageUsers {

    private $oApp;
    private $oPeopleDB;
    private $clinics;
    private $oClinicsDB;
    private $oAccountDB;

    private $sForm = "";

    public function __construct(SEEDAppConsole $oApp){
        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB($oApp);
        $this->clinics = new Clinics($oApp);
        $this->oClinicsDB = new ClinicsDB($oApp->kfdb);
        $this->oAccountDB = new SEEDSessionAccountDB($oApp->kfdb, $oApp->sess->GetUID());
    }

    public function manageUser(int $staff_key, bool $userStatus = true, int $cloneID = 0, bool $bShowSaved = false){
        $kfr = $this->getRecord($staff_key);    // gets empty record if staff_key is 0
        $oForm = new KeyframeForm( $this->oPeopleDB->KFRel(ClientList::INTERNAL_PRO), "A" );
        $oForm->SetKFR($kfr);
        $roles = ClientList::$staff_roles_name;
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
        $this->sForm = "<form onSumbit='submitForm(event)'>"
                ."<input type='hidden' id='staff_key' name='staff_key' value='$staff_key'/>"
                ."<input type='hidden' name='cmd' value='admin-userform-submit'/>"
                .($cloneID?"<input type='hidden' name='{$oForm->Name('P_uid')}' value='$cloneID'/>":"")
                .$oForm->HiddenKey()
                ."<table class='container-fluid table table-striped table-sm'>";
        $this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name'") ) );
        $this->drawPartialFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) );
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
        $this->drawPartialFormRow("Signature", "<img src='data:image/jpg;base64,".base64_encode($oForm->Value("signature"))."' style='width:100%;padding-bottom:2px' />");
        $this->drawPartialFormRow("", "<input type=\"file\" name=\"new_signature\" accept='.jpg' />");
        $this->endRowDraw();
        $this->drawFormRow( "<input type='submit' name='action' value='Save' style='margin:auto' onclick='clinicHack(event);submitForm(event);' />",
                            $bShowSaved ? "<div style='width:6em;text-align:center' class='alert alert-success'>Saved</div>" : "" );
        $this->sForm .=
             "</table>"
            ."</form>";

        $kfrClone = $this->getClinicRecord($cloneID); // Get the record of the user we are cloning
        $s = "<div class='container-fluid'>"
            ."<h3>".($staff_key?"CATS Staff: ".$oForm->Value('P_first_name')." ".$oForm->Value('P_last_name'):($cloneID?"Cloning: ".$kfrClone->Value('P_first_name')." ".$kfrClone->Value('P_last_name'):"New Staff"))."</h3>"
                ."<div class='row'>"
                   ."<div class='col-md-8'>".$this->sForm."</div>"
                   ."<div class='col-md-4'>[[Sidebar]]</div>"
                ."</div>"
            ."</div>";
        $sSidebar = "";
        if((new ScreenManager($this->oApp))->getScreen() != 'system-usersettings'){
            $sSidebar .= "<div style='padding:10px;border:1px solid #888; margin-bottom:5px'><strong>User Settings:</strong><br />";
            if(($uid = $kfr->Value('P_uid'))){
                $userInfo = $this->oAccountDB->GetUserInfo($uid);
                $status = $userInfo[1]['eStatus'];
                $sSidebar .= "Username: {$this->oAccountDB->GetEmail($uid)}<br />"
                            ."Status : {$status}<br />";
                if($userStatus){
                    switch($status){
                        case "PENDING":
                            //User has been created but credentials have not been issued
                            if($kfr->Value('P_email')){
                                $sSidebar .= "<button onclick='addSync(this);executeCMD(\"issueCredentials\",$uid)'>Issue Credentials</button>";
                            }
                            else{
                                $sSidebar .= "A valid Email must be entered for this staff before this user can be activated";
                            }
                            break;
                        case "ACTIVE":
                            //User has been created and credentials have been issued
                            $sSidebar .= "<button onclick='addSync(this);executeCMD(\"deactivate\",$uid)'>Deactivate</button>";
                            break;
                        case "INACTIVE":
                            //User has been created but has been deactivated
                            //Reactivation should reissue credentials
                            if($kfr->Value('P_email')){
                                $sSidebar .= "<button onclick='addSync(this);executeCMD(\"reissueCredentials\",$uid)'>Reactivate</button>";
                            }
                            else{
                                $sSidebar .= "A valid Email must be entered for this staff before this user can be reactivated";
                            }
                    }
                }
                else{
                    $sSidebar .= "You must wait before adjusting the status of this user";
                }
                $sSidebar .= "<br /><span title='Contact Developers to change account type'>Account Type: ".(array_key_exists(AccountType::KEY, $userInfo[2])?$userInfo[2][AccountType::KEY]:AccountType::NORMAL)."</span>";
            }
            else{
                $sSidebar .= "Staff must be saved before User Settings are available";
            }
            $sSidebar .= "</div>";
        }
        if(($uid = $kfr->Value('P_uid'))){
            $sSidebar .= "<button [[onClick]]>Copy record to clinic</button>";
            $sSidebar = str_replace("[[onClick]]", "onclick='cloneRecord(event,$uid)'", $sSidebar);
        }
        $s = str_replace("[[Sidebar]]", $sSidebar, $s);
        
        return $s;
    }

    public function userSettings(int $uid, bool $clone = false){
        $this->processUserCommands();
        $kfr = $this->getClinicRecord($uid);
        if(!$kfr || $clone){
            $kfr = $this->oPeopleDB->KFRel(ClientList::INTERNAL_PRO)->CreateRecord();
        }
        $oForm = new KeyframeForm( $this->oPeopleDB->KFRel(ClientList::INTERNAL_PRO), "A" );
        $oForm->SetKFR($kfr);
        $roles = ClientList::$staff_roles_name;
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
        $this->sForm = "<form>"
            ."<input type='hidden' id='staff_key' name='staff_key' value='{$kfr->Value('_key')}'/>"
                      ."<input type='hidden' name='cmd' value='".($clone?"user-clone":"user-save")."'/>"
                      .($clone?"<input type='hidden' name='{$oForm->Name('P_uid')}' value='$uid'/>":"")
                      .$oForm->HiddenKey()
                      ."<table class='container-fluid table table-striped table-sm'>";
        $this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name'") ) );
        $this->drawPartialFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) );
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
        $this->drawPartialFormRow("Signature", "<img src='data:image/jpg;base64,".base64_encode($oForm->Value("signature"))."' style='width:100%;padding-bottom:2px' />");
        $this->drawPartialFormRow("", "<input type=\"file\" name=\"new_signature\" accept='.jpg' />");
        $this->endRowDraw();
        $this->sForm .= "<tr class='row'>"
                       ."<td class='col-md-12'><input type='submit' name='action' value='Save' style='margin:auto' onclick='clinicHack(event);submitForm(event);' /></td>"
                       ."</tr>"
                       ."</table>"
                       ."</form>";
        $kfrClone = $this->getClinicRecord($uid); // Get the record of the user we are cloning
        $s = "<div class='container-fluid'>"
            .($clone?"<h3>Cloning: ".$kfrClone->Value('P_first_name')." ".$kfrClone->Value('P_last_name')."</h3>":"")
            ."<div class='row'>"
                ."<div class='col-md-8'>".$this->sForm."</div>"
                ."<div class='col-md-4'>[[Sidebar]]</div>"
            ."</div>"
            ."</div>";
        $sSidebar = "";
        if(($uid = $kfr->Value('P_uid')) && $kfr->Value("clinic") != $this->clinics->GetCurrentClinic($uid)){
            $sSidebar .= "<button [[onClick]]>Create Profile for this clinic</button>";
            $sSidebar = str_replace("[[onClick]]", "onclick='window.location = \"?clone=true\"'", $sSidebar);
        }
        $s = str_replace("[[Sidebar]]", $sSidebar, $s);

        $s .= "<script>
                    function doUpdateForm() {
                        var sel = document.getElementById('mySelect').value;
                        if( sel == 'Other' ) {
                            document.getElementById('other').style.display = 'inline';
                            document.getElementById('other').disabled = false;
                        } else {
                            document.getElementById('other').style.display = 'none';
                            document.getElementById('other').disabled = true;
                        }
                    }
                    function clinicHack(e) {
	                   $(\"select\",e.currentTarget.form).prop(\"disabled\", false);
                    }
               </script>";

        return $s;
    }

    public function drawList($raw = false){
        $s = "";
        $condStaff = "P.uid in (SELECT fk_SEEDSession_users FROM users_clinics WHERE fk_clinics = {$this->clinics->GetCurrentClinic()})";
        if($this->clinics->isCoreClinic()){
            $condStaff = "";
        }
        $raTherapists = $this->oPeopleDB->GetList(ClientList::INTERNAL_PRO, $condStaff, array("sSortCol" => "P.first_name,_key"));
        if(!$raw){
            $s .= "<div class='container-fluid'><div class='row'>"
                 ."<div id='users' class='col-md-4'>";
        }
        $s .= "<button onclick='getForm(0)'>New User</button>"
              .SEEDCore_ArrayExpandRows( $raTherapists, "<div style='padding:5px;cursor:pointer' onclick='getForm([[_key]])' >[[P_first_name]] [[P_last_name]] is a [[pro_role]]%[[clinic]]</div>" );
        if(!$raw){
            $s .= "</div>"
                 ."<div id='form' class='col-md-8'></div></div></div>";
        }
        foreach($this->oClinicsDB->KFRel()->GetRecordSetRA("") as $clinic){
            if($this->clinics->isCoreClinic()){
                $s = str_replace("%".$clinic['_key'], " @ the ".$clinic['clinic_name']." clinic", $s);
            }
            else {
                $s = str_replace("%".$clinic['_key'], "", $s);
            }
        }
        return $s;
    }

    public function saveForm($clone = false){
        $staff_key = SEEDInput_Int('staff_key'); // Get the staff Key from the form
        $oForm = new KeyframeForm( $this->oPeopleDB->KFRel(ClientList::INTERNAL_PRO), "A" );
        $clinic = 0;
        if($staff_key){
            // Get the clinic in the db (to detect changes)
            $clinic = $this->oApp->kfdb->Query1("SELECT clinic FROM pros_internal WHERE _key = $staff_key");
        }

        $oForm->Update(); // Update the db (save changes)
        $this->updatePeople( $oForm ); // Save changes to the people fields

        $staff_key = $oForm->GetKey(); // Update the staff key

        //Handle Signature Upload
        if(@$_FILES["new_signature"]["tmp_name"]){
            $this->oApp->kfdb->Execute("UPDATE pros_internal SET signature = '".addslashes(file_get_contents($_FILES["new_signature"]["tmp_name"]))."' WHERE pros_internal._key = ".$staff_key);
        }

        if($oForm->Value('P_uid') && $this->clinics->getUserClinic(0,$oForm->Value('P_uid'))['Clinics__key'] == $clinic){
            // The user is changing their primary info change their user info to match
            $username = strtolower(substr($oForm->Value('P_first_name'), 0,1).$oForm->Value('P_last_name'));
            $realname = $oForm->Value('P_first_name')." ".$oForm->Value('P_last_name');
            $userInfo = $this->oAccountDB->GetUserInfo($oForm->Value('P_uid'))[1];
            @list($fname,$lname) = explode(" ", $userInfo['realname']);
            $lname = $lname?:"";
            $uid = $oForm->Value('P_uid');
            if($userInfo['email'] == strtolower(substr($fname, 0,1).$lname) && $userInfo['email'] != $username){
                $this->oApp->kfdb->Execute("UPDATE seedsession_users SET _updated=NOW(),_updated_by={$this->oApp->sess->GetUID()},email='$username' WHERE seedsession_users._key = $uid");
            }
            if($realname != $userInfo['realname']){
                $this->oApp->kfdb->Execute("UPDATE seedsession_users SET _updated=NOW(),_updated_by={$this->oApp->sess->GetUID()},realname='$realname' WHERE seedsession_users._key = $uid");
            }
        }
        else if(!$oForm->Value("P_uid")){
            $username = strtolower(substr($oForm->Value('P_first_name'), 0,1).$oForm->Value('P_last_name'));
            $realname = $oForm->Value('P_first_name')." ".$oForm->Value('P_last_name');
            $uid = $this->oAccountDB->CreateUser($username, 'cats',['realname'=>$realname,'gid1'=>4]);
            $this->oApp->kfdb->Execute("UPDATE people SET uid = $uid WHERE people._key = ".$oForm->ValueInt('fk_people'));
        }
        if($clinic != $oForm->Value('clinic') && !in_array($oForm->Value('clinic'),array_column($this->clinics->GetUserClinics(), 'Clinics__key'))){
            if($clinic){
                $this->oApp->kfdb->Execute("UPDATE users_clinics SET _updated=NOW(),_updated_by={$this->oApp->sess->GetUID()},fk_clinics={$oForm->Value('clinic')} WHERE fk_SEEDSession_users = {$oForm->Value('P_uid')} AND fk_clinics = $clinic");
            }
            else{
                $this->oApp->kfdb->Execute("INSERT INTO users_clinics( _created, _created_by, _updated, _updated_by, _status, fk_SEEDSession_Users, fk_clinics) VALUES (NOW(),{$this->oApp->sess->GetUID()},NOW(),{$this->oApp->sess->GetUID()},0,{$oForm->Value('P_uid')},{$oForm->Value('clinic')})");
            }
        }


        return ["list"=>$this->drawList(true),"form"=>$this->manageUser($staff_key, true, 0, true)];

    }

    public function processCommands(String $cmd,int $uid = 0){
        if($uid == 0){
            $uid = $this->oApp->sess->GetUID();
        }
        switch($cmd){
            case 'issueCredentials':
                $info = $this->oAccountDB->GetUserInfo($uid,false)[1];
                $body = <<<body
Hi [[name]],
 
We are writing to let you know that an account for you has been setup on the backend of the CATS site (catherapyservices.ca/cats). We call the part of the site that requires an account to access the backend, and the part of the site that is available to the public the frontend.
 
To access your account:
1.	Go to catherapyservices.ca/cats
2.	Login with:
Username: [[username]]
Password: cats
3.	You will then be prompted to change your password. You will not be able to get off this screen until you change your password from cats. Other than that there are no requirements for your password.

Please let us know if you don�t have permission to do something that you should have permission to do.
 
Welcome to the CATS Team
 
CATS Development Team
 
The development team can be reached at developer@catherapyservices.ca or through the support button (Next to the home and logout button, looks like a question mark).

body;
                $body = str_replace(['[[name]]','[[username]]'], [$info['realname'],$info['email']], $body);
                SEEDEmailSend("developer@catherapyservices.ca", $this->getClinicRecord($uid)->Value('P_email'), "CATS Backend Account", $body);
                $this->oAccountDB->ActivateUser($uid);
                break;
            case 'deactivate':
                $this->oApp->kfdb->Execute("UPDATE `seedsession_users` SET _updated=NOW(),_updated_by={$this->oApp->sess->GetUID()},eStatus='INACTIVE' WHERE _key = $uid");
                break;
            case 'reissueCredentials':
                $info = $this->oAccountDB->GetUserInfo($uid,false)[1];
                $body = <<<body
Hi [[name]],

We are writing to let you know that your account on the backend of the CATS site (catherapyservices.ca/cats) has been reactivated.

As a reminder, to access your account:
1.	Go to catherapyservices.ca/cats
2.	Login with:
Username: [[username]]
Password: Same as before (use the reset password link on the login page if you don't remember it)

Please let us know if you don�t have permission to do something that you should have permission to do.

Welcome back to the CATS Team

CATS Development Team

Reminder the development team can be reached at developer@catherapyservices.ca or through the support button (Next to the home and logout button, looks like a question mark).

body;
                $body = str_replace(['[[name]]','[[username]]'], [$info['realname'],$info['email']], $body);
                SEEDEmailSend("developer@catherapyservices.ca", $this->getClinicRecord($uid)->Value('P_email'), "CATS Backend Account", $body);
                $this->oAccountDB->ActivateUser($uid);
                break;
        }
        return $this->manageUser($uid,false);
    }

    public function processUserCommands(){
        $cmd = SEEDInput_Str("cmd");
        switch($cmd){
            case "user-save":
                $this->saveForm();
                break;
            case "user-clone":
                $this->saveForm(true);
        }
    }

    public function getClinicRecord(int $uid):KeyframeRecord{
        if($uid){
            $ra = $this->oPeopleDB->GetList(ClientList::INTERNAL_PRO, "P.uid = $uid");
            $kfr = $this->oPeopleDB->GetKfrel(ClientList::INTERNAL_PRO)->CreateRecord();
            if(count($ra) == 0){goto done;}
            $kfr->LoadValuesFromRA($ra[0]);
            $clinic = $this->clinics->GetCurrentClinic();
            if($clinic != $ra[0]['clinic']){
                if(count($ra) > 1){
                    foreach($ra as $record){
                        if($clinic == $record['clinic']){
                            $kfr->LoadValuesFromRA($record);
                        }
                    }
                }
            }
            done:
            return $kfr;
        }
        else{
            return $this->oPeopleDB->GetKfrel(ClientList::INTERNAL_PRO)->CreateRecord();
        }
    }

    /**
     * Check if the clinic profile for the given user is valid.
     * A valid profile should not be limited in functionality
     * A profile is considered valid if the following fields are filled out:
     * First Name
     * Last Name
     * Email
     *
     * @param int $uid - uid of user to check
     * @return bool - true if the profile is filled out where all features are unlocked. False otherwise
     */
    public function profileValid(int $uid):bool{
        $valid = true;
        $kfr = $this->getClinicRecord($uid);
        if(!$kfr){
            $valid = false;
        }
        else if(!$kfr->Value("P_first_name")){
            $valid = false;
        }
        else if(!$kfr->Value("P_last_name")){
            $valid = false;
        }
        else if(!$kfr->Value("P_email")){
            $valid = false;
        }
        else if(!$kfr->Value("pro_role")){
            $valid = false;
        }
        return $valid;
    }

    private function getRecord(int $staff_key):KeyframeRecord{
        if($staff_key){
            return $this->oPeopleDB->GetKFR(ClientList::INTERNAL_PRO, $staff_key);
        }
        else{
            return $this->oPeopleDB->GetKfrel(ClientList::INTERNAL_PRO)->CreateRecord();
        }
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
                       ."<td class='col-md-5'><span>[[label]]</span></td>"
                       ."<td class='col-md-7'>[[control]]</td>"
                       ."</tr>";
    }

    private function endRowDraw(){
        $this->sForm = str_replace(array("[[label]]","[[control]]", "[[style]]"), "", $this->sForm);
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

    private function updatePeople( SEEDCoreForm $oForm )
    /***************************************************
     The relations C, PI, and PE join with P. When those are updated, the P_* fields have to be copied to table 'people'
     */
    {
        $peopleFields = array('pronouns','first_name','last_name','address','city','province','postal_code','dob','phone_number','email' );

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
            if( $oForm->Value('P_uid')) $kfr->SetValue('uid',$oForm->Value("P_uid"));
            $kfr->PutDBRow();
            $oForm->SetValue("fk_people", $kfr->Key());
            $oForm->Store();
        }
    }

}

class ManageUsers2 {
    
    private const DEFAULT_PROFILE = 'defaultProfile';
    
    private $oApp;
    private $oAccountDB;
    private $oPeopleDB;
    private $oClinicsDB;
    private $oClinics;
    /**
     * List of ids of staff user accounts.
     * Use $this->oAccountDB->GetUserInfo to get the info for a user in this list.
     */
    private $raUsers;
    
    private const TAB_STYLE = <<<Style
<style>
:root {
	--tab-color: lightgrey;
	--tab-rounding: 8px;
}
.tabs {
	display: inline-block;
	box-sizing: border-box;
	margin-top: 10px;
	margin-bottom: 20px;
	width: 100%;
	min-height: 30px;
}
.tab {
	display: inline-block;
	min-width: 20%;
	padding: 5px 10px;
	height: 100%;
	background-color: var(--tab-color);
	vertical-align: middle;
	text-align: center;
	border: 1px solid var(--tab-color);
	border-bottom: none;
	border-radius: var(--tab-rounding) var(--tab-rounding) 0 0;
	box-sizing: border-box;
	cursor: default;
	user-select: none;
}
.tab.active-tab {
	background-color: white;
}
</style>
Style;
    
    public function __construct(SEEDAppConsole $oApp){
        $this->oApp = $oApp;
        $this->oAccountDB = new SEEDSessionAccountDB($oApp->kfdb, $oApp->sess->GetUID());
        $this->oPeopleDB = new PeopleDB($oApp);
        $this->oClinicsDB = new ClinicsDB($oApp->kfdb);
        $this->oClinics = new Clinics($oApp);
        
        // Get list of users
        $this->raUsers = [];
        $raGroups = $this->oApp->kfdb->QueryRowsRA1("SELECT _key FROM seedsession_groups");
        foreach($raGroups as $group){
            if(stripos($this->oApp->kfdb->Query1("SELECT groupname FROM seedsession_groups WHERE _key = $group"),"client") !== false){
                //Skip the client group
                continue;
            }
            // Get a list of all the users.
            // A user may be in more than 1 group so we need to make the list unique
            $this->raUsers = array_unique(array_merge($this->raUsers,$this->oAccountDB->GetUsersFromGroup($group,['eStatus' => "'ACTIVE','INACTIVE','PENDING'",'bDetail' => false])));
        }
    }
    
    /**
     * Draw the manage users UI
     * @return String
     */
    public function drawUI():String{
        
        $s = "";
        $s .= self::TAB_STYLE;
        $s .= "<div class='container-fluid'><div class='row'>"
            ."<div id='users' class='col-md-4'>";
        $s .= $this->drawList();
        $s .= "</div>"
            ."<div id='form' class='col-md-8'>{$this->drawManageForm(0)}</div></div></div>";
        return $s;
    }
    
    /**
     * Draw the list of users.
     * @return String
     */
    public function drawList():String{
        
        $s = "";
        // List them like in v1
        foreach($this->raUsers as $userid){
            $raUser = $this->oAccountDB->GetUserInfo($userid,false);
            if(!$raUser[0]){continue;}
            $s .= "<div style='padding:5px;cursor:pointer'>".$raUser[1]['realname']."</div>";
        }
        return $s;
    }
    
    public function processCommands(String $cmd){
        
        // If name is changed update account name and ALL of the clinic records with the new name.
        
        //Valid Commands: newProfile, updateProfile
        $cmd = SEEDInput_Str("cmd");
        
        
    }
    
    /**
     * Draw the manage users form for a user.
     * This form allows admins to manage the profiles and account settings of a user.
     * It differs from drawProfile() in that there is a tab for the profile form and a tab for account settings.
     * The account settings are not availible to regular users and so the these higher level tabs are not rendered when a user is viewing their profile though they My Profile bubble.
     * @param int $uid - user id of the user to draw the form for.
     * @return String
     */
    public function drawManageForm(int $uid):String{
        
        $s = "";
        if($uid <= 0){
            $uid = $this->oApp->sess->GetUID();
        }
        $s .= "<div class='tabs'>";
        $s .= "<div id='profile-Tab' class='tab active-tab'>Profile</div>";
        $s .= "<div id='account-Tab' class='tab'>Account</div>";
        $s .= "</div><br/><div id='outerTab-content'>";
        $s .= $this->drawProfileForm($uid);
        $s .= "</div>";
        $s .= "<script>const profile = JSON.parse(`".json_encode($this->drawProfileForm($uid))."`);";
        $s .= "const account = JSON.parse(`".json_encode($this->drawAccountForm($uid))."`);</script>";
        return $s;
    }
    
    
    /**
     * Draw the profile form for the current user.
     * This allows the user to edit their own profiles.
     * @return String
     */
    public function drawProfile():String{
        
        $s = "";
        $s .= self::TAB_STYLE;
        $s .= $this->drawProfileForm($this->oApp->sess->GetUID());
        return $s;
    }
    
    /**
     * Get a users profile for the current clinic.
     * @param int $uid - user id of the user.
     * @return array of size 2 - $ra['kfr'] = KeyframeRecord of the profile, $ra['defaulted'] = true if the profile returned is the users default profile and not the clinic profile
     */
    public function getClinicProfile(int $uid):array{
        return $this->getProfile($uid, 0);
    }
    
    /**
     * Get the current users profile for a specific clinic.
     * @param int $clinic - clinic to get the user's profile for.
     * @return array of size 2 - $ra['kfr'] = KeyframeRecord of the profile, $ra['defaulted'] = true if the profile returned is the users default profile and not the clinic profile
     */
    public function getUserProfile(int $clinic):array{
        return $this->getProfile(0, $clinic);
    }
    
    /**
     * Get a users profile for a specific clinic.
     * @param int $uid - user id of the user.
     * @param int $clinic - clinic to get the user's profile for.
     * @return array of size 2 - $ra['kfr'] = KeyframeRecord of the profile, $ra['defaulted'] = true if the profile returned is the users default profile and not the clinic profile
     */
    public function getProfile(int $uid,int $clinic):array{
        if($uid <= 0){
            $uid = $this->oApp->sess->GetUID();
        }
        if($clinic <= 0){
            $clinic = $this->oClinics->GetCurrentClinic();
        }
        $bDefaulted = false;
        $kfr = $this->oPeopleDB->GetKFRCond(ClientList::INTERNAL_PRO, "P.uid=$uid AND clinic=$clinic");
        if($kfr == null){
            $defaultProfile = @$this->oAccountDB->GetUserMetadata($uid)[self::DEFAULT_PROFILE]?:0;
            $bDefaulted = true;
            if($defaultProfile > 0){
                $kfr = $this->oPeopleDB->GetKFR(ClientList::INTERNAL_PRO, $defaultProfile);
            }
            if($kfr == null){
                //Failed to get the default profile for some reason or one wasn't set
                $kfr = $this->oPeopleDB->GetKfrel(ClientList::INTERNAL_PRO)->CreateRecord();
            }
        }
        return ['kfr'=>$kfr,'defaulted'=>$bDefaulted];
    }
    
    /**
     * Draw a tab for each clinic the user is in and render the form for each of the tabs.
     * @param int $uid - user to render the entire profile form for.
     * @return String - The complete profile form.
     */
    private function drawProfileForm(int $uid):String{
        
        // When user selected provide tabs for the different records in each clinic.
        $s = "<div class='tabs'>";
        $raClinics = array_column($this->oClinics->GetUserClinics($uid),"Clinics__key");
        $defaultProfile = @$this->oAccountDB->GetUserMetadata($uid)[self::DEFAULT_PROFILE]?:0;
        if($defaultProfile == 0){
            $this->setDefaultProfile($uid, $this->getProfile($uid, $raClinics[0])['kfr']->Value("_key"));
            echo "Default Profile set to Clinic #{$raClinics[0]}<br />";
        }
        $raForms = [];
        $i = 1;
        $activeTab = 1;
        foreach($raClinics as $clinic){
            $clinicName = ($this->oClinicsDB->GetClinic($clinic)->Value('nickname')?:$this->oClinicsDB->GetClinic($clinic)->Value('clinic_name'));
            $raProfile = $this->getProfile($uid, $clinic);
            $profile = $raProfile['kfr']->ValuesRA();
            $sDefault = (!$raProfile['defaulted'] && $defaultProfile == $profile['_key']?" (default)":"");
            if($sDefault){
                $s .= "<div id='tab{$i}' class='tab active-tab'>$clinicName$sDefault</div>";
                $activeTab = $i;
            }
            else{
                $s .= "<div id='tab{$i}' class='tab'>$clinicName$sDefault</div>";
            }
            $raForms["tab$i"] = $this->drawInternalProfileForm($uid, $clinic);
            $i++;
        }
        $s .= "</div><br/><div id='tab-content'>".$raForms["tab$activeTab"]."</div>";
        $s .= "<script>const forms = JSON.parse(`".json_encode($raForms)."`);</script>"; // Pass the tab contents to JS
        return $s;
    }
    
    /**
     * Draw the form within the users profile tabs.
     * @param int $uid - user id of the user to draw the form of.
     * @param int $clinic - clinic of the profile to draw
     * @param String $cmd - command of the form
     * @return String - The form displaying the users profile or a message stating the clinic uses the default profile and an option to create a clinic profile.
     */
    private function drawInternalProfileForm(int $uid, int $clinic, String $cmd = "updateProfile"):String{
        
        $s = "";
        $raProfile = $this->getProfile($uid, $clinic);
        $kfr = $raProfile['kfr'];
        
        if($raProfile['defaulted']){
            return "<div style='text-align:center'>This Clinic uses the information from the default profile.<wbr>You can create a clinic specific one here."
                  ."<a href='?cmd=newProfile&uid=$uid&clinicId=$clinic'><button>Create Clinic Profile</button></a>"
                  ."</div>";
        }
        
        $oForm = new KeyframeForm( $this->oPeopleDB->KFRel(ClientList::INTERNAL_PRO), "A" );
        $oForm->SetKFR($kfr);
        $roles = ClientList::$staff_roles_name;
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
        $s .= "<form>"
             ."<input type='hidden' name='cmd' value='$cmd' />"
             ."<input type='hidden' name='uid' value='$uid' />"
             .$oForm->HiddenKey()
             ."<table class='container-fluid table table-striped table-sm'>";
        
        $s .= $this->drawFormRow( "First Name", $oForm->Text('P_first_name',"",array("attrs"=>"required placeholder='First Name'") ) );
        $s .= $this->drawFormRow( "Last Name", $oForm->Text('P_last_name',"",array("attrs"=>"required placeholder='Last Name'") ) );
        $s .= $this->drawFormRow( "Address", $oForm->Text('P_address',"",array("attrs"=>"placeholder='Address'") ) );
        $s .= $this->drawFormRow( "City", $oForm->Text('P_city',"",array("attrs"=>"placeholder='City'") ) );
        $s .= $this->drawFormRow( "Province", $oForm->Text('P_province',"",array("attrs"=>"placeholder='Province'") ) );
        $s .= $this->drawFormRow( "Postal Code", $oForm->Text('P_postal_code',"",array("attrs"=>"placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$'") ) );
        $s .= $this->drawFormRow( "Phone Number", $oForm->Text('P_phone_number', "", array("attrs"=>"placeholder='Phone Number' maxlength='200'") ) );
        $s .= $this->drawFormRow( "Fax Number", $oForm->Text('fax_number', "", array("attrs"=>"placeholder='Fax Number' pattern='^(\d{3}[-\s]?){2}\d{4}$'") ) );
        $s .= $this->drawFormRow( "Email", $oForm->Email('P_email',"",array("attrs"=>"placeholder='Email'") ) );
        $s .= $this->drawFormRow( "Pronouns", $this->getPronounList($oForm) );
        $s .= $this->drawFormRow( "Role", $selRoles );
        $s .= $this->drawFormRow( "Credentials", $oForm->Text('P_extra_credentials',"",array("attrs"=>"placeholder='To be shown after name'")));
        $s .= $this->drawFormRow( "Registration number", $oForm->Text('P_extra_regnumber',"",array("attrs"=>"placeholder='Registration number'")));
        $s .= $this->drawFormRow( "Rate","<input type='number' name='".$oForm->Name('rate')."' value='".$oForm->ValueEnt('rate')."' placeholder='Hourly rate' step='1' min='0' />" );
        $s .= $this->drawFormRow("Signature", "<img src='data:image/jpg;base64,".base64_encode($oForm->Value("signature"))."' style='width:100%;padding-bottom:2px' /><br /><input type=\"file\" name=\"new_signature\" accept='.jpg' />");
        $s .= "<tr class='row'><td class='col-md-12'><input id='save-button' type='submit' value='Save' /></tr>";
        
        $s .= "</table></form>";
        return $s;
    }
    
    /**
     * Draw the account information form for a user
     * @param int $uid - user to draw the form for
     * @return String
     */
    private function drawAccountForm(int $uid):String{
        
        $s = "";
        //TODO Draw User Form
        return $s;
    }
    
    private function drawFormRow( $label, $control ) {
        return( "<tr class='row'>"
            ."<td class='col-md-5'><p>$label</p></td>"
            ."<td class='col-md-7'>$control</td>"
            ."</tr>" );
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
    
    private function setDefaultProfile(int $uid, int $profile):bool{
        if(!in_array($profile, $this->oPeopleDB->Get1List(ClientList::INTERNAL_PRO, "_key", "P.uid=".$uid))){
            return false;
        }
        $this->oAccountDB->SetUserMetadata($uid, self::DEFAULT_PROFILE, $profile);
        return true;
    }
    
}

/**
 * Constants for the various account types.
 * For use with the password reset system.
 * @author Eric
 *
 */
abstract class AccountType {
    
    /**
     * The key in the account metadata that holds the account type.
     */
    public const KEY = "accountType";
    
    /**
     * Normal User
     */
    public const NORMAL = "normal";
    
    /**
     * Student User
     * Let them reset their password regardless of username
     */
    public const STUDENT = "student";
    
    /**
     * Developer User
     * Doesn't do anything but probably comes with the administrator permission.
     */
    public const DEVELOPER = "dev";
    
}