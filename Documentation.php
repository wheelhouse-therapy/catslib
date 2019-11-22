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
                echo "<div><a href='/?id=frame'>Back to Documentation</a></div>";
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