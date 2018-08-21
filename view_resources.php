<?php
if(!$dir_name){
    $s .= "Directory not specified";
    return;
}
if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
    $dir_name = CATSDIR_RESOURCES.$dir_name;
}
$dir = new DirectoryIterator($dir_name);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $s .= "<a href='".str_replace(" ", "%20", $fileinfo->getFilename())."'>".$fileinfo->getFilename()."</a><br />";
        
    }
}

?>