<?php
class Clinics {
    private $oApp;
    
    function __construct( SEEDAppSessionAccount $oApp ) {
        $this->oApp = $oApp;
    }
    
    function isCoreClinic(){
        return $this->GetCurrentClinic() == 1;
    }
    
    function GetCurrentClinic(){
        /*
         * Returns the current clinic the user is looking at
         * A result of NULL means a clinic has not been specefied
         * A list of accessable clinics should be presented at this point
         * 
         * A user with access to the core clinic will never return NULL through this call.
         * Clinic leaders default to the first clinic they lead.
         */
        $clinicsra = $this->GetUserClinics();
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
            else if($clinic["Clinics_fk_leader"] == $this->oApp->sess->GetUID() && $k == NULL){
                $k = $clinic['Clinics__key']; // The user is the leader of this clinic
            }
            else if(count($clinicsra) == 1){
                return $clinic['Clinics__key']; // The user only has one clinic
            }
        }
        return $k;
    }
    
    function GetUserClinics(){
        /*
         * Returns a list of clinics that the user is part of (accessable)
         * 
         * A clinic is considerd accessable to the user by CATS if they are part of that clinic
         * ie. their user id is mapped to the clinic id in the Users_Clinics Database table
         */
        $UsersClinicsDB = new Users_ClinicsDB($this->oApp->kfdb);
        $clinics = $UsersClinicsDB->KFRel()->GetRecordSetRA("Users._key='".$this->oApp->sess->GetUID()."'");
        $leading = (new ClinicsDB($this->oApp->kfdb))->KFRel()->GetRecordSetRA("Clinics.fk_leader='".$this->oApp->sess->GetUID()."'");
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
    
    //These functions are for managing clinics.
    
    function manageClinics(){
        $s = "";
        $clinic_key = SEEDInput_Int( 'clinic_key' );
        $ClinicsDB = new ClinicsDB($this->oApp->kfdb);
        $cmd = SEEDInput_Str('cmd');
        switch( $cmd ) {
            case "update_clinic":
                $kfr = $ClinicsDB->GetClinic( $clinic_key );
                foreach( $kfr->KFRel()->BaseTableFields() as $field ) {
                    $kfr->SetValue( $field['alias'], SEEDInput_Str($field['alias']) );
                }
                $kfr->PutDBRow();
                break;
            case "new_clinic":
                $name = SEEDInput_Str("new_clinic_name");
                $kfr = $ClinicsDB->KFRel()->CreateRecord();
                $kfr->SetValue("clinic_name",$name);
                $kfr->PutDBRow();
                $clinic_key = $kfr->Key();
                break;
        }
        $raClinics = $ClinicsDB->KFRel()->GetRecordSetRA("");
        
        $s .= "<div class='container-fluid'><div class='row'>"
            ."<div class='col-md-6'>"
            ."<h3>Clinics</h3>"
            ."<button onclick='add_new();'>Add Clinic</button>"
            ."<script>function add_new(){var value = prompt(\"Enter Clinic's Name\");
                 if(!value){return;}
                 document.getElementById('new_clinic_name').value = value;
                 document.getElementById('new_clinic').submit();
            }</script><form id='new_clinic'><input type='hidden' value='' name='new_clinic_name' id='new_clinic_name'><input type='hidden' name='cmd' value='new_clinic'/>
            </form>"
           .SEEDCore_ArrayExpandRows( $raClinics, "<div style='padding:5px;'><a href='?clinic_key=[[_key]]'>[[clinic_name]]</a></div>" )
           .($clinic_key ? $this->drawClinicForm($raClinics, $clinic_key) : "")
           ."</div>";
        return($s);
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
               
                ."<p>Clinic # {$clinic_key}</p>"
                ."<table class='container-fluid table table-striped table-sm'>"
                    // The first clinic must be called core and cannot have the name changed
                    // Disable the name box so it cant be changed
                .$this->drawFormRow( "Clinic Name", "<input type='text' name='clinic_name' required maxlength='200' value='".htmlspecialchars($ra['clinic_name'])."' placeholder='Name'".($ra['clinic_name'] == 'Core'?" readonly":"")." />")
                .$this->drawFormRow( "Address", "<input type='text' name='address' maxlength='200' value='".htmlspecialchars($ra['address'])."' placeholder='Address' />")
                .$this->drawFormRow( "City", "<input type='text' name='city' maxlength='200' value='".htmlspecialchars($ra['city'])."' placeholder='City' />")
                .$this->drawFormRow( "Postal Code", "<input type='text' name='postal_code' maxlength='200' value='".htmlspecialchars($ra['postal_code'])."' placeholder='Postal Code' pattern='^[a-zA-Z]\d[a-zA-Z](\s+)?\d[a-zA-Z]\d$' />")
                .$this->drawFormRow( "Phone Number", "<input type='text' name='phone_number' maxlength='200' value='".htmlspecialchars($ra['phone_number'])."' placeholder='Phone Number' pattern='^(\d{3}[-\s]?){2}\d{4}$' />")
                .$this->drawFormRow( "Fax Number", "<input type='text' name='fax_number' maxlength='200' value='".htmlspecialchars($ra['fax_number'])."' placeholder='Fax Number' />")
                .$this->drawFormRow( "Rate", "<input type='number' name='rate' value='".htmlspecialchars($ra['rate'])."' placeholder='Rate' step='1' min='0' />")
                .$this->drawFormRow( "Associated Business", "<input type='text' name='associated_business' maxlength='200' value='".htmlspecialchars($ra['associated_business'])."' placeholder='Associated Business' />")
                // The Developer account must be the leader of the core clinic
                // Disable the selector so it cant be changed
                .$this->drawFormRow( "Clinic Leader", $this->getLeaderOptions($ra['fk_leader'],$ra['clinic_name'] == 'Core'))
                ."<tr>"
                ."<td class='col-md-12'><input type='submit' value='Save' style='margin:auto' /></td>";
            $s .= $sForm;
        }
        return($s);
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
        }
        $s .= "</select>";
        return($s);
    }
    
}