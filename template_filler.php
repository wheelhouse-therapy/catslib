<?php

require(SEEDROOT.'/vendor/autoload.php');

class template_filler {
    
    private $oApp;
    
    public function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
    }
    
    public function fill_resource($resourcename){
        
        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($resourcename);
        foreach($templateProcessor->getVariables() as $tag){
            if(!$this->expandTag($tag)){
                continue; // Improper tag. Do Not Replace
            }
            list($table,$col) = $this->expandTag($tag);
            if($table != NULL){
                // It is not a single tag treat normaly
                if(!($kfr = $this->resolveTable($table))){
                    continue; // Could not resolve table. Do Not Replace
                }
                list($bCol,$col) = $this->resolveColumn($table, $col);
            }
            else{
                $bCol = FALSE;
                $col = $this->processSingleTag($col);
            }
            $templateProcessor->setValue($tag,$bCol?$kfr->Value($col):$col);
        }
        
        $ext = "";
        switch(strtolower(substr($resourcename,strrpos($resourcename, ".")))){
            case '.docx':
                $ext = 'Word2007';
                break;
            case '.html':
                $ext = 'HTML';
                break;
            case '.odt':
                $ext = 'ODText';
                break;
            case '.rtf':
                $ext = 'RTF';
                break;
            case '.doc':
                $ext = 'MsDoc';
                break;
        }
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($templateProcessor->save(),$ext);
        $phpWord->save(substr($resourcename,strrpos($resourcename, '/')+1),$ext,TRUE);
        die();
        
    }
    
    private function expandTag($tag){
        if($this->processSingleTag($tag)){
            // Single tags are things like date which dont require a database table
            return array(NULL,$tag);
        }
        $pos = strpos($tag, ":");
        if (FALSE === $pos){
            return FALSE;
        }
        $table = substr($tag, 0,$pos);
        $col = substr($tag, $pos+1);
        
        return array($table,$col);
        
    }
    
    private function resolveTable($table){
        $table = $this->resolveTableName($table);
        $oPeople = new PeopleDB( $this->oApp );
        switch(strtolower($table)){
            case 'clinic':
                $clinics = new Clinics($this->oApp);
                return (new ClinicsDB($this->oApp->kfdb))->GetClinic($clinics->GetCurrentClinic());
            case 'therapist':
                return $oPeople->getKFRCond("PI","P_uid=".$this->oApp->sess->GetUID());
            case 'client':
                $key = SEEDInput_Int("client");
                return $oPeople->getKFR("C", $key);
            default:
                return NULL; // Unknown Table
        }
        
    }
    
    private function resolveColumn($table,$col){
        $bCol = TRUE;
        $table = $this->resolveTableName($table);
        if($table == 'client' && (strtolower($col) == 'name' || strtolower($col) == 'clients_name' || strtolower($col) == 'client_name')){
            $bCol = FALSE;
            $col = $this->resolveTable($table)->Expand("[[client_first_name]] [[client_last_name]]");
        }
        if($table == 'client' && (strtolower($col) == 'age' || strtolower($col) == 'clients_age' || strtolower($col) == 'client_age')){
            $bCol = FALSE;
            $col = date_diff(new DateTime('now'), new DateTime($this->resolveTable($table)->Value('dob')))->format('%y Years');
        }
        if(strtolower($col) == 'full_address' && ($table == 'client' || $table == 'clinic')){
            $bCol = FALSE;
            $col = $this->resolveTable($table)->Expand("[[address]]\n[[city]] [[postal_code]]");
        }
        return array($bCol,$col);
    }
    
    private function resolveTableName($table){
        switch(strtolower($table)){
            case 'clinics':
                return 'clinic';
            case 't':
            case 'therapists':
                return 'therapist';
            case 'clients':
                return 'client';
            default:
                return $table; // Unable to resolve
        }
    }
    
    private function processSingleTag($tag){
        switch(strtolower($tag)){
            case 'date':
                return date("M d, Y");
        }
        return FALSE;
    }
    
}

?>