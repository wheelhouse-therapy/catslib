<?php

class Documentation {
    
    //Directory to fetch docs from
    private const DIR = CATSDIR_DOCUMENTATION;
    
    public function handleDocs(SEEDAppConsole $oApp){
        
        $documentation = <<<viewDoc
        <style>
            html, body {
                height: 100vh;
                overflow:hidden;
            }
            .docView {
                height: 88%;
            }
            .catsToolbar {
                margin-bottom:2px
            }
        </style>
        <div class='catsToolbar'><h3>System Documentation</h3><a href='?doc_view=list'><button>Back to List</button></a></div>
        <div class='docView'>
            <embed src='[[doc]]#toolbar=0&navpanes=0&scrollbar=0&view=fitH,100' type='application/pdf' style='width:100%;height:100%;'>
        </div>
viewDoc;
        
        $listDocs = "<h3>System Documentation</h3>";
        $dirIterator = new DirectoryIterator(self::DIR);
        if(iterator_count($dirIterator) == 2){
            $listDocs .= "<h2> No Documentation Avalible</h2>";
            goto brains;
        }
            
            $listDocs .= "<table border='0'>";
            foreach ($dirIterator as $fileinfo) {
                if( $fileinfo->isDot() ) continue;
                
                $listDocs .= "<tr>"
                    ."<td valign='top'>"
                        ."<a style='white-space: nowrap' href='?doc_view=item&doc_item=".pathinfo($fileinfo->getFilename(),PATHINFO_FILENAME)."' >"
                            .$fileinfo->getFilename()
                            ."</a>"
                                ."</td>"
                                    ."</tr>";
            }
            $listDocs .= "</table>";
            
            //Brains of operations
            brains:
            $view = $oApp->sess->SmartGPC("doc_view",array("list","item"));
            $item = $oApp->sess->SmartGPC("doc_item", array(""));
            
            switch ($view){
                case "item":
                    //Complicated method to ensure the file is in the directory
                    foreach (array_diff(scandir(self::DIR), array('..', '.')) as $file){
                        if(pathinfo($file,PATHINFO_FILENAME) == $item){
                            // show file
                            return str_replace("[[doc]]", self::DIR.$file, $documentation);
                        }
                    }
                    $oApp->sess->VarUnSet("doc_item");
                case "list":
                    return $listDocs;
            }
    }
    
}

class Placeholders{
    
    private const DIR = CATSDIR_IMG."placeholders/";
    
    public function drawPlaceholderList(){
        $this->handleCommands();
        $s = "<h3>System Placeholder Images</h3>";
        $dir = new DirectoryIterator(self::DIR);
        if(iterator_count($dir) == 2){
            $s .= "<h2> No files in directory</h2>";
            return $s;
        }
        
        $s .= "<form onsubmit=\"return $('div.checkbox-group.required :checkbox:checked').length > 0\">"
             ."<input type='hidden' name='cmd' value='download' />";
        
        foreach ($dir as $fileinfo) {
            
            if( $fileinfo->isDot() ) continue;
            
            if( strripos($fileinfo->getFilename(),"-old") !== false) continue;
            
            $s .= "<div class='checkbox-group required'>";
            $s .= "<label><input type='checkbox' name='placeholder[]' value='".$fileinfo->getFilename()."' />".$fileinfo->getFilename()."</label>";
            $s .= "</div>";
        }
        
        $s .= "<input type='submit' value='Download'>"
             ."</form>";
        
        return $s;
        
    }
    
    private function handleCommands(){
        $cmd = SEEDInput_Str("cmd");
        if(file_exists(self::DIR."placeholders.zip")){
            unlink(self::DIR."placeholders.zip");
        }
        switch($cmd){
            case "download":
                $file = "";
                $placeholders = $_REQUEST['placeholder'];
                if(count($placeholders) > 1){
                    $file = self::DIR."placeholders.zip";
                    $zip = new ZipArchive();
                    $zip->open($file, ZipArchive::CREATE);
                    foreach ($placeholders as $placeholder){
                        $zip->addFile(self::DIR.$placeholder,$placeholder);
                    }
                    $zip->close();
                }
                else{
                    $file = self::DIR.$placeholders[0];
                }
                header('Content-Type: '.(@mime_content_type($file)?:"application/octet-stream"));
                header('Content-Description: File Transfer');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Content-Transfer-Encoding: binary');
                if( ($fp = fopen( $file, "rb" )) ) {
                    fpassthru( $fp );
                    fclose( $fp );
                }
                if(file_exists(self::DIR."placeholders.zip")){
                    unlink(self::DIR."placeholders.zip");
                }
                exit;
                break;
        }
        
    }
    
}