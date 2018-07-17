<?php
class Clinics {
    private $oApp;
    
    function __construct( SEEDAppSessionAccount $oApp ) {
        $this->oApp = $oApp;
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
        $clinicsra = $this->GetUserClinics($this->oApp);
        if(in_array($this->oApp->sess->SmartGPC('clinic'),$clinicsra)){
            return $this->oApp->sess->SmartGPC('clinic');
        }
        $k = NULL;
        foreach ($clinicsra as $clinic){
            if($clinic["Clinics_clinic_name"] == "Core"){
                return $clinic["Clinics__key"];
            }
            else if($clinic["Clinics_fk_leader"] == $this->oApp->sess->GetUID() && $k == NULL){
                $k = $clinic['Clinics__key']; // The user is the leader of this clinic
            }
            else if(count($clinic) == 1){
                return $clinic['Clinics__key']; // The user only has one clinic
            }
        }
        return $k;
    }
    
    function GetUserClinics( SEEDAppSessionAccount $oApp ){
        /*
         * Returns a list of clinics that the user is part of (accessable)
         * 
         * A clinic is considerd accessable to the user by CATS if they are part of that clinic
         * ie. their user id is mapped to the clinic id in the Users_Clients Database table
         */
        $UsersClinicsDB = new Users_ClinicsDB($this->oApp->kfdb);
        return $UsersClinicsDB->KFRel()->GetRecordSetRA("Users._key='{$this->oApp->sess->GetUID()}'" );
    }
    
}