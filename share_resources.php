<?php

/* share_resources
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * Upload and manage files
 */


class FilingCabinet
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function DrawFilingCabinet()
    {
        $s = "";

        self::EnsureDirectory("*");
        if( ($dir = SEEDInput_Str('dir')) && ($raDirInfo = self::GetDirInfo($dir)) ) {
            $s .= ResourcesDownload( $this->oApp, $raDirInfo['directory'] );
        } else {
            $s .= "<h3>Filing Cabinet</h3>";
            // Some of the directories in the array are not part of the filing cabinet. Remove them here.
            $ras = array_diff_key(self::$raDirectories, array_flip(array('reports','SOP','sections','videos')));
            foreach( $ras as $k => $ra ) {
                $bgcolor = "background-color: grey;";
                if (array_key_exists("color", $ra)) {
                    $bgcolor = "background-color: {$ra['color']};";
                }
                $s .= "<p><div style='{$bgcolor} display: inline-block; min-width: 500px; text-align: center'>"
                        ."<a style='font-size: 18pt; color: #fff' href='?dir={$k}'>{$ra['name']}</a>"
                     ."</div></p>";
            }
        }
        return( $s );
    }

    function UploadToPending()
    /*************************
        Following a _FILES upload, put the file in the "pending" folder.
     */
    {
        $s = "";

        self::EnsureDirectory("pending");

        if( !isset($_FILES["fileToUpload"]["name"]) ) {
            $s .= "Sorry, nothing was uploaded.<br/>";
            goto done;
        }

        $target_dir = CATSDIR_RESOURCES."pending/";
        $target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
        $documentFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

        // Check if file already exists
        $s .= "<a href='?screen=therapist-submitresources'><button>Back</button></a><br />";
        if (file_exists($target_file)) {
            $s .= "Sorry, file already exists.<br />";
            goto done;
        }
        // Check file size
        if ($_FILES["fileToUpload"]["size"] > max_file_upload_in_bytes()) {
            $s .= "Sorry, your file is too large.<br />";
            goto done;
        }
        // Allow certain file formats
        if(!in_array($documentFileType, getExtensions())) {
            $s .= "Sorry, only ".implode(", ", array_unique(getExtensions()))." files are allowed.<br />";
            goto done;
        }

        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            $s .= "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded and is awaiting review.";
            if($this->oApp->sess->CanWrite("admin")){
                $s .= "<br /><a href='?screen=admin-resources'><button>Review Now</button></a>";
            }
        } else {
            $s .= "Sorry, there was an error uploading your file.";
        }

        done:
        return( $s );
    }

    static function EnsureDirectory($dirs, $silent = FALSE)
    {
        /* Live server should be running with suexec so php will have the same permissions as the user account.
         * Dev servers aren't necessarily set up this way so they need full permissions.
         * This secures the live server from having world-writable/readable directories (only apache and cpanel can see them).
         */
        if( SEED_isLocal ) {
            umask(0);
            $perm = 0777;
        } else {
            $perm = 0700;
        }

        if($dirs == "*"){
            // ensure that all directories are created
            foreach( self::$raDirectories as $k=>$v ) {
                self::EnsureDirectory($k,$silent);
            }
            self::EnsureDirectory("pending",$silent);
        }
        else if(is_array($dirs)){
            // ensure the given array of directories are created
            self:EnsureDirectory("pending");
            foreach ($dirs as $dir){
                self::EnsureDirectory($dir);
            }
        }
        else if($dirs == "pending"){
            // ensure the pending directory is created
            if(!file_exists(CATSDIR_RESOURCES."pending")) {
                $r = @mkdir(CATSDIR_RESOURCES."pending", $perm, true);
                if(!$silent){
                    echo "Pending Resources Directory ".($r ? "" : "Could Not Be")." Created<br />";
                }
            }
        }
        else {
            // ensure the specified single directory is created
            if( ($dirinfo = self::GetDirInfo($dirs)) &&
                ($dir_name = CATSDIR_RESOURCES.$dirinfo["directory"]) &&  // of course this always works but this is a nice place to put it
                !file_exists($dir_name))
            {
                $r = @mkdir($dir_name, $perm, true);
                if(!$silent){
                    echo $dirinfo["name"]." Resources Directory ".($r ? "" : "Could Not Be")." Created<br />";
                }
            }
        }
    }

    static function GetDirectories() { return( self::$raDirectories ); }
    static function GetDirInfo($dir) { return( @self::$raDirectories[$dir] ?: null ); }

    static private $raDirectories = [
        // Array of arrays containing directory information of resource folders
        // The key of the first array defines the intermal key for the directory
        // The directory value of the second array defines the path to the directory
        // ALL directories are stored in the resources folder
        // The name value of the second array is the name displayed in the select element
        // It should be a discriptive name indicating what goes in the folder
        // The extensions value of the second array is an array of all files extensions that are excepted in the directory
        // DO NOT include the dot in the file extension
        "clinic"                     => ["directory" => "clinic/",                        "name" => "Clinic Forms",                            "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "papers"                     => ["directory" => "papers/",                        "name" => "Paper Designs",                           "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "reg"                        => ["directory" => "reg/",     "color" => "#06962d", "name" => "Self Regulation",                         "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "visual"                     => ["directory" => "visual/",  "color" => "#ff0000", "name" => "Visual Motor",                            "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "other"                      => ["directory" => "other/",   "color" => "#ff8400", "name" => "Other Motor (fine, gross, oral, ocular)", "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "anxiety"                    => ['directory' => "anxiety/",                       "name" => "Anxiety",                                 "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "cog"                        => ['directory' => "cog/",     "color" => "#000000", "name" => "Cognitive",                               "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "adl"                        => ['directory' => "adl/",     "color" => "#ebcf00", "name" => "ADL's",                                   "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "assmt"                      => ['directory' => "assmt/",   "color" => "#0000ff", "name" => "Assessments",                             "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "old"                        => ['directory' => "old/",                           "name" => "Back Drawer",                             "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "reports"                    => ["directory" => "reports/",                       "name" => "Client Reports",                          "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "SOP"                        => ["directory" => "SOP/",                           "name" => "Standard Operating Procedures",           "extensions" => ["pdf"]                             ],
        "sections"                   => ["directory" => "sections/",                      "name" => "Resource Sections",                       "extensions" => ["docx"]                            ],
        "videos"                     => ["directory" => "videos/",                        "name" => "Videos",                                  "extensions" => ["mp4"]                             ]
    ];
}

function checkFileSystem(SEEDAppConsole $oApp){
    $FileSystemVersion = 2;
    $oBucket = new SEEDMetaTable_StringBucket( $oApp->kfdb );
    $currFileSystemVersion = intval($oBucket->GetStr( 'cats', 'FileSystemVersion') );
    if( $currFileSystemVersion != $FileSystemVersion ) {
        $oBucket->PutStr( 'cats', 'FileSystemVersion', $FileSystemVersion );
    }
    if ($currFileSystemVersion < 2) {
        FilingCabinet::EnsureDirectory('old');
        foreach(array('handouts/','forms/','marketing/','clinic/') as $folder){
            $directory_iterator = new DirectoryIterator(CATSDIR_RESOURCES.$folder);
            foreach ($directory_iterator as $fileinfo){
                if($fileinfo->isDot()){
                    continue;
                }
                rename(CATSDIR_RESOURCES.$folder."/".$fileinfo->getFilename(), CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('old')['directory'].$fileinfo->getFilename());
                $oApp->kfdb->Execute("UPDATE resources_files SET folder = '".addslashes(rtrim(FilingCabinet::GetDirInfo('old')['directory'],"/"))."' WHERE folder='".addslashes(rtrim($folder,"/\\"))."' AND filename='".addslashes($fileinfo->getFilename())."'");
            }
            rmdir(realpath(CATSDIR_RESOURCES.$folder));
        }
    }
}


function getExtensions(){
    $exts = array();
    foreach(FilingCabinet::GetDirectories() as $k => $v){
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
