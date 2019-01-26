<?php
if(!defined("CATSLIB")){define("CATSLIB", "./");}

require_once(CATSLIB.'/vendor/autoload.php');
require_once( SEEDCORE."SEEDEmail.php" );

use Ddeboer\Imap\Server;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Search\Flag\Unseen;
use Ddeboer\Imap\MessageIterator;
use Ddeboer\Imap\Message\EmailAddress;

class EmailProcessor {

    //Constants
    const HST = 1.13;
    const FOLDER = CATSDIR_FILES."/acounting/attachments/";

    //Potential proccessing error code constants
    const DISCARDED_NO_AMOUNT   = -1;
    const DISCARDED_NO_DATE     = -2;
    const DISCARDED_ZERO_AMOUNT = -3;
    const DISCARDED_UNKNOWN_SENDER = -4;

    //Body Entries Cutoff numbers
    const EMPTY_LINE_CUTOFF = 2;
    const DASH_CUTOFF = 8;

    //Paterns used to pull information out of emails
    private $PATTERNS = array(
        "amount" => "/\\$\\-?[0-9]+\\.?[0-9]*H?($|[, ])/",
        "income" => "/income/i",
        "date"   => "/(?<= )((Jan|Feb|Mar|Apr|May|Jun|June|Jul|July|Aug|Sept|Sep|Oct|Nov|Dec) [0-3]?[0-9]\/[0-9]{2,4})|([0-1]?[0-9]\\/[0-3]?[0-9]\/\\d{2}(\\d{2})?)/i",
        "companyCreditCard" => "/ccc/i"
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
            echo $attachment."<br />";

            $raAttachments = $message->getAttachments();
            if(count($raAttachments) > 0){
                $oAttachment = $raAttachments[0];
                $attachmentFile = fopen(self::FOLDER.$attachment.".".pathinfo($oAttachment->getFilename(), PATHINFO_EXTENSION), "w");
                fwrite($attachmentFile, $oAttachment->getDecodedContent());
                fclose($attachmentFile);
            }
            else{
                $attachment = '';
            }
            preg_match('/(?<=\.)\w+(?=@)/i', $message->getTo()[0]->getAddress(), $matches);
            if(!$matches){
                $responce = "This Message has been rejected since the system cannot determine the clinic from the to address.";
                if($attachment){
                    unlink(self::FOLDER.$attachment.".".pathinfo($oAttachment->getFilename(), PATHINFO_EXTENSION));
                }
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
                array_push($entries, $result);
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
                    if(!preg_match_all("/[$,-9A-Za-z]+/", $line)){
                        //The line is empty, don't try to process
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
                        array_push($entries, $result);
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
            
            if(count($entries) == 0){
                if($attachment){
                    unlink(self::FOLDER.$attachment.".".pathinfo($oAttachment->getFilename(), PATHINFO_EXTENSION));
                }
            }
            
            // Mark the message as processed so we dont make duplicate entries
            $message->markAsSeen();
            
            // Send the entries to Akaunting and record the results
            $results = AkauntingHook::submitJournalEntries($entries);
            
            if(!array_intersect(range(200,299), $results)){
                //There are no entries which were accepted into akaunting
                //Remove the attachment if there is one
                if($attachment){
                    unlink(self::FOLDER.$attachment.".".pathinfo($oAttachment->getFilename(), PATHINFO_EXTENSION));
                }
            }
            
            /* Compile the responce to send to the sender which reports the results of their entries.
             * This includes all errors raised by the email proccessor as well as errors raised by the Akaunting Hook.
             * It Also notes entries which were submitted successfully.
             */
            $responce = $this->handleErrors($errors).AkauntingHook::decodeErrors($results);
            
            done:
            // Add a closing message
            $responce .= "\nOur Dev Team is happy to help with any problems you encounter while using this system.\n"
                         ."You can reach them at developer@catherapyservices.ca\n"
                         ."\nCATS Automatic Akaunting Entry System";
            
            //Send the results
            SEEDEmailSend($message->getTo()[0]->getAddress(), $from->getAddress(), $subject, $responce, "", array('reply-to' => "developer@catherapyservices.ca"));
            
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
            return new AccountingEntry($amount, $incomeOrExpense, $clinic, $date,$category, $attachment, (preg_match($this->PATTERNS['companyCreditCard'], $value) > 0), $value, $person);
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
                               ."Accepted formats are MMM(M) DD?YY(YY) and MM/DD/YY(YY). Values in brackets are optional.\n";
                    break;
                case self::DISCARDED_ZERO_AMOUNT:
                    $responce .= "Amount is zero ".($k == "subject"?"in ":"on ").str_replace("_", " ", $k)."\n"
                                ."The amount was calulated as zero and the entry was discarded since it does not affect the balance\n";
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
        foreach($this->PATTERNS as $regex){
            if(preg_match($regex, $value)){
                $matches++;
            }
        }
        return $matches >= 2;
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

    function __construct($amount, String $type, String $clinic, $date, String $category, $attachment, bool $ccc, String $desc, String $person){
        $this->clinic = $clinic;
        $this->amount = $amount;
        $this->type = $type;
        $this->category = $category;
        $this->date = $this->parseDate($date);
        $this->attachment = $attachment;
        $this->ccc = $ccc;
        $this->desc = $desc;
        $this->person = $person;
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

    private function parseDate(String $date): String {
        if(preg_match("/(Jan|Feb|Mar|Apr|May|Jun|June|Jul|July|Aug|Sept|Sep|Oct|Nov|Dec) [0-3]?[0-9]\/[0-9]{2,4}/i", $date)){
            //Clear up some of the double options
            $date = str_replace("Sep", "Sept", $date);
            $date = str_replace("June", "Jun", $date);
            $date = str_replace("July", "Jul", $date);
            
            //Format switchers
            $day = (preg_match("/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sept|Oct|Nov|Dec) [0-3][0-9]\/[0-9]{2,4}/i", $date)?"d":"j");
            $year = (preg_match("/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sept|Oct|Nov|Dec) [0-3]?[0-9]\/[0-9]{4}/i", $date)?"Y":"y");
            
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

?>