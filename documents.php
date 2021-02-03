<?php

function ManageResources( SEEDAppSessionAccount $oApp ) {
    $s = "<h2>Manage Resources</h2>";
    $s .= "<div style='text-align:right'><a href='".CATSDIR."?screen=admin-resources'>Go To review resources -></a></div>";

    $script = <<<JavaScript
<script>
    var displayed = [];
    function toggleDisplay(block){
        if(displayed.indexOf(block) !== -1)
            displayed.splice( displayed.indexOf(block), 1 );
        else
            displayed.push(block);
        $(document.getElementById(block)).slideToggle(400);
        updateServer();
    }

    function setContents(block, contents){
    	block = document.getElementById(block);
    	contents = document.getElementById(contents);
    	if(block.innerHTML == contents.innerHTML){
    		if(block.style.display == 'none'){
    			block.style.display = 'block';
    		}
    		else {
    			block.style.display = 'none';
    		}
    	}
    	else{
    		block.innerHTML = contents.innerHTML;
    		block.style.display = 'block';
    	}
    }
    
    function updateServer(){
        $.ajax({
            type: "POST",
            data: {cmd:'admin-ResourceTrees',open:displayed},
            url: 'jx.php',
            error: function(jqXHR, status, error) {
                console.log(status + ": " + error);
            }
        });
    }
</script>
JavaScript;

    $style = <<<CSS
<style>
    .cats_doctree_level { margin-left:30px; }
    .cats_doctree {
        border:1px solid #888;
        background-color:#ddd;
        border-radius:10px;
        margin:20px;
        padding:20px;
    }
    .cats_docform {
        background-color:#eee;
        border:1px solid #777;
        border-radius: 10px;
        padding:20px;
    }
    .cats_form {
        width:180px;

        -ms-box-sizing:content-box;
        -moz-box-sizing:content-box;
        box-sizing:content-box;
        -webkit-box-sizing:content-box;
    }
</style>
CSS;

    $s .= $script
       .  $style;

    $oResources = new ResourceManager($oApp);

    $s .= $oResources->ManageResources();

    return $s;

}

class ResourceManager{

    private $oApp;
    private $oFCT;
//    private $selected_File = 0;
    private $openTrees;

    public function __construct(SEEDAppSessionAccount $oApp){
        $this->oApp = $oApp;
        $this->oFCT = new FilingCabinetTools($oApp);
    }

    public function ManageResources(){
        if( ($id = SEEDInput_Int("id")) ) {   // $this->selected_File = SEEDInput_Str("id");
            if( ($oRR = ResourceRecord::GetRecordById($this->oApp, $id)) ) {
                if( $oRR->getCabinet()=='videos' ) {
                    $this->processCommandsVideos( $oRR );
                } else {
                    $this->processCommands( $oRR );
                }
            }
        }

        if(isset($_SESSION['ResourceCMDResult'])){
            $cmdResult = $_SESSION['ResourceCMDResult'];
            unset($_SESSION['ResourceCMDResult']);
        }
        else{
            $cmdResult = "";
        }
        $this->openTrees = $this->oFCT->TreeListGet();
        if( !$this->openTrees || !is_array($this->openTrees) ) $this->openTrees = [];
//var_dump($this->openTrees);
        $script = "<script>displayed = Object.values(JSON.parse('".json_encode($this->openTrees)."'));</script>";
        return $script.$cmdResult."<div class='cats_doctree'>".$this->listResources(CATSDIR_RESOURCES)."</div>";
    }

    private function listResources($dir, $bRecursing = false ){
        FilingCabinet::EnsureDirectory("*",true);
        $s = "";

        if( !$bRecursing )  $s .= "<h4>Filing Cabinet</h4>";

        // General cabinet
        $directory_iterator = new DirectoryIterator($dir);
        if(iterator_count($directory_iterator) <= 2){
            $s .= "No Resources<br />";
        } else {
            foreach ($directory_iterator as $fileinfo){
                if($fileinfo->isDot()){
                    continue;
                }
                if($fileinfo->isDir() && $fileinfo->getFilename() == "pending") continue;
                if($fileinfo->isDir() && $fileinfo->getFilename() == "videos") continue;

                if(!$fileinfo->isDir()){
                    $oRR = ResourceRecord::GetRecordFromRealPath($this->oApp, 'general', $fileinfo->getRealPath());
                }
                else{
                    $oRR = Null;
                }

//                if($this->selected_File && $oRR != Null && $this->selected_File == $oRR->getID()){
//                    $this->processCommands($oRR);
//                }
                $filename = $fileinfo->getFilename();
                if(FilingCabinet::GetDirInfo($filename)){
                    $filename = FilingCabinet::GetDirInfo($filename)['name'];
                }

                // open/close divs using resourceId for files and folder/subdir for folders
                $toggleId = $oRR ? "toggle-{$oRR->getId()}" : addslashes($this->getPathRelativeTo($fileinfo->getRealPath(),CATSDIR_RESOURCES));
                $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$toggleId')\">$filename</a><br />";
                $s .= "<div class='[style]' id=\"$toggleId\" style='".(in_array($toggleId, $this->openTrees)?"":"display:none;")." width: [width];'>";
                if($fileinfo->isDir()){
                    $s = str_replace(array("[style]","[width]"), array("cats_doctree_level","100%"), $s);
                    $s .= $this->listResources($fileinfo->getRealPath(), true);
                }
                elseif($fileinfo->isFile()){
                    $s = str_replace(array("[style]","[width]"), array("cats_docform","50%"), $s);
                    $s .= $this->drawCommands($fileinfo->getRealPath());
                }
                $s .= "</div>";
            }
        }

        if( $bRecursing ) goto done;    // only process the videos in the top level

        $s .= "<h4 style='margin-top:30px'>Videos</h4>";

        // Video cabinet
        if( ($raRR = ResourceRecord::GetRecordFromPath( $this->oApp, 'videos',
                                                        ResourceRecord::WILDCARD, ResourceRecord::WILDCARD, ResourceRecord::WILDCARD )) ) {
            if( !is_array($raRR) )  $raRR = array($raRR);
            $currDir = $currSubdir = "";
            foreach( $raRR as $oRR ) {
//                if( $this->selected_File && $this->selected_File == $oRR->getID() ) {
//                    $this->processCommands($oRR);
//                }

                if( $currDir != $oRR->getDirectory() ) {
                    // end of previous dir (if any), start of a new one
                    if( $currDir ) {
                        if( $currSubdir ) $s .= "</div>";   // end the previous subdir
                        $s .= "</div>";                      // end the previous dir
                    }

                    $currDir = $oRR->getDirectory();
                    $currSubdir = $oRR->getSubDirectory();

                    // start of new dir (and subdir if defined)
                    $d = SEEDCore_HSC("videos/$currDir");
                    $dirLabel = @FilingCabinet::GetDirInfo($currDir, 'videos')['name'] ?: "[no name]";
                    $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$d')\">$dirLabel</a><br />";
                    $s .= "<div class='cats_doctree_level' id='$d'
                                style='".(in_array($d, $this->openTrees)?"":"display:none;")." width: 100%;'>";

                    if( $currSubdir ) {
                        $d = SEEDCore_HSC("videos/$currDir/$currSubdir");
                        $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$d')\">".$currSubdir."</a><br />";
                        $s .= "<div class='cats_doctree_level' id='$d'
                                    style='".(in_array($d, $this->openTrees)?"":"display:none;")." width: 100%;'>";
                    }
                } else if( $currSubdir != $oRR->getSubDirectory() ) {
                    // same dir but end of previous subdir
                    if( $currSubdir ) {
                        $s .= "</div>";      // end the previous subdir
                    }

                    $currSubdir = $oRR->getSubDirectory();

                    if( $currSubdir ) {
                        // start of new subdir
                        $d = SEEDCore_HSC("videos/$currDir/$currSubdir");
                        $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$d')\">".$currSubdir."</a><br />";
                        $s .= "<div class='cats_doctree_level' id='$d'
                                    style='".(in_array($d, $this->openTrees)?"":"display:none;")." width: 100%;'>";
                    }
                }

                // filename
                $toggleId = "toggle-{$oRR->getId()}";
                $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$toggleId')\">".$oRR->getFile()."</a><br />"
                     ."<div class='cats_docform' id='$toggleId'
                            style='".(in_array($toggleId, $this->openTrees)?"":"display:none;")." width: 50%;'>"
                     .$this->drawCommandsVideos($oRR)
                     ."</div>";
            }
            if( $currSubdir )  $s .= "</div>";
            if( $currDir )     $s .= "</div>";
        }

        done:
        return $s;
    }

    private function processCommands( ResourceRecord $oRR )
    {
        $cmd = SEEDInput_Str("cmd");
        switch($cmd){
            case "move":
                $fromFileBase = $oRR->getFile();                                          // base file name
                $fromFileFull = realpath($oRR->getPath());                                // full file path with ".." removed
                //$fromFileRel = $this->getPathRelativeTo($fromFileFull,CATSDIR_RESOURCES); // path relative to CATSDIR_RESOURCES
                //$fromDirRel = pathinfo($fromFileRel, PATHINFO_DIRNAME);                   // dir of relative path
                $toDirRel = SEEDInput_Str('folder');                                      // destination relative dir
                $toDirFull = realpath(CATSDIR_RESOURCES.$toDirRel);                       // destination full dir with ".." removed
                $toFileFull = $toDirFull.'/'.$fromFileBase;                               // destination full filename
//var_dump($toDirRel,$toDirFull,$fromFileRel,$toFileFull);exit;

                // Don't allow somebody to move a file outside of CATSDIR_RESOURCES.
                // This is possible if the destination folder name contains "..", but realpath removes those.
                if( !$this->getPathRelativeTo($toFileFull, CATSDIR_RESOURCES) ) {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Cannot move file to that folder</div>";
                    break;
                }

                // Could check if $movedFileFull and $toFolder don't exist, but that could only happen if you're doing something stupid

                //if(rename((substr($movedFileDir, 0,9) == "resources"?CATSDIR:CATSDIR_RESOURCES).$directory."/".$file_info->getFilename(), CATSDIR_RESOURCES.SEEDInput_Str("folder").$file_info->getFilename())){
                if( rename($fromFileFull, $toFileFull) ) {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>Successfully Moved $fromFileBase to $toDirRel</div>";
                    $dir = explode("/", rtrim($toDirRel,"/"))[0];
                    $subdir = @explode("/", rtrim($toDirRel,"/"))[1]?:"";
                    $oRR->setDirectory($dir);
                    $oRR->setSubDirectory($subdir);
                    if(!$oRR->StoreRecord())
                    {
                        $_SESSION['ResourceCMDResult'] .= "<div class='alert alert-danger alert-dismissible'>Unable to update index for $fromFileBase<br /> Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
                    }
                }
                else{
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error Moving file $fromFileBase to $toFolder</div>";
                }
                break;

            case "rename":
                $oldFileBase = $oRR->getFile();                                   // base path of old file
                $oldFileFull = realpath($oRR->getPath());                         // full path of old file
                $dir = pathinfo($oldFileFull,PATHINFO_DIRNAME);                   // full dir of old file
                if( ($name = SEEDInput_Str("name")) && ($ext = SEEDInput_Str("ext")) ) {
                    $newFileBase = $name.'.'.$ext;                                // base name of new file
                } else {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>New file name not given</div>";
                    break;
                }
                $newFileFull = $dir.'/'.$newFileBase;                             // full path of new file
                if( rename($oldFileFull, $newFileFull) ) {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>File $oldFileBase renamed to $newFileBase</div>";
                    $oRR->setFile($newFileBase);
                    if( !$oRR->StoreRecord())
                    {
                        $_SESSION['ResourceCMDResult'] .= "<div class='alert alert-danger alert-dismissible'>Unable to update index for $oldFileBase<br /> Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
                    }
                }
                else {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error renaming file $oldFileBase to $newFileBase</div>";
                }
                break;
            case "delete":
                if(unlink(realpath($oRR->getPath()))){
                    //$directory = $this->getPartPath(realpath($oRR->getPath()),-2);
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>File ".$oRR->getFile()." has been deleted</div>";
                    if(!$oRR->DeleteRecord()){
                        $_SESSION['ResourceCMDResult'] .= "<div class='alert alert-danger alert-dismissible'>Unable to delete index for ".$oRR->getFile()."<br /> Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
                    }
                }
                else{
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error deleting file ".$oRR->getFile()."</div>";
                }
                break;
            case "download":
                $file = $oRR->getPath();
                header('Content-Type: '.(@mime_content_type($file)?:"application/octet-stream"));
                header('Content-Description: File Transfer');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Content-Transfer-Encoding: binary');
                if( ($fp = fopen( $file, "rb" )) ) {
                    fpassthru( $fp );
                    fclose( $fp );
                }
                exit;
        }
        header("HTTP/1.1 303 SEE OTHER");
        header("Location: ?");
        exit();
    }

    private function processCommandsVideos( ResourceRecord $oRR )
    {
        switch( SEEDInput_Str('cmd') ) {
            case "move":
                // videos are all stored in 'videos/' so moving just means changing the folder/subfolder in the db
                $ra = explode('/', SEEDInput_Str('folder'), 2);
                $newDir = @$ra[0] ?: "";
                $newSubdir = @$ra[1] ?: "";
                $oRR->setDirectory($newDir);
                $oRR->setSubDirectory($newSubdir);
                if($oRR->StoreRecord()) {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>Successfully Moved {$oRR->getFile()}</div>";
                } else {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error Moving file {$oRR->getFile()}</div>";
                }
                break;

            case "rename":
                // videos are stored as 'videos/id filebase' so renaming means changing db-filename and renaming filesystem filebase
                if( ($name = SEEDInput_Str("name")) && ($ext = SEEDInput_Str("ext")) ) {
                    $newFileBase = $name.'.'.$ext;                                // base name of new file
                } else {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>New file name not given</div>";
                    break;
                }

                $oldFileBase = $oRR->getFile();
                $oldFile = realpath($oRR->getPath());
                $newFile = CATSDIR_RESOURCES."videos/{$oRR->getId()} $newFileBase";

                $oRR->setFile($newFileBase);
                if( $oRR->StoreRecord() && rename($oldFile, $newFile) ) {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>File $oldFileBase renamed to $newFileBase</div>";
                } else {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error renaming file $oldFileBase to $newFileBase</div>";
                }
                break;
            case "delete":
                // delete file from videos/ and delete the resource record
                $file = $oRR->getFile();
                $oRR->setStatus(1);
                if( $oRR->StoreRecord() && unlink(realpath($oRR->getPath())) ) {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>File $file has been deleted</div>";
                } else{
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error deleting file $file</div>";
                }
                $this->oFCT->TreeClose("toggle-{$oRR->getId()}");
                break;
            case "download":
                // use FilingCabinetDownload instead
                $file = $oRR->getPath();
                header('Content-Type: '.(@mime_content_type($file)?:"application/octet-stream"));
                header('Content-Description: File Transfer');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Content-Transfer-Encoding: binary');
                if( ($fp = fopen( $file, "rb" )) ) {
                    fpassthru( $fp );
                    fclose( $fp );
                }
                exit;
        }
        header("HTTP/1.1 303 SEE OTHER");
        header("Location: ?");
        exit();
    }

    private function drawCommands($file_path){
        if(!$this->oApp->sess->CanAdmin("admin")){
            return "You don't have permission to edit files";
        }
        if( !($oRR = ResourceRecord::GetRecordFromRealPath($this->oApp, 'general', realpath($file_path))) ) {
            return( "<p>Note there is a file <pre>$file_path</pre> that is not indexed</p>" );
        }

        $directory = pathinfo(substr($file_path,strlen(CATSDIR_RESOURCES)),PATHINFO_DIRNAME);
        $move = "<a href='javascript:void(0)' onclick=\"setContents('".addslashes($this->getPathRelativeTo($file_path,CATSDIR_RESOURCES))."_command','".addslashes($this->getPathRelativeTo($file_path,CATSDIR_RESOURCES))."_move')\">move</a>";
        $move .= "<div id=\"".addslashes($this->getPathRelativeTo($file_path,CATSDIR_RESOURCES))."_move\" style='display:none'>"
                ."<br /><form>
                  <input type='hidden' name='cmd' value='move' />
                  <input type='hidden' name='id' value='".$oRR->getID()."' />
                  <select name='folder' class='cats_form' required><option value='' selected>-- Select Folder --</option>";
        foreach (FilingCabinet::GetDirectories() as $k=>$v){
            // don't allow moving files into folders where they aren't supported
            if( !in_array(pathinfo($this->getPartPath($file_path,-1),PATHINFO_EXTENSION),$v['extensions']) ) continue;

            $sDisabled = ($v['directory'] == $directory."/") ? " disabled" : "";
            $move .= "<option value='".$v['directory']."' $sDisabled>".$v['name']."</option>";
            foreach( FilingCabinet::GetSubfolders($k) as $sub ) {
                $subfolder = $v['directory'].$sub;
                $sDisabled = ($subfolder== $directory) ? " disabled" : "";
                $move .= "<option value='$subfolder' $sDisabled> -- $sub</option>";
            }
        }
        $move .= "</select>&nbsp&nbsp<input type='submit' value='move' /></form></div>";

        $rename = "<a href='javascript:void(0)' onclick=\"setContents('".addslashes($this->getPathRelativeTo($file_path,CATSDIR_RESOURCES))."_command','".addslashes($this->getPathRelativeTo($file_path,CATSDIR_RESOURCES))."_rename')\">rename</a>";
        $rename .= "<div id=\"".addslashes($this->getPathRelativeTo($file_path,CATSDIR_RESOURCES))."_rename\" style='display:none'>"
                  ."<br /><form>"
                  ."<input type='hidden' name='cmd' value='rename' />"
                  ."<input type='hidden' name='id' value='".$oRR->getID()."' />"
                  ."<input type='text' class='cats_form' name='name' required value='".explode(".",$this->getPartPath($file_path,-1))[0]."' />.";
        $dir_key = "old";
        foreach (FilingCabinet::GetDirectories() as $k=>$v){
            if($v['directory'] == $this->getPartPath($file_path,-2)."/"){
                $dir_key = $k;
                break;
            }
        }
        $rename .= "<select name='ext' required><option value=''>Select Extension</option>";

        if( ($exts = @FilingCabinet::GetDirInfo($dir_key)['extensions']) ) {
            $file_ext = pathinfo($this->getPartPath($file_path,-1),PATHINFO_EXTENSION);
            foreach ($exts as $k=>$v){
                $rename .= "<option ".($file_ext == $v ? "selected":"").">$v</option>";
            }
        }
        $rename .= "</select>&nbsp&nbsp<input type='submit' value='rename' />"
                  ."</form>"
                  ."</div>";

        $download = "<a href='?cmd=download&id=".$oRR->getID()."' data-tooltip='Download Resource'><i class='fa fa-download'></i></a>";

        $delete = "<a href='?cmd=delete&id=".$oRR->getID()."' data-tooltip='Delete Resource'><img src='".CATSDIR_IMG."delete-resource.png'/></a>";

        $s = "<div style='display: flex;justify-content: space-around;'>".$move.$rename.$delete.$download."</div><div id='".addslashes($this->getPathRelativeTo($file_path,CATSDIR_RESOURCES))."_command' style='display:none'></div>";
        return $s;
    }

    private function drawCommandsVideos( ResourceRecord $oRR )
    {
        if(!$this->oApp->sess->CanAdmin("admin")){
            return "You don't have permission to edit files";
        }

        // Move form
        $move = "<a href='javascript:void(0)' onclick=\"setContents('cmd-{$oRR->getId()}','cmd-move-{$oRR->getId()}')\">move</a>";
        $move .= "<div id='cmd-move-{$oRR->getId()}' style='display:none'>"
                ."<br /><form>
                  <input type='hidden' name='cmd' value='move' />
                  <input type='hidden' name='id' value='{$oRR->getID()}' />
                  <select name='folder' class='cats_form' required><option value='' selected>-- Select Folder --</option>";
        foreach (FilingCabinet::GetDirectories('videos') as $k=>$v){
            // don't allow moving files into folders where they aren't supported
            if( !in_array($oRR->getExtension(),$v['extensions']) ) continue;

            $sDisabled = ($oRR->getDirectory() == $k && !$oRR->getSubDirectory()) ? " disabled" : "";
            $move .= "<option value='$k' $sDisabled>".$v['name']."</option>";
            foreach( FilingCabinet::GetSubfolders($k,'videos') as $sub ) {
                $sDisabled = ($oRR->getDirectory() == $k && $oRR->getSubDirectory()==$sub) ? " disabled" : "";
                $subfolder = SEEDCore_HSC($v['directory'].$sub);
                $move .= "<option value='$subfolder' $sDisabled> -- $sub</option>";
            }
        }
        $move .= "</select>&nbsp&nbsp<input type='submit' value='move' /></form></div>";

        // Rename form
        //$file_path='';
        $rename = "<a href='javascript:void(0)' onclick=\"setContents('cmd-{$oRR->getId()}','cmd-rename-{$oRR->getId()}')\">rename</a>";
        $rename .= "<div id='cmd-rename-{$oRR->getId()}' style='display:none'>"
                  ."<br /><form>"
                  ."<input type='hidden' name='cmd' value='rename' />"
                  // PATHINFO_FILENAME is a poorly-named arg that gets the part of the name before the '.'
                  ."<input type='hidden' name='id' value='".$oRR->getID()."' />"
                  ."<input type='text' class='cats_form' name='name' required value='".pathinfo($oRR->getFile(),PATHINFO_FILENAME)."' />.";
        $rename .= "<select name='ext' required><option value=''>Select Extension</option>";

        if( ($exts = @FilingCabinet::GetDirInfo($oRR->getDirectory(),'videos')['extensions']) ) {
            foreach ($exts as $v){
                $rename .= "<option ".($oRR->getExtension() == $v ? "selected":"").">$v</option>";
            }
        }
        $rename .= "</select>&nbsp&nbsp<input type='submit' value='rename' />"
                  ."</form>"
                  ."</div>";

        $download = "<a href='?cmd=download&id=".$oRR->getID()."' data-tooltip='Download Resource'><i class='fa fa-download'></i></a>";

        $delete = "<a href='?cmd=delete&id=".$oRR->getID()."' data-tooltip='Delete Resource'><img src='".CATSDIR_IMG."delete-resource.png'/></a>";

        $s = "<div style='display: flex;justify-content: space-around;'>".$move.$rename.$delete.$download."</div>"
            ."<div id='cmd-{$oRR->getId()}' style='display:none'></div>";

        return $s;
    }

    private function getPartPath($path = '', $depth = 0) {
        $pathArray = array();
        $pathArray = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
        if($depth < 0)
            $depth = count($pathArray)+$depth;

            if(!isset($pathArray[$depth]))
                return false;
                return $pathArray[$depth];
    }

    private function getPathRelativeTo($path, $relativeTo = ""){
        $output = "";
        if($relativeTo = realpath($relativeTo)){
            $relativeTo = $this->getPartPath($relativeTo,-1);
            $pathArray = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
            for($i = array_search($relativeTo, $pathArray)+1;$i<count($pathArray);$i++){
                if($output){
                    $output .= "/";
                }
                $output .= $pathArray[$i];
            }
        }
        return ($output?:false);
    }

}

?>