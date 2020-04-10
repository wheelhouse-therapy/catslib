<?php

/* FilingCabinet
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * Upload and manage files
 *
 * FilingCabinetUpload   provides the UI to upload files to a pending status
 * FilingCabinetReview   provides the UI to review uploaded files to a final status
 * FilingCabinetDownload provides the UI to download files
 * FilingCabinetTools    provides the UI to move, rename, and delete files
 */

class FilingCabinet
/******************
    Base filesystem class for the filing cabinet.
    Code for any user actions on files goes in the appropriate UI class.
 */
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

//move this to FilingCabinetDownload
    function DrawFilingCabinet()
    {
        $s = "";

        self::EnsureDirectory("*");
        if( ($dir = SEEDInput_Str('dir')) && ($raDirInfo = self::GetDirInfo($dir)) ) {
            $s .= "<h3>Filing Cabinet : ".$raDirInfo['name']."</h3>"
                ."<p><a href='?screen=therapist-filing-cabinet'>Back to Filing Cabinet</a></p>";
            if($dir == 'papers'){
                include(CATSLIB."papers.php");
            }
            else{
                $s .= ResourcesDownload( $this->oApp, $raDirInfo['directory'] );
            }
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
    static function GetSubFolders($dir){ return( @self::$raSubFolders[$dir] ?: []); }

    static function GetSupportedExtensions()
    /***************************************
        Array of all the extensions supported by the Filing Cabinet
     */
    {
        $exts = [];
        foreach(self::GetDirectories() as $ra) {
            $exts = array_merge($exts,$ra['extensions']);
        }
        return( array_unique($exts) );
    }


    private static $raDirectories = [
        // Array of arrays containing directory information of resource folders
        // The key of the first array defines the internal key for the directory
        // The directory value of the second array defines the path to the directory
        // ALL directories are stored in the resources folder
        // The name value of the second array is the name displayed in the select element
        // It should be a discriptive name indicating what goes in the folder
        // The extensions value of the second array is an array of all files extensions that are excepted in the directory
        // DO NOT include the dot in the file extension
        "clinic"   => ["directory" => "clinic/",                        "name" => "Clinic Forms",                            "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "papers"   => ["directory" => "papers/",                        "name" => "Paper Designs",                           "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "reg"      => ["directory" => "reg/",     "color" => "#06962d", "name" => "Self Regulation",                         "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "visual"   => ["directory" => "visual/",  "color" => "#ff0000", "name" => "Visual Motor",                            "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "other"    => ["directory" => "other/",   "color" => "#ff8400", "name" => "Other Motor (fine, gross, oral, ocular)", "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "anxiety"  => ['directory' => "anxiety/",                       "name" => "Anxiety",                                 "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "cog"      => ['directory' => "cog/",     "color" => "#000000", "name" => "Cognitive",                               "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "adl"      => ['directory' => "adl/",     "color" => "#ebcf00", "name" => "ADL's",                                   "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "assmt"    => ['directory' => "assmt/",   "color" => "#0000ff", "name" => "Assessments",                             "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "old"      => ['directory' => "old/",                           "name" => "Back Drawer",                             "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "reports"  => ["directory" => "reports/",                       "name" => "Client Reports",                          "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
        "SOP"      => ["directory" => "SOP/",                           "name" => "Standard Operating Procedures",           "extensions" => ["pdf"]                             ],
        "sections" => ["directory" => "sections/",                      "name" => "Resource Sections",                       "extensions" => ["docx"]                            ],
        "videos"   => ["directory" => "videos/",                        "name" => "Videos",                                  "extensions" => ["mp4"]                             ]
    ];

    private static $raSubFolders = [
        // Array of arrays containing the "subfolders" for a directory
        // The key of the first array must match the directory key above to link the "subfolders" with the directory
        // The second array defines the "subfolders" wich will be stored in the database
        'reg'     => ["Psycho Education","Strategies","Social Skills","Zones","Social Thinking"],
        'visual'  => ["Pencil Control","Cutting","Upper case","Lower case","Reversals","Print Correction"],
        'other'   => ["Hand skills","Gross Motor","Occulomotor"],
        'anxiety' => ["Dragon","Monster","Behaviour/Exposure"],
        'cog'     => ["literacy","writing","problem-solving","organization"],
        'adl'     => ["Feeding","Toiletting?","Lifeskills"],
        'assmt'   => ["MOTOR","PERCEPTION","VISUAL/SCANNING","SENSORY","FUNCTIONAL","BEHAV/COMMUNICATION/EMOTIONAL","GENERAL DEVELOPMENTAL"]
    ];
}
