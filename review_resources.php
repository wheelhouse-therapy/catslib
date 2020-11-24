<?php

require_once 'share_resources.php';

FilingCabinet::EnsureDirectory("*");

$dir_name = CATSDIR_RESOURCES."pending/";
$cmd = SEEDInput_Str( 'cmd' );

if($cmd == "accept"){
    $rrID = SEEDInput_Str( 'rrID' );
    $oRR = ResourceRecord::GetRecordByID($oApp, $rrID);
    $file = $oRR->getFile();
    $ra = explode("/",SEEDInput_Str( 'dir' ));
    $dir = $ra[0];
    $subdir = @$ra[1]? "{$ra[1]}/" : "";
    if(rename($dir_name.$file, CATSDIR_RESOURCES.FilingCabinet::GetDirInfo($dir)['directory'].$subdir.$file)){
        if(!ResourceRecord::GetRecordFromPath($oApp, $dir, $file,$subdir)){
            $s .= "<div class='alert alert-success'> File ".$file." has been accepted as a resource</div>";
            $oRR->setDirectory($dir);

            $oRR->setSubDirectory(@$ra[1]?: "");
            if(!$oRR->StoreRecord()){
                $s .= "<div class='alert alert-danger'>Unable to update the index. Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
            }
        }
        else{
            $s .= "<div class='alert alert-success'> Resource ".$file." has been overwritten. This CANNOT be undone</div>";
            if(!$oRR->DeleteRecord()){
                $s .= "<div class='alert alert-danger'>Unable to update the index. Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
            }
        }
        $oRR->CreateThumbnail();
    }
    else{
        $s .= "<div class='alert alert-danger'>An error occured while accepting File ".$file."</div>";
    }
}
elseif ($cmd == "reject"){
    $rrID = SEEDInput_Str( 'rrID' );
    $oRR = ResourceRecord::GetRecordByID($oApp, $rrID);
    $file = $oRR->getFile();
    if(unlink($dir_name.$file)){
        $s .= "<div class='alert alert-danger'> File ".$file." has been rejected as a resource. This CANNOT be undone</div>";
        if(!$oRR->DeleteRecord() && $oRR->getStatus() != 1){
            $s .= "<div class='alert alert-danger'> Unable to delete file index. Contact a System Administrator Immediatly (Code 504)</div>";
        }
    }
    else{
        $s .= "<div class='alert alert-danger'>An error occured while rejecting File ".$file."</div>";
    }
}
elseif ($cmd == "download"){
    $rrID = SEEDInput_Str( 'rrID' );
    $oRR = ResourceRecord::GetRecordByID($oApp, $rrID);
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

//TODO Remove once papers files are accessable
$s .= "<div class='alert alert-warning'>Files in the Papers directory are currently <strong>NOT ACCESSABLE</strong> thru the CATS platform</div>";

$dir = new DirectoryIterator($dir_name);

if(iterator_count($dir) == 2){
    $s .= "<h2> No files awaiting review</h2>";
    goto footer;
}
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $oRR = ResourceRecord::GetRecordFromRealPath($oApp, $fileinfo->getRealPath());
        if(!$oRR){
            $oRR = ResourceRecord::CreateFromRealPath($oApp, $fileinfo->getRealPath(),0);
            $oRR->StoreRecord();
        }
        $s .= "<div><a href='".CATSDIR_URL."?cmd=download&rrID={$oRR->getID()}'>".$fileinfo->getFilename()."</a>
        <form id='form".$oRR->getID()."' style='display:inline' onsubmit='disable({$oRR->getID()},event)'>
        <input type='hidden' name='cmd' value='accept' />
        <input type='hidden' name='rrID' value='{$oRR->getID()}' />";
        $excluded = array();
        $options = "<option selected value=''>Select a directory</option>";
        foreach(FilingCabinet::GetDirectories() as $k => $v){
            if(!in_array($fileinfo->getExtension(), $v['extensions'])){
                continue;
            }
            if(file_exists(CATSDIR_RESOURCES.$v['directory'] . basename($fileinfo->getFilename()))){
                array_push($excluded, $k);
            }
            $options .= "<option value='".$k."'>".$v['name']."</option>";
            if(FilingCabinet::GetSubFolders($k)){
                foreach(FilingCabinet::GetSubFolders($k) as $folder){
                    if(file_exists(CATSDIR_RESOURCES.$v['directory'].$folder.'/' . basename($fileinfo->getFilename()))){
                        array_push($excluded, $k."/".$folder);
                    }
                    $options .= "<option value='".$k."/".$folder."'>".$v['name']."/".$folder."</option>";
                }
            }
        }
        $s .= "<select name='dir' onchange='".js($excluded)."' required>".$options."</select>
        <button type='submit' form='form".$oRR->getID()."' data-tooltip='Accept Resource' value='' style='background: url(".CATSDIR_IMG."accept-resource.png) 0px/24px no-repeat; width: 24px; height: 24px;border:  none; position: relative; top: 5px; margin-left: 5px' class='resource{$oRR->getID()}'></button>
        </form>
        <a href='".CATSDIR_URL."?cmd=reject&rrID=".$oRR->getID()."' data-tooltip='Reject Resource' class='resource{$oRR->getID()}' onclick='disable({$oRR->getID()},event)'><img src='".CATSDIR_IMG."reject-resource.png' style='max-width:22px; position: relative; bottom: 2px; margin-left: 2px'/></a>
        <br />Uploaded By: {$oRR->getUploader(true)['realname']}</div>";
    }
}
$url = "/cats" . substr(CATSDIR_IMG, 1);

$s .= "
<script>
function replace(event, ra) {
        var index = event.target.selectedIndex;
        var options = event.target.options;
        var submit = event.target.nextElementSibling;
        if($.inArray(options[index].value,ra) !== -1){
            $(submit).css('background-image','url(".CATSDIR_IMG."overwrite-resource.png)');
            submit.firstElementChild.innerHTML = 'Overwrite Resource';
        }else{
            $(submit).css('background-image','url(".CATSDIR_IMG."accept-resource.png)');
            submit.firstElementChild.innerHTML = 'Accept Resource';
        }
}
function disable(rrid,event){
    let className = 'resource'+rrid;
    if($('a.'+className)[0] != event.currentTarget){
        $('a.'+className)[0].href = '';
    }
    $('button.'+className)[0].disabled = true;
}
</script>
";
footer:
$s .= "<a href='".CATSDIR."?screen=admin-manageresources'><- Go To manage resources</a>";

function js($replace){
    $s = "replace(event, " . json_encode($replace) . ");";
    return $s;
}