<?php
require_once 'handle_images.php';

class Clinics {
    private $oApp;
    private $clinicsDB;

    //Access Rights
    private const NO_ACCESS     = 0;
    private const LEADER_ACCESS = 1;
    private const FULL_ACCESS   = 2;
    
    public const LOGO_SQUARE    = 10;
    public const LOGO_WIDE      = 11;
    public const FOOTER         = 12;
    
    function __construct( SEEDAppSessionAccount $oApp ) {
        $this->oApp = $oApp;
        $this->clinicsDB = new ClinicsDB($this->oApp->kfdb);
    }

    function isCoreClinic(){
        return $this->GetCurrentClinic() == 1;
    }

    /**
     * Returns the current clinic the user is looking at
     * A result of NULL means a clinic has not been specefied
     * A list of accessable clinics should be presented at this point
     *
     * A user with access to the core clinic will never return NULL through this call.
     * Clinic leaders default to the first clinic they lead.
     */
    function GetCurrentClinic($user = NULL){

        if(!$user){
            $user = $this->oApp->sess->GetUID();
        }

        $clinicsra = $this->GetUserClinics($user);
        
        foreach($clinicsra as $clinic){
            if($clinic['Clinics__key'] == $this->oApp->sess->SmartGPC('clinic')){
                return $this->oApp->sess->SmartGPC('clinic');
            }
        }
        $k = NULL;
        foreach ($clinicsra as $clinic){
            if($clinic["Clinics_clinic_name"] == "Core"){
                return $clinic["Clinics__key"];
            }
            else if($clinic["Clinics_fk_leader"] == $user && $k == NULL){
                $k = $clinic['Clinics__key']; // The user is the leader of this clinic
            }
            else if(count($clinicsra) == 1){
                $k = $clinic['Clinics__key']; // The user only has one clinic
            }
        }
        return $k;
    }

    /**
     * Returns a list of clinics that the user is part of (accessable)
     *
     * A clinic is considerd accessable to the user by CATS if they are part of that clinic
     * ie. their user id is mapped to the clinic id in the Users_Clinics Database table
     */
    function GetUserClinics($user = NULL){

        if(!$user){
            $user = $this->oApp->sess->GetUID();
        }

        if($user == 1){
            // We are working with the developer account. Grant access to all clinics.
            $clinics = $this->clinicsDB->KFRel()->GetRecordSetRA("");
            foreach($clinics as $k => $ra){
                $clinics1 = array();
                foreach($ra as $k1 => $v){
                    $clinics1["Clinics_".$k1] = $v;
                }
                $clinics[$k] = $clinics1;
            }
            return $clinics;
        }
        
        $UsersClinicsDB = new Users_ClinicsDB($this->oApp->kfdb);
        $clinics = $UsersClinicsDB->KFRel()->GetRecordSetRA("Users._key='".$user."'");
        $leading = $this->clinicsDB->KFRel()->GetRecordSetRA("Clinics.fk_leader='".$user."'");
        foreach($leading as $k => $ra){
            if($this->containsClinic($ra, $clinics)){
                unset($leading[$k]);
                continue;
            }
            $leading1 = array();
            foreach($ra as $k1 => $v){
                $leading1["Clinics_".$k1] = $v;
            }
            $leading[$k] = $leading1;
        }
        return array_merge($clinics,$leading);
    }

    /**
     * Get the clinic at the given position in the users clinic
     * @param int $clinicPosition - position of clinic to fetch. ZERO BASED.
     * @param int|NULL $user - user to get clinics of. Default NULL = currently signed it user
     */
    public function getUserClinic(int $clinicPosition, $user = NULL){
        $clinics = $this->GetUserClinics($user);
        return $clinics[$clinicPosition];
    }
    
    function displayUserClinics($selector = false){
        $s = "";
        foreach($this->GetUserClinics() as $ra){
            if($s){
                $s .=", ";
            }
            if($ra['Clinics__key'] == $this->GetCurrentClinic()){
                $s .= "<span class='selectedClinic'> ".$ra['Clinics_clinic_name']."</span>";
            }
            else {
                $s .= "<a href='?clinic=".$ra['Clinics__key']."'".($selector?" class='selectedClinic'":"").">".$ra['Clinics_clinic_name']."</a>";
            }
        }
        return($s);
    }

    private function containsClinic($needle,$haystack){
        //This function checks if the clinic already exists in the list of user clinics
        $name = '';
        if(array_key_exists('clinic_name', $needle)){
            $name = $needle['clinic_name'];
        }
        elseif (array_key_exists('Clinics_clinic_name', $needle)){
            $name = $needle['Clinics_clinic_name'];
        }
        if(!$name) return NULL; // Not a valid clinic array
        foreach($haystack as $k => $v){
            if($haystack[$k]['Clinics_clinic_name'] == $name){
                return TRUE;
            }
        }
        return FALSE;
    }

    private function checkAccess($user = NULL){
        if(!$user){
            $user = $this->oApp->sess->GetUID();
        }
        if($this->oApp->sess->GetUID() == $user){
            $bFull = CATS_SYSADMIN;
        }
        else{
            // Little Hack to get arround the current login to check another users perms
            $bFull = in_array("administrator",$this->oApp->sess->oDB->GetPermsFromUser($user)["modes2perms"]["R"]);
        }
        if($bFull){
            // The user has full access to clinic settigs
            return self::FULL_ACCESS;
        }
        if($this->getClinicsILead($user)){
            // The user has leads a clinic and therefore can access that clinic settings
            return self::LEADER_ACCESS;
        }
        // The user does not have access to any clinic settings
        return self::NO_ACCESS;
    }
    
    /** Get array of clinic keys by email
     * This should not be relided on to produce unique results
     * Because emails may not me unique this method returns an array of matching clinic keys
     * As Clinics can have the same email address on file
     * @param String $email Email of the clinic to fetch
     * @return array of keys of clinics which match the email
     */
    public function getClinicsByEmail(String $email){
        $clinics = $this->clinicsDB->KFRel()->GetRecordSetRA("Clinics.email='".$email."'");
        $clinicKeys = array();
        foreach($clinics as $k => $v){
            array_push($clinicKeys, $v["_key"]);
        }
        return $clinicKeys;
    }
    
    public function getClinicsByName(String $name){
        $clinics = $this->clinicsDB->KFRel()->GetRecordSetRA("Clinics.clinic_name='".$name."'");
        $clinicKeys = array();
        foreach($clinics as $k => $v){
            array_push($clinicKeys, $v["_key"]);
        }
        return $clinicKeys;
    }
    
    public function getClinicsByCity(String $city){
        $clinics = $this->clinicsDB->KFRel()->GetRecordSetRA("Clinics.city'".$city."'");
        $clinicKeys = array();
        foreach($clinics as $k => $v){
            array_push($clinicKeys, $v["_key"]);
        }
        return $clinicKeys;
    }
    
    public function getClinicsILead($user = NULL){
        if(!$user){
            $user = $this->oApp->sess->GetUID();
        }
        $clinics = $this->clinicsDB->KFRel()->GetRecordSetRA("Clinics.fk_leader='".$user."'");
        $clinicKeys = array();
        foreach($clinics as $k => $v){
            array_push($clinicKeys, $v["_key"]);
        }
        return $clinicKeys;
    }
    
    //TODO Deprecate
    public function getClinicsWithAkaunting($user=NULL){
        $accessType = $this->checkAccess($user);
        if(!$accessType == self::FULL_ACCESS && !$this->isCoreClinic()){
            //Not core clinic and user does not have full access to clinic settings
            return false;
        }
        return( array_column($this->clinicsDB->KFRel()->GetRecordSetRA("akaunting_company != 0"),'_key') );
    }
    
    public function getUsersInClinic(int $clinic, bool $invert=false){
        $users = $this->oApp->kfdb->QueryRowsRA("SELECT fk_SEEDSession_users FROM users_clinics WHERE fk_clinics = ".$clinic.";");
        $users1 = array();
        if(!$invert){
            foreach(array_column($users, "fk_SEEDSession_users") as $key){
                array_push($users1,$this->oApp->kfdb->QueryRA("SELECT * FROM SEEDSession_Users WHERE _key = ".$key.";"));
            }
        }
        else{
            $sql = "SELECT * FROM SEEDSession_Users";
            foreach(array_column($users, "fk_SEEDSession_users") as $key){
                if(str_word_count($sql,0,"_*") == 4){
                    $sql .= " WHERE _key != ".$key;
                }
                else{
                    $sql .= " AND _key != ".$key;
                }
            }
            $sql .= ";";
            $users1 = $this->oApp->kfdb->QueryRowsRA($sql);
        }
        return $users1;
    }
    
    public function getImage(int $imageID, $clinic = null){
        if($clinic == null){
            $clinic = $this->GetCurrentClinic();
        }
        $filepath = CATSDIR_FILES."clinic Images/".$clinic."/";
        $filename = "";
        switch ($imageID){
            case self::LOGO_SQUARE:
                $filename = "Logo Square";
                break;
            case self::LOGO_WIDE:
                $filename = "Logo Wide";
                break;
            case self::FOOTER:
                $filename = "Footer";
                break;
        }
        $filename = $this->getFile($filepath, $filename);
        if(strpos($filename, ".") === false || !file_exists($filepath.$filename)){
            switch ($imageID){
                case self::LOGO_SQUARE:
                    $filepath = CATSDIR_IMG."cats_square.png";
                    break;
                case self::LOGO_WIDE:
                    $filepath = CATSDIR_IMG."cats_wide.png";
                    break;
                case self::FOOTER:
                    $filepath = FALSE;
            }
            goto done;
        }
        $filepath .= $filename;
        done:
        return $filepath;
    }
    
    public function setImage($imageID, bool $isRestore = false, $clinic = null){
        $accessType = $this->checkAccess();
        if($accessType == self::NO_ACCESS){
            return "No Access"; // Abort the opperation because the user lacks the permission to perform the action
        }
        if($clinic == null){
            $clinic = $this->GetCurrentClinic();
        }
        $filepath = CATSDIR_FILES."clinic Images/".$clinic."/";
        if(!file_exists($filepath)){
            @mkdir($filepath, 0777, true);
        }
        $filename = "";
        switch ($imageID){
            case self::LOGO_SQUARE:
                $filename = "Logo Square";
                break;
            case self::LOGO_WIDE:
                $filename = "Logo Wide";
                break;
            case self::FOOTER:
                $filename = "Footer";
                break;
        }
        if($isRestore){
            $filename .= " old";
            $filename = $this->getFile($filepath,$filename);
            if(strpos($filename, ".") === false || !file_exists($filepath.$filename)){
                $filename = $this->getFile($filepath, str_replace(" old", "", $filename));
                if(strpos($filename, ".") !== false && file_exists($filepath.$filename)){
                    if(unlink($filepath.$filename)){
                        return "Default Restored";
                    }
                    return "Unable to restore default image";
                }
                return "No Backup Found"; // Abort because we could not find a backup to restore
            }
            else{
                if(rename($filepath.$filename, $filepath.str_replace(" old", "", $filename))){
                    return "Restore successful";
                }
                else{
                    return "Error restoring";
                }
            }
        }
        else{
            $documentFileType = strtolower(pathinfo(basename($_FILES["clinicImage"]["name"]),PATHINFO_EXTENSION));
            if(!in_array($documentFileType, array("gif","jpg","png"))){
                return "Only .jpg,.png,and .gif Images are supported";
            }
            else{
                $newFilename = $filename;
                $filename = $this->getFile($filepath,$filename);
                if(strpos($filename, ".") !== false && file_exists($filepath.$filename)){
                    rename($filepath.$filename, $filepath.str_replace(".", " old.", $filename));
                }
                if(move_uploaded_file($_FILES["clinicImage"]["tmp_name"], $filepath.$newFilename.".".$documentFileType)){
                    return "Clinic Image Set";
                }
                else{
                    return "Could not set Clinic Image";
                }
            }
        }
    }
    
    //These functions are for managing clinics.

    function manageClinics(){
        $s = "";
        $accessType = $this->checkAccess();
        if($accessType == self::NO_ACCESS){
            return "<h2>You do not have permission to manage clinics.</h2>"
                   ."If you believe this is a mistake please contact a system administrator.";
        }
        $clinic_key = SEEDInput_Int( 'clinic_key' );
        if($accessType != self::FULL_ACCESS && !in_array($clinic_key, $this->getClinicsILead())){
            $clinic_key = 0;
        }
        $ClinicsDB = new ClinicsDB($this->oApp->kfdb);
        $cmd = SEEDInput_Str('cmd');
        switch( $cmd ) {
            case "update_clinic":
                if($accessType != self::FULL_ACCESS && !in_array($clinic_key, $this->getClinicsILead())){
                    $s .= "Cannot update clinic. NO ACCESS";
                    break;
                }
                $kfr = $ClinicsDB->GetClinic( $clinic_key );
                foreach( $kfr->KFRel()->BaseTableFields() as $field ) {
                    if($field['alias'] == 'email' && SEEDInput_Str('email') == 'default'){
                        $kfr->SetValue( $field['alias'], str_replace(" ", ".", strtolower(SEEDInput_Str('clinic_name')))."@catherapyservices.ca" );
                    }
                    elseif($field['alias'] == 'mailing_address' && SEEDInput_Str('mailing_address') == 'add%91ress'){
                        $kfr->SetValue( $field['alias'], SEEDInput_Str('address') );
                    }else{
                        $kfr->SetValue( $field['alias'], SEEDInput_Str($field['alias']) );
                    }
                }
                $kfr->PutDBRow();
                break;
            case "new_clinic":
                if($accessType != self::FULL_ACCESS){
                    $s .= "Cannot create new clinic. NO ACCESS";
                    break;
                }
                $name = SEEDInput_Str("new_clinic_name");
                $kfr = $ClinicsDB->KFRel()->CreateRecord();
                $kfr->SetValue("clinic_name",$name);
                $kfr->PutDBRow();
                $clinic_key = $kfr->Key();
                break;
        }
        if($clinic_key){
            //Process user commands here so that changes can be reflected in the leader list
            $this->processUserCommands();
        }
        $raClinics = $ClinicsDB->KFRel()->GetRecordSetRA($accessType == self::LEADER_ACCESS?"Clinics.fk_leader='".$this->oApp->sess->GetUID()."'":"");
        $s .= "<div class='container-fluid'><div class='row'>"
             ."<div class='col-md-6'>";
        if($accessType == self::FULL_ACCESS){
            $s .= "<h3>Clinics</h3>"
                 ."<button onclick='add_new();'>Add Clinic</button>"
                 ."<script>function add_new(){var value = prompt(\"Enter Clinic's Name\");
                       if(!value){return;}
                       document.getElementById('new_clinic_name').value = value;
                       document.getElementById('new_clinic').submit();
                   }</script><form id='new_clinic'><input type='hidden' value='' name='new_clinic_name' id='new_clinic_name'><input type='hidden' name='cmd' value='new_clinic'/>
                   </form>";
        }
        else if($accessType == self::LEADER_ACCESS && in_array($this->GetCurrentClinic(), $this->getClinicsILead())){
            $clinic_key = $this->GetCurrentClinic();
            $s .= "<h3>Clinic Settings</h3>";
        }
        else{
            $s .= "<h2>You do not have permission to manage the settings of this clinic.</h2>"
                ."If you believe this is a mistake please contact a system administrator.";
        }
        $s .= ($accessType == self::FULL_ACCESS ?SEEDCore_ArrayExpandRows( $raClinics, "<div style='padding:5px;'><a href='?clinic_key=[[_key]]'>[[clinic_name]]</a></div>" ):"")
              .($clinic_key ? $this->drawClinicForm($raClinics, $clinic_key) : "")
              ."</div>";
        $s .= $this->manageUsers(true); //Bypass the command processing part since they were already processed above
        return($s);
    }

    public function manageUsers(bool $cmdProccessed = false){
        if(!$cmdProccessed){
            $this->processUserCommands();
        }
        $s = "<table>
                <tr><th style='text-align:center'>[[unassignedTitle]]</th><th style='text-align:center'>Action</th><th style='text-align:center'>Users in Clinic</th></tr>
                <tr>
                    <td><form id='add_user'><input type='hidden' name='cmd' value='add_user'><input type='hidden' name='clinic_key' value='[[clinic]]'>[[Unassigned]]</form></td>
                    <td style='text-align:center;padding-left:10px;padding-right:10px'><input type='submit' value='Add' form='add_user' /><br /><input type='submit' value='Remove' form='remove_user' /></td>
                    <td><form id='remove_user'><input type='hidden' name='cmd' value='remove_user'><input type='hidden' name='clinic_key' value='[[clinic]]'><select name='toRemove[]' multiple='multiple' required>[[Assigned]]</select></form></div>
                </tr>
              </table>";
        $accessType = $this->checkAccess();
        if($accessType == self::NO_ACCESS){
            return "<h2>You do not have permission to manage Users.</h2>"
                ."If you believe this is a mistake please contact a system administrator.";
        }
        $clinic_key = SEEDInput_Int( 'clinic_key' );
        if($accessType != self::FULL_ACCESS && !in_array($clinic_key, $this->getClinicsILead())){
            $clinic_key = 0;
        }
        if($accessType == self::LEADER_ACCESS && in_array($this->GetCurrentClinic(), $this->getClinicsILead())){
            $clinic_key = $this->GetCurrentClinic();
        }
        if($clinic_key > 0){
            $s = str_replace("[[clinic]]", $clinic_key, $s);
            $attached_users = $this->getUsersInClinic($clinic_key);
            foreach ($attached_users as $ra){
                if($ra['_key'] == $this->clinicsDB->GetClinic($clinic_key)->Value("fk_leader")){
                    $s = str_replace("[[Assigned]]", SEEDCore_ArrayExpand($ra, "<option value='[[_key]]' disabled>[[realname]]</option>")."[[Assigned]]", $s);
                }
                else{
                    $s = str_replace("[[Assigned]]", SEEDCore_ArrayExpand($ra, "<option value='[[_key]]'>[[realname]]</option>")."[[Assigned]]", $s);
                }
            }
            $s = str_replace("[[Assigned]]","", $s);
            if($accessType == self::FULL_ACCESS){
                $non_attached_users = $this->getUsersInClinic($clinic_key,true);
                $s = str_replace("[[unassignedTitle]]", "Users not in Clinic", $s);
                $s = str_replace("[[Unassigned]]", "<select name='toAdd[]' multiple='multiple' required>".SEEDCore_ArrayExpandRows($non_attached_users, "<option value='[[_key]]'>[[realname]]</option>")."</select>", $s);
            }
            else{
                $s = str_replace("[[unassignedTitle]]", "Add User to Clinic", $s);
                $s = str_replace("[[Unassigned]]", "<input id='toAdd' type='text' name='toAdd' />", $s);
            }
        }
        else{
            $s = "";
        }
        
        return $s;
    }
    
    private function processUserCommands(){
        $cmd = SEEDInput_Str("cmd");
        $clinic_id = SEEDInput_Int('clinic_key');
        $uid = $this->oApp->sess->GetUID();
        switch($cmd){
            case "add_user":
                $toAdd = $_REQUEST['toAdd'];
                if(is_array($toAdd)){
                    foreach($toAdd as $user){
                        $userData = $this->oApp->kfdb->QueryRA("SELECT * FROM SEEDSession_Users WHERE _key = $user;");
                        if(!$userData){
                            $this->oApp->oC->AddErrMsg("User $user Not Found<br />");
                        }
                        else{
                            if($this->oApp->kfdb->Execute("INSERT INTO `users_clinics`(`_key`, `_created`, `_created_by`, `_updated`, `_updated_by`, `_status`, `fk_SEEDSession_Users`, `fk_clinics`) VALUES (0,NOW(),$uid,NOW(),$uid,0,$user,$clinic_id);")){
                                $this->oApp->oC->AddUserMsg("Added {$userData['realname']} to the clinic<br />");
                            }
                            else{
                                $this->oApp->oC->AddErrMsg("Could Not add {$userData['realname']} to the clinic<br />");
                            }
                        }
                    }
                }
                else{
                    $userData = $this->oApp->kfdb->QueryRA("SELECT * FROM SEEDSession_Users WHERE email = '$toAdd' OR realname = '$toAdd';");
                    if(!$userData){
                        $this->oApp->oC->AddErrMsg("User Not Found<br />");
                    }
                    else{
                        $user = $userData['_key'];
                        if($this->oApp->kfdb->Execute("INSERT INTO `users_clinics`(`_key`, `_created`, `_created_by`, `_updated`, `_updated_by`, `_status`, `fk_SEEDSession_Users`, `fk_clinics`) VALUES (0,NOW(),$uid,NOW(),$uid,0,$user,$clinic_id);")){
                            $this->oApp->oC->AddUserMsg("Added {$userData['realname']} to the clinic<br />");
                        }
                        else{
                            $this->oApp->oC->AddErrMsg("Could Not add {$userData['realname']} to the clinic<br />");
                        }
                    }
                }
                break;
            case "remove_user":
                $toRemove = $_REQUEST['toRemove'];
                foreach($toRemove as $user){
                    $userName = $this->oApp->kfdb->Query1("SELECT realname FROM SEEDSession_Users WHERE _key = $user;");
                    $key = $this->oApp->kfdb->Query1("SELECT _key FROM users_clinics WHERE fk_SEEDSession_Users = $user AND fk_clinics = $clinic_id;");
                    if($this->oApp->kfdb->Execute("DELETE FROM `users_clinics` WHERE `users_clinics`.`_key` = $key;")){
                        $this->oApp->oC->AddUserMsg("Removed $userName from the clinic<br />");
                    }
                    else{
                        $this->oApp->oC->AddErrMsg("Could Not remove $userName from the clinic<br />");
                    }
                }
                break;
        }
    }
    
    public function renderImage(int $imageID, $clinic = null){
        $path = $this->getImage($imageID,$clinic);
        if($path === FALSE){
            //Output a blank page since the image is not set and has no default
            exit;
        }
        switch(strtolower(pathinfo($path,PATHINFO_EXTENSION))){
            case "png":
                $imageType = IMAGETYPE_PNG;
                break;
            case "jpg":
                $imageType = IMAGETYPE_JPEG;
                break;
            case "gif":
                $imageType = IMAGETYPE_GIF;
                break;
            default:
                die("Could Not Render Image");
        }
        $i = getImageData($path, $imageType);
        echo "<style>"
            ."img {
                position: fixed; 
                top: 0; 
                left: 0;
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
              }
              body {
                margin: 0;
              }"
            ."</style>";
        echo "<img src='data:".image_type_to_mime_type($imageType).";base64," . base64_encode( $i )."'>";
        die();
    }
    
    private function drawFormRow( $label, $control )
    {
        return( "<tr>"
            ."<td class='col-md-4'><p>$label</p></td>"
            ."<td class='col-md-8'>$control</td>"
            ."</tr>" );
    }

    private function drawClinicForm($raClinics,$clinic_key){
        $s = "";
        foreach ($raClinics as $ra){
            if($ra['_key'] != $clinic_key){
                continue;
            }
            $sForm =
                "<form>"
                ."<input type='hidden' name='cmd' value='update_clinic'/>"
                ."<input type='hidden' name='clinic_key' value='{$clinic_key}'/>"

                .($this->checkAccess() == self::FULL_ACCESS?"<p>Clinic # {$clinic_key}</p>":"")
                ."<table class='container-fluid table table-striped table-sm'>"
                    // The first clinic must be called core and cannot have the name changed
                    // Disable the name box so it cant be changed
                .$this->drawFormRow( "Clinic Name", "<input type='text' name='clinic_name' required maxlength='200' value='".htmlspecialchars($ra['clinic_name'])."' placeholder='Name'".($ra['clinic_name'] == 'Core'?" readonly":"")."autofocus />")
                .$this->drawFormRow( "Address", "<input type='text' name='address' maxlength='200' value='".htmlspecialchars($ra['address'])."' placeholder='Address' />")
                .$this->drawFormRow( "City", "<input type='text' name='city' maxlength='200' value='".htmlspecialchars($ra['city'])."' placeholder='City' />")
                .$this->drawFormRow( "Postal Code", "<input type='text' name='postal_code' maxlength='200' value='".htmlspecialchars($ra['postal_code'])."' placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$' />")
                .$this->drawFormRow("Mailing Address", $this->getMailingAddress($ra))
                .$this->drawFormRow( "Phone Number", "<input type='text' name='phone_number' maxlength='200' value='".htmlspecialchars($ra['phone_number'])."' placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$' />")
                .$this->drawFormRow( "Fax Number", "<input type='text' name='fax_number' maxlength='200' value='".htmlspecialchars($ra['fax_number'])."' placeholder='Fax Number' />")
                .$this->drawFormRow( "Rate", "<input type='number' name='rate' value='".htmlspecialchars($ra['rate'])."' placeholder='Rate' step='1' min='0' />")
                .$this->drawFormRow( "Associated Business", "<input type='text' name='associated_business' maxlength='200' value='".htmlspecialchars($ra['associated_business'])."' placeholder='Associated Business' />")
                .$this->drawFormRow("Email", $this->getEmail($ra))
                // The Developer account must be the leader of the core clinic
                // Disable the selector so it cant be changed
                .$this->drawFormRow( "Clinic Leader", $this->getLeaderOptions($clinic_key,$ra['fk_leader'],$ra['clinic_name'] == 'Core'))
                ."<tr>"
                ."<td class='col-md-12'><input type='submit' value='Save' style='margin:auto' /></td></table></form>";
            $images = "<h4>Square Logo:</h4><iframe src='?screen=clinicImage&imageID=".self::LOGO_SQUARE."&clinic=".$clinic_key."' style='width:200px;height:200px' id='slogo'></iframe><br />"
                     ."<button style='margin-top:3px' onclick='showModal(\"slogo\")'>Change</button>"
                     ."<h4>Wide Logo:</h4><iframe src='?screen=clinicImage&imageID=".self::LOGO_WIDE."&clinic=".$clinic_key."' style='width:400px;height:100px' id='wlogo'></iframe><br />"
                     ."<button style='margin-top:3px' onclick='showModal(\"wlogo\")'>Change</button>"
                     ."<h4>Footer:</h4><iframe src='?screen=clinicImage&imageID=".self::FOOTER."&clinic=".$clinic_key."' style='width:400px;height:100px' id='footer'></iframe><br />"
                     ."<button style='margin-top:3px' onclick='showModal(\"footer\")'>Change</button>";
            $s .= "<div><div style='width:60%;display:inline-block;float:left'>".$sForm."</div><div style='width:40%;display:inline-block;float:left'>".$images."</div></div>"
                 ."<style>.col-md-6{max-width:100%;flex:0 0 100%}iframe{border:none}</style>"
                 .$this->getClinicImageModal();
        }
        return($s);
    }

    private function getClinicImageModal(){
        return <<<Modal
<!-- the div that represents the modal dialog -->
<div class="modal fade" id="clinic_image_dialog" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Change Clinic <span id='imageName'></span></h4>
            </div>
            <div class="modal-body">
                <div id='action_result'></div>
                <form id="clinic_image_form" onsubmit='event.preventDefault();' action="jx.php" method="POST" enctype="multipart/form-data">
                    <input type='hidden' name='cmd' value='clinicImg' />
                    <input type='hidden' name='image_ID' value='' id='image_ID' />
                    <input type='file' accept='.png,.jpg,.gif' name='clinicImage' id='imageSelector' />
                    <img id='imagePreview' alt='Image Preview' />
                </form>
            </div>
            <div class="modal-footer">
                <input type='submit' form='clinic_image_form' name='action' class="btn btn-default" value='Restore' onclick='submitModal(event)' />
                <input type='submit' form='clinic_image_form' name='action' class="btn btn-default" value='Change' onclick='submitModal(event)' />
            </div>
        </div>
    </div>
</div>
Modal;
    }
    
    private function getMailingAddress($ra){
        $isAddress = $ra['address'] == $ra['mailing_address'];
        $s = "<input type='checkbox' value='add%91ress' id='mailingAddress' name='mailing_address' onclick='notAddress()' ".($isAddress?"checked":"").">Same as Address</input>"
            ."<input type='text' id='mailing_address' name='mailing_address' ".($isAddress?"style='display:none' disabled ":"")
            .(!$isAddress?"value='".$ra['mailing_address']."' ":"")." maxlenght='200' required placeholder='Mailing Address' />"
            ."<script>
                function notAddress() {
                    var checkBox = document.getElementById('mailingAddress');
                    var text = document.getElementById('mailing_address');
                    if (checkBox.checked == false){
                        text.style.display = 'block';
                        text.disabled = false;
                    } else {
                        text.style.display = 'none';
                        text.disabled = true;
                    }
                }
              </script>";
        return $s;
    }
    
    private function getEmail($ra){
        $useDefault = !$ra['email'] || substr(strtolower($ra['email']), 0, strlen(strtolower($ra['clinic_name']))) === strtolower($ra['clinic_name']);
        $s = "<input type='checkbox' value='default' id='clinicEmail' name='email' onclick='notDefault()' ".($useDefault?"checked ":"").">Use Default Email</input>"
            ."<input type='email' id='email' name='email' ".($useDefault?"style='display:none' disabled ":"")
                .(!$useDefault?"value='".$ra['email']."' ":"")."required placeholder='Email' />"
            ."<script>
                function notDefault() {
                    var checkBox = document.getElementById('clinicEmail');
                    var text = document.getElementById('email');
                    if (checkBox.checked == false){
                        text.style.display = 'block';
                        text.disabled = false;
                    } else {
                        text.style.display = 'none';
                        text.disabled = true;
                    }
                }
              </script>";
        return $s;
    }

    private function getLeaderOptions(int $clinic, int $leader_key, bool $readonly){
        $access = $this->checkAccess();
        $raUsers = $this->getUsersInClinic($clinic);
        $tooltip = "";
        if ($readonly){
            $tooltip = "You don't have permission to change the leader of this clinic";
        }
        if($access != self::FULL_ACCESS && count($raUsers) <= 1){
            $readonly = true;
            $tooltip = "Add more users to the clinic to unlock";
        }
        
        $s = "<div ".($tooltip?'data-tooltip="'.$tooltip.'"':"")."><select name='fk_leader'".($readonly?" disabled":"").">";
        $accountsDB = new SEEDSessionAccountDB($this->oApp->kfdb, 0);
        // Fetch all users that are not clients
        // Client credentials generated through therapist---credentials command
        // ALLWAYS have their client id in their metadata as key clientId
        // By this logic anyone who lacks this metadata is not a client
        foreach($accountsDB->GetUsersFromMetadata("clientId", "UM.uid is null") as $k => $ra){
            if($ra['eStatus'] != "ACTIVE"){
                //Skip any account that is not in the active state
                continue;
            }
            if($access != self::FULL_ACCESS && !in_array($k, array_column($raUsers,'_key'))){
                //Skip any account that is not attached to the clinic unless the users has full access
                continue;
            }
            if($k == $leader_key){
                $s .= "<option selected value='".$k."' />".$ra['realname'];
            }
            else{
                $s .= "<option value='".$k."' />".$ra['realname'];
            }
            if($k == $this->oApp->sess->GetUID()){
                $s .= " (me)";
            }
        }
        $s .= "</select></div>";
        return($s);
    }

    private function getFile($filepath,$filename){
        if(file_exists($filepath)){
            foreach (scandir($filepath) as $file){
                if(substr($file, 0,strlen($filename)) == $filename){
                    $filename = $file;
                }
            }
        }
        return $filename;
    }
    
}

class ImageGenerator {
    
    private const TEMPLATE = CATSDIR_IMG."footer/footer_template.jpg";
    private const DOT = CATSDIR_IMG."footer/dot.gif";
    private const ROW1 = 60;
    private const ROW2 = 130;
    private const ROW2_DOT = 100;
    private const ROW3 = 205;
    private const ROW3_DOT = 170;
    private const FONT = 45;
    private const FONTFILE = CATSDIR_FONTS."arialbd.ttf";
    private const DOT_SIZE = 30;
    private const LINE_START1 = 700;
    private const LINE_START2 = 500;
    private const LINE_START3 = 260;
    
    private $oApp;
    
    public function __construct(SEEDAppConsole $oApp){
        $this->oApp = $oApp;
    }
    
    public function generateFooter(int $clinicId){
        $ClinicsDB = new ClinicsDB($this->oApp->kfdb);
        $kfr = $ClinicsDB->GetClinic( $clinicId );
        $im = imagecreatefromjpeg(self::TEMPLATE);
        $dot = imagecreatefromgif(self::DOT);
        $color = imagecolorallocate($im, 0, 0, 0);
        $ra = imagettftext($im, self::FONT, 0, self::LINE_START1, self::ROW1, $color, realpath(self::FONTFILE), "Collaborative Approach Therapy Services");
        $ra = imagettftext($im, self::FONT, 0, self::LINE_START2, self::ROW2, $color, realpath(self::FONTFILE), $kfr->Value('address'));
        imagecopy($im, $dot, $ra[2]+100, self::ROW2_DOT, 0, 0, self::DOT_SIZE, self::DOT_SIZE);
        $ra = imagettftext($im, self::FONT, 0, $ra[2]+205, self::ROW2, $color, realpath(self::FONTFILE), $kfr->Value("city").", ON");
        imagecopy($im, $dot, $ra[2]+100, self::ROW2_DOT, 0, 0, self::DOT_SIZE, self::DOT_SIZE);
        $ra = imagettftext($im, self::FONT, 0, $ra[2]+205, self::ROW2, $color, realpath(self::FONTFILE), $kfr->Value('postal_code'));
        $ra = imagettftext($im, self::FONT, 0, self::LINE_START3, self::ROW3, $color, realpath(self::FONTFILE), $kfr->Value("phone_number"));
        imagecopy($im, $dot, $ra[2]+35, self::ROW3_DOT, 0, 0, self::DOT_SIZE, self::DOT_SIZE);
        $ra = imagettftext($im, self::FONT, 0, $ra[2]+100, self::ROW3, $color, realpath(self::FONTFILE), $kfr->Value('email'));
        imagecopy($im, $dot, $ra[2]+35, self::ROW3_DOT, 0, 0, self::DOT_SIZE, self::DOT_SIZE);
        imagettftext($im, self::FONT, 0, $ra[2]+100, self::ROW3, $color, realpath(self::FONTFILE), "www.catherapyservices.ca");
        imagedestroy($dot);
        
        return $im;
        
    }
    
    public function processCMDs(string $cmd,int $clinic_id = 0){
        if(substr($_SERVER['PHP_SELF'],strrpos($_SERVER['PHP_SELF'], '/')+1) != 'jx.php'){
            die("Calling of ImageGenerator->processCMDs() through the the main CATS Software is forbidden");
        }
        if($clinic_id <= 0 || $clinic_id > intval($this->oApp->kfdb->Query1("SELECT MAX(`_key`) FROM `clinics`"))){
           $clinic_id = (new Clinics($this->oApp))->GetCurrentClinic(); 
        }
        switch(strtolower($cmd)){
            case 'view':
                $im = $this->generateFooter($clinic_id);
                ob_start();
                imagejpeg($im);
                $data = ob_get_clean();
                imagedestroy($im);
                return "<img style='width:100%' src='data:image/jpeg;base64," . base64_encode( $data )."'>";
            case 'download':
                $ClinicsDB = new ClinicsDB($this->oApp->kfdb);
                $kfr = $ClinicsDB->GetClinic( $clinic_id );
                header("Content-Transfer-Encoding: binary");
                header('Content-Description: File Transfer');
                header('Content-Type: image/jpeg');
                header('Content-Disposition: attachment; filename="' . $kfr->Value('clinic_name').' footer.jpg"');
                $im = $this->generateFooter($clinic_id);
                imagejpeg($im);
                imagedestroy($im);
                exit;
            case 'save':
                $filepath = CATSDIR_FILES."clinic Images/".$clinic_id."/";
                $filename = "Footer";
                $continue = true;
                $saved = true;
                if(file_exists($filepath)){
                    foreach (scandir($filepath) as $file){
                        if(substr($file, 0,strlen($filename)) == $filename){
                            $filename = $file;
                        }
                    }
                }
                if(strpos($filename, ".") !== false && file_exists($filepath.$filename)){
                    $continue = rename($filepath.$filename, $filepath.str_replace(".", " old.", $filename));
                }
                if($continue){
                    $im = $this->generateFooter($clinic_id);
                    $saved = imagejpeg($im,$filepath.'Footer.jpg');
                    imagedestroy($im);
                }
                if($continue && $saved){
                    $s = "<div class='alert alert-success'><strong>Success!</strong> Footer Saved to clinic</div>";
                }
                else if($continue){
                    $s = "<div class='alert alert-danger'><strong>Error!</strong> An Error Occured while saving the footer. A backup of the previous footer was saved. <strong>The footer Was NOT Saved</strong></div>";
                }
                else{
                    $s = "<div class='alert alert-danger'><strong>Error!</strong> An Error Occured while backing up the previous footer. <strong>The footer Was NOT Saved</strong></div>";
                }
                return $s;
            default:
                return '';
        }
    }
    
    public function footerOptions(){
        return <<<FooterOptions
<script>
    function sendCMD(act){
        $.ajax({
            type: "POST",
            data: {cmd:'system-footergenerator',action:act},
            url: 'jx.php',
            success: function(data, textStatus, jqXHR) {
                var jsData = JSON.parse(data);
                if(jsData.bOk){
                    document.getElementById('system_body').innerHTML = jsData.sOut;
                    $('#system_dialog').modal('handleUpdate');
                    $('#system_dialog').modal('show');
                }
                else{
                    console.log(jsData.sErr);
                }
            },
            error: function(jqXHR, status, error) {
                console.log(status + ": " + error);
            }
        });
    }
</script>
<div class="modal fade" id="system_dialog" role="dialog">
    <div class="modal-dialog modal-lg" style='max-width:100%'>
        <div class="modal-content" style='max-width:100%'>
            <div class="modal-body" id="system_body" style='max-width:100%'>
            </div>
        </div>
    </div>
</div>
<div>
    <button onclick='sendCMD("view")'>View</button>
    <button onclick='window.open("jx.php?cmd=system-footergenerator&action=download")'>Download</button>
    <button onclick='sendCMD("save")'>Save</button>
</div>
FooterOptions;
    }
    
}