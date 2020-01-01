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
            if($file && file_exists(self::DIR.$file)){
                echo "<div><a href='?id=frame'>Back to Documentation</a></div>";
                readfile(self::DIR.$file);
            }
            else{
                $dir = new DirectoryIterator(self::DIR);
                if(iterator_count($dir) == 2){
                    echo"<h2> No files in directory</h2>";
                }
                else{
                    echo "<table border='0'>";
                    foreach ($dir as $fileinfo) {
                        if( $fileinfo->isDot() ) continue;
                        
                        echo "<tr>"
                                ."<td valign='top'>"
                                    ."<a style='white-space: nowrap' href='?id=frame&file=".$fileinfo->getFilename()."' >"
                                        .pathinfo($fileinfo->getFilename(),PATHINFO_FILENAME)
                                    ."</a>"
                                ."</td>"
                            ."</tr>";
                    }
                    echo "</table>";
                }
            }
            exit;
        }
        else{
            $s = "<h3>Documentation</h3>"
                ."<style>html,body{height:100%}</style>"
                ."<iframe src='?id=frame[[file]]' style='border:none;width:100%;height:100%'></iframe>";
            return str_replace("[[file]]", ($file?"&file=".$file:""), $s);
        }
    }
    
}

class Placeholders{
    
    private const DIR = CATSDIR_IMG."placeholders/";
    
    public function drawPlaceholderList(){
        $this->handleCommands();
        $s = "<h3>Get Placeholder Images</h3>";
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