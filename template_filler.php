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
                continue; // Improper tag do not replace
            }
            list($table,$row) = $this->expandTag($tag);
            
        }
        
    }
    
    private function expandTag($tag){
        
        $pos = strpos($tag, ":");
        if (FALSE === $pos){
            return FALSE;
        }
        $table = substr($tag, 0,$pos);
        $row = substr($tag, $pos+1);
        
        return array($table,$row);
        
    }
    
}

?>