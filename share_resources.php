<?php

// Array of arrays containing directory information of resource folders
// The key of the first array defines the intermal key for the directory
// The directory value of the second array defines the path to the directory
// ALL directories are stored in the resources folder
// The name value of the second array is the name displayed in the select element
// It should be a discriptive name indicating what goes in the folder
// The extensions value of the second array is an array of all files extensions that are excepted in the directory
// DO NOT include the dot in the file extension
global $directories;
$directories= array(
                    "clinicForms"     => array("directory" => "clinic/",    "name" => "Clinic Forms",                            "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "papers"          => array("directory" => "papers/",    "name" => "Paper Designs",                           "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "selfReg"         => array("directory" => "reg/",       "name" => "Self Regulation",                         "extensions" => array("docx", "pdf", "txt", "rtf", "doc"), "color" => "#06962d" ),
                    "vMotor"          => array("directory" => "visual/",    "name" => "Visual Motor",                            "extensions" => array("docx", "pdf", "txt", "rtf", "doc"), "color" => "#ff0000" ),
                    "oMotor"          => array("directory" => "other/",     "name" => "Other Motor (fine, gross, oral, ocular)", "extensions" => array("docx", "pdf", "txt", "rtf", "doc"), "color" => "#ff8400" ),
                    "anxiety"         => array('directory' => "anxiety/",   "name" => "Anxiety",                                 "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "cognitive"       => array('directory' => "cog/",       "name" => "Cognitive",                               "extensions" => array("docx", "pdf", "txt", "rtf", "doc"), "color" => "#000000" ),
                    "adl"             => array('directory' => "adl/",       "name" => "ADL's",                                   "extensions" => array("docx", "pdf", "txt", "rtf", "doc"), "color" => "#ebcf00" ),
                    "assmt"           => array('directory' => "assmt/",     "name" => "Assessments",                             "extensions" => array("docx", "pdf", "txt", "rtf", "doc"), "color" => "#0000ff" ),
                    "old"             => array('directory' => "old/",       "name" => "Back Drawer",                             "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "reports"         => array("directory" => "reports/",   "name" => "Client Reports",                          "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "SOP"             => array("directory" => "SOP/",       "name" => "Standard Operating Procedures",           "extensions" => array("pdf")                              ),
                    "sections"        => array("directory" => "sections/",  "name" => "Resource Sections",                       "extensions" => array("docx")                             ),
                    "videos"          => array("directory" => "videos/",    "name" => "Videos",                                  "extensions" => array("mp4")                              )
);

function checkFileSystem(SEEDAppConsole $oApp){
    $FileSystemVersion = 2;
    $oBucket = new SEEDMetaTable_StringBucket( $oApp->kfdb );
    $currFileSystemVersion = intval($oBucket->GetStr( 'cats', 'FileSystemVersion') );
    if( $currFileSystemVersion != $FileSystemVersion ) {
        $oBucket->PutStr( 'cats', 'FileSystemVersion', $FileSystemVersion );
    }
    if ($currFileSystemVersion < 2) {
        ensureDirectory('old');
        foreach(array('handouts/','forms/','marketing/','clinic/') as $folder){
            $directory_iterator = new DirectoryIterator(CATSDIR_RESOURCES.$folder);
            foreach ($directory_iterator as $fileinfo){
                if($fileinfo->isDot()){
                    continue;
                }
                rename(CATSDIR_RESOURCES.$folder."/".$fileinfo->getFilename(), CATSDIR_RESOURCES.$GLOBALS['directories']['old']['directory'].$fileinfo->getFilename());
                $oApp->kfdb->Execute("UPDATE resources_files SET folder = '".addslashes(rtrim($GLOBALS['directories']['old']['directory'],"/"))."' WHERE folder='".addslashes(rtrim($folder,"/\\"))."' AND filename='".addslashes($fileinfo->getFilename())."'");
            }
            rmdir(realpath(CATSDIR_RESOURCES.$folder));
        }
    }
}

function ensureDirectory($dirs, $silent = FALSE){
    // Live server should be running with suexec so php will have the same permissions as the user account.
    // Dev servers aren't necessarily set up this way so they need full permissions.
    // This secures the live server from having world-writable/readable directories (only apache and cpanel can see them).
    if( SEED_isLocal ) {
        umask(0);
        $perm = 0777;
    } else {
        $perm = 0700;
    }

    if($dirs == "*"){
        foreach(array_keys($GLOBALS['directories']) as $k){
            ensureDirectory($k,$silent);
        }
        ensureDirectory("pending",$silent);
    }
    else if(is_array($dirs)){
        ensureDirectory("pending");
        foreach ($dirs as $dir){
            ensureDirectory($dir);
        }
    }
    else if($dirs == "pending"){
        if (!file_exists(CATSDIR_RESOURCES."pending")) {
            $r = @mkdir(CATSDIR_RESOURCES."pending", $perm, true);
            if(!$silent){
                echo "Pending Resources Directiory ".($r ? "" : "Could Not Be")." Created<br />";
            }
        }
    }
    else{
        if (!file_exists(CATSDIR_RESOURCES.$GLOBALS["directories"][$dirs]["directory"])) {
            $r = @mkdir(CATSDIR_RESOURCES.$GLOBALS["directories"][$dirs]["directory"], $perm, true);
            if(!$silent){
                echo $GLOBALS["directories"][$dirs]["name"]." Resources Directiory ".($r ? "" : "Could Not Be")." Created<br />";
            }
        }
    }
}

function getExtensions(){
    $exts = array();
    foreach($GLOBALS['directories'] as $k => $v){
        foreach ($v['extensions'] as $ext){
            array_push($exts, trim($ext, ". \t\n\r\0\x0B"));
        }
    }
    return $exts;
}

function share_resources(){
    return "<form action=\"?screen=therapist-resources\" method=\"post\" enctype=\"multipart/form-data\">
                    Select resource to upload:
                    <input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\" accept='".SEEDCore_ArrayExpandSeries(array_unique(getExtensions()), ".[[]],",true,array("sTemplateLast"=>".[[]]"))."'> Max File size:".ini_get('upload_max_filesize')."b"."
                    <br /><input type=\"submit\" value=\"Upload File\" name=\"submit\">
                    </form>";
}

function return_bytes($val) {
    $val = trim($val);

    $last = strtolower(substr($val, -1));
    $val = substr($val, 0,-1);
    switch($last)
    {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

function max_file_upload_in_bytes() {
    //select maximum upload size
    $max_upload = return_bytes(ini_get('upload_max_filesize'));
    //select post limit
    $max_post = return_bytes(ini_get('post_max_size'));
    //select memory limit
    $memory_limit = return_bytes(ini_get('memory_limit'));
    // return the smallest of them, this defines the real limit
    return min($max_upload, $max_post, $memory_limit);
}