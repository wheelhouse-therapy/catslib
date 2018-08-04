<?php

if (!file_exists(CATSDIR_RESOURCES."pending")) {
    @mkdir(CATSDIR_RESOURCES."pending", 0777, true);
    echo "Pending Resources Directiory Created<br />";
}
if (!file_exists(CATSDIR_RESOURCES."accepted")) {
    @mkdir(CATSDIR_RESOURCES."accepted", 0777, true);
    echo "Accepted Resources Directiory Created<br />";
}

$dir_name = CATSDIR_RESOURCES."pending/";
$dir_accept = CATSDIR_RESOURCES."accepted/";

$s .= "<a href='?screen=home'><button>Back</button></a><br />";
$cmd = SEEDInput_Str( 'cmd' );
if($cmd == "accept"){
    $file = SEEDInput_Str( 'file' );
    if(rename($dir_name."/".$file, $dir_accept."/".$file)){
        $s .= "<div class='alert alert-success'> File ".$file." has been accepted as a resource</div>";
    }
    else{
        $s .= "<div class='alert alert-error'>An error occured while accepting File ".$file."</div>";
    }
}
elseif ($cmd == "reject"){
    $file = SEEDInput_Str( 'file' );
    if(unlink($dir_name."/".$file)){
        $s .= "<div class='alert alert-success'> File ".$file." has been rejected as a resource. This CANNOT be undone</div>";
    }
    else{
        $s .= "<div class='alert alert-error'>An error occured while rejecting File ".$file."</div>";
    }
}
$dir = new DirectoryIterator($dir_name);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $s .= "<a href='".$fileinfo->getPath()."/".$fileinfo->getFilename()."'>".$fileinfo->getFilename()."</a>
        <a href='?cmd=accept&file=".$fileinfo->getFilename()."'><img src='".CATSDIR_IMG."accept-resource.png' style='max-width:20px;'/></a>
        <a href='?cmd=reject&file=".$fileinfo->getFilename()."'><img src='".CATSDIR_IMG."reject-resource.png' style='max-width:20px;'/></a>
        <br />";
    }
}

?>
