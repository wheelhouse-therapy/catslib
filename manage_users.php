<?php

class ManageUsers {
    
    private $oApp;
    private $oPeopleDB;
    private $clinics;
    private $oClinicsDB;
    
    public function __construct(SEEDAppConsole $oApp){
        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB($oApp);
        $this->clinics = new Clinics($oApp);
        $this->oClinicsDB = new ClinicsDB($oApp->kfdb);
    }
    
    public function manageUser(int $uid){
        $ra = $this->getRecord($uid);
        $s = "<form>"
                ."<input type='hidden' name='uid' value='$uid'"
                .""
             ."</form>";
        
    }
    
    public function drawList(){
        $s = "";
        $condStaff = "P.uid in (SELECT fk_SEEDSession_users FROM users_clinics WHERE fk_clinics = {$this->clinics->GetCurrentClinic()})";
        if($this->clinics->isCoreClinic()){
            $condStaff = "";
        }
        $raTherapists = $this->oPeopleDB->GetList(ClientList::INTERNAL_PRO, $condStaff, array("sSortCol" => "P.first_name,_key"));
        $s .= SEEDCore_ArrayExpandRows( $raTherapists, "<div style='padding:5px;' >[[P_first_name]] [[P_last_name]] is a [[pro_role]]%[[clinic]]</div>" );
        foreach($this->oClinicsDB->KFRel()->GetRecordSetRA("") as $clinic){
            if($this->clinics->isCoreClinic()){
                $s = str_replace("%".$clinic['_key'], " @ the ".$clinic['clinic_name']." clinic", $s);
            }
            else {
                $s = str_replace("%".$clinic['_key'], "", $s);
            }
        }
    }
    
    public function saveForm(){
        
    }
    
    private function getRecord(int $uid):KeyframeRecord{
        return $this->oPeopleDB->GetKFRCond(ClientList::INTERNAL_PRO,"U._key = $uid");
    }
    
}