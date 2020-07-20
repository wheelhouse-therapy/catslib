<?php

class ClientCodeGenerator {
    
    private $oApp;
    private $oPeopleDB;
    
    public function __construct( SEEDAppSessionAccount $oApp ){
        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB($this->oApp);
    }
    
    /** Get/Generate the specified clients code.
     * Codes are generated using the first three letters of the clients last name
     * followed by the first three letters of the clients first name.
     * If multiple clients exist with that letter combo then a number is added to the end.
     * Codes are only generated for a client if they exist in the database and have their first and last name set.
     * Generated codes are stored in database for future reference
     * @param int $client_key - ID of Client to get/generate the code for
     * @return string - The generated client code or empty string if above generation conditions are not met.
     */
    public function getClientCode(int $client_key){
        $kfr = $this->oPeopleDB->GetKFR(ClientList::CLIENT, $client_key);
        if(!$kfr || !$kfr->Value("P_first_name") || !$kfr->Value("P_last_name")){
            //Do not create a client code if the client has not been set up completely
            // ie. they dont exist or are missing their first or last name
            return "";
        }
        if($code = $kfr->Value("code")){
            return $code;
        }
        $code = strtoupper(substr($kfr->Value("P_last_name"),0,3))
               .strtoupper(substr($kfr->Value("P_first_name") ,0,3));
        $existingCodes = $this->oPeopleDB->GetList(ClientList::CLIENT, "code LIKE '".$code."%'", array("iStatus" => -1));
        if(count($existingCodes) > 0){
            $code .= (count($existingCodes)+1);
        }
        $kfr->SetValue("code", $code);
        $kfr->PutDBRow();
        return $code;
    }
    
    /** Regenerate the code of the specified client
     * This could effect codes of other clients, and should only be performed by developers when strictly nessicisary.
     * It essentially sets the clients code in the database to an empty string then calles getClientCode for the client to regenerate.
     * @param int $client_key - ID of the Client to regenerate the code for 
     * @return string|false - The regenerated client code or false if the current user does not have permission to perform this action
     */
    public function regenerateCode(int $client_key){
        if(!CATS_SYSADMIN){
            // Do not have permission to perform this action.
            return false;
        }
        $kfr = $this->oPeopleDB->GetKFR(ClientList::CLIENT, $client_key);
        $kfr->SetValue("code", "");
        $kfr->PutDBRow();
        return $this->getClientCode($client_key);
    }
    
}

?>