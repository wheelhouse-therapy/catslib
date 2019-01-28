<?php

if(!defined("CATSLIB")){define("CATSLIB", "./");}

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
        $hooks = new Requests_Hooks();
        $hooks->register('requests.before_redirect_check', 'fixRedirects');
        self::$session = new Requests_Session("https://catherapyservices.ca/");
        self::$session->options['hooks'] = $hooks;
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

    public static function submitJournalEntries(array $entries):array{
        if (self::$session == NULL){
            throw new Exception("Not Logged in");
        }
        $responces = array();
        foreach($entries as $k=>$entry){
            $responces[$k] = self::submitJournalEntry($entry);
        }
        return $responces;
    }

    public static function submitJournalEntry(AccountingEntry $entry):int{
        //Ensure we have connected to Akaunting before we attempt to submit entries
        if (self::$session == NULL){
            throw new Exception("Not Logged in");
        }
        
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

        // Switch to the correct Company
        self::$session->get("/akaunting/common/companies/".$company."/set");

        //Fetch accounts
        self::fetchAccounts();

        $data = array("_token" => self::$_token, "paid_at" => $entry->getDate(), "description" => $entry->getDesc(), "item" => array(
                      array("account_id" => "", "debit" => "$0.00", "credit" => "$"),
                      array("account_id" => "", "debit" => "$",     "credit" => "$0.00")
                ), "reference" => "System Entry. ", "currency_code" => "CAD");
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
                $data["item"][0]["account_id"] = self::getAccountByCode("201");
                $data["item"][0]["credit"] .= $entry->getAmount();
                $data["item"][1]["account_id"] = $account;
                $data["item"][1]["debit"] .= $entry->getAmount();
            }
            else{
                $data["item"][1]["account_id"] = self::getAccountByCode("201");
                $data["item"][0]["credit"] .= $entry->getAmount();
                $data["item"][0]["account_id"] = $account;
                $data["item"][1]["debit"] .= $entry->getAmount();
            }
        }
        elseif (strtolower($entry->getPerson()) == "sue") {
            if($entry->getType() == "Expense"){
                $data["item"][0]["account_id"] = self::getAccountByCode("210");
                $data["item"][0]["credit"] .= $entry->getAmount();
                $data["item"][1]["account_id"] = $account;
                $data["item"][1]["debit"] .= $entry->getAmount();
            }
            else{
                $data["item"][1]["account_id"] = self::getAccountByCode("210");
                $data["item"][0]["credit"] .= $entry->getAmount();
                $data["item"][0]["account_id"] = $account;
                $data["item"][1]["debit"] .= $entry->getAmount();
            }
        }
        elseif (strtolower($entry->getPerson()) == "alison") {
            if($entry->getType() == "Expense"){
                $data["item"][0]["account_id"] = self::getAccountByCode("211");
                $data["item"][0]["credit"] .= $entry->getAmount();
                $data["item"][1]["account_id"] = $account;
                $data["item"][1]["debit"] .= $entry->getAmount();
            }
            else{
                $data["item"][1]["account_id"] = self::getAccountByCode("211");
                $data["item"][0]["credit"] .= $entry->getAmount();
                $data["item"][0]["account_id"] = $account;
                $data["item"][1]["debit"] .= $entry->getAmount();
            }
        }
        if($entry->getAttachment()){
            $data['reference'] .= "Attachment: ".$entry->getAttachment();
        }
        else{
            $data['reference'] .= "No Attachment Included";
        }
        //Make journal Entry
        $responce = self::$session->post("/akaunting/double-entry/journal-entry", array(), $data);
        $ret = $responce->status_code;

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
        if($value = self::getAccountByKeyWord($name)){
            return $value;
        }
        foreach(self::$accounts as $k => $account){
            if(strtolower($account['name']) == strtolower($name)){
                return $k;
            }
        }
        return NULL;
    }

    private static function getAccountByKeyWord($string){
        /**
         * The key is the account name as written in akaunting
         * The value is a array of keywords which are equivilent to the the akaunting account
         * @var array $keywords
         */
        $keywords = array(
            'advertising'                               => array("ads","ad"),
            'wages and salaries'                        => array("wages", "salary"),
            'payroll tax expense (CPP & EI)'            => array("deductions", "payroll_tax"),
            'consulting - legal &accounting'            => array("tax-help", "lawyer", "accountant"),
            'meals & entertainment'                     => array("meal", "meals", "restaurant"),
            'postage - for reports (not advertising)'   => array("stamps", "postage"),
            'therapy supplies - toys/small items'       => array("toys", "toy"),
            'therapy supplies -- assessment tools'      => array("ax", "assessment", "ax_forms"),
            'therapy supplies -- books and manuals'     => array("book", "books", "manual", "manuals"),
            'therapy equipment'                         => array("equipment", "equip"),
            'education expense'                         => array("course", "courses", "education", "pd"),
            'mileage expenses (not clinical)'           => array("kms", "km", "mileage"),
            'office supplies'                           => array("office", "office supply"),
            'professional dues & memberships'           => array("caot", "osot", "dues"),
            'telephone and internet'                    => array("phone", "phone bill", "telephone")
        );
        
        foreach($keywords as $account=>$words){
            foreach ($words as $words){
                if(preg_match("/(^|[^\\w])"."$word"."([^\\w]|$)/i", $string)){
                    return self::getAccountByName($account);
                }
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

    public static function decodeErrors(array $errors):String{
        $s = "";
        foreach ($errors as $error){
            $s .= self::decodeError($error);
        }
        return $s;
    }
    
    public static function decodeError(int $error):String{
        $s = "Submission of Entry resulted in ";
        switch ($error){
            case self::REJECTED_NO_ACCOUNT:
                $s .= "not being able to find an account to put the entry in.";
                break;
            case self::REJECTED_NOT_SETUP:
                $s .= "the clinic is not setup for automatic Akaunting entries.";
                break;
            default:
                if($error >= 200 && $error < 300){
                    $s .= "the entry successfully being submitted to Akaunting.";
                }
                elseif ($error >= 400 && $error < 600){
                    $s .= "an Error while comunicating with Akaunting. Error:".$error;
                }
                else {
                    $s .= "an Unknown Error. Error:".$error;
                }
                break;
        }
        return $s."\n";
    }
    
}

function fixRedirects($return, $req_headers, $req_data, $options){
    if ($return->status_code === 302){
        $return->status_code = 303;
    }
}

?>