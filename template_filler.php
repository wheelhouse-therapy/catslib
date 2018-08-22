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
            list($col) = $this->resolveColumn($table, $col);
        }
        
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
        switch(strtolower($table)){
              
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