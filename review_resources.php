<?php

if (!file_exists(CATSDIR_RESOURCES."pending")) {
    @mkdir(CATSDIR_RESOURCES."pending", 0777, true);
    echo "Pending Resources Directiory Created<br />";
}

//Array of arrays containing directory information of resource folders
// The key of the first array defines the intermal key for the directory
// The directory value of the second array defines the path to the directory
// ALL directories are stored in the resources folder
// The name value of the second array is the name displayed in the select element
// It should be a discriptive name indicating what goes in the folder
$directories = array("papers"    => array("directory" => "papers/",    "name" => "Papers"              ),
                     "handouts"  => array("directory" => "handouts/",  "name" => "Handouts"            ),
                     "reports"   => array("directory" => "reports/",   "name" => "Client Reports"      ),
                     "forms"     => array("directory" => "forms/",     "name" => "Forms"               ),
                     "marketing" => array("directory" => "marketing/", "name" => "Marketing Materials" )
                    );

foreach($directories as $k => $v){
    if (!file_exists(CATSDIR_RESOURCES.$v["directory"])) {
        @mkdir(CATSDIR_RESOURCES.$v["directory"], 0777, true);
        echo $v["name"]." Resources Directiory Created<br />";
    }
}

$dir_name = CATSDIR_RESOURCES."pending/";

$s .= "<a href='?screen=home'><button>Back</button></a><br />";
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
        $s .= "<a href='".$fileinfo->getPath()."/".$fileinfo->getFilename()."'>".$fileinfo->getFilename()."</a>
        <form style='display:inline'>
        <input type='hidden' name='cmd' value='accept' />
        <input type='hidden' name='file' value='".$fileinfo->getFilename()."' />
        <select name='dir' required>
        <option selected value=''>Select a directory</option>";
        foreach($directories as $k => $v){
            $sdisabled = "";
            if(CATSDIR_RESOURCES.$v['directory'] . basename($fileinfo->getFilename())){
                $sdisabled = "disabled";
            }
            $s .= "<option value='".$k."'$sdisabled>".$v['name']."</option>";
        }
        $s .= "</select>
        <input type='submit' value='' style='background: url(".CATSDIR_IMG."accept-resource.png);width: 24px;height: 24px;border:  none;background-size: 20px;background-repeat:  no-repeat;'>
        </form>
        <a href='?cmd=reject&file=".$fileinfo->getFilename()."'><img src='".CATSDIR_IMG."reject-resource.png' style='max-width:20px;'/></a>
        <br />";
    }
}

?>
