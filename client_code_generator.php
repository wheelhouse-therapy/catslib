<?php

class ClientCodeGenerator {
    
    private $oApp;
    private $oPeopleDB;
    
    public function __construct( SEEDAppSessionAccount $oApp ){
        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB($this->oApp);
    }
    
    public function getClientCode(int $client_key){
        $kfr = $this->oPeopleDB->GetKFR("C", $client_key);
        if($code = $kfr->Value("code")){
            return $code;
        }
        $code = strtoupper(substr($kfr->Value("P_last_name"),0,3))
               .strtoupper(substr($kfr->Value("P_first_name") ,0,3));
        $existingCodes = $this->oPeopleDB->GetList("C", "code LIKE '".$code."%'");
        if(count($existingCodes) > 0){
            $code .= (count($existingCodes)+1);
        }
        $kfr->SetValue("code", $code);
        $kfr->PutDBRow();
        return $code;
    }
    
    public function regenerateCode(int $client_key){
        $kfr = $this->oPeopleDB->GetKFR("C", $client_key);
        $kfr->SetValue("code", "");
        $kfr->PutDBRow();
        return $this->getClientCode($client_key);
    }
    
}

?>