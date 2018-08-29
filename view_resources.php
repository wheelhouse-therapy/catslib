<?php
require_once 'template_filler.php';
if(!$dir_name){
    $s .= "Directory not specified";
    return;
}
$cmd = SEEDInput_Str('cmd');
if($cmd == 'download'){
    $file = SEEDInput_Str('file');
    $filler = new template_filler($this->oApp);
    $filler->fill_resource($file);
}
if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
    $dir_name = CATSDIR_RESOURCES.$dir_name;
}
$dir = new DirectoryIterator($dir_name);
if(iterator_count($dir) == 2){
    $s .= "<h2> No files in directory</h2>";
    return;
}
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        $s .= "<a href='?cmd=download&file=".$dir_name.$fileinfo->getFilename()."'>".$fileinfo->getFilename()."</a><br />";
    }
};

?>