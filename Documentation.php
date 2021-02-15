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
                            .pathinfo($fileinfo->getFilename(),PATHINFO_FILENAME)
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
    private const fileid = "fileToCheck";
    
    private const COMPARE_NONE = 0;
    private const COMPARE_EMPTY = 1;
    private const COMPARE_INVALID = 2;
    private const COMPARE_INVALID_ZIP = 3;
    private const COMPARE_INVALID_TYPE = 4;
    private const COMPARE_VALID = 5;
    private const COMPARE_VALID_ZIP = 6;
    
    private $compareState = self::COMPARE_NONE;
    
    public function drawPlaceholderList(){
        $this->handleCommands();
        $dir = new DirectoryIterator(self::DIR);
        $s = "";
        if(iterator_count($dir) == 2){
            $s .= "<h2>No Placeholder images in the system</h2>";
            return $s;
        }
        
        $s .= "<div style='float:right;border:black 1px solid;padding: 5px'><h4>Validate Placeholders</h4>";
        switch($this->compareState){
            case self::COMPARE_NONE:
                $s .= "<form method='post' enctype='multipart/form-data'>
                    <input type='hidden' name='cmd' value='validate' />
                    Select Placeholder(s) to validate:
                    <input type='file' name='".self::fileid."' accept='.png,.jpg,.zip' required /><br />"
                  ."<input type='submit' value='Validate'>
                    Max Upload size:".ini_get('upload_max_filesize')."b
                </form>";
                break;
            case self::COMPARE_EMPTY:
                $s .= "<div class='alert alert-info'>Sorry, we couldn't validate your placeholder(s) because nothing was uploaded.</div>";
                break;
            case self::COMPARE_INVALID:
                $s .= "<div class='alert alert-danger'>Sorry, but that placeholder is invalid and will not be replaced by our system.</div>";
                break;
            case self::COMPARE_INVALID_ZIP:
                $s .= "<div class='alert alert-danger'>Sorry, but one or more of the placeholders are invalid and will not be replaced by our system.</div>";
                break;
            case self::COMPARE_INVALID_TYPE:
                $s .= "<div class='alert alert-warning'>Sorry, that file type is unsupported. Upload the png or jpg image you wish to validate or upload a zip to validate multiple at once.</div>";
                break;
            case self::COMPARE_VALID:
                $s .= "<div class='alert alert-success'>That placeholder is valid and will be replaced by our system.</div>";
                break;
            case self::COMPARE_VALID_ZIP:
                $s .= "<div class='alert alert-success'>Those placeholders are valid and will be replaced by our system.</div>";
                break;
            default:
                $s .= "<div class='alert alert-danger'>An unknown error occured (Error:{$this->compareState}</div>";
                break;
        }
        $s .= "</div>";
        
        $s .= "<h4>Download Placeholders</h4><form onsubmit=\"return $('div.checkbox-group.required :checkbox:checked').length > 0\">"
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
            case "validate":
                if( !$_FILES[self::fileid]["name"] || !$_FILES[self::fileid]['size'] ) {
                    // Nothing uploaded
                    $this->compareState = self::COMPARE_EMPTY;
                    break;
                }
                $documentFileType = strtolower(pathinfo(basename($_FILES[self::fileid]["name"]),PATHINFO_EXTENSION));
                $placeholders = array_values(array_diff(scandir(CATSDIR_IMG."placeholders"), [".",".."]));
                $hashes = array();
                foreach ($placeholders as $placeholder){
                    $hashes[] = sha1(file_get_contents(CATSDIR_IMG."placeholders/".$placeholder));
                }
                if($documentFileType == "zip"){
                    // Zip uploaded check the contents
                    $zip = new ZipArchive();
                    $this->compareState = $zip->open($_FILES[self::fileid]["tmp_name"])?self::COMPARE_VALID_ZIP:self::COMPARE_INVALID_ZIP;
                    if($this->compareState == self::COMPARE_VALID_ZIP){
                        $i = 0;
                        while($this->compareState == self::COMPARE_VALID_ZIP && $i < $zip->numFiles){
                            $this->compareState = in_array(sha1($zip->getFromIndex($i)),$hashes)?self::COMPARE_VALID_ZIP:self::COMPARE_INVALID_ZIP;
                            $i++;
                        }
                        $zip->close();
                    }
                }
                else if(in_array($documentFileType, ["png","jpg"])){
                    // Image uploaded check the hash
                    $this->compareState = in_array(sha1_file($_FILES[self::fileid]['tmp_name']),$hashes)?self::COMPARE_VALID:self::COMPARE_INVALID;
                }
                else{
                    // Not valid placeholder file
                    $this->compareState = self::COMPARE_INVALID_TYPE;
                }
                break;
        }
        
    }
    
}