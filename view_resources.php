<?php
if(!$dir_name){
    $s .= "Directory not specified";
    return;
}
if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
    $dir_name = CATSDIR_RESOURCES.$dir_name;
}
$dir = new DirectoryIterator($dir_name);
if(iterator_count($dir) == 0){
    $s .= "<h2> No files in directory</h2>";
    return;
}
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $s .= "<a href='".$dir_name.str_replace(" ", "%20", $fileinfo->getFilename())."'>".$fileinfo->getFilename()."</a><br />";
        
    }
}

?>