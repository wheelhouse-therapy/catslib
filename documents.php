<?php

function ManageResources( SEEDAppSessionAccount $oApp ) {
    $s = "<h2>Manage Resources</h2>";
    $s .= "<div style='text-align:right'><a href='".CATSDIR."admin-resources'>Go To review resources -></a></div>";

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

    function toggleFolder(el){
        el.firstElementChild.classList.toggle("fa-folder-open");
		el.firstElementChild.classList.toggle("fa-folder");
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

class ResourceManager {
    
    private $oApp;
    private $oFCT;
    
    public function __construct(SEEDAppConsole $oApp){
        $this->oApp = $oApp;
        $this->oFCT = new FilingCabinetTools($oApp);
    }
    
    public function ManageResources(){
        if( ($id = SEEDInput_Int("id")) ) {
            if( ($oRR = ResourceRecord::GetRecordById($this->oApp, $id)) ) {
                $this->processCommands($oRR);
            }
        }
        
        if(isset($_SESSION['ResourceCMDResult'])){
            $cmdResult = $_SESSION['ResourceCMDResult'];
            unset($_SESSION['ResourceCMDResult']);
        }
        else{
            $cmdResult = "";
        }
        $script = "<script>displayed = Object.values(JSON.parse('".json_encode($this->oFCT->TreeListGet())."'));</script>";
        return $script.$cmdResult."<div class='cats_doctree'>".$this->listResources()."</div>";
    }
  
    private function processCommands(ResourceRecord $oRR){
        
        // Valid Commands: move, rename, download, delete, adjustvisibility
        $cmd = SEEDInput_Str("cmd");
        switch(strtolower($cmd)){
            case "move":
                if(!$oRR){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Cannot retrieve file</div>";
                    break;
                }
                $oldFile = $oRR->getPath();
                $ra = explode("/",SEEDInput_Str('folder'));
                $cabinet = $ra[0];
                $dir = $ra[1];
                $subdir = @$ra[2]? "{$ra[2]}/" : "";
                if(!in_array($cabinet, FilingCabinet::GetCabinets())){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>The $cabinet cabinet does not exist. File was <strong>NOT</strong> moved.</div>";
                    break;
                }
                if(!in_array($dir, array_keys(FilingCabinet::GetDirectories($cabinet)))){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>The $dir folder in the $cabinet cabinet does not exist. File was <strong>NOT</strong> moved.</div>";
                    break;
                }
                if($subdir && !in_array($subdir, FilingCabinet::GetSubFolders($dir,$cabinet))){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>The $sub sub foler in the $dir folder of the $cabinet cabinet does not exist. File was <strong>NOT</strong> moved.</div>";
                    break;
                }
                $changed = $oRR->setCabinet($cabinet);
                $changed = $oRR->setDirectory($dir) || $changed;
                $changed = $oRR->setSubDirectory($subdir) || $changed;
                $newFile = $oRR->getPath();
                if(rename($oldFile, $newFile)){
                    if($oRR->StoreRecord()){
                        $toDir = FilingCabinet::GetDirInfo($dir)['name'];
                        if($subdir){
                            $toDir .= "/".$subdir;
                        }
                        $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>Successfully Moved {$oRR->getFile()} to $toDir</div>";
                    }
                    else if($changed){
                        $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Index could not be updated, the file will not be visible till the issue is corrected. Contact a developer (Code 504-{$oRR->getID()})</div>";
                    }
                }
                else{
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>ERROR Moving the file</div>";
                }
                break;
            case "rename":
                $oldFileBase = $oRR->getFile();
                if( ($name = SEEDInput_Str("name")) && ($ext = SEEDInput_Str("ext")) ) {
                    $newFileBase = $name.'.'.$ext;                                // base name of new file
                } else {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>New file name not given</div>";
                    break;
                }
                if(!in_array($ext, (@FilingCabinet::GetDirInfo($oRR->getDirectory(),$oRR->getCabinet())['extensions']?:[]))){
                    $dirName = FilingCabinet::GetDirInfo($oRR->getDirectory(),$oRR->getCabinet())['name'];
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>The file extension $ext is not supported in the $dirName folder</div>";
                    break;
                }
                if($oRR->rename($newFileBase)){
                    if($oRR->StoreRecord()){
                        $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>File $oldFileBase renamed to $newFileBase</div>";
                    }
                    else if($oldFileBase != $oRR->getFile()){
                        $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Index could not be updated, the file will not be visible till the issue is corrected. Contact a developer (Code 504-{$oRR->getID()})</div>";
                    }
                }
                else{
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>ERROR Renaming the file</div>";
                }
                break;
            case "delete":
                if(unlink($oRR->getPath())){
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
            case "adjustvisibility":
                $newStatus = SEEDInput_Int("status");
                if($newStatus < 0){
                    $newStatus = 0;
                }
                $oldStatus = $oRR->getStatus();
                if($newStatus == ResourceRecord::STATUS_DELETED){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Sorry, this command can't be used to delete resources. Use the delete command instead</div>";
                    break;
                }
                if($oRR->getStatus() == ResourceRecord::STATUS_DELETED){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Sorry, this command can't be used to restore deleted resources. Deleted Resources must be reuploaded</div>";
                    break;
                }
                $oRR->setStatus($newStatus);
                if($oRR->StoreRecord()){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>Visibility Updated</div>";
                }
                else if($oldStatus != $newStatus){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Could not update the visibility of the resource (Code 504-{$oRR->getID()}-$oldStatus-$newStatus)</div>";
                }
                break;
            case "download":
                $oFD = new FilingCabinetDownload($this->oApp);
                $oFD->OutputResource($oRR->getID(), true);
                exit;
        }
        header("HTTP/1.1 303 SEE OTHER");
        header("Location: ?");
        exit();
    }
    
    private function listResources():String{
        $s = "";
        foreach(FilingCabinet::GetCabinets() as $cabinet){
            $s .= "<h4>".ucfirst($cabinet)." Cabinet</h4>";
            foreach(FilingCabinet::GetFilingCabinetDirectories($cabinet) as $directory=>$dirInfo){
                $dirID = $this->getID($cabinet, $directory);
                $dirCount = count(FilingCabinet::GetSubFolders($directory,$cabinet));
                $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$dirID');toggleFolder(this);\"><i class='[[dirclass]] ".(in_array($dirID, $this->oFCT->TreeListGet())?"fa-folder-open":"fa-folder")."'></i> {$dirInfo['name']}</a><br />";
                $s .= "<div class='cats_doctree_level' id='$dirID' style='".(in_array($dirID, $this->oFCT->TreeListGet())?"":"display:none;")." width: 100%;'>";
                foreach(FilingCabinet::GetSubFolders($directory,$cabinet) as $subdirectory){
                    $subdirID = $this->getID($cabinet, $directory,$subdirectory);
                    $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$subdirID');toggleFolder(this);\"><i class='[[subclass]] ".(in_array($subdirID, $this->oFCT->TreeListGet())?"fa-folder-open":"fa-folder")."'></i> $subdirectory</a><br />";
                    $s .= "<div class='cats_doctree_level' id='$subdirID' style='".(in_array($subdirID, $this->oFCT->TreeListGet())?"":"display:none;")." width: 100%;'>";
                    $raRRSub = ResourceRecord::GetResources($this->oApp, $cabinet, $directory,$subdirectory);
                    foreach( $raRRSub as $oRR ) {
                        $fileID = $this->getID($cabinet, $directory,$subdirectory,$oRR->getID());
                        $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$fileID')\"><i class='far fa-file'></i> {$oRR->getFile()}".($oRR->getStatus() == ResourceRecord::STATUS_HIDDEN?" <i class='fas fa-low-vision' title='Resource is hidden from some users'></i>":"")."</a><br />";
                        $s .= "<div class='cats_docform' id='$fileID' style='".(in_array($fileID, $this->oFCT->TreeListGet())?"":"display:none;")." width: 50%;'>";
                        $s .= $this->drawCommands($oRR);
                        $s .= "</div>";
                    }
                    if(count($raRRSub) == 0){
                        $s = str_replace("[[subclass]]", "far", $s);
                        $s .= "No Resources";
                    }
                    else{
                        $s = str_replace("[[subclass]]", "fas", $s);
                    }
                    $s .= "</div>";
                }
                $raRR = ResourceRecord::GetResources($this->oApp, $cabinet, $directory);
                $dirCount += count($raRR);
                foreach ($raRR as $oRR) {
                    $fileID = $this->getID($cabinet, $directory,"",$oRR->getID());
                    $s .= "<a href='javascript:void(0)' onclick=\"toggleDisplay('$fileID')\"><i class='fas fa-file'></i> {$oRR->getFile()}".($oRR->getStatus() == ResourceRecord::STATUS_HIDDEN?" <i class='fas fa-low-vision' title='Resource is hidden from some users'></i>":"")."</a><br />";
                    $s .= "<div class='cats_docform' id='$fileID' style='".(in_array($fileID, $this->oFCT->TreeListGet())?"":"display:none;")." width: 50%;'>";
                    $s .= $this->drawCommands($oRR);
                    $s .= "</div>";
                }
                if($dirCount == 0){
                    $s = str_replace("[[dirclass]]", "far", $s);
                    $s .= "No Resources";
                }
                else{
                    $s = str_replace("[[dirclass]]", "fas", $s);
                }
                $s .= "</div>";
            }
        }
        return $s;
    }
    
    private function drawCommands(ResourceRecord $oRR):String{
        if(!$this->oApp->sess->CanAdmin("admin")){
            return "You don't have permission to edit files";
        }
        if(!file_exists($oRR->getPath())){
            return "The Referenced file does not exist. (Error 504-{$oRR->getID()})";
        }
        $move = "<a href='javascript:void(0)' onclick=\"setContents('".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_command','".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_move')\">move</a>";
        $move .= "<div id=\"".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_move\" style='display:none'>"
                ."<br /><form>
                    <input type='hidden' name='cmd' value='move' />
                    <input type='hidden' name='id' value='".$oRR->getID()."' />
                    <select name='folder' class='cats_form' required><option value='' selected>-- Select Folder --</option>";
        $extension = pathinfo($oRR->getFile(),PATHINFO_EXTENSION);
        foreach(FilingCabinet::GetCabinets() as $cabinet){
            if(!in_array($extension, FilingCabinet::GetSupportedExtensions($cabinet))){
                // Skip cabinets that don't support the file
                continue;
            }
            $move .= "<optgroup label='".ucfirst($cabinet)."'>";
            foreach(FilingCabinet::GetFilingCabinetDirectories($cabinet) as $dir=>$dirInfo){
                if($dir=="papers"){
                    //TODO Remove once papers files are accessable
                    continue;
                }
                if(!in_array($extension, $dirInfo['extensions'])){
                    // Skip folders that don't support the file
                    continue;
                }
                if(ResourceRecord::GetRecordFromPath($this->oApp, $cabinet, $dir, $oRR->getFile())){
                    $move .= "<option disabled title='A resource with this name already exists at this location' value='$cabinet/$dir'>{$dirInfo['name']}</option>";
                }
                else{
                    $move .= "<option value='$cabinet/$dir'>{$dirInfo['name']}</option>";
                }
                foreach(FilingCabinet::GetSubFolders($dir,$cabinet) as $subdir){
                    if(ResourceRecord::GetRecordFromPath($this->oApp, $cabinet, $dir, $oRR->getFile(),$subdir)){
                        $move .= "<option disabled title='A resource with this name already exists at this location' value='$cabinet/$dir/$subdir'>{$dirInfo['name']}/$subdir</option>";
                    }
                    else{
                        $move .= "<option value='$cabinet/$dir/$subdir'>{$dirInfo['name']}/$subdir</option>";
                    }
                }
            }
            $move .= "</optgroup>";
        }
        $move .= "</select>&nbsp&nbsp<input type='submit' value='move' /></form></div>";
            
        $rename = "<a href='javascript:void(0)' onclick=\"setContents('".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_command','".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_rename')\">rename</a>";
        $rename .= "<div id=\"".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_rename\" style='display:none'>"
                  ."<br /><form>"
                  ."<input type='hidden' name='cmd' value='rename' />"
                  ."<input type='hidden' name='id' value='".$oRR->getID()."' />"
                  ."<input type='text' class='cats_form' name='name' required value='".explode(".",$oRR->getFile())[0]."' />.";
        $dir_key = "old";
        foreach (FilingCabinet::GetDirectories() as $k=>$v){
            if($k == $oRR->getDirectory()){
                $dir_key = $k;
                break;
            }
        }
        $rename .= "<select name='ext' required><option value=''>Select Extension</option>";
        
        if( ($exts = @FilingCabinet::GetDirInfo($dir_key,$oRR->getCabinet())['extensions']) ) {
            $file_ext = pathinfo($oRR->getFile(),PATHINFO_EXTENSION);
            foreach ($exts as $k=>$v){
                $rename .= "<option ".($file_ext == $v ? "selected":"").">$v</option>";
            }
        }
        $rename .= "</select>&nbsp&nbsp<input type='submit' value='rename' />"
                  ."</form>"
                  ."</div>";
                                    
        $download = "<a href='?cmd=download&id=".$oRR->getID()."' data-tooltip='Download Resource'><i class='fa fa-download'></i></a>";
        
        $delete = "<a href='?cmd=delete&id=".$oRR->getID()."' data-tooltip='Delete Resource'><img src='".CATSDIR_IMG."delete-resource.png'/></a>";
        
        $visibility = "<a href='javascript:void(0)' data-tooltip='".($oRR->getStatus() == ResourceRecord::STATUS_HIDDEN?"Adjust Visibility. Currently hidden from some users.":"Adjust Visibility. Currently visible to all users.")."' onclick=\"setContents('".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_command','".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_visibility')\">".($oRR->getStatus() == ResourceRecord::STATUS_HIDDEN?"<i class='fas fa-low-vision'></i>":"<i class='fas fa-eye'></i>")."</a>";
        $visibility .= "<div id=\"".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_visibility\" style='display:none'>"
                      ."<br /><form>"
                      ."<input type='hidden' name='cmd' value='adjustvisibility' />"
                      ."<input type='hidden' name='id' value='".$oRR->getID()."' />";
        $visibility .= "<select name='status'>";
        
        foreach([0=>"Everyone",2=>"Everyone but students"] as $status=>$name){
            if($status == $oRR->getStatus()){
                $visibility .= "<option selected value='$status'>$name</option>";
            }
            else{
                $visibility .= "<option value='$status'>$name</option>";
            }
        }
        
        $visibility .= "</select>&nbsp&nbsp<input type='submit' value='Change visibility' />"
                      ."</form>"
                      ."</div>";
        
        $s = "<div style='display: flex;justify-content: space-around;'>".$move.$rename.$delete.$download.$visibility."</div><div id='".$this->getID($oRR->getCabinet(), $oRR->getDirectory(),$oRR->getSubDirectory(),$oRR->getID())."_command' style='display:none'></div>";
        return $s;
    }
    
    private function getID(String $cabinet, String $directory, String $subdirectory = "",int $rrid = 0):String{
        $s = $cabinet."/".$directory;
        if($subdirectory){
            $s .= "/".$subdirectory;
        }
        if($rrid > 0){
            $s .= "/".strval($rrid);
        }
        return $s;
    }
    
}