<?php

if(!defined("CATSLIB")){define("CATSLIB", "./");}

require_once(CATSLIB.'/vendor/autoload.php');
require_once('email_processor.php');

/**
 * Hook to submit journal entries to akaunting
 * @author Eric
 * @version 1.1
 */
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

    private static $bDebug = CATS_DEBUG;     // set this to true to turn on debugging messages

    private static function dbg( $s )  { if( self::$bDebug )  echo str_replace("\n", "<br />", "$s<br/>"); }

    public static function login(String $email, String $password, String $server){
        global $email_processor;

        self::dbg("Connecting to Akaunting...");
        if (self::$session != NULL){
            throw new Exception("Already Loged in");
        }
        $hooks = new Requests_Hooks();
        $hooks->register('requests.before_redirect_check', 'fixRedirects');
        self::$session = new Requests_Session($server);
        self::$session->options['hooks'] = $hooks;
        $responce = self::$session->get($email_processor['akauntingBaseUrl']."/auth/login");
        preg_match('|(?<=_token" value=").*?(?=")|', $responce->body, $matches);
        self::$_token = $matches[0];
        self::$session->cookies = new Requests_Cookie_Jar(Requests_Cookie::parse_from_headers($responce->headers));
        self::$session->post($email_processor['akauntingBaseUrl']."/auth/login", array(), array('_token' => self::$_token, 'email' => $email, 'password' => $password));
        self::dbg("connected");
    }

    public static function logout(){
        if (self::$session == NULL){
            throw new Exception("Not Logged in");
        }
        global $email_processor;
        self::$session->get($email_processor['akauntingBaseUrl']."/auth/logout");
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
        if($entry->getCategory() < 0){
            $account = $entry->getCategory()*-1;
        }
        else if(!$account){
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
            global $email_processor;
            $responce = self::$session->post($email_processor['akauntingBaseUrl']."/double-entry/journal-entry".($entry->getEntryID()?"/".$entry->getEntryID():""), array(), $data);
            $ret = $responce->status_code;
        }
        else{
            var_dump($data);
        }

        done:
        return( array($ret,$possibilities) );
    }

    /**
     * Get the akaunting accounts associated with the company
     * @param int $company - numerical company id of the company to fetch accounts for.
     * Passing a value of zero with use the current company.
     * @param bool $loadCach - wether or not the results of the fetch should be stored in self::$accounts
     * @throws Exception if no akaunting accounts are found. Or is we are not logged in to akaunting
     * @return array containing the results of the fetch. This is identical to self::$accounts if $loadCach is true
     */
    public static function fetchAccounts(int $company = 0,bool $fetchOnly = false ,bool $loadCach = true){
        //Ensure we have connected to Akaunting before we attempt to fetch accounts
        if (self::$session == NULL){
            throw new Exception("Not Logged in");
        }

        if($company){
            self::switchCompany($company,TRUE);
        }
        global $email_processor;
        $responce = self::$session->get($email_processor['akauntingBaseUrl']."/double-entry/journal-entry/create");
        preg_match('!<option.*?(?=</select>)!', $responce->body,$matches);
        $data = @$matches[0]?:"";
        if($company){
            self::restoreCompany();
        }
        if(!$fetchOnly){
            $accounts = self::loadAccounts($data);
            if($loadCach){
                self::$accounts = $accounts;
            }
            return $accounts;
        }
        else{
            return $data;
        }
    }

    private static function loadAccounts(String $data):array{
        preg_match_all('|(?<=\<option value=")(\d*)">(\d*) - (.*?)(?=<\/option>)|', $data, $matches, PREG_SET_ORDER);
        if(!$matches){
            throw new Exception("Could not find any accounts");
        }
        $accounts = array();
        foreach($matches as $match){
            $accounts[$match[1]] = array( "code" => $match[2], "name" => $match[3]);
        }
        return $accounts;
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
        global $email_processor;
        self::$session->get($email_processor['akauntingBaseUrl']."/common/companies/".$company."/set");
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

/**
 * Class which contains the information for a journal entry in Akaunting
 * Used by Akaunting Hook to submit entries to Akaunting
 * @author Eric
 * @version 2
 */
class AccountingEntry {

    //TODO find a more expandable way of doing this
    /**
     * @var array Mappings of names to liability accounts
     * @static
     */
    private static $liability_mappings = array('sue' => 210, 'alison' => 211);

    /**
     * @var float The amount the entry is for
     */
    private $amount;
    /**
     * @var String The type of the entry
     * Accepted types are Income or Expense
     */
    private $type;
    /**
     * @var int The akaunting company id to put the entry into
     */
    private $company;
    /**
     * @var String|int Category to put the entry.
     * This can be the account name, account code, or account_id.
     * A negative number is used to separate an account_id from an account code
     * since codes cant be negative.
     * Since account_id's also can't be negative it is converted to a positive before its submitted to Akaunting.
     */
    private $category;
    /**
     * @var String Date which the entry is to be filed under
     */
    private $paid_at;
    /**
     * @var String reference to the attachment file if any.
     */
    private $attachment;
    /**
     * @var String description of entry
     */
    private $description;
    /**
     * @var int Liability account to balance the entry
     */
    private $liability_account;
    /**
     * @var int id of the entry in akaunting, set to zero for new entry
     */
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

    /**
     * Create an entry from the data recieved in an email.
     * Pre Version 2 the code in this method could be found in the constructor
     * @since Version 1
     * @param float $amount Amount of entry
     * @param String $type Type of entry
     * @param String $clinic Name of clinic with akaunting company linked to file entry in
     * @param String $date Date when entry is to be submitted under
     * @param String $category Category of entry
     * @param String|null $attachment Reference to an attachment is any
     * @param bool $ccc If company credit card was used
     * @param String $desc Description of entry
     * @param String $person Who made the entry. If $ccc is false this is used to determine the liability account to balance entry
     * @param String $account Type of account. Can be UNPAID or CA
     * @param int $entryId Id of entry in akaunting or zero to create a new entry
     * @return AccountingEntry
     */
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
                $liability_account = (@self::$liability_mappings[strtolower($person)]?:0);
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

    /**
     * Create an entry from an array
     * @since Version 2
     * @todo Complete method
     * @param array $ra Data for the journal entry
     * @return AccountingEntry
     */
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