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

    function DrawFilingCabinet()
    {
        $s = "";

        //FIXME Only Ensure directories when we need access to them
        //This causes if a folder fails to be created when downloading
        self::EnsureDirectory("*");

        // Handle cmds: download (does not return), and other cmds (return here then draw the filing cabinet)
        $this->handleCmd();

        if( ($dir = SEEDInput_Str('dir')) && ($dirbase = strtok($dir,"/")) && ($raDirInfo = self::GetDirInfo($dirbase)) ) {
            // Show the "currently-open drawer" of the filing cabinet
            $s .= "<h3>Filing Cabinet : ".$raDirInfo['name']."</h3>"
                ."<p><a href='?screen=therapist-filing-cabinet'>Back to Filing Cabinet</a></p>";
            if($dir == 'papers'){
                include(CATSLIB."papers.php");
            }
            else{
                $s .= ResourcesDownload( $this->oApp, $raDirInfo['directory'] );
            }
        } else {
            // Show the "closed drawers" of the filing cabinet
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

    private function handleCmd()
    {
        switch( ($cmd = SEEDInput_Str('cmd')) ) {
            case 'download':
                $oFCD = new FilingCabinetDownload( $this->oApp );
                $oFCD->DownloadFile();
                exit;       // download doesn't return here, but this is just a good reminder of that
            default:
                break;
        }
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
            // ensure the specified single directory is created, and its subdirectories
            if( ($dirinfo = self::GetDirInfo($dirs)) ) {
                $dir_name = CATSDIR_RESOURCES.$dirinfo["directory"];

                if( !file_exists($dir_name) ) {
                    $r = @mkdir($dir_name, $perm, true);
                    if(!$silent){
                        echo $dirinfo["name"]." Resources Directory ".($r ? "" : "Could Not Be")." Created<br />";
                    }
                }
                foreach( self::GetSubFolders($dirs) as $d ) {
                    $subdirname = $dir_name.$d;
                    if( !file_exists($subdirname) ) {
                        $r = @mkdir($subdirname, $perm, true);
                        if(!$silent){
                            echo "$subdirname directory ".($r ? "" : "Could Not Be")." Created<br />";
                        }
                    }
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

/**
 * Class Representing a resource in the database.
 * This serves as a communication layer between the database and other files.
 * The functions in this ADT are garenteed, regardles of the underlying database structure.
 * @author Eric
 *
 */
class ResourceRecord {
    
    //raParam keys
    private const ID_KEY = 'id';
    private const CREATED_KEY = 'created';
    private const STATUS_KEY = 'status';
    private const SUBDIRECTORY_KEY = 'subdir';
    private const TAGS_KEY = 'tags';
    
    private const TAG_SEPERATOR = "\t";
    
    
    //search constants
    /**
     * Used to denote that a search parameter should be excluded
     */
    public const WILDCARD = '*';
    
    private $oApp;
    
    // Database flags
    /**
     * Index of the database row this data was fetched from
     * value of 0 represents new row (not in database yet)
     * READ ONLY
     */
    private $id = 0;
    /**
     * Date the file was initially revieved
     * value of 0 represents new file (usually accompanied by and id of 0)
     * READ ONLY
     */
    private $created = 0;
    private $status = 0;
    
    // File info
    private $dir;
    private $subdir = '';
    private $file;
    private $tags = [];
    /**
     * Wether or not this record has been committed to the database.
     * Only true if data has not changed since the last store or initial fetch
     * INTERNAL USE ONLY
     */
    private $committed = false;
    
    private function __construct(SEEDAppConsole $oApp, String $dirname, String $filename, array $raParams = []){
        $this->oApp = $oApp;
        
        $this->file = $filename;
        $this->dir = $dirname;
        $this->id = @$raParams[self::ID_KEY]?:0;
        $this->created = @$raParams[self::CREATED_KEY]?:0;
        $this->status = @$raParams[self::STATUS_KEY]?:0;
        $this->subdir = @$raParams[self::SUBDIRECTORY_KEY]?:'';
        if(is_string(@$raParams[self::TAGS_KEY])){
            $this->tags = explode(self::TAG_SEPERATOR, @$raParams[self::TAGS_KEY]);
        }
        else{
            $this->tags = @$raParams[self::TAGS_KEY]?:[];
        }
    }
    
    public function addTag(String $tag){
        if(in_array($tag, $this->tags)){
            return; // The Tag already exists in the list dont add it again
        }
        $this->committed = false; //Assume the tag did not exist before
        $this->tags += [$tag];
    }
    
    public function removeTag(String $tag){
        $ra = array_diff($this->tags, [$tag]);
        if($ra != $this->tags){
            $this->committed = false;
        }
        $this->tags = $ra;
    }
    
    public function setDirectory(String $dir){
        if($dir != $this->dir){
            $this->committed = false;
        }
        $this->dir = $dir;
    }
    
    public function setSubDirectory(String $subdir){
        if($subdir != $this->subdir){
            $this->committed = false;
        }
        $this->subdir = $subdir;
    }
    
    public function setStatus(int $status){
        if($status != $this->status){
            $this->committed = false;
        }
        $this->status = $status;
    }
    
    public function StoreRecord(){
        if($this->committed){
            //The data has not changed since the last store
            return;
        }
        //TODO implement Storing Mechanism
    }
    
    public function getID():int{
        return $this->id;
    }
    
    public function getCreated(){
        return $this->created;
    }
    
    public function getStatus():int{
        return $this->status;
    }
    
    public function getTags():array{
        return $this->tags;
    }
    
    public function getFile():String{
        return $this->file;
    }
    
    public function getDirectory():String{
        return $this->dir;
    }
    
    public function getSubDirectory():String{
        return $this->subdir;
    }
    
    public function getPath():String{
        return CATSDIR_RESOURCES.$this->dir.DIRECTORY_SEPARATOR.$this->subdir.DIRECTORY_SEPARATOR.$this->file;
    }
    
    // These methods should allow calling files to get a record without needing to depend on the underlying database structure
    // i.e the sql to query the database should be provided by these methods and not passed in as a parameter.
    
    public static function GetRecordByID(SEEDAppConsole $oApp,int $id){
        $ra = $oApp->kfdb->QueryRA( "SELECT * FROM resources_files WHERE _key=".$id, KEYFRAMEDB_RESULT_ASSOC );
        if(!$ra){
            // No Record with that id exists
            return NULL;
        }
        $raParams = [];
        $raParams += [self::ID_KEY=>$ra['_key']];
        $raParams += [self::CREATED_KEY=>$ra['_created']];
        $raParams += [self::STATUS_KEY=>$ra['_status']];
        $raParams += [self::SUBDIRECTORY_KEY=>$ra['subfolder']];
        $raParams += [self::TAGS_KEY=>$ra['tags']];
        $oRR = new ResourceRecord($oApp, $ra['folder'], $ra['filename'],$raParams);
        $oRR->committed = true; // The data in this record was just pulled from the DB
        return $oRR;
        
    }
    
    public static function CreateNewRecord(SEEDAppConsole $oApp, String $dirname,String $filename):ResourceRecord{
        return new ResourceRecord($oApp, $dirname, $filename);
    }
    
}
