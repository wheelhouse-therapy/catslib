<?php

require_once(CATSLIB.'/vendor/autoload.php');
require_once( SEEDCORE."SEEDEmail.php" );
require_once 'share_resources.php';
require_once 'AkauntingHook.php';

use Ddeboer\Imap\Server;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Flag\Unseen;
use Ddeboer\Imap\MessageIterator;
use Ddeboer\Imap\Message\EmailAddress;
use Ddeboer\Imap\Message\Attachment;
use Ddeboer\Imap\Search\Flag\Flagged;

class ReceiptsProcessor {

    //Constants
    const HST = 1.13;
    const GST = 1.05;
    const FOLDER = CATSDIR_FILES."/acounting/attachments/";

    //Potential proccessing error code constants
    /**
     * No amount was found in the entry
     */
    const DISCARDED_NO_AMOUNT       = -1;
    /**
     * No date was found in the entry
     */
    const DISCARDED_NO_DATE         = -2;
    /**
     * The parsed date has not occured so there is no possible way this entry is real
     */
    const DISCARDED_FUTURE_DATE     = -3;
    /**
     * The amount found is zero. Assume invalid since and entry of zero wont change clinic balance
     */
    const DISCARDED_ZERO_AMOUNT     = -4;
    /**
     * Could not identify the sender
     */
    const DISCARDED_UNKNOWN_SENDER  = -5;
    /**
     * Could not identify if the entry is unpaid/company account/company credit card
     */
    const DISCARDED_UNKNOWN_ACCOUNT = -6;
    /**
     * Cannot schedule an entry with an attachment
     */
    const DISCARDED_SCHEDULING_FAILED = -7;

    //Body Entries Cutoff numbers
    const EMPTY_LINE_CUTOFF = 2;
    const DASH_CUTOFF = 8;
    const RETRY_CUTOFF = 3;

    //Type Constants
    const EXPENSE = "Expense";
    const INCOME = "Income";
    const NORMAL_PAYMENT = "Normal";
    const SCHEDULED_PAYMENT = "Scheduled";
    
    //Account Constants
    const COMPANY_ACCOUNT = "CA";
    const COMPANY_CREDIT_CARD_ACCOUNT = "CCC";
    const UNPAID_ACCOUNT = "UNPAID";
    
    //Paterns used to pull information out of emails
    private $PATTERNS = array(
        "amount" => '/\$-?[0-9]+\.?[0-9]*[HG]?($|[, ])/',
        "income" => "/".self::INCOME."/i",
        "scheduled" => "/".self::SCHEDULED_PAYMENT."/i",
        "dates"   => array(
            "/(?<=^| )(?'month'jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec),? (?'day'[1-3][0-9]|0?[1-9])(?:, |\/)(?'year'\d{2}|\d{4})(?=$| |,)/i",
            "/(?<=^| )(?'month'january|febuary|march|april|may|june|july|augest|september|october|november|december),? (?'day'[1-3][0-9]|0?[1-9])(?:, |\/)(?'year'\d{2}|\d{4})(?=$| |,)/i",
            "/(?<=^| )(?'month'1[0-2]|0?[1-9])\/(?'day'[1-3][0-9]|0?[1-9])\/(?'year'\d{2}|\d{4})(?=$| |,)/i"
        ),
        "companyCreditCard" => "/(?<=^|[^\\w])".self::COMPANY_CREDIT_CARD_ACCOUNT."(?=$|[^\\w])/i",
        "companyAccount" => "/(?<=^|[^\\w])".self::COMPANY_ACCOUNT."(?=$|[^\\w])/i",
        "unpaid" => "/(?<=^|[^\\w])".self::UNPAID_ACCOUNT."(?=$|[^\\w])/i",
        "forward" => "/Fwd:/i",
        "reply" => "/Re:/i",
        "escapedSequence" => '/(?:"|“).*?(?:"|“)/'
    );

    private $connection;
    private $email;

    public function __construct($server,$email, $psw){
        $server = new Server($server);
        $this->connection = $server->authenticate($email,$psw);
        $this->email = $email;
        if(!file_exists(self::FOLDER)){
            @mkdir(self::FOLDER, 0777, true);
            echo "Attachments Directiory Created<br />";
        }
    }

    public function __destruct(){
        $this->connection->close();
    }
    
    public function processEmails($box = 'INBOX'){
        $mailbox = $this->connection->getMailbox($box);
        $search = new SearchExpression();
        if(CATS_DEBUG){
            //Flagged Messages require dev investigation.
            $search->addCondition(new Flagged());
        }
        else{
            $search->addCondition(new Unseen());
        }
        $this->processMessages($mailbox->getMessages($search));
    }

    private function processMessages(MessageIterator $messages){
        echo "Processing ".count($messages)." messages<br/>";
        foreach($messages as $message){
            $attachment = microtime(TRUE);

            if(!$this->getValidAttachment(new ArrayOfAttachment($message->getAttachments()))){
                $attachment = '';
            }
            preg_match('/AUTO SUBMISSION #(?<i>\d) FOR (?<clinic>\w+)/', $message->getSubject(),$options);
            preg_match('/(?<=\.)\w+(?=@)/i', $message->getTo()[0]->getAddress(), $matches);
            if(!$options && !$matches){
                $responce = "This Message has been rejected since the system cannot determine the clinic from the to address.";
                goto done;
            }
            $clinic = $options?$options['clinic']:$matches[0];

            $entries = array();
            $errors = array();
            $i = $options?$options['i']:0;
            $from = $message->getFrom();
            $subject = $message->getSubject();
            $date = $message->getDate();
            $body = $message->getBodyText();

            //Pull the information out of subject
            if(!$options){
                //Dont attempt to pull information out of the subject if it matches our auto responce since all entries will be in the body
                $result = $this->processString($subject, $attachment, $clinic,$from);
                if($result instanceof AccountingEntry){
                    $entries['subject'] = $result;
                }
                else{
                        $errors['subject'] = $result;
                }
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
                    $line = trim($line,'*');
                    if($emptyLineCount >= self::EMPTY_LINE_CUTOFF || preg_match('/-{'.self::DASH_CUTOFF.',}/', $line)){
                        //Its safe to assume there won't be any entries after this point
                        break;
                    }
                    $emptyLineCount = 0;
                    if(!preg_match_all('/[^\s-]+/', $line)){
                        //The line is empty but contains asterisks, don't try to process
                        //The line will only match if it only contains whitespace, dashes and asterisks
                        //The only difference between here and the above empty check is that we don't count the line as empty
                        continue;
                    }
                    $result = $this->processString($line, $attachment, $clinic, $from);
                    if($result instanceof AccountingEntry){
                        $entries["line_".($key+1)] =  $result;
                    }
                    else{
                        $errors["line_".($key+1)] = $result;
                    }
                }
            }
            if(count($entries) > 0 && !$this->verifyString($subject)){
                // The subject is not a potential entry. do not report the error
                unset($errors['subject']);
            }
            
            // Send the entries to Akaunting and record the results
            $results = AkauntingHook::submitJournalEntries($entries);
            
            //AkauntingHook will return all entries that result in successfull communication as well as unsuccessful
            //However we need to sort out the different types of entries
            $failures = $results['failed'];
            $results = array_diff_key($results, array('failed' => TRUE));
            
            // Mark the message as processed so we dont make duplicate entries
            if($results || $errors){
                if(CATS_DEBUG){
                    // Instead of marking as seen we will unflag proccessed messages on dev mechines
                    $message->clearFlag('\Flagged');
                }
                else{
                    $message->markAsSeen();
                }
            }
            else if(!CATS_DEBUG){
                $raAttachments =  TempAttachment::createRA(new ArrayOfAttachment($message->getAttachments()));
                $clinics = array_map(create_function('$o', 'return $o->getClinic();'), $failures);
                $sortedEntries = array();
                foreach($clinics as $k=>$v){
                    $sortedEntries[$v][] = $failures[$k];
                }
                foreach($sortedEntries as $c=>$ra){
                    $body = "";
                    foreach($ra as $o){
                        $body .= $o->getDesc()."\n";
                    }
                    $to = "developer@catherapyservices.ca";
                    $topic = "Message for $c failed to submit more than cutoff";
                    if($i < self::RETRY_CUTOFF){
                        $i++;
                        $to = $this->email;
                        $topic = "AUTO SUBMISSION #$i FOR $c";
                    }
                    SEEDEmailSend($message->getFrom()->getAddress(), $to, $topic, $body, "", array('attachments' => TempAttachment::createRAOfPaths($raAttachments)));
                }
            }
            
            if(!CATS_DEBUG && array_intersect(range(200,299), array_column($results,0)) && $attachment){
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
        $amount = trim($amount, '$, '); //Extract amount from wrappers
        if(substr_compare($amount, "H", -1, 1, TRUE) == 0){
            $amount = substr($amount, 0, -1); //Remove the 'H' from the amount
            $amount *= self::HST;
            $amount = round($amount, 2);
        }
        else if(substr_compare($amount, "G", -1, 1, TRUE) == 0){
            $amount = substr($amount, 0, -1); //Remove the 'G' from the amount
            $amount *= self::GST;
            $amount = round($amount, 2);
        }
        $incomeOrExpense = NULL; // Start as not defined
        if($amount < 0 || preg_match($this->PATTERNS["income"], $value)){
            $incomeOrExpense = self::INCOME;
            if($amount < 0){
                $amount *= -1;
            }
        }
        elseif ($amount > 0){
            $incomeOrExpense = self::EXPENSE;
        }
        
        $typeOfPayment = self::NORMAL_PAYMENT; // Start as normal payment
        if(preg_match($this->PATTERNS["scheduled"], $value)){
            $typeOfPayment = self::SCHEDULED_PAYMENT;
        }
        
        if(preg_match($this->PATTERNS['companyCreditCard'], $value) == 0 && !(SEEDCore_StartsWith($from->getAddress(), "sue") || SEEDCore_StartsWith($from->getAddress(), "alison"))){
            return self::DISCARDED_UNKNOWN_SENDER;
        }

        $caOrUnpaid = NULL; // Start as not defined
        if(preg_match($this->PATTERNS['companyAccount'], $value) != 0 || preg_match($this->PATTERNS['unpaid'], $value) != 0 || preg_match($this->PATTERNS['companyCreditCard'], $value) != 0){
            if(preg_match($this->PATTERNS['companyAccount'], $value) != 0 && preg_match($this->PATTERNS['unpaid'], $value) == 0 && preg_match($this->PATTERNS['companyCreditCard'], $value) == 0){
                $caOrUnpaid = self::COMPANY_ACCOUNT;
            }
            else if((preg_match($this->PATTERNS['unpaid'], $value) != 0 || preg_match($this->PATTERNS['companyCreditCard'], $value) != 0) && preg_match($this->PATTERNS['companyAccount'], $value) == 0){
                $caOrUnpaid = self::UNPAID_ACCOUNT;
            }
        }
        
        if(!$caOrUnpaid){
            return self::DISCARDED_UNKNOWN_ACCOUNT;
        }
        
        if($incomeOrExpense){
            $date = $this->processDate($value);
            if($date == false){
                return self::DISCARDED_NO_DATE;
            }
            if(new DateTime($date) > new DateTime()){
                if($typeOfPayment == self::SCHEDULED_PAYMENT && $attachment){
                    return self::DISCARDED_SCHEDULING_FAILED;
                }
                else{
                    return self::DISCARDED_FUTURE_DATE;
                }
            }
            
            preg_match('|\w.*\w|',$this->preg_replace_array($this->PATTERNS, "", $value), $matches);
            $category = $matches[0];
            preg_match("/\w+(?=@)/i", $from->getAddress(), $matches);
            $person = $matches[0];
            return AccountingEntry::createFromEmail($amount, $incomeOrExpense, $clinic, $date,$category, $attachment, (preg_match($this->PATTERNS['companyCreditCard'], $value) > 0), $value, $person, $caOrUnpaid);
        }
        return self::DISCARDED_ZERO_AMOUNT;
    }

    private function processDate(string $value):string{
        $matches = array();
        $found = false;
        foreach($this->PATTERNS['dates'] as $pattern){
            if(preg_match($pattern, $value,$matches)){
                if(count($matches) == 7){
                    $found = true;
                    break;
                }
            }
        }
        if($found){
            $day = $matches['day'];
            $month = $matches['month'];
            $year = $matches['year'];
            
            // Attempt to convert textual months to numarical month
            // and check numarical months are in valid range
            // sets $found to false if its not valid
            switch(strtolower($month)){
                case "jan":
                case "january":
                    $month = 1;
                    break;
                case "feb":
                case "febuary":
                    $month = 2;
                    break;
                case "mar":
                case "march":
                    $month = 3;
                    break;
                case "apr":
                case "april":
                    $month = 4;
                    break;
                case "may":
                    $month = 5;
                    break;
                case "jun":
                case "june":
                    $month = 6;
                    break;
                case "jul":
                case "july":
                    $month = 7;
                    break;
                case "aug":
                case "auguest":
                    $month = 8;
                    break;
                case "sep":
                case "sept":
                case "september":
                    $month = 9;
                    break;
                case "oct":
                case "october":
                    $month = 10;
                    break;
                case "nov":
                case "november":
                    $month = 11;
                    break;
                case "dec":
                case "december":
                    $month = 12;
                    break;
                default:
                    if(1<$month || $month >12){
                        // The month is not valid
                        $found = false;
                    }
            }
        }
        return ($found?$month."/".$day."/".$year:"");
        
    }
    
    private function handleErrors(array $errors){
        $responce = "";
        foreach($errors as $k => $v){
            switch ($v){
                case self::DISCARDED_NO_AMOUNT:
                    $responce .= "Missing Amount ".($k == "subject"?"in ":"on ").str_replace("_", " ", $k)."\n"
                        ."Please check that the amount begins with a $, ends with either a comma, a space or the end of the ".($k == "subject"?"subject":"line").", and contains a maximum of one decimal\n";
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
                                ."Possible entries are: ".self::UNPAID_ACCOUNT.", ".self::COMPANY_CREDIT_CARD_ACCOUNT." and ".self::COMPANY_ACCOUNT.".\n";
                    break;
                case self::DISCARDED_FUTURE_DATE:
                    $responce .= "The date ".($k == "subject"?"in ":"on ").str_replace("_", " ", $k)." has not occured yet\n"
                                ."The entry was discarded since there is no possible way a payment was actually made on this day.\n";
                    break;
                case self::DISCARDED_SCHEDULING_FAILED:
                    $responce .= "The entry ".($k == "subject"?"in ":"on ").str_replace("_", " ", $k)." could not be submitted as a scheduled entry.\n"
                                ."This is because an attachment was included. If this entry needs to be scheduled, please send it again without and attachment.\n"
                                ."NOTE: Only valid attachments are considered. (ie. not used by the system. eg. logo)\n ";
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
            if(is_array($regex)){
                foreach ($regex as $key=>$exp){
                    if(preg_match($exp, $value) != false && !($key == 'forward' || $key == 'reply')){
                        $matches++;
                    }
                }
            }
            // Dont count forward or reply regex matches towords is the string is valid
            else if(preg_match($regex, $value) != false && !($name == 'forward' || $name == 'reply')){
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
    
    private function preg_replace_array($pattern, $replacement, $subject, $limit=-1) {
        if (is_array($pattern)) {
            foreach ($pattern as $value) $subject=$this->preg_replace_array($value, $replacement, $subject, $limit);
            return $subject;
        } else {
            return preg_replace($pattern, $replacement, $subject, $limit);
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
            $recipient = $message->getFrom()->getAddress();
            if(stripos($message->getFrom()->getAddress(),"no-reply") == 0 || stripos($message->getBodyText(), "send-only") || stripos($message->getBodyText(), "send only") || stripos($message->getBodyText(), "do not reply")){
                //TODO Programatically determine who to send the return email to.
                // This Will likely come from the subject and will only happen if the email came from a send only address
                $recipient = "developer@catherapyservices.ca";
            }
            SEEDEmailSend($message->getTo()[0]->getAddress(), $recipient, $message->getSubject(), $responce, "", array('reply-to' => "developer@catherapyservices.ca", 'attachments' =>($tempFiles?TempAttachment::createRAOfPaths($tempFiles):"")));
                                        
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
            if(!file_exists(CATSDIR_IMG.$attachment->getFilename()) && !file_exists(CATSDIR_RESOURCES."pending/".$attachment->getFilename())){
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