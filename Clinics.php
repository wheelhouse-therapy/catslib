<?php

require_once 'handle_images.php';

class Clinics {
    private $oApp;
    private $clinicsDB;

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

    function GetCurrentClinic($user = NULL){
        /*
         * Returns the current clinic the user is looking at
         * A result of NULL means a clinic has not been specefied
         * A list of accessable clinics should be presented at this point
         *
         * A user with access to the core clinic will never return NULL through this call.
         * Clinic leaders default to the first clinic they lead.
         */

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
                return $clinic['Clinics__key']; // The user only has one clinic
            }
        }
        return $k;
    }

    function GetUserClinics($user = NULL){
        /*
         * Returns a list of clinics that the user is part of (accessable)
         *
         * A clinic is considerd accessable to the user by CATS if they are part of that clinic
         * ie. their user id is mapped to the clinic id in the Users_Clinics Database table
         */

        if(!$user){
            $user = $this->oApp->sess->GetUID();
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

    function displayUserClinics(){
        $s = "";
        foreach($this->GetUserClinics() as $ra){
            if($s){
                $s .=", ";
            }
            if($ra['Clinics__key'] == $this->GetCurrentClinic()){
                $s .= "<span class='selectedClinic'> ".$ra['Clinics_clinic_name']."</span>";
            }
            else {
                $s .= "<a href='?clinic=".$ra['Clinics__key']."'>".$ra['Clinics_clinic_name']."</a>";
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
            $bFull = $this->oApp->sess->CanRead('administrator');
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
    
    public function getClinicsWithAkaunting($user=NULL){
        $accessType = $this->checkAccess($user);
        if(!$accessType == self::FULL_ACCESS && !$this->isCoreClinic()){
            //Not core clinic and user does not have full access to clinic settings
            return false;
        }
        return( array_column($this->clinicsDB->KFRel()->GetRecordSetRA("akaunting_company != 0"),'_key') );
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
        if(file_exists($filepath)){
            foreach (scandir($filepath) as $file){
                if(substr($file, 0,strlen($filename)) == $filename){
                    $filename = $file;
                }
            }
        }
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
                        $kfr->SetValue( $field['alias'], strtolower(SEEDInput_Str('clinic_name'))."@catherapyservices.ca" );
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
                    $s .= "Cannot create new clinic. NO ACESS";
                    break;
                }
                $name = SEEDInput_Str("new_clinic_name");
                $kfr = $ClinicsDB->KFRel()->CreateRecord();
                $kfr->SetValue("clinic_name",$name);
                $kfr->PutDBRow();
                $clinic_key = $kfr->Key();
                break;
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
        return($s);
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
                .$this->drawFormRow( "Clinic Leader", $this->getLeaderOptions($ra['fk_leader'],$ra['clinic_name'] == 'Core'))
                ."<tr>"
                ."<td class='col-md-12'><input type='submit' value='Save' style='margin:auto' /></td></table></form>";
            $images = "<h4>Square Logo:</h4><iframe style='max-width:30%' src='?screen=clinicImage&imageID=".self::LOGO_SQUARE."&clinic=".$clinic_key."'></iframe><br />"
                     ."<h4>Wide Logo:</h4><iframe style='height:5%;' src='?screen=clinicImage&imageID=".self::LOGO_WIDE."&clinic=".$clinic_key."'></iframe><br />"
                     ."<h4>Footer:</h4><iframe style='height:5%;' src='?screen=clinicImage&imageID=".self::FOOTER."&clinic=".$clinic_key."'></iframe>";
            $s .= "<div><div style='width:60%;display:inline-block;float:left'>".$sForm."</div><div style='width:20%;display:inline-block;float:left'>".$images."</div></div>"
                 ."<style>.col-md-6{max-width:100%;flex:0 0 100%}</style>";
        }
        return($s);
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

    private function getLeaderOptions($leader_key, $readonly){
        $s = "<select name='fk_leader'".($readonly?" disabled":"").">";
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
        $s .= "</select>";
        return($s);
    }

}