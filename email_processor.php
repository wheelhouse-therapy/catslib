<?php

require_once(CATSLIB.'/vendor/autoload.php');
require_once( SEEDCORE."SEEDEmail.php" );
require_once 'share_resources.php';

use Ddeboer\Imap\Server;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Flag\Unseen;
use Ddeboer\Imap\MessageIterator;
use Ddeboer\Imap\Message\EmailAddress;
use Ddeboer\Imap\Message\Attachment;

class ReceiptsProcessor {

    //Constants
    const HST = 1.13;
    const FOLDER = CATSDIR_FILES."/acounting/attachments/";

    //Potential proccessing error code constants
    const DISCARDED_NO_AMOUNT   = -1;
    const DISCARDED_NO_DATE     = -2;
    const DISCARDED_ZERO_AMOUNT = -3;
    const DISCARDED_UNKNOWN_SENDER = -4;
    const DISCARDED_UNKNOWN_ACCOUNT = -5;

    //Body Entries Cutoff numbers
    const EMPTY_LINE_CUTOFF = 2;
    const DASH_CUTOFF = 8;

    //Paterns used to pull information out of emails
    private $PATTERNS = array(
        "amount" => "/\\$\\-?[0-9]+\\.?[0-9]*H?($|[, ])/",
        "income" => "/income/i",
        "date"   => "/(?<=^| )((Jan|Feb|Mar|Apr|May|Jun|June|Jul|July|Aug|Sept|Sep|Oct|Nov|Dec) [0-3]?[0-9](?:, |\/)[0-9]{2,4})|([0-1]?[0-9]\\/[0-3]?[0-9]\/\\d{2}(\\d{2})?)/i",
        "companyCreditCard" => "/(?<=^|[^\\w])ccc(?=[^\\w])/i",
        "companyAccount" => "/(?<=^|[^\\w])ca(?=[^\\w])/i",
        "unpaid" => "/(?<=^|[^\\w])unpaid(?=[^\\w])/i",
        "forward" => "/Fwd:/i",
        "reply" => "/Re:/i"
    );

    private $connection;

    public function __construct($server,$email, $psw){
        $server = new Server($server);
        $this->connection = $server->authenticate($email,$psw);
        if(!file_exists(self::FOLDER)){
            @mkdir(self::FOLDER, 0777, true);
            echo "Attachments Directiory Created<br />";
        }
    }

    public function processEmails($box = 'INBOX'){
        $mailbox = $this->connection->getMailbox($box);
        $search = new SearchExpression();
        $search->addCondition(new Unseen());
        $this->processMessages($mailbox->getMessages($search));
    }

    private function processMessages(MessageIterator $messages){
        echo "Processing ".count($messages)." messages<br/>";
        foreach($messages as $message){
            $attachment = microtime(TRUE);

            if($this->getValidAttachment(new ArrayOfAttachment($message->getAttachments()))){
                $attachment = '';
            }
            preg_match('/(?<=\.)\w+(?=@)/i', $message->getTo()[0]->getAddress(), $matches);
            if(!$matches){
                $responce = "This Message has been rejected since the system cannot determine the clinic from the to address.";
                goto done;
            }
            $clinic = $matches[0];

            $entries = array();
            $errors = array();
            $from = $message->getFrom();
            $subject = $message->getSubject();
            $date = $message->getDate();
            $body = $message->getBodyText();
            $subject = $message->getSubject();

            //Pull the information out of subject
            $result = $this->processString($subject, $attachment, $clinic,$from);
            if($result instanceof AccountingEntry){
                $entries['subject'] = $result;
            }
            else{
                    $errors['subject'] = $result;
            }
            if($body){
                $lines = explode("\n", $body);
                $emptyLineCount = 0;
                //For every line in the body
                //Pull the information out
                foreach ($lines as $key => $line){
                    if(!preg_match_all('/[^\s-]+/', $line)){
                        //The line is empty, don't try to process
                        //The line will only match if it only contains whitespace and dashes
                        $emptyLineCount++;
                        continue;
                    }
                    if($emptyLineCount >= self::EMPTY_LINE_CUTOFF || substr_count($line, "-") >= self::DASH_CUTOFF){
                        //Its safe to assume there won't be any entries after this point
                        break;
                    }
                    $emptyLineCount = 0;
                    $result = $this->processString($line, $attachment, $clinic, $from);
                    if($result instanceof AccountingEntry){
                        $entries["line_".$key] =  $result;
                    }
                    else{
                        $errors["line_".$key] = $result;
                    }
                }
            }
            if(count($entries) > 0 && !$this->verifyString($subject)){
                // The subject is not a potential entry. do not report the error
                unset($errors['subject']);
            }
            
            // Mark the message as processed so we dont make duplicate entries
            $message->markAsSeen();
            
            // Send the entries to Akaunting and record the results
            $results = AkauntingHook::submitJournalEntries($entries);
            
            if(array_intersect(range(200,299), $results) && $attachment){
                if($oAttachment = $this->getValidAttachment(new ArrayOfAttachment($message->getAttachments()))){
                    $attachmentFile = fopen(self::FOLDER.$attachment.".".pathinfo($oAttachment->getFilename(), PATHINFO_EXTENSION), "w");
                    fwrite($attachmentFile, $oAttachment->getDecodedContent());
                    fclose($attachmentFile);
                }
            }
            
            /* Compile the responce to send to the sender which reports the results of their entries.
             * This includes all errors raised by the email proccessor as well as errors raised by the Akaunting Hook.
             * It Also notes entries which were submitted successfully.
             */
            $responce = $this->handleErrors($errors).AkauntingHook::decodeErrors($results);
            
            if(count($message->getAttachments()) > 1 && $this->getValidAttachment(new ArrayOfAttachment($message->getAttachments()))){
                $responce .= "\nNOTE: Only the first valid (not used in our system) was stored\n";
            }
            
            done:
            // Add a closing message
            $responce .= "\nOur Dev Team is happy to help with any problems you encounter while using this system.\n"
                         ."You can reach them at developer@catherapyservices.ca\n"
                         ."\nCATS Automatic Akaunting Entry System"
                         .str_repeat("\n", 3)
                         ."--------Original Message--------\n"
                         ."From: ".$message->getFrom()->getAddress()
                         ."\nTo: ".$message->getTo()[0]->getAddress()
                         ."\nDate: ".$message->getDate()->format("m/d/y H:i")
                         ."\nSubject: ".$message->getSubject()
                         .str_repeat("\n", 2)
                         .$message->getBodyText();
            
            $tempFile = NULL;
            //Send the results
            if($attachment){
                $tempFile = new TempAttachment($this->getValidAttachment(new ArrayOfAttachment($message->getAttachments())));
            }
            SEEDEmailSend($message->getTo()[0]->getAddress(), $from->getAddress(), $subject, $responce, "", array('reply-to' => "developer@catherapyservices.ca", 'attachments' =>($tempFile?array($tempFile->path):"")));
            
        }
    }

    private function processString(String $value, String $attachment, String $clinic, EmailAddress $from){
        preg_match($this->PATTERNS['amount'], $value, $matches);
        if(count($matches) === 0){
            return self::DISCARDED_NO_AMOUNT;
        }
        $amount = $matches[0];
        $amount = substr($amount, 1, -1); //Extract amount from wrappers
        if(substr_compare($amount, "H", -1, 1, TRUE) == 0){
            $amount = substr($amount, 0, -1); //Remove the 'H' from the amount
            $amount *= self::HST;
            $amount = round($amount, 2);
        }
        $incomeOrExpense = NULL; // Start as not defined
        if($amount < 0 || preg_match($this->PATTERNS["income"], $value)){
            $incomeOrExpense = "Income";
            if($amount < 0){
                $amount *= -1;
            }
        }
        elseif ($amount > 0){
            $incomeOrExpense = "Expense";
        }
        
        if(preg_match($this->PATTERNS['companyCreditCard'], $value) == 0 && !(SEEDCore_StartsWith($from->getAddress(), "sue") || SEEDCore_StartsWith($from->getAddress(), "alison"))){
            return self::DISCARDED_UNKNOWN_SENDER;
        }

        $caOrUnpaid = NULL; // Start as not defined
        if(preg_match($this->PATTERNS['companyAccount'], $value) != 0 || preg_match($this->PATTERNS['unpaid'], $value) != 0 || preg_match($this->PATTERNS['companyCreditCard'], $value) != 0){
            if(preg_match($this->PATTERNS['companyAccount'], $value) != 0 && preg_match($this->PATTERNS['unpaid'], $value) == 0 && preg_match($this->PATTERNS['companyCreditCard'], $value) != 0){
                $caOrUnpaid = "CA";
            }
            else if((preg_match($this->PATTERNS['unpaid'], $value) != 0 || preg_match($this->PATTERNS['companyCreditCard'], $value) != 0) && preg_match($this->PATTERNS['companyAccount'], $value) == 0){
                $caOrUnpaid = "UNPAID";
            }
        }
        
        if(!$caOrUnpaid){
            return self::DISCARDED_UNKNOWN_ACCOUNT;
        }
        
        if($incomeOrExpense){
            preg_match($this->PATTERNS["date"], $value, $matches);
            if(count($matches) === 0){
                return self::DISCARDED_NO_DATE;
            }

            $date = $matches[0];
            
            preg_match('|\w.*\w|',preg_replace($this->PATTERNS, "", $value), $matches);
            $category = $matches[0];
            preg_match("/\w+(?=@)/i", $from->getAddress(), $matches);
            $person = $matches[0];
            return new AccountingEntry($amount, $incomeOrExpense, $clinic, $date,$category, $attachment, (preg_match($this->PATTERNS['companyCreditCard'], $value) > 0), $value, $person, $caOrUnpaid);
        }
        return self::DISCARDED_ZERO_AMOUNT;
    }

    private function handleErrors(array $errors){
        $responce = "";
        foreach($errors as $k => $v){
            switch ($v){
                case self::DISCARDED_NO_AMOUNT:
                    $responce .= "Missing Amount ".($k == "subject"?"in ":"on ").str_replace("_", " ", $k)."\n"
                                ."Please check that the amount begins with a $, ends with either a comma, a space or the end of the line/subject, and contains a maximum of one decimal\n";
                    break;
                case self::DISCARDED_NO_DATE:
                    $responce .= "Missing Date ".($k == "subject"?"in ":"on ").str_replace("_", " ", $k)."\n"
                               ."Accepted formats are MMM(M) DD/YY(YY), MMM(M) DD, YY(YY) and MM/DD/YY(YY). Values in brackets are optional.\n";
                    break;
                case self::DISCARDED_ZERO_AMOUNT:
                    $responce .= "Amount is zero ".($k == "subject"?"in ":"on ").str_replace("_", " ", $k)."\n"
                                ."The amount was calulated as zero and the entry was discarded since it does not affect the balance\n";
                    break;
                case self::DISCARDED_UNKNOWN_ACCOUNT:
                    $responce .= "Could not determine account to charge the amount to ".($k == "subject"?"in ":"on ").str_replace("_", " ", $k)."\n"
                                ."This is a different error than Akaunting rejecting the entry.\n"
                                ."Possible entries are: UNPAID, CCC and CA.\n";
                    break;
            }
        }
        return $responce;
    }

    /**
     * Check if a string is a potential entry
     * It must match at least 2 of the regex strings used for parsing to be considered a potential entry
     * @param String $value - string to check potentiality
     * @return boolean - True if string matches 2 or more of the parsing regex expressions
     */
    private function verifyString(String $value){
        $matches = 0;
        foreach($this->PATTERNS as $name=>$regex){
            // Dont count forward or reply regex matches towords is the string is valid
            if(preg_match($regex, $value) && !($name == 'forward' || $name == 'reply')){
                $matches++;
            }
        }
        return $matches >= 2;
    }
    
    /** Return attachment object of the first valid attachment
     * Or NULL if there is none
     * @param array $attachments - array of attachments included in the message
     * @return Attachment - attachment object representing the first valid attachment or NULL if it does not exist
     */
    private function getValidAttachment(ArrayOfAttachment $attachments){
        if(count($attachments) == 0){
            goto notfound;
        }
        foreach($attachments as $attachment){
            if(!file_exists(CATSDIR_IMG.$attachment->getFilename())){
                return $attachment;
            }
        }
        notfound:
        return NULL;
    }
    
}

class AccountingEntry {

    private $amount;
    private $type;
    private $clinic;
    private $category;
    private $date;
    private $attachment;
    private $ccc;
    private $desc;
    private $person;
    private $account; // Account to use to balance the entry

    function __construct($amount, String $type, String $clinic, $date, String $category, $attachment, bool $ccc, String $desc, String $person, String $account){
        $this->clinic = $clinic;
        $this->amount = $amount;
        $this->type = $type;
        $this->category = $category;
        $this->date = $this->parseDate($date);
        $this->attachment = $attachment;
        $this->ccc = $ccc;
        $this->desc = $desc;
        $this->person = $person;
        $this->account = $account;
    }

    public function getAmount(){
        return $this->amount;
    }

    public function getType(){
        return $this->type;
    }
    public function getClinic()
    {
        return $this->clinic;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getAttachment()
    {
        return $this->attachment;
    }

    public function getPerson()
    {
        if($this->ccc){
            return "CCC";
        }
        return $this->person;
    }

    public function getDesc()
    {
        return $this->desc;
    }

    public function getAccount(){
        return $this->account;
    }
    
    private function parseDate(String $date): String {
        if(preg_match("/(Jan|Feb|Mar|Apr|May|Jun|June|Jul|July|Aug|Sept|Sep|Oct|Nov|Dec) [0-3]?[0-9](?:, |\/)[0-9]{2,4}/i", $date)){
            //Clear up some of the double options
            $date = str_replace("Sep ", "Sept ", $date);
            $date = str_replace("June", "Jun", $date);
            $date = str_replace("July", "Jul", $date);
            
            //Format switchers
            $day = (preg_match("/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sept|Oct|Nov|Dec) [0-3][0-9](?:, |\/)[0-9]{2,4}/i", $date)?"d":"j");
            $year = (preg_match("/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sept|Oct|Nov|Dec) [0-3]?[0-9](?:, |\/)[0-9]{4}/i", $date)?"Y":"y");
            
            //Clear up alternate date formats
            $date = str_replace(", ", "/", $date);
            
            return DateTime::createFromFormat('M '.$day.'/'.$year, $date)->format('Y-m-d');
        }
        if(preg_match("/[0-1]?[0-9]\\/[0-3]?[0-9]\/\\d{2}(\\d{2})?/", $date)){
            $day = (preg_match("/[0-1]?[0-9]\\/[0-3][0-9]\/\\d{2}(\\d{2})?/", $date)?"d":"j");
            $month = (preg_match("/[0-1][0-9]\\/[0-3]?[0-9]\/\\d{2}(\\d{2})?/", $date)?"m":"n");
            $year = (preg_match("/[0-1]?[0-9]\\/[0-3]?[0-9]\/\\d{4}/", $date)?"Y":"y");
            
            return DateTime::createFromFormat($month.'/'.$day.'/'.$year, $date)->format('Y-m-d');
        }
    }
    
}

class ResourcesProcessor {
    
    private $connection;
    
    public function __construct($server,$email, $psw){
        $server = new Server($server);
        $this->connection = $server->authenticate($email,$psw);
    }
    
    public function processEmails($box = 'INBOX'){
        $mailbox = $this->connection->getMailbox($box);
        $search = new SearchExpression();
        $search->addCondition(new Unseen());
        $this->processMessages($mailbox->getMessages($search));
    }
    
    private function processMessages($messages){
        echo "Processing ".count($messages)." messages<br/>";
        foreach($messages as $message){
            $successfulEntries = array();
            $attachments = $this->getValidAttachments(new ArrayOfAttachment($message->getAttachments()));
            foreach ($attachments as $attachment){
                if($attachmentFile = fopen(/*CATSDIR_RESOURCES*/"../cats/resources/"."pending/".$attachment->getFilename(), "w")){
                    fwrite($attachmentFile, $attachment->getDecodedContent());
                    fclose($attachmentFile);
                    array_push($successfulEntries,$attachment->getFilename());
                }
            }
            
            $message->markAsSeen();
            
            $responce = "Success: ".count($successfulEntries)."\n"
                       ."Error:".(count($message->getAttachments())-count($successfulEntries))."\n"
                       .str_repeat("\n", 3)
                       ."The following files were entered successfully:\n"
                       .SEEDCore_ArrayExpandSeries($successfulEntries, "[[]]\n");
                        
            $responce .= "\nOur Dev Team is happy to help with any problems you encounter while using this system.\n"
                        ."You can reach them at developer@catherapyservices.ca\n"
                        ."\nCATS Automatic Resource Entry System"
                        .str_repeat("\n", 3)
                        ."--------Original Message--------\n"
                        ."From: ".$message->getFrom()->getAddress()
                        ."\nTo: ".$message->getTo()[0]->getAddress()
                        ."\nDate: ".$message->getDate()->format("m/d/y H:i")
                        ."\nSubject: ".$message->getSubject()
                        .str_repeat("\n", 2)
                        .$message->getBodyText();
            $tempFiles = NULL;
            if($message->getAttachments()){
                $tempFiles = TempAttachment::createRA(new ArrayOfAttachment($message->getAttachments()));
            }
            // TODO Auto determine who to reply to
            // This should only occur if the email is sent from a send only email
            SEEDEmailSend($message->getTo()[0]->getAddress(), $message->getFrom()->getAddress(), $message->getSubject(), $responce, "", array('reply-to' => "developer@catherapyservices.ca", 'attachments' =>($tempFiles?TempAttachment::createRAOfPaths($tempFiles):"")));
                                        
        }
    }
    
    /** Returns array of attachment objects of all valid attachments
     * Or empty array if there are none
     * @param array $attachments - array of attachments included in the message
     * @return array - of attachment objects representing all valid attachments
     */
    private function getValidAttachments(ArrayOfAttachment $attachments){
        $valid = new ArrayOfAttachment();
        foreach($attachments as $attachment){
            if(!file_exists(CATSDIR_IMG.$attachment->getFilename()) && !file_exists(/*CATSDIR_RESOURCES*/"../cats/resources/"."pending/".$attachment->getFilename())){
                echo $attachment->getFilename()." is Valid";
                $valid[] = $attachment;
            }
        }
        return $valid;
    }
    
}

class TempAttachment {
    
    public $path;
    
    public function __construct(Attachment $oAttachment)
    {
        $this->path = sys_get_temp_dir().$oAttachment->getFilename();
        if($file = fopen($this->path, "w")){
            fwrite($file, $oAttachment->getDecodedContent());
            fclose($file);
        }
    }
    
    public function __destruct()
    {
        unlink($this->path);
    }
    
    static function createRA(ArrayOfAttachment $attachments){
        $tempAttachments = new ArrayOfTempAttachment();
        foreach($attachments as $attachment){
            $tempAttachments[] = new TempAttachment($attachment);
        }
        return $tempAttachments;
    }
    
    static function createRAOfPaths(ArrayOfTempAttachment $attachments){
        $ra = array();
        foreach ($attachments as $attachment){
            array_push($ra, $attachment->path);
        }
        return $ra;
    }
    
}

//Array Hack Classes

class ArrayOfAttachment extends \ArrayObject {
    public function offsetSet($key, $val) {
        if ($val instanceof Attachment) {
            return parent::offsetSet($key, $val);
        }
        throw new \InvalidArgumentException('Value must be an Attachment');
    }
    public function __call($func, $argv)
    {
        if (!is_callable($func) || substr($func, 0, 6) !== 'array_')
        {
            throw new BadMethodCallException(__CLASS__.'->'.$func);
        }
        return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
    }
}

class ArrayOfTempAttachment extends \ArrayObject {
    public function offsetSet($key, $val) {
        if ($val instanceof TempAttachment) {
            return parent::offsetSet($key, $val);
        }
        throw new \InvalidArgumentException('Value must be a TempAttachment');
    }
    public function __call($func, $argv)
    {
        if (!is_callable($func) || substr($func, 0, 6) !== 'array_')
        {
            throw new BadMethodCallException(__CLASS__.'->'.$func);
        }
        return call_user_func_array($func, array_merge(array($this->getArrayCopy()), $argv));
    }
}

?>