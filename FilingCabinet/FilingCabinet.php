<?php

/* FilingCabinet
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * Upload and manage files
 *
 * FilingCabinet         provides the base filesystem mechanism
 * FilingCabinetUI       provides the top-level UI
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
            foreach( array_keys(self::$raDirectories) as $k ) {
                self::EnsureDirectory($k,$silent);
            }
            self::EnsureDirectory("pending",$silent);
//            foreach( self::$raDrawersVideos as $k=>$v ) {
//                self::EnsureDirectory('videos/'.$k,$silent);
//            }
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
//            if( ($bVideos = SEEDCore_StartsWith( $dirs, 'videos/')) ) {
//                $dirs = substr($dirs,strlen('videos/'));
//            }
$bVideos = false;
            $sCabinet = $bVideos ? 'videos' : 'general';
            if( ($dirinfo = self::GetDirInfo($dirs, $sCabinet)) ) {
                $dir_name = CATSDIR_RESOURCES.($bVideos ? 'videos/' : '').$dirinfo["directory"];

                if( !file_exists($dir_name) ) {
                    $r = @mkdir($dir_name, $perm, true);
                    if(!$silent){
                        echo $dirinfo["name"]." Resources Directory ".($r ? "" : "Could Not Be")." Created<br />";
                    }
                }
                foreach( self::GetSubFolders($dirs,$sCabinet) as $d ) {
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

    /**
     * Get the list of directories in the Resource Subsystem
     * @return array - containing directory information
     */
    static function GetDirectories($sCabinet = 'general')
    {
        switch($sCabinet) {
            case 'reports':
            case 'SOP':     return([$sCabinet=>self::$raDirectories[$sCabinet]]);
            case 'videos':  return(self::$raDrawersVideos);
            case 'general':
            default:        return(self::$raDirectories);
        }
    }
    /**
     * Get Directory information (eg. allowed extensions, display name, etc.) for a directory
     * @param string $dir - directory to get information of
     * @return NULL|array - array containing the info for the given directory, or Null if its not part of the Resource Subsystem
     */
    static function GetDirInfo(String $dir, $sCabinet = 'general')
    {
        return( @self::GetDirectories($sCabinet)[$dir] ?: null );
    }

    /**
     * Get Subdirectories in the given directory
     * @param string $dir - directory to get subdirs of
     * @return array - list of defined subdirectories if defined
     */
    static function GetSubFolders(String $dir, $sCabinet = 'general' )
    {
        switch($sCabinet) {
            case 'reports':
            case 'SOP':     return([]);

            case 'videos':  return(@self::$raSubfoldersVideos[$dir] ?: []);

            case 'general':
            default:        return(@self::$raSubFolders[$dir] ?: []);
        }
    }

    /**
     * Get the extensions supported by the Resource Subsystem.
     * NOTE: the extensions do not contain dots
     * @return array - array of supported extensions
     */
    static function GetSupportedExtensions( $sCabinet = '*' )
    {
        if($sCabinet == "*"){
            $exts = [];
            foreach(["general","videos","reports","SOP"] as $cabinet) {
                $exts = array_merge($exts,self::GetSupportedExtensions($cabinet));
            }
            return( array_unique($exts) );
        }
        
        $exts = [];
        foreach(self::GetFilingCabinetDirectories($sCabinet) as $ra) {
            $exts = array_merge($exts,$ra['extensions']);
        }
        return( array_unique($exts) );
    }

    /**
     * Get directories which are part of the filing cabinet
     * @return array - array containing directory information of the directories which are part of the filing cabinet
     */
    static function GetFilingCabinetDirectories( $sCabinet = 'general' )
    {
        switch($sCabinet) {
            case 'reports':
            case 'SOP':     return(array_intersect_key(self::GetDirectories(), array_flip([$sCabinet])));

            case 'videos':  return(self::$raDrawersVideos);

            case 'general':
            default:        return(array_diff_key(self::GetDirectories(), array_flip(['reports','SOP','sections','videos'])));
        }
    }

    /**
     * Get the accessor string to navigate a browser to the given file
     * @param ResourceRecord $oRR - file to navigate to
     * @return String - URI that directs to the appropriate location so the file can be accessed. Returns empty string if the file has no accessor
     */
    static function GetAccessor(ResourceRecord $oRR):String{
        $directory = $oRR->getDirectory();
        $cabinet = $oRR->getCabinet();
        if($cabinet == 'general' && array_key_exists($directory, self::GetFilingCabinetDirectories('general'))){
            // File is part of the filing cabinet, return the appropiate accessor
            if($oRR->templateFillerSupported()){
                return CATSDIR."therapist-filing-cabinet?dir=".$directory."&rr=".$oRR->getID();
            }
            return CATSDIR."therapist-filing-cabinet?dir=".$directory."&cmd=download&rr={$oRR->getID()}&resource-mode=no_replace";
        }
        elseif(array_key_exists($directory, self::GetFilingCabinetDirectories('reports'))){
            // File is a report
            if($oRR->templateFillerSupported()){
                return CATSDIR."therapist-reports?dir=".$directory."&rr=".$oRR->getID();
            }
            return CATSDIR."therapist-reports?dir=".$directory."&cmd=download&rr={$oRR->getID()}&resource-mode=no_replace";
        }
        elseif(array_key_exists($directory, self::GetFilingCabinetDirectories('SOP'))){
            return CATSDIR."therapist-viewSOPs";
        }
        elseif($cabinet == "videos" && array_key_exists($directory, self::GetFilingCabinetDirectories('videos'))){
            return CATSDIR."therapist-viewVideos?cmd=viewVideo&rr={$oRR->getID()}";
        }
        return ""; //No accessor for this file
    }

    /**
     * Returns an array of the current cabinets.
     * @return array
     */
    public static function GetCabinets():array {
        return ['general','videos','reports','SOP'];
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
        "autism"   => ["directory" => "autism/",  "color" => "#06962d", "name" => "Early Autism Intervention",               "extensions" => ["docx", "pdf", "txt", "rtf", "doc"]],
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
        'visual'  => ["Pencil Control","Cutting","Upper case","Lower case","Reversals","Print Correction","Numbers","Drawing"],
        'other'   => ["Hand skills","Gross Motor","Occulomotor"],
        'anxiety' => ["Dragon","Thought Monster Curriculum","Behaviour & Exposure"],
        'cog'     => ["literacy","writing","problem-solving","organization"],
        'adl'     => ["Feeding","Toiletting","Lifeskills"],
        'assmt'   => ["MOTOR","PERCEPTION","VISUAL & SCANNING","SENSORY","FUNCTIONAL","BEHAV & COMMUNICATION & EMOTIONAL","GENERAL DEVELOPMENTAL"],
    ];


    private static $raDrawersVideos = [
        // Drawers for the videos filing cabinet. (what we call 'directories' in the code, and 'folders' in the db).
        // On the filesystem these are directories under 'videos/'
        'onboard'     => ['directory'=>"onboard/",     'color'=>"#06962d", 'name'=>"CATS Onboarding",            'extensions'=>['mp4','webm']],
        'reg'         => ['directory'=>"reg/",         'color'=>"#06962d", 'name'=>"Self Regulation",            'extensions'=>['mp4','webm']],
        'autism'      => ['directory'=>"autism/",      'color'=>"#06962d", 'name'=>"Early Autism Intervention",  'extensions'=>['mp4','webm']],
        'visualmotor' => ['directory'=>"visualmotor/", 'color'=>"#ff0000", 'name'=>"Visual Motor",               'extensions'=>['mp4','webm']],
        'othermotor'  => ['directory'=>"othermotor/",  'color'=>"#ff8400", 'name'=>"Other Motor (fine, gross, oral, ocular)", 'extensions'=>['mp4','webm']],
        'anxiety'     => ['directory'=>"anxiety/",                         'name'=>"Anxiety",                    'extensions'=>['mp4','webm']],
        'cog'         => ['directory'=>"cog/",         'color'=>"#000000", 'name'=>"Cognitive",                  'extensions'=>['mp4','webm']],
        'adl'         => ['directory'=>"adl/",         'color'=>"#ebcf00", 'name'=>"ADLs",                       'extensions'=>['mp4','webm']],
        'teens'       => ['directory'=>"teens/",       'color'=>"#ebcf00", 'name'=>"Teens",                      'extensions'=>['mp4','webm']],
        'assmt'       => ['directory'=>"assmt/",       'color'=>"#0000ff", 'name'=>"Assessments",                'extensions'=>['mp4','webm']],
        'backdrawer'  => ['directory'=>"backdrawer/",  'color'=>"#0000ff", 'name'=>"Back Drawer",                'extensions'=>['mp4','webm']],
    ];

    private static $raSubfoldersVideos = [
        // Subfolders for the videos filing cabinet
        'reg'     => ["Psycho Education","Strategies"],
        'cog'     => ["Executive Function","Literacy","Writing"],
        'adl'     => ["Picky Eating","Fasteners"],
        'othermotor' => ["Hand Skills","Gross Motor","Oculomotor"],
    ];

    static function checkFileSystem(SEEDAppConsole $oApp)
    {
        $FileSystemVersion = 4;
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
                    //TODO Use ResourceRecord instead.
                    // This is legacy update code for updating a legacy filesystem.
                    // All (or most) of the file systems should be updated now.
                    $oApp->kfdb->Execute("UPDATE resources_files SET folder = '".addslashes(rtrim(FilingCabinet::GetDirInfo('old')['directory'],"/"))."' WHERE folder='".addslashes(rtrim($folder,"/\\"))."' AND filename='".addslashes($fileinfo->getFilename())."'");
                }
                rmdir(realpath(CATSDIR_RESOURCES.$folder));
            }
        }
        if ($currFileSystemVersion < 3){
            $oApp->kfdb->SetDebug(2);
            $cabinets = $oApp->kfdb->QueryRowsRA("SELECT cabinet FROM resources_files GROUP BY cabinet",KEYFRAMEDB_RESULT_NUM);
            $cabinets = array_column($cabinets, 0);
            foreach($cabinets as $sCabinet){
                $ras = FilingCabinet::GetFilingCabinetDirectories( $sCabinet );
                foreach( $ras as $k => $ra ) {
                    if($sCabinet == "videos"){
                        $raRR = ResourceRecord::GetRecordFromPath($oApp, $sCabinet, $k, ResourceRecord::WILDCARD, ResourceRecord::WILDCARD);
                        if( is_array($raRR) ) {
                            $i = 0;
                            foreach ($raRR as $oRR) {
                                $oApp->kfdb->Execute("UPDATE resources_files SET iOrder={$i} WHERE _key = {$oRR->getID()}");
                                $i++;
                            }
                        } else if( $raRR ) {
                            $oRR = $raRR;
                            $oApp->kfdb->Execute("UPDATE resources_files SET iOrder=0 WHERE _key = {$oRR->getID()}");
                        }
                    }
                    else{
                        $raRR = ResourceRecord::GetResources($oApp, $sCabinet, $k);
                        $i = 0;
                        foreach ($raRR as $oRR) {
                            $oApp->kfdb->Execute("UPDATE resources_files SET iOrder={$i} WHERE _key = {$oRR->getID()}");
                            $i++;
                        }
                        foreach(FilingCabinet::GetSubFolders($k) as $subfolder) {
                            if(!file_exists($ra['directory'].$subfolder)) continue;
                            $raRRSub = ResourceRecord::GetResources($oApp, $sCabinet, $dir_short,$subfolder);
                            $i = 0;
                            foreach ($raRRSub as $oRR) {
                                $oApp->kfdb->Execute("UPDATE resources_files SET iOrder={$i} WHERE _key = {$oRR->getID()}");
                                $i++;
                            }
                        }
                    }
                }
            }
            $oApp->kfdb->SetDebug(0);
        }
        if($currFileSystemVersion < 4){
            rename(CATSDIR_RESOURCES.self::$raDirectories['anxiety']['directory']."Monster", CATSDIR_RESOURCES.self::$raDirectories['anxiety']['directory']."Thought Monster Curriculum");
            echo "Renamed Monster Folder<br />";
            $oApp->kfdb->Execute("UPDATE resources_files SET subfolder='Thought Monster Curriculum' WHERE subfolder = 'Monster'");
        }
    }
}

class VideoWatchList {
    
    private const LIST_SEPARATOR = " | ";
    private const METADATA_KEY = "watchlist";
    
    private $oAccountDB;
    private $user;
    private $viewedVideos;
    
    public function __construct(SEEDAppConsole $oApp, int $user){
        $this->oAccountDB = new SEEDSessionAccountDB($oApp->kfdb, $oApp->sess->GetUID());
        $this->user = $user;
        $metaData = @$this->oAccountDB->GetUserMetadata($user)[self::METADATA_KEY]?:"";
        $this->viewedVideos = array_map('intval', explode(self::LIST_SEPARATOR, $metaData));
    }
    
    /**
     * Print all properties except the account db to clean up the screen when printing object
     */
    public function __debugInfo():array {
        return [
            'user'=>$this->user,
            'viewedVideos'=>$this->viewedVideos
        ];
    }
    
    public function hasWatched(int $rrid):bool {
        return in_array($rrid, $this->viewedVideos);
    }
    
    public function markAsWatched(int $rrid):bool {
        if($rrid <= 0){
            return false;
        }
        if($this->hasWatched($rrid)){
            return true;
        }
        array_push($this->viewedVideos,$rrid);
        
        $metaData = "";
        foreach ($this->viewedVideos as $video){
            if($metaData){
                $metaData .= self::LIST_SEPARATOR;
            }
            $metaData .= strval($video);
        }
        
        return $this->oAccountDB->SetUserMetadata($this->user, self::METADATA_KEY, $metaData);
    }
    
}

class FileDownloadsList {
    
    private const LIST_SEPARATOR = "&";
    private const COUNT_SEPARATOR = "|";
    private const METADATA_KEY = "downloads";
    
    private $oAccountDB;
    private $user;
    private $downloadedResources;
    
    public function __construct(SEEDAppConsole $oApp, int $user){
        $this->oAccountDB = new SEEDSessionAccountDB($oApp->kfdb, $oApp->sess->GetUID());
        $this->user = $user;
        $metaData = @$this->oAccountDB->GetUserMetadata($user)[self::METADATA_KEY]?:"";
        $this->downloadedResources = [];
        foreach(explode(self::LIST_SEPARATOR, $metaData) as $data){
            $ra = explode(self::COUNT_SEPARATOR, $data,2);
            $rrid = intval($ra[0]);
            $count = @$ra[1]? intval($ra[1]) : 1;
            $this->downloadedResources += [$rrid=>$count];
        }
    }
    
    /**
     * Print all properties except the account db to clean up the screen when printing object
     */
    public function __debugInfo():array {
        return [
            'user'=>$this->user,
            'downloadedResources'=>$this->downloadedResources
        ];
    }
    
    public function hasDownloaded(int $rrid):bool {
        return array_key_exists($rrid, $this->downloadedResources);
    }
    
    public function downloadCount(int $rrid):int {
        return @$this->downloadedResources[$rrid]?:0;
    }
    
    public function countDownload(int $rrid){
        if(!@$this->downloadedResources[$rrid]){
            $this->downloadedResources[$rrid] = 0;
        }
        $this->downloadedResources[$rrid]++;
        $result =  $this->store();
        if(!$result){
            // Store failed decrement the number
            $this->downloadedResources[$rrid]--;
        }
        return $result;
    }
    
    public function store(){
        $metaData = "";
        foreach ($this->downloadedResources as $rrid=>$count){
            if($metaData){
                $metaData .= self::LIST_SEPARATOR;
            }
            $metaData .= strval($rrid).self::COUNT_SEPARATOR.strval($count);
        }
        
        return $this->oAccountDB->SetUserMetadata($this->user, self::METADATA_KEY, $metaData);
    }
    
}

/**
 * Class Representing a resource in the database.
 * This serves as a communication layer between the database and other files.
 * The functions in this ADT are guaranteed, regardles of the underlying database structure.
 * @author Eric
 *
 */
class ResourceRecord {

    //raParam keys
    private const ID_KEY = 'id';
    private const CREATED_KEY = 'created';
    private const CREATED_BY_KEY = 'created_by';
    private const STATUS_KEY = 'status';
    private const CABINET_KEY = 'cabinet';
    private const SUBDIRECTORY_KEY = 'subdir';
    private const TAGS_KEY = 'tags';
    private const PREVIEW_KEY = 'preview';
    private const DESCRIPTION_KEY = 'description';
    private const NEWNESS_KEY = 'newness';
    private const ORDER_KEY = 'order';
    private const DOWNLOADS_KEY = 'downloads';

    /**
     *  Cutoff for resources to be considered "new"
     */
    private const NEWNESS_CUTOFF = 30;
    /**
     * How many "groups" of "new" resources there are, depicted by different "badges" in the filing cabinet
     */
    private const NEWNESS_GROUPS = 4;

    private const TAG_SEPERATOR = "\t";
    
    public const STATUS_NORMAL = 0;
    public const STATUS_DELETED = 1;
    public const STATUS_HIDDEN = 2;


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
     * Date the file was initially revieved or uploaded
     * value of 0 represents new file (usually accompanied by an id of 0)
     * READ ONLY
     */
    private $created = 0;
    /**
     * User id of the person who uploaded the file
     * value of 0 represents a new file (accompanied by an id of 0) or an unknown uploader
     * READ ONLY
     */
    private $created_by = 0;
    /**
     * Status of the record
     * value of 1 means the record is deleted and can be overwritten
     * value of 0 is the default value and represents a "normal" record
     * any other value may be ignored by search methods
     */
    private $status = self::STATUS_NORMAL;

    // File info
    private $cabinet;
    private $dir;
    private $subdir = '';
    private $file;
    private $tags = [];
    private $preview = "";
    private $description = "";
    private $newness = 0; // Which newness "group" this resource belongs to
    private $downloads = 0;
    /**
     * Sort order.
     */
    private $order = 0;
    
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
        $this->cabinet = @$raParams['cabinet'] ?: 'general';
        $this->id = intval(@$raParams[self::ID_KEY]?:0);
        $this->created = @$raParams[self::CREATED_KEY]?:0;
        $this->created_by = intval(isset($raParams[self::CREATED_BY_KEY])?$raParams[self::CREATED_BY_KEY]:$oApp->sess->getUID());
        $this->status = intval(@$raParams[self::STATUS_KEY]?:self::STATUS_NORMAL);
        $this->subdir = @$raParams[self::SUBDIRECTORY_KEY]?:'';
        if(is_string(@$raParams[self::TAGS_KEY])){
            $this->tags = explode(self::TAG_SEPERATOR, @$raParams[self::TAGS_KEY]);
        }
        else{
            $this->tags = @$raParams[self::TAGS_KEY]?:[];
        }
        $this->tags = array_values(array_filter($this->tags)); //Remove empty tags, saving to DB should keep them

        $this->preview = @$raParams[self::PREVIEW_KEY]?:'';
        $this->description = @$raParams[self::DESCRIPTION_KEY]?:'';
        $this->newness = intval(@$raParams[self::NEWNESS_KEY]?:0);
        $this->order = intval(@$raParams[self::ORDER_KEY]?:0);
        $this->downloads = intval(@$raParams[self::DOWNLOADS_KEY]?:0);
    }

    /**
     * Print all properties except oApp to clean up the screen when printing object
     */
    public function __debugInfo():array {
        return [
            'id' => $this->id,
            'created' => $this->created,
            'status' => $this->status,
            'cabinet' => $this->cabinet,
            'dir' => $this->dir,
            'subdir' => $this->subdir,
            'file' => $this->file,
            'tags' => $this->tags,
            'committed' => $this->committed,
            'preview' => $this->preview,
            'created_by' => $this->created_by,
            'description' => $this->description,
            'newness' => $this->newness,
            'order' => $this->order,
            'downloads' => $this->downloads
        ];
    }

    /**
     * Add a tag to the record
     * NOTE: This DOES NOT STORE THE TAG
     * @param String $tag - tag to add
     * @return bool - true if the tag was added, false otherwise
     */
    public function addTag(String $tag):bool{
        if(in_array($tag, $this->tags)){
            return false; // The Tag already exists in the list dont add it again
        }
        $this->committed = false; //Assume the tag did not exist before
        array_push($this->tags,$tag);
        return true;
    }

    /**
     * Attempt to remove a tag from the record
     * NOTE: This DOES NOT REMOVE THE TAG FROM THE DATABASE
     * @param String $tag - tag to attempt to remove
     * @return bool - true if tag no longer exists in record, false otherwise
     */
    public function removeTag(String $tag):bool{
        $ra = array_diff($this->tags, [$tag]);
        if($ra != $this->tags){
            $this->committed = false;
        }
        $this->tags = $ra;
        return !$this->committed;
    }

    /**
     * Set the cabinet of the record.
     * NOTE: This DOES NOT STORE THE CHANGE
     * @param String $cabinet - cabinet to set
     * @return bool - true if the cabinet has changed, false otherwise
     */
    public function setCabinet(String $cabinet):bool{
        if($cabinet != $this->cabinet){
            $this->committed = false;
        }
        $this->cabinet = $cabinet;
        return !$this->committed;
    }

    /**
     * Set the directory of the record.
     * NOTE: This DOES NOT STORE THE CHANGE
     * NOTE 2: This DOES NOT MOVE THE FILE
     * @param String $dir - dir to set
     * @return bool - true if the directory has changed, false otherwise
     */
    public function setDirectory(String $dir):bool{
        $dir = trim($dir,'/\\');
        if($dir != $this->dir){
            $this->committed = false;
            if($this->dir == 'pending'){
                // Update the created column to reflect the review time.
                // Only do this if the file is being moved from pending to another folder (being reviewed)
                $this->created = 'NOW()';
            }
        }
        $this->dir = $dir;
        return !$this->committed;
    }

    /**
     * Set the sub-directory of the record.
     * NOTE: This DOES NOT STORE THE CHANGE
     * NOTE 2: This DOES NOT MOVE THE FILE
     * @param String $subdir - sub-directory to set
     * @return bool - true if the subdirectory has changed, false otherwise
     */
    public function setSubDirectory(String $subdir):bool{
        $subdir = trim($subdir,'/\\');
        if($subdir != $this->subdir){
            $this->committed = false;
        }
        $this->subdir = $subdir;
        return !$this->committed;
    }

    /**
     * Set the file of the record.
     * NOTE: This DOES NOT STORE THE CHANGE
     * NOTE 2: This DOES NOT RENAME THE FILE
     * @param String $file - file to set
     * @return bool - true if the file has changed, false otherwise
     */
    public function setFile(String $file):bool{
        if($file != $this->file){
            $this->committed = false;
        }
        $this->file = $file;
        return !$this->committed;
    }

    /**
     * Set the status of the record
     * 0 for normal.
     * 1 for deleted.
     * 2 for hidden.
     * NOTE: This DOES NOT STORE THE CHANGE
     * NOTE 2: Record Searches MAY IGNORE records which have a status other than 0.
     * NOTE 3: Create Methods MAY OVERWRITE records which have a status of 1.
     * @param int $status - status to set
     * @return bool - true if status has changed, false otherwise
     */
    public function setStatus(int $status):bool{
        if($status != $this->status){
            $this->committed = false;
        }
        $this->status = $status;
        return !$this->committed;
    }

    /**
     * Set the preview of the resource
     * NOTE: This DOES NOT STORE THE CHANGE
     * NOTE 2: The image data SHOULD NOT be base 64 encoded
     * NOTE 3: Slashes are added automatically when stored and SHOULD NOT be added by caller.
     * @param String $image - Image data to store as preview
     * @return bool - true if preview has changed, false otherwise
     */
    public function setPreview(String $image):bool{
        if($image != $this->preview){
            $this->committed = false;
        }
        $this->preview = $image;
        return !$this->committed;
    }

    /**
     * Set the description of the resource
     * NOTE: This DOES NOT STORE THE CHANGE
     * NOTE 2: Slashes are added automatically when stored and SHOULD NOT be added by caller.
     * @param String $description - Description to store
     * @return bool - true if description has changed, false otherwise
     */
    public function setDescription(String $description):bool{
        if($description != $this->description){
            $this->committed = false;
        }
        $this->description = $description;
        return !$this->committed;
    }

    /**
     * Move the resource toward the top left of the filing cabinet
     * Raises a warning if the number of steps is negative
     * NOTE: This DOES STORE THE CHANGE
     * @param int $steps - Number of steps to move left. Default: 1
     * @return bool - true if the position has changed, false otherwise
     */
    public function moveLeft(int $steps=1):bool{
        if($this->id == 0){
            // New Record
            trigger_error("Trying to move new record",E_USER_ERROR);
            return false;
        }
        if($steps < 0){
            // Negative Steps
            trigger_error("Trying to move left a negative number of steps",E_USER_WARNING);
            return false;
        }
        if($steps == 0){
            // Not Moving
            return false;
        }
        $dirname = trim($this->dir,'/\\');
        $subdir = trim($this->subdir,'/\\');
        $dbCabinet = addslashes($this->cabinet);
        $dbFolder = addslashes($dirname);
        $dbSubFolder = addslashes($subdir);
        $cond = "cabinet='$dbCabinet' AND folder='$dbFolder' AND subfolder='$dbSubFolder' AND _status != ".self::STATUS_DELETED;
        $raRows = $this->oApp->kfdb->QueryRowsRA("SELECT _key FROM resources_files WHERE {$cond} ORDER BY iOrder",KEYFRAMEDB_RESULT_ASSOC);
        $raRecords = array_column($raRows, "_key");
        $position = array_search($this->id, $raRecords);
        $i = 0;
        while($this->order > 0 && $i < $steps){
            $this->order -= 1;
            $this->oApp->kfdb->Execute("UPDATE resources_files SET iOrder={$this->order} WHERE _key = {$this->id}");
            $this->oApp->kfdb->Execute("UPDATE resources_files SET iOrder=".($this->order+1)." WHERE _key = ".$raRecords[$position-1]);
            $i++;
            $position--;
        }
        return true;
    }

    /**
     * Move the resource toward the bottom right of the filing cabinet
     * Raises a warning if number of steps is negative
     * NOTE: This DOES STORE THE CHANGE
     * @param int $steps - Number of steps to move right. Default: 1
     * @return bool - true if the position has changed, false otherwise
     */
    public function moveRight(int $steps=1):bool{
        if($this->id == 0){
            // New Record
            trigger_error("Trying to move new record",E_USER_ERROR);
            return false;
        }
        if($steps < 0){
            // Negative Steps
            trigger_error("Trying to move right a negative number of steps",E_USER_WARNING);
            return false;
        }
        if($steps == 0){
            // Not Moving
            return false;
        }
        $dirname = trim($this->dir,'/\\');
        $subdir = trim($this->subdir,'/\\');
        $dbCabinet = addslashes($this->cabinet);
        $dbFolder = addslashes($dirname);
        $dbSubFolder = addslashes($subdir);
        $cond = "cabinet='$dbCabinet' AND folder='$dbFolder' AND subfolder='$dbSubFolder' AND _status != ".self::STATUS_DELETED;
        $raRows = $this->oApp->kfdb->QueryRowsRA("SELECT _key FROM resources_files WHERE {$cond} ORDER BY iOrder",KEYFRAMEDB_RESULT_ASSOC);
        $raRecords = array_column($raRows, "_key");
        $position = array_search($this->id, $raRecords);
        if($position===false){
            // Record from another location
            return false;
        }
        $i = 0;
        while($this->order < count($raRecords)-1 && $i < $steps){
            $this->order += 1;
            $this->oApp->kfdb->Execute("UPDATE resources_files SET iOrder={$this->order} WHERE _key = {$this->id}");
            $this->oApp->kfdb->Execute("UPDATE resources_files SET iOrder=".($this->order-1)." WHERE _key = ".$raRecords[$position+1]);
            $i++;
            $position++;
        }
        return true;
    }
    
    /**
     * Move the resource to the bottom right of the filing cabinet
     * The change IS NOT STORED for NEW records but for EXISTING records it IS STORED
     * @return bool - true if the position has changed, false otherwise
     */
    public function moveToEnd(){
        $dirname = trim($this->dir,'/\\');
        $subdir = trim($this->subdir,'/\\');
        $dbCabinet = addslashes($this->cabinet);
        $dbFolder = addslashes($dirname);
        $dbSubFolder = addslashes($subdir);
        $cond = "cabinet='$dbCabinet' AND folder='$dbFolder' AND subfolder='$dbSubFolder' AND _status != ".self::STATUS_DELETED;
        $raRows = $this->oApp->kfdb->QueryRowsRA("SELECT _key FROM resources_files WHERE {$cond} ORDER BY iOrder",KEYFRAMEDB_RESULT_ASSOC);
        $raRecords = array_column($raRows, "_key");
        if($this->id == 0){
            $this->order = count($raRecords);
        }
        else{
            if(!$this->moveRight(count($raRecords))){
                $this->order = count($raRecords);
            }
        }
    }
    
    /**
     * Renames a file represented by a ResourceRecord, updating the ResourceRecord to match the new filename.
     * Always keeps a file in the same directory, can't be used to move resources to another directory.
     * Returns true, but does nothing if the given filename is identical to the old filename.
     * NOTE: This does NOT commit changes to the database. Use StoreRecord to commit changes.
     * @return bool - true on success, false on failure.
     */
    public function rename(String $newName) : bool {
        if ($newName == $this->file) {
            return True; // succeed, but do nothing if the name is the same
        }
        if (rename($this->getPath(), $this->getPathToDir().$newName)) {
            // if renaming is successful, update the record
            $this->setFile($newName); // this takes care of the "committed" flag
            return True;
        }
        // renaming failed
        return False;
    }

    /**
     * Commit any record changes to the database, and update the id if needed.
     * NOTE: To prevent unnessiary db commits, the data is only written to the db if it has been changed by a setter.
     * NOTE 2: Record is moved to the end if its location has changed
     * @return bool - true if data successfully committed, false otherwise
     */
    public function StoreRecord():bool{
        if($this->committed){
            //The data has not changed since the last store
            return false;
        }
        $dbCabinet = addslashes($this->cabinet);
        $dbFolder = addslashes($this->dir);
        $dbSubFolder = addslashes($this->subdir);
        $dbFilename = addslashes($this->file);
        $dbPreview = $this->oApp->kfdb->EscapeString($this->preview);   // better to use the mysqli function to escape binary data
        $dbDescription = addslashes($this->description);
        $uid = $this->oApp->sess->getUID();
        if($this->id == 0){
            //Check if db already contains a record, update key if it does, to prevent duplicate records for the SAME file
            $cond = "_status={$this->status} AND cabinet='{$dbCabinet}' AND subfolder='{$dbSubFolder}' AND folder='{$dbFolder}' AND filename='{$dbFilename}'";
            $this->id = @$this->oApp->kfdb->Query1( "SELECT _key FROM resources_files WHERE $cond" )?:0;
        }
        if($this->id == 0){
            // Could not find an existing record, overwrite a deleted record
            $this->id = @$this->oApp->kfdb->Query1( "SELECT _key FROM resources_files WHERE _status=".self::STATUS_DELETED )?:0;
            if($this->id){
                $this->status = 0;
                $this->created = 'NOW()';
                $this->oApp->kfdb->Execute("UPDATE resources_files SET _created_by={$this->created_by} WHERE _key = {$this->id}");
            }
        }
        $tags = '';
        foreach ($this->tags as $tag){
            $dbtag = addslashes($tag);
            $tags .= self::TAG_SEPERATOR.$dbtag.self::TAG_SEPERATOR;
        }
        $ra = $this->oApp->kfdb->QueryRA("SELECT cabinet,folder,subfolder FROM resources_files WHERE _key=".$this->id,KEYFRAMEDB_RESULT_ASSOC);
        if($ra == NULL){
            $ra = ["cabinet"=>"","folder"=>"","subdir"=>""];
        }
        if($ra['cabinet'] != $this->cabinet || $ra['folder'] != $this->dir || $ra['subfolder'] != $this->subdir){
            // Location has changed, put at end of document
            $this->moveToEnd();
        }
        
        if($this->id){
            if($this->created != "NOW()"){
                $this->created = "'$this->created'";
            }
            $this->committed = $this->oApp->kfdb->Execute("UPDATE resources_files SET _created={$this->created},_updated=NOW(),_updated_by=$uid,_status={$this->status},cabinet='$dbCabinet',folder='$dbFolder',filename='$dbFilename',tags='$tags',subfolder='$dbSubFolder',preview='$dbPreview',description='$dbDescription',iOrder={$this->order},downloads={$this->downloads} WHERE _key = {$this->id}");
        }
        else{
            if(($this->id = $this->oApp->kfdb->InsertAutoInc("INSERT INTO resources_files (_created, _created_by, _updated, _updated_by, _status, cabinet, folder, filename, tags, subfolder,preview,description,iOrder,downloads) VALUES (NOW(),{$this->created_by},NOW(),$uid,{$this->status},'$dbCabinet','$dbFolder','$dbFilename','$tags','$dbSubFolder','$dbPreview','$dbDescription',{$this->order},{$this->downloads})"))){
                $this->committed = true;
            }
        }
        return $this->committed;
    }

    /**
     * Delete the Record from the database.
     * NOTE: the record is not actually deleted but the status has been set to 1
     * NOTE2: once deleted the record is only retrieveable by its ID. As search methods ignore records with non-zero statuses
     * NOTE3: The record can be recovered by setting the status to 0.
     * NOTE4: New records will overwrite deleted records if availible instead of creating new records.
     * NOTE5: The record will be moved to the end
     * @return bool - True if record was successfully deleted. Note returning false does not mean the record was not deleted.
     * Check the status of the record to ensure it was deleted.
     */
    public function DeleteRecord(){
        $this->moveToEnd();
        $this->order = -1;
        $result1 = $this->setStatus(self::STATUS_DELETED);
        $result2 = $this->StoreRecord();
        return $result1 && $result2;
    }

    public function containsTag(String $tag){
        foreach($this->tags as $v){
            if(strrpos($v, $tag) !== false){
                return true;
            }
        }
        return false;
    }
    
    public function getID()           : int    { return $this->id; }
    public function getCreated()               { return $this->created; }
    public function getStatus()       : int    { return $this->status; }
    public function getTags()         : array  { return $this->tags; }
    public function getFile()         : String { return $this->file; }
    public function getCabinet()      : String { return $this->cabinet; }
    public function getDirectory()    : String { return $this->dir; }
    public function getSubDirectory() : String { return $this->subdir; }
    public function getExtension()    : String { return (($p = pathinfo($this->file)) ? $p['extension'] : '' ); }
    public function getDescription()  : String { return $this->description; }
    public function getPath() : String
    {
        /* (for videos) Files are stored in a directory named after their cabinet, as "id file.ext".
         * 1) id differentiates all files because it's unique, so two files with the same filename (in different folders) are okay.
         * 2) file.ext is redundant because id uniquely identifies the file, but it makes the directory human-readable for us
         * 3) folder and subfolder are only used in the UI to group files meaningfully, not used for storage location
         */
        return $this->cabinet == 'videos'
                ? (CATSDIR_RESOURCES."videos/{$this->id} {$this->file}")
                : (CATSDIR_RESOURCES.$this->dir.DIRECTORY_SEPARATOR.($this->subdir ? ($this->subdir.DIRECTORY_SEPARATOR) : "").$this->file);
    }
    
    /**
     * Gets the file path to the directory the resource is stored in.
     * Similar to getPath(), but does not include the filename.
     * @return String - path to the directory containing the resource.
     */
    public function getPathToDir() : String
    {
        return $this->cabinet == 'videos'
            ? (CATSDIR_RESOURCES."videos/")
            : (CATSDIR_RESOURCES.$this->dir.DIRECTORY_SEPARATOR.($this->subdir ? ($this->subdir.DIRECTORY_SEPARATOR) : ""));
    }

    /**
     * Get the stored preview of the resource.
     * @param bool $encode - Wether or not to base 64 encode the image data for output. Default true.
     * @return String containing the encoded or raw image data. Or empty string if there is no image stored
     */
    public function getPreview(bool $encode = true):String{
        if($encode){
            return base64_encode($this->preview);
        }
        return $this->preview;
    }

    /**
     * Get the user who created the record.
     * NOTE: this user is also considered to be the uploader
     * NOTE2: a value of 0 represents an unknown uploader. Requesting the userdata of a  0 value yields a name of "Unknown"
     * NOTE3: changes the current users name to "Self"
     * NOTE4: NOTE3 only applies when $userdata is true.
     * @param bool $userdata - wether to return the data associated with the uid or the uid itself
     * @return array|int - array of user data if $userdata is true, or the uid of the user who uploaded the indexed resource or 0 if the uploader is unknown
     */
    public function getUploader(bool $userdata = false){
        if($userdata){
            $acctDB = new SEEDSessionAccountDBRead($this->oApp->kfdb);
            $result = $acctDB->GetUserInfo($this->created_by,false,true);
            if(!$result[0]){
                $result[1] = ['realname' => 'Unknown'];
            }
            else if($result[0] == $this->oApp->sess->getUID()){
                $result[1]['realname'] = 'Self';
            }
            $result = array_merge($result[1],['metadata'=>$result[2]]);
            return $result;
        }
        return $this->created_by;
    }

    public function getOrder():int{
        return $this->order;
    }

    public function getDownloads():int{
        return $this->downloads;
    }
    
    public function getNewness():int{
        return $this->newness;
    }

    /**
     * Get if the resource is "new"
     * @return bool true if it's a "new" resource, false otherwise
     */
    public function isNewResource():bool{
        return $this->newness >= 0;
    }
    
    /**
     * Get if this resource is supported by the template filler subsystem.
     * NOTE: Only docx files are supported at this time.
     * NOTE2: if this method returns true it is safe to pass the file to the template filler subsystem
     * @return bool - true if its supported false otherwise
     */
    public function templateFillerSupported():bool{
        return pathinfo($this->file,PATHINFO_EXTENSION) == "docx";
    }

    /**
     * Get if this resource is a video and can be used in the video tag.
     * NOTE: Only mp4 files are considered valid videos.
     * @return bool - true if its a video false otherwise
     */
    public function isVideo():bool{
        return pathinfo($this->file,PATHINFO_EXTENSION) == "mp4";
    }
    
    /**
     * Get if this resource is hidden from some users.
     * NOTE: Only records with a status of 2 are considered hidden at this time.
     * @return bool - true if the resource is hidden from users, false otherwise
     */
    public function isHidden():bool{
        return $this->status == self::STATUS_HIDDEN;
    }
    
    /**
     * Count a download of the file
     * @return bool - true if the record was saved false otherwise
     */
    public function countDownload():bool{
        $this->downloads++;
        $this->committed = false;
        $result = $this->StoreRecord();
        if(!$result){
            // Saving failed, revert the downloads number
            $this->downloads--;
        }
        return $result;
    }
    
    /**
     * Merge one record into this one.
     * The merged record will be deleted
     * NOTE: The Record IS SAVED TO THE DATABASE
     * @param ResourceRecord $oRR - Record to merge into
     * @return bool - true if saving and deleting was sucessful
     */
    public function merge(ResourceRecord $oRR):bool{
        if($oRR->description){
            $this->description = $oRR->description;
        }
        if($oRR->containsTag("Created By: ")){
            foreach($this->getTags() as $tag){
                if(strrpos($tag, "Created By: ") !== false){
                    $this->removeTag($tag);
                }
            }
        }
        foreach($oRR->getTags() as $tag){
            $this->addTag($tag);
        }
        if($oRR->created > $this->created){
            $this->created = $oRR->created;
            $this->created_by = $oRR->created_by;
            $this->committed = false;
        }
        $r1 = $oRR->DeleteRecord();
        $r2 = $this->StoreRecord();
        var_dump($r1,$r2);
        return $r1 && $r2;
    }
    
    /**
     * Join a condition to the end of a condition.
     * Will add AND/OR between the conditons if nessesary
     * @param String $cond - condition to append to
     * @param String $add - condition to append
     * @param bool $disJuctive - weather to use or instead of and when joining conditions
     * @return String - joined Conditions
     */
    private static function joinCondition(String $cond,String $add,bool $disJuctive = false):String{
        $addedBracket = false;
        if($cond){
            if($disJuctive){
                // Try to find the last condition joined by AND
                $lastCond = strripos($cond, "AND ");
                if($lastCond === false){
                    // Try to find the last OR
                    $lastCond = strripos($cond, "OR ");
                    if($lastCond !== false){
                        $cond = substr($cond, 0,$lastCond)."OR (".substr($cond, $lastCond+3);
                        $addedBracket = true;
                    }
                }
                else{
                    $cond = substr($cond, 0,$lastCond)."AND (".substr($cond, $lastCond+4);
                    $addedBracket = true;
                }$cond .= " OR ";
                
            }
            else{
                $cond .= " AND ";
            }
        }
        $cond .= $add;
        if($addedBracket){
            $cond .= ")";
        }
        return $cond;
    }

    /**
     * Get Resource Records from a db query
     * NOTE: Result type depends on number of records retrieved.
     * NULL is returned if no records are retrieved.
     * A ResourceRecord Object is returned if one record is retrieved.
     * An array of ResourceRecord Objects is returned if more than one record is retrieved.
     * @param SEEDAppConsole $oApp - object with access to db to query
     * @param String $query - query to run on db
     * @return array|NULL|ResourceRecord - Resource Records containing data or Null of there are no results
     */
    private static function getFromQuery(SEEDAppConsole $oApp, String $query){
        $orderBy = "";
        if(stripos($query, " ORDER BY")){
            $orderBy = substr($query, strripos($query, " ORDER BY"));
            $query = substr($query, 0,strpos($query,$orderBy));
        }
        if(strripos($query, "_status") === FALSE){
            $query = self::joinCondition($query, "_status=".self::STATUS_NORMAL);
            $raMeta = (new SEEDSessionAccountDBRead($oApp->kfdb))->GetUserMetadata($oApp->sess->GetUID());
            if((array_key_exists(AccountType::KEY, $raMeta)?$raMeta[AccountType::KEY]:AccountType::NORMAL) !== AccountType::STUDENT){
                $query = self::joinCondition($query, "_status=".self::STATUS_HIDDEN,true);
            }
        }
        $query .= $orderBy;
        $ra = $oApp->kfdb->QueryRowsRA1($query,KEYFRAMEDB_RESULT_NUM);
        $oRR = NULL;
        if(count($ra) == 1){
            $oRR = self::GetRecordByID($oApp, intval($ra[0]));
        }
        else if(count($ra) > 1){
            $oRR = array();
            foreach($ra as $id){
                $oRR[] = self::GetRecordByID($oApp, intval($id));
            }
        }
        return $oRR;
    }

    // These methods should allow calling files to get a record without needing to depend on the underlying database structure
    // i.e the sql to query the database should be provided by these methods and not passed in as a parameter.

    /**
     * Get Resource Record from Id (aka db _key)
     * @param SEEDAppConsole $oApp - object with access to db to get from
     * @param int $id - id of the record to get
     * @return NULL|ResourceRecord - Resource Record containing the data from the db or null if the key is invalid
     */
    public static function GetRecordByID(SEEDAppConsole $oApp,int $id){
        $ra = $oApp->kfdb->QueryRA( "SELECT *,".self::newnessFunction()." as newness FROM resources_files WHERE _key=".$id, KEYFRAMEDB_RESULT_ASSOC );
        if(!$ra){
            // No Record with that id exists
            return NULL;
        }
        $raParams = [];
        $raParams += [self::ID_KEY=>$ra['_key']];
        $raParams += [self::CREATED_KEY=>$ra['_created']];
        $raParams += [self::STATUS_KEY=>$ra['_status']];
        $raParams += [self::CABINET_KEY=>$ra['cabinet']];
        $raParams += [self::SUBDIRECTORY_KEY=>$ra['subfolder']];
        $raParams += [self::TAGS_KEY=>$ra['tags']];
        $raParams += [self::PREVIEW_KEY=>$ra['preview']];
        $raParams += [self::CREATED_BY_KEY=>$ra['_created_by']];
        $raParams += [self::DESCRIPTION_KEY=>$ra['description']];
        $raParams += [self::NEWNESS_KEY=>$ra['newness']];
        $raParams += [self::ORDER_KEY=>$ra['iOrder']];
        $raParams += [self::DOWNLOADS_KEY=>$ra['downloads']];
        $oRR = new ResourceRecord($oApp, $ra['folder'], $ra['filename'],$raParams);
        $oRR->committed = true; // The data in this record was just pulled from the DB
        return $oRR;
    }

    public static function CreateNewRecord(SEEDAppConsole $oApp, String $dirname,String $filename,int $created_by=-1, String $cabinet="general"):ResourceRecord{
        if($created_by == -1){
            $created_by = $oApp->sess->getUID();
        }
        return new ResourceRecord($oApp, $dirname, $filename,[self::CABINET_KEY=>$cabinet,self::CREATED_BY_KEY=>$created_by]);
    }

    public static function CreateFromRealPath(SEEDAppConsole $oApp, String $realpath, String $cabinet="general",int $created_by=-1){
        $resourcesPath = str_replace(['\\'], DIRECTORY_SEPARATOR, realpath(CATSDIR_RESOURCES));
        $realpath = str_replace(['\\'], DIRECTORY_SEPARATOR, $realpath);
        $realpath = str_replace($resourcesPath.DIRECTORY_SEPARATOR, "", $realpath); // Remove the start of the resources path
        if($realpath == ""){
            //Cant create a record from an empty path
            return NULL;
        }
        $parts = explode(DIRECTORY_SEPARATOR, $realpath);
        $dir = "";
        $subdir = "";
        $filename = "";
        switch(count($parts)){
            case 3:
                $filename = $parts[2];
                $subdir = $parts[1];
                $dir = $parts[0];
                break;
            case 2:
                $filename = $parts[1];
                $dir = $parts[0];
            case 1:
                $dir = $parts[0];
                break;
            default:
                //Should never reach here
                return NULL;
                break;
        }
        $oRR =  self::CreateNewRecord($oApp, $dir, $filename,$created_by, $cabinet);
        $oRR->subdir = $subdir;
        return $oRR;
    }

    public static function GetRecordFromPath(SEEDAppConsole $oApp, String $cabinet, String $dirname,String $filename, String $subdir = self::WILDCARD){
        $cond = "";
        $dirname = trim($dirname,'/\\');
        $subdir = trim($subdir,'/\\');
        if($cabinet != self::WILDCARD){
            $dbCabinet = addslashes($cabinet);
            $cond = self::joinCondition($cond,"cabinet='$dbCabinet'");
        }
        if($dirname != self::WILDCARD){
            $dbFolder = addslashes($dirname);
            $cond = self::joinCondition($cond,"folder='$dbFolder'");
        }
        if($filename != self::WILDCARD){
            $dbFilename = addslashes($filename);
            $cond = self::joinCondition($cond, "filename='$dbFilename'");
        }
        if($subdir != self::WILDCARD){
            $dbSubFolder = addslashes($subdir);
            $cond = self::joinCondition($cond,"subfolder='$dbSubFolder'");
        }
        $query = "SELECT _key FROM resources_files";
        if($cond){
            $query .= " WHERE $cond ORDER BY cabinet,folder,subfolder";
        }
        return self::getFromQuery($oApp,$query);
    }

    public static function GetRecordFromRealPath(SEEDAppConsole $oApp, String $cabinet, String $realpath){
        $resourcesPath = str_replace(['\\'], DIRECTORY_SEPARATOR, realpath(CATSDIR_RESOURCES));
        $realpath = str_replace(['\\'], DIRECTORY_SEPARATOR, $realpath);
        $realpath = str_replace($resourcesPath.DIRECTORY_SEPARATOR, "", $realpath); // Remove the start of the resources path
        if($realpath == ""){
            //Cant fetch a record of an empty path
            return NULL;
        }
        $parts = explode(DIRECTORY_SEPARATOR, $realpath);
        $dir = self::WILDCARD;
        $subdir = self::WILDCARD;
        $filename = self::WILDCARD;
        switch(count($parts)){
            case 3:
                $filename = $parts[2];
                $subdir = $parts[1];
                $dir = $parts[0];
                break;
            case 2:
                $filename = $parts[1];
                $dir = $parts[0];
            case 1:
                $dir = $parts[0];
                break;
            default:
                //Should never reach here
                return NULL;
                break;
        }
        return self::GetRecordFromPath($oApp, $cabinet, $dir, $filename,$subdir);
    }

    public static function GetRecordFromGlobalSearch(SEEDAppConsole $oApp,String $search){
        $dbSearch = addslashes($search);
        $query = "SELECT _key FROM resources_files WHERE filename LIKE '%$dbSearch%'";
        $query = self::joinCondition($query, "tags LIKE '%$dbSearch%'",true);
        return self::getFromQuery($oApp, $query);
    }

    /**
     * Get All records which do not have a preview stored.
     * Useful for generating previews.
     * NOTE: Result type depends on number of records retrieved.
     * NULL is returned if no records are retrieved.
     * A ResourceRecord Object is returned if one record is retrieved.
     * An array of ResourceRecord Objects is returned if more than one record is retrieved.
     * @param SEEDAppConsole $oApp - Connection to DB to fetch from
     * @return array|NULL|ResourceRecord - Resource Records containing data or Null of there are no results
     */
    public static function GetRecordsWithoutPreview(SEEDAppConsole $oApp):array{
        $query = "SELECT _key FROM resources_files WHERE preview = ''";
        $raRec = self::getFromQuery($oApp, $query);

        // always return an array to keep it simple
        if( !$raRec ) {
            $raRec = array();
        } else if( !is_array($raRec) ) {
            $raRec = [$raRec];
        }
        return( $raRec );
    }

    public static function GetResources(SeedAppConsole $oApp,String $cabinet,String $dirname,String $subdir=""):array{
        $cond = "";
        $dirname = trim($dirname,'/\\');
        $subdir = trim($subdir,'/\\');
        $dbCabinet = addslashes($cabinet);
        $cond = self::joinCondition($cond,"cabinet='$dbCabinet'");
        if($dirname != self::WILDCARD){
            $dbFolder = addslashes($dirname);
            $cond = self::joinCondition($cond,"folder='$dbFolder'");
        }
        if($subdir != self::WILDCARD){
            $dbSubFolder = addslashes($subdir);
            $cond = self::joinCondition($cond,"subfolder='$dbSubFolder'");
        }
        $query = "SELECT _key FROM resources_files WHERE $cond ORDER BY iOrder";
        $raRec = self::getFromQuery($oApp, $query);
        // always return an array to keep it simple
        if( !$raRec ) {
            $raRec = array();
        } else if( !is_array($raRec) ) {
            $raRec = [$raRec];
        }
        return( $raRec );
    }

    public static function GetResourcesByNewness(SEEDAppConsole $oApp,int $newness = self::NEWNESS_GROUPS-1):array{
        if($newness >= self::NEWNESS_GROUPS){
            $newness = self::NEWNESS_GROUPS-1;
        }
        $query = "SELECT _key FROM resources_files WHERE ".self::newnessFunction()."= $newness ORDER BY iOrder";
        $raRec = self::getFromQuery($oApp, $query);
        // always return an array to keep it simple
        if( !$raRec ) {
            $raRec = array();
        } else if( !is_array($raRec) ) {
            $raRec = [$raRec];
        }
        return( $raRec );
    }

    /**
     * Get the SQL of the function used to calculate newness
     * @return String
     */
    private static function newnessFunction():String{
        return "-FLOOR(-(".self::NEWNESS_CUTOFF."-DATEDIFF(NOW(),_created))/".(floor(self::NEWNESS_CUTOFF/self::NEWNESS_GROUPS)).")-1";
    }

    function CreateThumbnail(): bool
    /*************************
         Create or re-create a thumbnail image for this resource
     */
    {
        $ok = false;

        $fnameThumb = "";

        $srcfname = realpath($this->getPath());

        switch( strtolower(pathinfo($srcfname,PATHINFO_EXTENSION)) ) {
            case 'pdf':
                $fnameThumb = $this->createThumbFromPdf( $srcfname );
                break;

            case 'docx':
            case 'doc':
                $fnameThumb = $this->createThumbFromDocx( $srcfname );
                break;

            case 'mp4':
            // and every other video format
                $fnameThumb = $this->createThumbFromVideo( $srcfname );
                break;
        }

        if( $fnameThumb ) {
            if( ($img = file_get_contents( $fnameThumb )) ) {
                $this->setPreview( $img );
                $ok = $this->StoreRecord();
            }
            //rename($fnameThumb,"/home/bob/catsstuff/a.jpg");
            unlink($fnameThumb);
        }

        return( $ok );
    }

    private function createThumbFromPdf( $fnameSrc )
    {
        $fnameThumb = tempnam("", "thumb_");

        $raDummy = [];
        $iRet = 0;
        // srcfname[0] means convert the first page
        $sExec = "convert \"{$fnameSrc}[0]\" -background white -alpha remove -resize 200x200\\> \"JPEG:$fnameThumb\"";
        exec( $sExec, $raDummy, $iRet );
        if( CATS_SYSADMIN ) {
            var_dump($sExec,$iRet);
        }

        return( $fnameThumb );
    }

    private function createThumbFromDocx( $fnameSrc )
    {
        // use PHPWord and dompdf to create a tmp pdf, then convert that to a thumbnail
        $fnameTmpPdf = tempnam("", "thumb_");

        // dompdf is PHPWord's preferred pdf writer, but it supports others too
        $domPdfPath = realpath(SEEDROOT.'vendor/dompdf/dompdf');
        \PhpOffice\PhpWord\Settings::setPdfRendererPath($domPdfPath);
        \PhpOffice\PhpWord\Settings::setPdfRendererName('DomPDF');

        try {
            // PHPWord tends to die with fatal errors if it has trouble understanding something in a docx/doc file
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($fnameSrc);
            $xmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord , 'PDF');
            $xmlWriter->save($fnameTmpPdf);
            //rename($fnameThumb,"/home/bob/catsstuff/a.pdf");
        } catch (Exception $e) {
            if( CATS_DEBUG ) {
                var_dump( 'Caught exception: '.$e->getMessage()." for $fnameSrc\n" );
            }
            return( "" );
        }

        $fnameThumb = $this->createThumbFromPdf( $fnameTmpPdf );

        return( $fnameThumb );
    }

    private function createThumbFromVideo( $fnameSrc )
    {
        $fnameThumb = tempnam("", "thumb_").".jpg";

        $raDummy = [];
        $iRet = 0;
        // srcfname[0] means convert the first page
        $sExec = "ffmpeg -i \"{$fnameSrc}\" -vf  \"thumbnail,scale=480:-1\" -frames:v 1 \"$fnameThumb\"";
        exec( $sExec, $raDummy, $iRet );
        if( CATS_SYSADMIN ) {
            var_dump($sExec,$iRet);
        }

        return( $fnameThumb );
    }

}
