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

class FilingCabinetUI
/********************
    Top-level UI for the filing cabinet.
 */
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function DrawFilingCabinet( /* take a dir argument for Reports and other special drawers */ )
    {
        $s = "";

        if($this->oApp->sess->SmartGPC('dir') == 'main'){
            $this->oApp->sess->VarUnSet('dir');
        }

        // Handle cmds: download (does not return), and other cmds (return here then draw the filing cabinet)
        $this->handleCmd();

        if( CATS_SYSADMIN ) {
            if( SEEDInput_Str('adminCreateAllThumbnails') ) {
                // Create thumbnails for all resources that don't have them.
                // You have to have PHPWord, dompdf, and convert(ImageMagick) installed.
                $raRR = ResourceRecord::GetRecordsWithoutPreview($this->oApp);
                foreach( $raRR  as $oRR ) {
                    $oRR->CreateThumbnail( $oRR );
                }
                $s .= "<div class='alert alert-success'>Tried to create ".count($raRR)." thumbnails</div>";
            } else {
                $s .= "<form><input type='hidden' name='adminCreateAllThumbnails' value='1'/>"
                     ."<input type='submit' value='Admin: Create Thumbnails'/></form>";
            }
        }

        if( ($dir = $this->oApp->sess->SmartGPC('dir')) && ($dirbase = strtok($dir,"/")) && ($raDirInfo = FilingCabinet::GetDirInfo($dirbase)) ) {
            // Show the "currently-open drawer" of the filing cabinet
            FilingCabinet::EnsureDirectory($dirbase);
            $title = "Close {$raDirInfo['name']} Drawer";
            if(stripos($raDirInfo['name'], "Drawer") !== false){
                $title = "Close {$raDirInfo['name']}";
            }
            $s .= "<div><h3 style='display:inline;padding-right:50px'>Filing Cabinet</h3><i style='cursor:pointer' class='fa fa-search' onclick='$(\"#search_dialog\").modal(\"show\")' role='button' title='Search Filing Cabinet'></i></div>"
                 .($dir != 'papers'?"<div style='float:right'><a href='?screen=system-documentation&doc_view=item&doc_item=Template Format Reference'>Template Format Reference</a></div>":"")
                 //."<p><a href='?screen=therapist-filing-cabinet'>Back to Filing Cabinet</a></p>"
                 ."<a title='{$title}' href='?screen=therapist-filing-cabinet&dir=main'><p><div style='background-color: ".(array_key_exists("color", $raDirInfo)?$raDirInfo['color']:"grey")."; display: inline-block; min-width: 500px; text-align: center; font-size: 18pt; color: #fff'>"
                    ."Back to Filing Cabinet"
                 ."</div></p></a>";
            if($dir == 'papers'){
                include(CATSLIB."papers.php");
            }
            else{
                $s .= ResourcesDownload( $this->oApp, $raDirInfo['directory'] );
            }
            $s .= $this->getSearchDialog();
        } else {
            FilingCabinet::EnsureDirectory("*");
            $s .= "<div style='float:right;' id='uploadForm'>"
                    .FilingCabinetUpload::DrawUploadForm()
                 ."</div><script>const upload = document.getElementById('uploadForm').innerHTML;</script>";

            // Show the "closed drawers" of the filing cabinet
            $s .= "<div><h3 style='display:inline;padding-right:50px'>Filing Cabinet</h3><i style='cursor:pointer' class='fa fa-search' onclick='$(\"#search_dialog\").modal(\"show\")' role='button' title='Search Filing Cabinet'></i></div>";

            // Some of the directories in the array are not part of the filing cabinet. Remove them here.
            $ras = FilingCabinet::GetFilingCabinetDirectories();
            foreach( $ras as $k => $ra ) {
                $bgcolor = "background-color: grey;";
                if (array_key_exists("color", $ra)) {
                    $bgcolor = "background-color: {$ra['color']};";
                }
                $title = "Open {$ra['name']} Drawer";
                if(stripos($ra['name'], "Drawer") !== false){
                    $title = "Open {$ra['name']}";
                }

                $s .= "<a href='?dir={$k}' title='{$title}'><p><div style='{$bgcolor} display: inline-block; min-width: 500px; text-align: center; font-size: 18pt; color: #fff'>"
                        ."{$ra['name']}"
                     ."</div></a></p>";
            }
            $s .= $this->getSearchDialog();
        }
        return( $s );
    }

    private function getSearchDialog(){
        return <<<SearchDialog
<div class='modal fade' id='search_dialog' role='dialog'>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <input type='text' class='search' id='search' name='search' placeholder='Search..' onkeyup='searchFiles(event)' role='search'>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class='modal-body' id='searchResults'>
            </div>
        </div>
    </div>
</div>
SearchDialog;
    }

    private function handleCmd()
    {
        switch( ($cmd = SEEDInput_Str('cmd')) ) {
            case 'download':
                $oFCD = new FilingCabinetDownload( $this->oApp );
                $oFCD->DownloadFile();
                exit;       // download doesn't return here, but this is just a good reminder of that
            case 'view':
                $oFCD = new FilingCabinetDownload( $this->oApp );
                $oFCD->DownloadFile();
                exit;       // download doesn't return here, but this is just a good reminder of that

            default:
                break;
        }
    }
}


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

    function tmpEnsureResourceRecords()
    {
        /* This is a temporary measure to make sure that a ResourceRecord exists for every file in the Filing Cabinet, except for "pending"
         * Remove this method when ResourceRecords are naturally created for all files.
         */
        foreach( self::$raDirectories as $dir=>$raD ) {

            $dirIterator = new DirectoryIterator(CATSDIR_RESOURCES.$dir);
            foreach( $dirIterator as $fileinfo ) {
                if( $fileinfo->isDot() || $fileinfo->isDir() ) continue;

                if( !($oRR = ResourceRecord::GetRecordFromRealPath($this->oApp, realpath($fileinfo->getPathname()))) ) {
                    if( CATS_SYSADMIN ) {
                        echo "<p>Creating ResourceRecord for: ".$fileinfo->getPathname()."</p>";
                    }
                    $oRR = ResourceRecord::CreateFromRealPath($this->oApp, realpath($fileinfo->getPathname()),0);
                    $oRR->StoreRecord();
                }
            }

            foreach( FilingCabinet::GetSubFolders($dir) as $subfolder) {
                if(!file_exists(CATSDIR_RESOURCES.$dir.'/'.$subfolder))continue;
                $subdir = new DirectoryIterator(CATSDIR_RESOURCES.$dir.'/'.$subfolder);
                foreach( $subdir as $fileinfo ) {
                    if( $fileinfo->isDot() || $fileinfo->isDir() ) continue;

                    if( !($oRR = ResourceRecord::GetRecordFromRealPath($this->oApp, realpath($fileinfo->getPathname()))) ) {
                        if( CATS_SYSADMIN ) {
                            echo "<p>Creating ResourceRecord for: ".$fileinfo->getPathname()."</p>";
                        }
                        $oRR = ResourceRecord::CreateFromRealPath($this->oApp, realpath($fileinfo->getPathname()),0);
                        $oRR->StoreRecord();
                    }
                }
            }
        }
    }

    /**
     * Get the list of directories in the Resource Subsystem
     * @return array - containing directory information
     */
    static function GetDirectories() { return( self::$raDirectories ); }
    /**
     * Get Directory information (eg. allowed extensions, display name, etc.) for a directory
     * @param string $dir - directory to get information of
     * @return NULL|array - array containing the info for the given directory, or Null if its not part of the Resource Subsystem
     */
    static function GetDirInfo(String $dir) { return( @self::$raDirectories[$dir] ?: null ); }
    /**
     * Get Subdirectories in the given directory
     * @param string $dir - directory to get subdirs of
     * @return array - list of defined subdirectories if defined
     */
    static function GetSubFolders(String $dir){ return( @self::$raSubFolders[$dir] ?: []); }

    /**
     * Get the extensions supported by the Resource Subsystem.
     * NOTE: the extensions do not contain dots
     * @return array - array of supported extensions
     */
    static function GetSupportedExtensions(){
        $exts = [];
        foreach(self::GetDirectories() as $ra) {
            $exts = array_merge($exts,$ra['extensions']);
        }
        return( array_unique($exts) );
    }

    /**
     * Get directories which are part of the filing cabinet
     * @return array - array containing directory information of the directories which are part of the filing cabinet
     */
    static function GetFilingCabinetDirectories(){
        return array_diff_key(self::GetDirectories(), array_flip(array('reports','SOP','sections','videos')));
    }

    /**
     * Get the accessor string to navigate a browser to the given file
     * @param ResourceRecord $oRR - file to navigate to
     * @return String - URI that directs to the appropriate location so the file can be accessed. Returns empty string if the file has no accessor
     */
    static function GetAccessor(ResourceRecord $oRR):String{
        $directory = $oRR->getDirectory();
        if(array_key_exists($directory, self::GetFilingCabinetDirectories())){
            // File is part of the filing cabinet, return the appropiate accessor
            if($oRR->templateFillerSupported()){
                return "?screen=therapist-filing-cabinet&dir=".$directory."&rr=".$oRR->getID();
            }
            return "?screen=therapist-filing-cabinet&dir=".$directory."&cmd=download&rr={$oRR->getID()}&resource-mode=no_replace";
        }
        elseif($directory == "reports"){
            // File is a report,
            return "?screen=therapist-reports";
        }
        elseif($directory == "SOP"){
            return "?screen=therapist-viewSOPs";
        }
        elseif($directory == "videos"){
            return "?screen=therapist-viewVideos";
        }
        return ""; //No accessor for this file
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
        'visual'  => ["Pencil Control","Cutting","Upper case","Lower case","Reversals","Print Correction","Numbers","Drawing"],
        'other'   => ["Hand skills","Gross Motor","Occulomotor"],
        'anxiety' => ["Dragon","Monster","Behaviour & Exposure"],
        'cog'     => ["literacy","writing","problem-solving","organization"],
        'adl'     => ["Feeding","Toiletting","Lifeskills"],
        'assmt'   => ["MOTOR","PERCEPTION","VISUAL & SCANNING","SENSORY","FUNCTIONAL","BEHAV & COMMUNICATION & EMOTIONAL","GENERAL DEVELOPMENTAL"]
    ];
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
    private const SUBDIRECTORY_KEY = 'subdir';
    private const TAGS_KEY = 'tags';
    private const PREVIEW_KEY = 'preview';

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
     * Date the file was initially revieved or uploaded
     * value of 0 represents new file (usually accompanied by an id of 0)
     * READ ONLY
     */
    private $created = 0;
    private $created_by = 0;
    private $status = 0;

    // File info
    private $dir;
    private $subdir = '';
    private $file;
    private $tags = [];
    private $preview = "";
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
        $this->id = intval(@$raParams[self::ID_KEY]?:0);
        $this->created = @$raParams[self::CREATED_KEY]?:0;
        $this->created_by = intval(isset($raParams[self::CREATED_BY_KEY])?$raParams[self::CREATED_BY_KEY]:$oApp->sess->getUID());
        $this->status = intval(@$raParams[self::STATUS_KEY]?:0);
        $this->subdir = @$raParams[self::SUBDIRECTORY_KEY]?:'';
        if(is_string(@$raParams[self::TAGS_KEY])){
            $this->tags = explode(self::TAG_SEPERATOR, @$raParams[self::TAGS_KEY]);
        }
        else{
            $this->tags = @$raParams[self::TAGS_KEY]?:[];
        }
        $this->tags = array_values(array_filter($this->tags)); //Remove empty tags, saving to DB should keep them

        $this->preview = @$raParams[self::PREVIEW_KEY]?:'';
    }

    /**
     * Print all properties except oApp to clean up the screen when printing object
     */
    public function __debugInfo() {
        return [
            'id' => $this->id,
            'created' => $this->created,
            'status' => $this->status,
            'dir' => $this->dir,
            'subdir' => $this->subdir,
            'file' => $this->file,
            'tags' => $this->tags,
            'committed' => $this->committed,
            'preview' => $this->preview,
            'created_by' => $this->created_by,
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
        $this->tags += [$tag];
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
     * Set the directory of the record.
     * NOTE: This DOES NOT STORE THE CHANGE
     * NOTE 2: This DOES NOT MOVE THE FILE
     * @param String $dir - dir to set
     * @return bool - true if the directory has changed, false otherwise
     */
    public function setDirectory(String $dir):bool{
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
     * NOTE: This DOES NOT STORE THE CHANGE
     * NOTE 2: Record Searches MAY IGNORE records which have a status other than 0
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
     * @return boolean - true if preview has changed, false otherwise
     */
    public function setPreview(String $image){
        if($image != $this->preview){
            $this->committed = false;
        }
        $this->preview = $image;
        return !$this->committed;
    }

    /**
     * Commit any record changes to the database, and update the id if needed.
     * NOTE: To prevent unnessiary db commits, the data is only written to the db if it has been changed by a setter.
     * @return bool - true if data successfully committed, false otherwise
     */
    public function StoreRecord():bool{
        if($this->committed){
            //The data has not changed since the last store
            return false;
        }
        $dbFolder = addslashes($this->dir);
        $dbSubFolder = addslashes($this->subdir);
        $dbFilename = addslashes($this->file);
        $dbPreview = $this->oApp->kfdb->EscapeString($this->preview);   // better to use the mysqli function to escape binary data
        $uid = $this->oApp->sess->getUID();
        if($this->id == 0){
            //Check if db already contains a record, update key if it does, to prevent duplicate records for the SAME file
            $cond = "_status={$this->status} AND subfolder='{$dbSubFolder}' AND folder='{$dbFolder}' AND filename='{$dbFilename}'";
            $this->id = @$this->oApp->kfdb->Query1( "SELECT _key FROM resources_files WHERE $cond" )?:0;
        }
        if($this->id == 0){
            // Could not find an existing record, overwrite an deleted record
            $this->id = @$this->oApp->kfdb->Query1( "SELECT _key FROM resources_files WHERE _status=1" )?:0;
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
        if($this->id){
            if($this->created != "NOW()"){
                $this->created = "'$this->created'";
            }
            $this->committed = $this->oApp->kfdb->Execute("UPDATE resources_files SET _created={$this->created},_updated=NOW(),_updated_by=$uid,_status={$this->status},folder='$dbFolder',filename='$dbFilename',tags='$tags',subfolder='$dbSubFolder',preview='$dbPreview' WHERE _key = {$this->id}");
        }
        else{
            if(($this->id = $this->oApp->kfdb->InsertAutoInc("INSERT INTO resources_files (_created, _created_by, _updated, _updated_by, _status, folder, filename, tags, subfolder,preview) VALUES (NOW(),{$this->created_by},NOW(),$uid,{$this->status},'$dbFolder','$dbFilename','$tags','$dbSubFolder','$dbPreview')"))){
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
     * @return boolean - True if record was successfully deleted. Note returning false does not mean the record was not deleted.
     * Check the status of the record to ensure it was deleted.
     */
    public function DeleteRecord(){
        $result1 = $this->setStatus(1);
        $result2 = $this->StoreRecord();
        return $result1 && $result2;
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

    /**
     * Get the stored preview of the resource.
     * @param bool $encode - Wether or not to base 64 encode the image data for output. Default true.
     * @return string containing the encoded or raw image data. Or empty string if there is no image stored
     */
    public function getPreview(bool $encode = true){
        if($encode){
            return base64_encode($this->preview);
        }
        return $this->preview;
    }

    /**
     * Get the user who created the record.
     * NOTE: this user is also considered to be the uploader
     * NOTE2: a value of 0 represents an unknown/anonynous uploader. Requesting the userdata of a  0 value yields a name of "Anonymous"
     * NOTE3: this method enforces clinic privacy, and changes the current users name to "Self"
     * NOTE4: if a user does not have permission to see the user who uploaded a file (ie. dont share a clinic) the users name is replaced with "Anonymous"
     * NOTE5: NOTE3 only applies when $userdata is true.
     * @param bool $userdata - wether to return the data associated with the uid or the uid itself
     * @return array|int - array of user data if $userdata is true, or the uid of the user who uploaded the indexed resource or 0 if the uploader is unknown
     */
    public function getUploader(bool $userdata = false){
        if($userdata){
            $acctDB = new SEEDSessionAccountDBRead($this->oApp->kfdb);
            $clinics = new Clinics($this->oApp);
            $result = $acctDB->GetUserInfo($this->created_by,false,true);
            if(!$result[0]){
                $result[1] = ['realname' => 'Anonymous'];
            }
            else if($result[0] == $this->oApp->sess->getUID()){
                $result[1]['realname'] = 'Self';
            }
            $result = array_merge($result[1],['metadata'=>$result[2]]);
            return $result;
        }
        return $this->created_by;
    }

    /**
     * Get if this file is supported by the template filler subsystem.
     * NOTE: Only docx files are supported at this time.
     * NOTE2: if this method returns true it is safe to pass the file to the template filler subsystem
     * @return bool - true if its supported false otherwise
     */
    public function templateFillerSupported():bool{
        return pathinfo($this->file,PATHINFO_EXTENSION) == "docx";
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
        if($cond){
            if($disJuctive){
                $cond .= " OR ";
            }
            else{
                $cond .= " AND ";
            }
        }
        $cond .= $add;
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
        if(strripos($query, "_status") === FALSE){
            $query = self::joinCondition($query, "_status=0");
        }
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
        $raParams += [self::PREVIEW_KEY=>$ra['preview']];
        $raParams += [self::CREATED_BY_KEY=>$ra['_created_by']];
        $oRR = new ResourceRecord($oApp, $ra['folder'], $ra['filename'],$raParams);
        $oRR->committed = true; // The data in this record was just pulled from the DB
        return $oRR;

    }

    public static function CreateNewRecord(SEEDAppConsole $oApp, String $dirname,String $filename,int $created_by=-1):ResourceRecord{
        if($created_by == -1){
            $created_by = $oApp->sess->getUID();
        }
        return new ResourceRecord($oApp, $dirname, $filename,[self::CREATED_BY_KEY=>$created_by]);
    }

    public static function CreateFromRealPath(SEEDAppConsole $oApp, String $realpath,int $created_by=-1){
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
        $oRR =  self::CreateNewRecord($oApp, $dir, $filename,$created_by);
        $oRR->subdir = $subdir;
        return $oRR;
    }

    public static function GetRecordFromPath(SEEDAppConsole $oApp, String $dirname,String $filename, String $subdir = self::WILDCARD){
        $cond = "";
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
            $query .= " WHERE $cond";
        }
        return self::getFromQuery($oApp,$query);
    }

    public static function GetRecordFromRealPath(SEEDAppConsole $oApp, String $realpath){
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
        return self::GetRecordFromPath($oApp, $dir, $filename,$subdir);
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
    public static function GetRecordsWithoutPreview(SEEDAppConsole $oApp){
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


    function CreateThumbnail()
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
}
