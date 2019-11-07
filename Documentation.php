<?php

class Documentation {
    
    //Directory to fetch docs from
    private const DIR = CATSDIR_DOCUMENTATION;
 
    //Id of the iframe to distgiguish it from the main browser window
    private const FRAME_ID = "frame";
    
    public function handleDocs(){
        
        $file = SEEDInput_Str('file');
        $id = SEEDInput_Str("id");
        
        if($id == self::FRAME_ID){
            if($file){
                
            }
        }
        
    }
    
}