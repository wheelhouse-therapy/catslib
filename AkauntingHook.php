<?php

require_once(CATSLIB.'/vendor/autoload.php');
require_once('email_processor.php');

class AkauntingHook {

    const REJECTED_NO_ACCOUNT = -1;
    const REJECTED_NOT_SETUP = -2;

    private static $session = NULL;
    private static $_token = NULL;
    private static $accounts = array();

    private static $bDebug = true;     // set this to true to turn on debugging messages

    private static function dbg( $s )  { if( self::$bDebug )  echo "$s<br/>"; }

    public static function login(String $email, String $password){
        self::dbg("Connecting to Akaunting...");
        if (self::$session != NULL){
            throw new Exception("Already Loged in");
        }
        self::$session = new Requests_Session("https://catherapyservices.ca/");
        $responce = self::$session->get("/akaunting/auth/login");
        preg_match('|(?<=_token" value=").*?(?=")|', $responce->body, $matches);
        self::$_token = $matches[0];
        self::$session->cookies = new Requests_Cookie_Jar(Requests_Cookie::parse_from_headers($responce->headers));
        self::$session->post("/akaunting/auth/login", array(), array('_token' => self::$_token, 'email' => $email, 'password' => $password));
        self::dbg("connected");
    }

    public static function logout(){
        if (self::$session == NULL){
            throw new Exception("Not Logged in");
        }
        self::$session->get("/akaunting/auth/logout");
        self::$session = NULL;
    }

    public static function submitJournalEntries(array $entries) {
        if (self::$session == NULL){
            throw new Exception("Not Logged in");
        }
    }

    public static function submitJournalEntry(AccountingEntry $entry){
        $ret = self::REJECTED_NOT_SETUP;

        $oApp = $GLOBALS['oApp'];
        self::dbg("Submitting Entry");

        $clinics = (new Clinics($oApp))->getClinicsByName($entry->getClinic());
        if( !count($clinics) ) {
            self::dbg("You don't have clinic '".$entry->getClinic()."' defined");
            goto done;
        }
        $clinicId = $clinics[0];
        if( !($company = (new ClinicsDB($oApp->kfdb))->GetClinic($clinicId)->Value("akaunting_company")) ) {
            self::dbg("Clinic $clinicId doesn't have an Akaunting company code");
            goto done;
        }

        //Switch to the correct Clinic
        self::$session->get("/akaunting/companies/".$company."/set");

        //Fetch accounts
        self::fetchAccounts();

        $data = array("_token" => self::$_token, "paid_at" => $entry->getDate(), "description" => $entry->getDesc(),
                      "item[0][account_id]" => "", "item[0][credit]" => "$0.00",
                      "item[1][account_id]" => "", "item[1][debit]" => "$0.00"
                );
        if (self::$session == NULL){
            throw new Exception("Not Loged in");
        }
        if(!($account = self::getAccountByName($entry->getCategory()))){
            $account = self::getAccountByCode($entry->getCategory());
        }
        if($account == NULL){
            $ret = self::REJECTED_NO_ACCOUNT;
            goto done;
        }
        if($entry->getPerson() == "CCC"){
            if($entry->getType() == "Expense"){
                $data["item[0][account_id]"] = self::getAccountByCode("201");
                $data["item[0][credit]"] = $entry->getAmount();
                $data["item[1][account_id]"] = $account;
                $data["item[1][debit]"] = $entry->getAmount();
            }
            else{
                $data["item[1][account_id]"] = self::getAccountByCode("201");
                $data["item[1][debit]"] = $entry->getAmount();
                $data["item[0][account_id]"] = $account;
                $data["item[0][credit]"] = $entry->getAmount();
            }
        }
        elseif (strtolower($entry->getPerson()) == "sue") {
            if($entry->getType() == "Expense"){
                $data["item[0][account_id]"] = self::getAccountByCode("210");
                $data["item[0][credit]"] = $entry->getAmount();
                $data["item[1][account_id]"] = $account;
                $data["item[1][debit]"] = $entry->getAmount();
            }
            else{
                $data["item[1][account_id]"] = self::getAccountByCode("210");
                $data["item[1][debit]"] = $entry->getAmount();
                $data["item[0][account_id]"] = $account;
                $data["item[0][credit]"] = $entry->getAmount();
            }
        }
        elseif (strtolower($entry->getPerson()) == "alison") {
            if($entry->getType() == "Expense"){
                $data["item[0][account_id]"] = self::getAccountByCode("211");
                $data["item[0][credit]"] = $entry->getAmount();
                $data["item[1][account_id]"] = $account;
                $data["item[1][debit]"] = $entry->getAmount();
            }
            else{
                $data["item[1][account_id]"] = self::getAccountByCode("211");
                $data["item[1][debit]"] = $entry->getAmount();
                $data["item[0][account_id]"] = $account;
                $data["item[0][credit]"] = $entry->getAmount();
            }
        }
        if($entry->getAttachment()){
            $data['reference'] = $entry->getAttachment();
        }

        //Make journal Entry
        $ret = self::$session->post("/akaunting/double-entry/journal-entry", array(), $data)->status_code;

        done:
        return( $ret );
    }

    private static function fetchAccounts(){
        $responce = self::$session->get("/akaunting/double-entry/journal-entry/create");
        preg_match_all('|(?<=\<option value=")(\d*)">(\d*) - (.*?)(?=<\/option>)|', $responce->body, $matches, PREG_SET_ORDER);
        if(!$matches){
            throw new Exception("Could not find any accounts");
        }
        foreach($matches as $match){
            self::$accounts[$match[1]] = array( "code" => $match[2], "name" => $match[3]);
        }
    }

    private static function getAccountByName($name){
        foreach(self::$accounts as $k => $account){
            if(strtolower($account['name']) == strtolower($name)){
                return $k;
            }
        }
        return NULL;
    }

    private static function getAccountByCode($code){
        foreach(self::$accounts as $k => $account){
            if($account['code'] == $code){
                return $k;
            }
        }
        return NULL;
    }

}

?>