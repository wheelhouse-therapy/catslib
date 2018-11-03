<?php

require_once 'share_resources.php';

if (!file_exists(CATSDIR_RESOURCES."pending")) {
    @mkdir(CATSDIR_RESOURCES."pending", 0777, true);
    echo "Pending Resources Directiory Created<br />";
}

foreach($directories as $k => $v){
    if (!file_exists(CATSDIR_RESOURCES.$v["directory"])) {
        @mkdir(CATSDIR_RESOURCES.$v["directory"], 0777, true);
        echo $v["name"]." Resources Directiory Created<br />";
    }
}

$dir_name = CATSDIR_RESOURCES."pending/";
$cmd = SEEDInput_Str( 'cmd' );
if($cmd == "accept"){
    $file = SEEDInput_Str( 'file' );
    $dir = SEEDInput_Str( 'dir' );
    $file = str_replace("+", " ", $file);
    if(rename($dir_name.$file, CATSDIR_RESOURCES.$directories[$dir]['directory'].$file)){
        $s .= "<div class='alert alert-success'> File ".$file." has been accepted as a resource</div>";
    }
    else{
        $s .= "<div class='alert alert-error'>An error occured while accepting File ".$file."</div>";
    }
}
elseif ($cmd == "reject"){
    $file = SEEDInput_Str( 'file' );
    if(unlink($dir_name."/".$file)){
        $s .= "<div class='alert alert-danger'> File ".$file." has been rejected as a resource. This CANNOT be undone</div>";
    }
    else{
        $s .= "<div class='alert alert-error'>An error occured while rejecting File ".$file."</div>";
    }
}
$dir = new DirectoryIterator($dir_name);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        //TODO Add tooltips to icons
        $s .= "<a href='".$fileinfo->getPath()."/".$fileinfo->getFilename()."'>".$fileinfo->getFilename()."</a>
        <form style='display:inline'>
        <input type='hidden' name='cmd' value='accept' />
        <input type='hidden' name='file' value='".$fileinfo->getFilename()."' />";
        $excluded = array();
        $options = "<option selected value=''>Select a directory</option>";
        foreach($directories as $k => $v){
            if(file_exists(CATSDIR_RESOURCES.$v['directory'] . basename($fileinfo->getFilename()))){
                array_push($excluded, $k);
            }
            else if(!in_array($fileinfo->getExtension(), $v['extensions'])){
                continue;
            }
            $options .= "<option value='".$k."'>".$v['name']."</option>";
        }
        $s .= "<select name='dir' onchange='".js($excluded)."' required>".$options."</select>
        <input id='accept' type='submit' data-tooltip='Accept Resource' value='' style='background: url(".CATSDIR_IMG."accept-resource.png) 0px/24px no-repeat; width: 24px; height: 24px;border:  none; position: relative; top: 5px; margin-left: 5px'>
        </form>
        <a id='reject' href='?cmd=reject&file=".$fileinfo->getFilename()."'><img data-tooltip='Reject Resource' src='".CATSDIR_IMG."reject-resource.png' style='max-width:22px; position: relative; bottom: 2px; margin-left: 2px'/></a>
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
            submit.dataset.tooltip = 'Overwrite Resource';
        }else{
            $(submit).css('background-image','url(".CATSDIR_IMG."accept-resource.png)');
            submit.dataset.tooltip = 'Accept Resource';
        }
}
</script>
";

function js($replace){
    $s = "replace(event, " . json_encode($replace) . ");";
    return $s;
}

?>
