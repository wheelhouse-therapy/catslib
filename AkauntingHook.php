<?php

if(!defined("CATSLIB")){define("CATSLIB", "./");}

require_once(CATSLIB.'/vendor/autoload.php');
require_once('email_processor.php');

class AkauntingHook {

    const REJECTED_NO_ACCOUNT = -1;
    const REJECTED_NOT_SETUP = -2;
    const REJECTED_NO_PERSON = -3;
    const REJECTED_NO_CCC = -4;
    const REJECTED_NO_CA = -5;

    private static $session = NULL;
    private static $_token = NULL;
    private static $accounts = array();
    private static $company = 0;

    private static $bDebug = true;     // set this to true to turn on debugging messages

    private static function dbg( $s )  { if( self::$bDebug )  echo str_replace("\n", "<br />", "$s<br/>"); }

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
        self::$company = 0;
    }

    public static function submitJournalEntries(array $entries):array{
        if (self::$session == NULL){
            throw new Exception("Not Logged in");
        }
        $responces = array();
        $failedEntries = array();
        foreach($entries as $k=>$entry){
            try{
                $responces[$k] = self::submitJournalEntry($entry);
            }catch(Requests_Exception $e){
                //An error occured while proccessing an entry.
                $failedEntries[$k] = $entry;
            }
        }
        return array_merge($responces,array('failed' => $failedEntries));
    }

    public static function submitJournalEntry(AccountingEntry $entry):array{
        //Ensure we have connected to Akaunting before we attempt to submit entries
        if (self::$session == NULL){
            throw new Exception("Not Logged in");
        }
        
        $ret = self::REJECTED_NOT_SETUP;

        self::dbg("\nSubmitting Entry");

        // Switch to the correct Company
        self::switchCompany($entry->getCompany());

        //Fetch accounts
        self::fetchAccounts();

        $data = array("_token" => self::$_token, "paid_at" => $entry->getDate(), "description" => $entry->getDescription(), "item" => array(
                      array("account_id" => "", "debit" => "$0.00", "credit" => "$"),
                      array("account_id" => "", "debit" => "$",     "credit" => "$0.00")
                ), "reference" => "System Entry. ", "currency_code" => "CAD");
        if (self::$session == NULL){
            throw new Exception("Not Loged in");
        }
        list($account,$possibilities) = self::getAccountByName($entry->getCategory());
        if(!$account){
            list($account,$accountPossibilities) = self::getAccountByCode($entry->getCategory());
            if(!empty($accountPossibilities)){
                $possibilities = $accountPossibilities;
            }
        }
        if($account == NULL){
            $ret = self::REJECTED_NO_ACCOUNT;
            goto done;
        }
        if(!$entry->getLiability() || !self::getAccountByCode($entry->getLiability())){
            $ret = self::REJECTED_NO_PERSON;
            goto done;
        }
        if($entry->getLiability() == 201 && !self::getAccountByCode("201")){
            $ret = self::REJECTED_NO_CCC;
            goto done;
        }
        if($entry->getLiability() == 836 && !self::getAccountByCode("836")){
            $ret = self::REJECTED_NO_CA;
            goto done;
        }
        if($entry->getType() == "Expense"){
            $data["item"][0]["account_id"] = self::getAccountByCode($entry->getLiability())[0];
            $data["item"][0]["credit"] .= $entry->getAmount();
            $data["item"][1]["account_id"] = $account;
            $data["item"][1]["debit"] .= $entry->getAmount();
        }
        else{
            $data["item"][1]["account_id"] = self::getAccountByCode($entry->getLiability())[0];
            $data["item"][0]["credit"] .= $entry->getAmount();
            $data["item"][0]["account_id"] = $account;
            $data["item"][1]["debit"] .= $entry->getAmount();
        }
            
        if($entry->getAttachment()){
            $data['reference'] .= "Attachment: ".$entry->getAttachment();
        }
        else{
            $data['reference'] .= "No Attachment Included";
        }
        
        if($entry->getEntryID()){
            $data['_method'] = "PATCH";
        }
        
        //Make journal Entry
        // Only submit entries if we are not running off a production mechine
        if(!CATS_DEBUG){
            $responce = self::$session->post("/akaunting/double-entry/journal-entry".($entry->getEntryID()?"/".$entry->getEntryID():""), array(), $data);
            $ret = $responce->status_code;
        }
        else{
            var_dump($data);
        }

        done:
        return( array($ret,$possibilities) );
    }

    private static function fetchAccounts(int $company = 0){
        if($company){
            self::switchCompany($company,TRUE);
        }
        $responce = self::$session->get("/akaunting/double-entry/journal-entry/create");
        preg_match_all('|(?<=\<option value=")(\d*)">(\d*) - (.*?)(?=<\/option>)|', $responce->body, $matches, PREG_SET_ORDER);
        if(!$matches){
            throw new Exception("Could not find any accounts");
        }
        foreach($matches as $match){
            self::$accounts[$match[1]] = array( "code" => $match[2], "name" => $match[3]);
        }
        if($company){
            self::restoreCompany();
        }
    }

    private static function getAccountByName($name, $override = FALSE){
        $possible = array();
        if(!$override && list($value,$possible) = self::getAccountByKeyWord($name)){
            return array($value,$possible);
        }
        foreach(self::$accounts as $k => $account){
            if(strtolower($account['name']) == strtolower($name)){
                array_unshift($possible, $account['name']);
                return array($k,$possible);
            }
            else if(stripos($account['name'], $name) === 0){
                // The entry could possibly be incomplete and supposed to be this account.
                // Add to list of possibilities to show the send back in the responce for this entry
                $possible[] = $account['name'];
            }
        }
        return array(NULL,$possible);
    }

    private static function getAccountByKeyWord($string){
        /**
         * The key is the account name as written in akaunting
         * The value is a array of keywords which are equivilent to the the akaunting account
         * @var array $keywords
         */
        $keywords = array(
            'advertising'                               => array("ads","ad", "advertising"),
            'wages and salaries'                        => array("wages", "salary"),
            'payroll tax expense (CPP & EI)'            => array("deductions", "payroll_tax"),
            'rent'                                      => array("rent"),
            'consulting - legal &accounting'            => array("tax-help", "lawyer", "accountant"),
            'meals & entertainment'                     => array("meal", "meals", "restaurant"),
            'postage - for reports (not advertising)'   => array("stamps", "postage"),
            'therapy supplies - toys/small items'       => array("toys", "toy"),
            'therapy supplies -- assessment tools'      => array("ax", "assessment", "ax_forms"),
            'therapy supplies -- books and manuals'     => array("book", "books", "manual", "manuals"),
            'therapy equipment'                         => array("equipment", "equip"),
            'insurance'                                 => array("insurance"),
            'education expense'                         => array("course", "courses", "education", "pd"),
            'mileage expenses (not clinical)'           => array("kms", "km", "mileage"),
            'office supplies'                           => array("office"),
            'professional dues & memberships'           => array("caot", "osot", "dues"),
            'telephone and internet'                    => array("phone", "telephone")
        );
        foreach($keywords as $account=>$words){
            foreach ($words as $word){
                if(preg_match("/(^|[^\\w])"."$word"."([^\\w]|$)/i", $string)){
                    return self::getAccountByName($account, true);
                }
            }
        }
        
        return array(NULL,array());
        
    }
    
    private static function getAccountByCode($code){
        foreach(self::$accounts as $k => $account){
            if($account['code'] == $code){
                return array($k,array($account['name']));
            }
        }
        return array(NULL,array());
    }

    private static function switchCompany(int $company,bool $recoverable = FALSE){
        if(!$recoverable){
            self::$company = $company;
        }
        self::$session->get("/akaunting/common/companies/".$company."/set");
    }
    
    private static function restoreCompany(){
        self::switchCompany(self::$company);
    }
    
    public static function decodeErrors(array $errors):String{
        $s = "";
        foreach ($errors as $location=>$error){
            $s .= self::decodeError($location,$error);
        }
        return $s;
    }
    
    public static function decodeError(String $location,array $details):String{
        $s = "[Result] Submission of Entry ".($location == "subject"?"in ":"on ").str_replace("_", " ", $location)." resulted in ";
        $error = $details[0];
        switch ($error){
            case self::REJECTED_NO_ACCOUNT:
                $s .= "not being able to find an account to put the entry in.\n
                       When using key words, the Akaunting company associated with the clinic might not have an account that matches the name associated with the key word.\n
                       The following accounts were detected as possible accounts:"
                      .SEEDCore_ArrayExpandSeries($details[1], "\n[[]]");
                break;
            case self::REJECTED_NOT_SETUP:
                $s .= "the clinic is not setup for automatic Akaunting entries.";
                break;
            case self::REJECTED_NO_PERSON:
                $s .= "not being able to determine submitted the entry.";
                break;
            case self::REJECTED_NO_CCC:
                $s .= "not being able to find the Akaunting account for CCC (code 201)";
            case self::REJECTED_NO_CA:
                $s .= "not being able to find the Akaunting account for CA (code 836)";
            default:
                if($error >= 200 && $error < 300){
                    $s = str_replace("[Result]", "!SUCCESS!", $s);
                    $s .= "the entry successfully being submitted to Akaunting.\n"
                         ."The entry was submitted into ".$details[0];
                }
                elseif ($error >= 400 && $error < 600){
                    $s .= "an Error while comunicating with Akaunting. Error:".$error;
                }
                else {
                    $s .= "an Unknown Error. Error:".$error;
                }
                break;
        }
        //Replace result with Error if it has not yet been set
        $s = str_replace("[Result]", "!!ERROR!!", $s);
        return $s."\n";
    }
    
}

class AccountingEntry {
    
    //TODO find a more expandable way of doing this
    private static $liability_mappings = array('sue' => 210, 'alison' => 211);
    
    private $amount;
    private $type;
    private $company;
    private $category;
    private $paid_at;
    private $attachment;
    private $description;
    private $liability_account;
    private $entryId;
    
    private function __construct($amount, String $type, int $company, $paid_at, $category, $attachment, String $description, int $liability_account, int $entryId = 0){
        $this->company = $company;
        $this->amount = $amount;
        $this->type = $type;
        $this->category = $category;
        $this->paid_at = $this->parseDate($paid_at);
        $this->attachment = $attachment;
        $this->description = $description;
        $this->liability_account = $liability_account;
        $this->entryId = $entryId;
    }
    
    public static function createFromEmail($amount, String $type, String $clinic, $date, String $category, $attachment, bool $ccc, String $desc, String $person, String $account, int $entryId = 0):AccountingEntry{
        $oApp = $GLOBALS['oApp'];
        
        $clinics = (new Clinics($oApp))->getClinicsByName($clinic);
        if( !count($clinics) ) {
            self::dbg("You don't have clinic '".$clinic."' defined");
            return NULL;
        }
        $clinicId = $clinics[0];
        if( !($company = (new ClinicsDB($oApp->kfdb))->GetClinic($clinicId)->Value("akaunting_company")) ) {
            self::dbg("Clinic $clinicId doesn't have an Akaunting company code");
            return NULL;
        }
        
        $liability_account = 0;
        if($account == "UNPAID"){
            if($ccc){
                $liability_account = 201;
            }
            else{
                $liability_account = self::$liability_mappings[strtolower($person)];
            }
        }
        else if($account == "CA"){
            if($ccc){
                $liability_account = 201;
            }
            else{
                $liability_account = 836;
            }
        }
        
        return new AccountingEntry($amount, $type, $company, $date, $category, $attachment, $desc, $liability_account, $entryId);
        
    }
    
    public static function createFromRA(array $ra):AccountingEntry{
        //TODO Complete
        return NULL;
    }
    
    public function getAmount(){
        return $this->amount;
    }
    
    public function getType(){
        return $this->type;
    }
    public function getCompany()
    {
        return $this->company;
    }
    
    public function getCategory()
    {
        return $this->category;
    }
    
    public function getDate()
    {
        return $this->paid_at;
    }
    
    public function getAttachment()
    {
        return $this->attachment;
    }
    
    public function getDescription()
    {
        return $this->description;
    }
    
    public function getLiability(){
        return $this->liability_account;
    }
    
    public function getEntryID(){
        return $this->entryId;
    }
    
    private function parseDate(String $date): String {
        return (new DateTime($date))->format('Y-m-d');
    }
    
}

function fixRedirects($return, $req_headers, $req_data, $options){
    if ($return->status_code === 302){
        $return->status_code = 303;
    }
}

?>