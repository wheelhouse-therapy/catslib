<?php
class Doctors_Letter {
    
    private const DATA_SEPARATOR = "\t";
    private const CONTENT_FIELDS = [
        "self regulation",
        "Sensory processing",
        "anxiety",
        "fine motor skills",
        "gross motor skills",
        "feeding",
        "concerns at home",
        "concerns at school",
        "return to work planning",
        "cognitive performance"
    ];
    private const ATTACHMENTS_FIELDS = [
        "OT Report",
        "OT Goals",
        "a questionaire to be completed"
    ];
    
    private $client;
    private $content;
    private $attachments;
    private $key;
    
    private function __construct(int $cid,int $key,array $content, array $attachments){
        if(array_unique($content) == [""]){
            $content = [];
        }
        if(array_unique($attachments) == [""]){
            $attachments = [];
        }
        $this->client = $cid;
        $this->content = $content;
        $this->attachments = $attachments;
        $this->key = $key;
    }
    
    /**
     * Update the data in this doctors letter object from the request.
     * NOTE: This can clear the data if no data was passed in the request when called.
     */
    function updateData(){
        $this->content = @$_REQUEST['content']?:[];
        $this->attachments = @$_REQUEST['attachment']?:[];
    }
    
    /**
     * Render the form of this doctors letter.
     * @return String
     */
    function drawForm():String{
        $otherContent = array_diff($this->content, self::CONTENT_FIELDS);
        $otherAttachments = array_diff($this->attachments, self::ATTACHMENTS_FIELDS);
        
        $s  = "<style>label {margin-left:5px}</style>";
        $s .= "<form method='post'>";
        $s .= "<strong>Team Letter Content</strong><br />";
        foreach(self::CONTENT_FIELDS as $k=>$c){
            if(in_array($c, $this->content)){
                $s .= "<input type='checkbox' id='box$k' name='content[]' value='$c' checked><label for='box$k'>$c</label><br />";
            }
            else{
                $s .= "<input type='checkbox' id='box$k' name='content[]' value='$c'><label for='box$k'>$c</label><br />";
            }
        }
        $s .= "<input type='text' name='content[]' value='".implode(',',$otherContent)."' placeholder='Other'></input><br />";
        
        $s .= "Please find attached;<br />";
        foreach(self::ATTACHMENTS_FIELDS as $k=>$c){
            if(in_array($c, $this->attachments)){
                $s .= "<input type='checkbox' id='box$k' name='attachment[]' value='$c' checked><label for='box$k'>$c</label><br />";
            }
            else{
                $s .= "<input type='checkbox' id='box$k' name='attachment[]' value='$c'><label for='box$k'>$c</label><br />";
            }
        }
        $s .= "<input type='text' name='content[]' value='".implode(',',$otherAttachments)."' placeholder='Other'></input><br />";
        $s .= "<input type='submit' value='Save'>";
        $s .= "</form>";
        
        return $s;
    }
    
    /**
     * Get a clients doctors letter
     * @param SEEDAppConsole $oApp - db connection to use
     * @param int $client_key - key of client of the doctors letter to get
     * @return Doctors_Letter with the information loaded from the database if one exists.
     */
    public static function getDoctorsLetter(SEEDAppConsole $oApp,int $client_key):Doctors_Letter{
        $oDoctorsLetterDB = new DoctorsLettersDB($oApp);
        $kfr = $oDoctorsLetterDB->GetKFRCond("D","fk_clients2=$client_key");
        if(!$kfr){
            $kfr = $oDoctorsLetterDB->GetKfrel("D")->CreateRecord();
        }
        return new Doctors_Letter($client_key, $kfr->Key(), explode(self::DATA_SEPARATOR,$kfr->Value("content")),explode(self::DATA_SEPARATOR,$kfr->Value("attachments")));
    }
    
    /**
     * Put Doctors Letter in Database
     * @param SEEDAppConsole $oApp - with the db connection to use.
     * @param Doctors_Letter $oDL - Doctors letter to save
     * @return bool - true if the letter saved successfully, false otherwise.
     */
    public static function saveDoctorsLetter(SEEDAppConsole $oApp, Doctors_Letter $oDL):bool{
        $oDoctorsLetterDB = new DoctorsLettersDB($oApp);
        $kfr = $oDoctorsLetterDB->GetKFRCond("D","fk_clients2={$oDL->client}");
        if(!$kfr){
            $kfr = $oDoctorsLetterDB->GetKfrel("D")->CreateRecord();
        }
        $kfr->SetValue("fk_clients2", $oDL->client);
        $kfr->SetValue("content", implode(self::DATA_SEPARATOR, $oDL->content));
        $kfr->SetValue("attachments", implode(self::DATA_SEPARATOR, $oDL->attachments));
        return $kfr->PutDBRow();
    }
    
}