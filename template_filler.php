<?php

require('./seeds/vendor/autoload.php');

class template_filler {
    
    private $oApp;
    
    public function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
    }
    
    public function fill_resource($resourcename){
        
        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor(CATSDIR_RESOURCES.$resourcename);
        foreach($templateProcessor->getVariables() as $tag){
            if(!$this->expandTag($tag)){
                continue; // Improper tag. Do Not Replace
            }
            list($table,$col) = $this->expandTag($tag);
            if(!($kfr = $this->resolveTable($table))){
                continue; // Could not resolve table. Do Not Replace
            }
            list($bCol,$col) = $this->resolveColumn($table, $col);
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
        $phpWord = \PhpOffice\PhpWord\IOFactory::load(($file = $templateProcessor->save()),$ext);
        $phpWord->save($file,$ext,TRUE);
        
    }
    
    private function expandTag($tag){
        
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
        switch(strtolower($table)){
            case 'clinic':
                $clinics = new Clinics($this->oApp);
                return (new ClinicsDB($this->oApp->kfdb))->GetClinic($clinics->GetCurrentClinic());
            case 'therapist':
                return NULL; // Insuficent information
            case 'client':
                return NULL; // Insuficent information
            default:
                return NULL; // Unknown Table
        }
        
    }
    
    private function resolveColumn($table,$col){
        $bCol = TRUE;
        if($this->resolveTableName($table) == 'clinics' && (strtolower($col) == 'name' || strtolower($col) == 'clients_name' || strtolower($col) == 'client_name')){
            $bCol = FALSE;
            $col = $this->resolveTable($table)->Expand("[[client_first_name]] [[client_last_name]]");
        }
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
    
}

?>