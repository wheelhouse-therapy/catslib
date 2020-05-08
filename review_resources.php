<?php

require_once 'share_resources.php';

FilingCabinet::EnsureDirectory("*");

$dir_name = CATSDIR_RESOURCES."pending/";
$cmd = SEEDInput_Str( 'cmd' );

if($cmd == "accept"){
    $file = SEEDInput_Str( 'file' );
    $ra = explode("/",SEEDInput_Str( 'dir' ));
    $dir = $ra[0];
    $subdir = @$ra[1]? "{$ra[1]}/" : "";
    if(rename($dir_name.$file, CATSDIR_RESOURCES.FilingCabinet::GetDirInfo($dir)['directory'].$subdir.$file)){
        $s .= "<div class='alert alert-success'> File ".$file." has been accepted as a resource</div>";
        $oRR = ResourceRecord::CreateFromRealPath($oApp, realpath(CATSDIR_RESOURCES.FilingCabinet::GetDirInfo($dir)['directory'].$subdir.$file));
        if(!$oRR->StoreRecord()){
            $s .= "<div class='alert alert-danger'>Unable to index the file. Contact a System Administrator Imeadiatly (Code 504)</div>";
        }
    }
    else{
        $s .= "<div class='alert alert-danger'>An error occured while accepting File ".$file."</div>";
    }
}
elseif ($cmd == "reject"){
    $file = SEEDInput_Str( 'file' );
    if(unlink($dir_name.$file)){
        $s .= "<div class='alert alert-danger'> File ".$file." has been rejected as a resource. This CANNOT be undone</div>";
    }
    else{
        $s .= "<div class='alert alert-danger'>An error occured while rejecting File ".$file."</div>";
    }
}

//TODO Remove once papers files are accessable
$s .= "<div class='alert alert-warning'>Files in the Papers directory are currently <strong>NOT ACCESSABLE</strong> thru the CATS platform</div>";

$i = 0; // Internal Counter for form ID

$dir = new DirectoryIterator($dir_name);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $i++;
        $s .= "<a href='".$fileinfo->getPath()."/".$fileinfo->getFilename()."'>".$fileinfo->getFilename()."</a>
        <form id='form".$i."' style='display:inline'>
        <input type='hidden' name='cmd' value='accept' />
        <input type='hidden' name='file' value=\"".$fileinfo->getFilename()."\" />";
        $excluded = array();
        $options = "<option selected value=''>Select a directory</option>";
        foreach(FilingCabinet::GetDirectories() as $k => $v){
            $exclude = file_exists(CATSDIR_RESOURCES.$v['directory'] . basename($fileinfo->getFilename()));
            if(!in_array($fileinfo->getExtension(), $v['extensions'])){
                continue;
            }
            if(FilingCabinet::GetSubFolders($k)){
                foreach(FilingCabinet::GetSubFolders($k) as $folder){
                    if($exclude){
                        array_push($excluded, $k."/".$folder);
                    }
                    $options .= "<option value='".$k."/".$folder."'>".$v['name']."/".$folder."</option>";
                }
            }
            else{
                if($exclude){
                    array_push($excluded, $k);
                }
                $options .= "<option value='".$k."'>".$v['name']."</option>";
            }
        }
        $s .= "<select name='dir' onchange='".js($excluded)."' required>".$options."</select>
        <button type='submit' form='form".$i."' data-tooltip='Accept Resource' value='' style='background: url(".CATSDIR_IMG."accept-resource.png) 0px/24px no-repeat; width: 24px; height: 24px;border:  none; position: relative; top: 5px; margin-left: 5px'></button>
        </form>
        <a href='?cmd=reject&file=".$fileinfo->getFilename()."' data-tooltip='Reject Resource'><img src='".CATSDIR_IMG."reject-resource.png' style='max-width:22px; position: relative; bottom: 2px; margin-left: 2px'/></a>
        <br />";
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
</script>
";

function js($replace){
    $s = "replace(event, " . json_encode($replace) . ");";
    return $s;
}

?>