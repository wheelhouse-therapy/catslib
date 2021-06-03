<?php
class DoctorsLetter {
    
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
        $this->client = $cid;
        $this->content = array_filter($content);
        $this->attachments = array_filter($attachments);
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
        $s .= "<input type='hidden' name='cmd' value='therapist-updateDoctorsLetter'>";
        $s .= "<input type='hidden' name='client_key' value='{$this->client}'>";
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
        $s .= "<input type='submit' value='".($this->key?"Update":"Save")."'>";
        $s .= "</form>";
        
        return $s;
    }
    
    public function isNew():bool{
        return $this->key == 0;
    }
    
    public function getContent():String{
        return SEEDCore_ArrayExpandSeries($this->content, "[[v]],",true,["sTemplateLast"=>"and [[v]]"]);
    }
    
    public function getAttachments():String{
        return SEEDCore_ArrayExpandSeries($this->attachments, "[[v]],",true,["sTemplateLast"=>"and [[v]]"]);
    }
    
    /**
     * Get a clients doctors letter
     * @param SEEDAppConsole $oApp - db connection to use
     * @param int $client_key - key of client of the doctors letter to get
     * @return DoctorsLetter with the information loaded from the database if one exists.
     */
    public static function getDoctorsLetter(SEEDAppConsole $oApp,int $client_key):DoctorsLetter{
        $oDoctorsLetterDB = new DoctorsLettersDB($oApp);
        $kfr = $oDoctorsLetterDB->GetKFRCond("D","fk_clients2=$client_key");
        if(!$kfr){
            $kfr = $oDoctorsLetterDB->GetKfrel("D")->CreateRecord();
        }
        return new DoctorsLetter($client_key, $kfr->Key(), explode(self::DATA_SEPARATOR,$kfr->Value("content")),explode(self::DATA_SEPARATOR,$kfr->Value("attachments")));
    }
    
    /**
     * Put Doctors Letter in Database
     * @param SEEDAppConsole $oApp - with the db connection to use.
     * @param DoctorsLetter $oDL - Doctors letter to save
     * @return bool - true if the letter saved successfully, false otherwise.
     */
    public static function saveDoctorsLetter(SEEDAppConsole $oApp, DoctorsLetter $oDL):bool{
        $oDoctorsLetterDB = new DoctorsLettersDB($oApp);
        $kfr = $oDoctorsLetterDB->GetKFR("D",$oDL->key);
        if(!$kfr){
            $kfr = $oDoctorsLetterDB->GetKfrel("D")->CreateRecord();
        }
        $kfr->SetValue("fk_clients2", $oDL->client);
        $kfr->SetValue("content", implode(self::DATA_SEPARATOR, $oDL->content));
        $kfr->SetValue("attachments", implode(self::DATA_SEPARATOR, $oDL->attachments));
        return $kfr->PutDBRow();
    }
    
}

class DoctorsLetterUI {
    
    public static function DrawUI(SEEDAppConsole $oApp):String{
        $s = "<h2>Doctors Letters</h2>";
        
        $client_key = SEEDInput_Int("client_key");
        $cmd = SEEDInput_Str("cmd");
        
        $clientlist = new ClientList($oApp);
        $raClients = $clientlist->getMyClients();
        $raKeys = array_column($raClients, "_key");
        $oCCG = new ClientCodeGenerator($oApp);
        $s .= "<div class='container-fluid'><div class='row'>"
            ."<div id='users' class='col-md-4'>";
            foreach( $raClients as $ra ) {
                $oDL = DoctorsLetter::getDoctorsLetter($oApp, $ra['_key']);
                $dlStatus = "<i class='fas fa-check-circle' style='color:green'></i> ";
                if($oDL->isNew()){
                    $dlStatus = '<i class="fas fa-exclamation-circle"></i> ';
                }
                if($ra["_key"] == $client_key){
                    $s .= "<a href='?client_key={$ra['_key']}' style='text-decoration:none;color:black;'><div style='padding:5px;cursor:pointer;overflow: hidden;border: 2px solid green;background-color: lightgreen;border-radius: 5px;'>$dlStatus{$ra['P_first_name']} {$ra['P_last_name']} (".$oCCG->getClientCode($ra['_key']).")</div></a>";
                }
                else{
                    $s .= "<a href='?client_key={$ra['_key']}' style='text-decoration:none;color:black;'><div style='padding:5px;cursor:pointer'>$dlStatus{$ra['P_first_name']} {$ra['P_last_name']} (".$oCCG->getClientCode($ra['_key']).")</div></a>";
                }
            }
            $s .= "</div>"
                ."<div id='form' class='col-md-8'>";
            
            if($client_key > 0 && in_array($client_key, $raKeys)){
                $oDL = DoctorsLetter::getDoctorsLetter($oApp, $client_key);
                if($oApp->sess->IsAllowed( $cmd )[0]){
                    switch(strtolower($cmd)){
                        case "therapist-updatedoctorsletter":
                            $oDL->updateData();
                            DoctorsLetter::saveDoctorsLetter($oApp, $oDL);
                            header("HTTP/1.1 303 SEE OTHER");
                            header("Location: ?client_key=$client_key");
                            exit();
                            break;
                    }
                }
                $kfr = (new PeopleDB($oApp))->GetKFR(ClientList::CLIENT,$client_key);
                if($kfr){
                    $s .= "<h3>Doctors letter for ".$kfr->Value('P_first_name')." ".$kfr->Value('P_last_name')." (".$oCCG->getClientCode($kfr->Key()).")</h3>";
                    $s .= $oDL->drawForm();
                }
            }
            
            $s .= "</div></div></div>";
        
        return $s;
    }
    
}