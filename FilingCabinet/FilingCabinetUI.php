<?php

/* FilingCabinetUI
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * See FilingCabinet.php for information
 */

class FilingCabinetUI
/********************
    Top-level UI for the filing cabinet.
 */
{
    private $oApp;
    private $sCabinet;  // the value of resources_files.cabinet that defines which filing cabinet to use

    function __construct( SEEDAppConsole $oApp, String $sCabinet = 'general' )
    {
        $this->oApp = $oApp;
        $this->sCabinet = $sCabinet;
    }

    function DrawFilingCabinet()
    /***************************
        Show the UI for a FilingCabinet
     */
    {
        $s = "";

        FilingCabinet::EnsureDirectory("*");

        if($this->oApp->sess->SmartGPC('dir') == 'main'){
            $this->oApp->sess->VarUnSet('dir');
        }

        // Handle cmds: download (does not return), and other cmds (return here then draw the filing cabinet)
        $this->handleCmd();

        if( CATS_SYSADMIN ) {
            if( SEEDInput_Str('adminCreateAllThumbnails') ) {
                // Create thumbnails for all resources that don't have them.
                // You have to have PHPWord, dompdf, convert(ImageMagick), ffmpeg installed.
                $raRR = ResourceRecord::GetRecordsWithoutPreview($this->oApp);
                foreach( $raRR  as $oRR ) {
                    $oRR->CreateThumbnail( $oRR );
                }
                $s .= "<div class='alert alert-success'>Tried to create ".count($raRR)." thumbnails</div>";
            } else {
                $s .= "<form><input type='hidden' name='adminCreateAllThumbnails' value='1'/>"
                     ."<input type='submit' value='Admin: Create Thumbnails'/></form>";
            }
            if(SEEDInput_Str('adminIndexAllFiles')){
                // Force create records of all resources in the file system.
                // The filing cabinet is now database driven so this can be used as a "sync" forcing the database to match the filesystem
                foreach(FilingCabinet::GetDirectories() as $dir_short=>$dirInfo) {
                    if($dir_short == "videos") {continue;}
                    $cabinet = "general";
                    switch(strtolower($dir_short)){
                        case "sop":
                            $cabinet = "SOP";
                            break;
                        case "reports":
                            $cabinet = "reports";
                            break;
                        default:
                            $cabinet = "general";
                            break;
                    }
                    $dir_name = CATSDIR_RESOURCES.$dirInfo['directory'];
                    $dirIterator = new DirectoryIterator($dir_name);
                    foreach ($dirIterator as $fileinfo) {
                        if( $fileinfo->isDot() || $fileinfo->isDir() ) continue;

                        $oRR = ResourceRecord::GetRecordFromRealPath($this->oApp, $cabinet, realpath($fileinfo->getPathname()));

                        if(!$oRR){
                            // The file does not have a record yet, create one
                            $oRR = ResourceRecord::CreateFromRealPath($this->oApp, realpath($fileinfo->getPathname()),$cabinet, 0);
                            if($oRR->StoreRecord()){
                                $s .= "<div class='alert alert-success'>Created Record for ".SEEDCore_HSC($dir_short."/".$fileinfo->getFilename())."</div>";
                            }
                            else{
                                echo "<div class='alert alert-danger'>Could not create record for ".SEEDCore_HSC($dir_short."/".$subdir.$fileinfo->getFilename())."</div>";
                            }
                        }
                    }
                    foreach(FilingCabinet::GetSubFolders($dir_short,$cabinet) as $subfolder) {
                        if(!file_exists($dir_name.$subfolder)) continue;
                        $subdir = new DirectoryIterator($dir_name.$subfolder);
                        foreach( $subdir as $fileinfo ) {
                            if( $fileinfo->isDot() || $fileinfo->isDir() ) continue;
                            $oRR = ResourceRecord::GetRecordFromRealPath($this->oApp, $cabinet, realpath($fileinfo->getPathname()));
                            if(!$oRR){
                                // The file does not have a record yet, create one
                                $oRR = ResourceRecord::CreateFromRealPath($this->oApp, realpath($fileinfo->getPathname()),$cabinet, 0);
                                if($oRR->StoreRecord()){
                                    $s .= "<div class='alert alert-success'>Created Record for ".SEEDCore_HSC($dir_short."/".$subdir.$fileinfo->getFilename())."</div>";
                                }
                                else{
                                    echo "<div class='alert alert-danger'>Could not create record for ".SEEDCore_HSC($dir_short."/".$subdir.$fileinfo->getFilename())."</div>";
                                }
                            }
                        }
                    }
                }

            } else {
                $s .= "<form><input type='hidden' name='adminIndexAllFiles' value='1'/>"
                    ."<input type='submit' value='Admin: Index Files'/></form>";
            }
        }

        if( ($dir = $this->oApp->sess->SmartGPC('dir')) && ($dirbase = strtok($dir,"/")) && ($raDirInfo = FilingCabinet::GetDirInfo($dirbase, $this->sCabinet)) ) {
            // Show the "currently-open drawer" of the filing cabinet
            $title = "Close {$raDirInfo['name']} Drawer";
            if(stripos($raDirInfo['name'], "Drawer") !== false){
                $title = "Close {$raDirInfo['name']}";
            }
            if( $this->sCabinet == 'videos' ) {
                $s .= "<div><h3 style='display:inline;padding-right:50px'>Video Filing Cabinet</h3><i style='cursor:pointer' class='fa fa-search' onclick='$(\"#search_dialog\").modal(\"show\")' role='button' title='Search Videos'></i></div>"
                     .($dir != 'papers'?"<div style='float:right'><a href='?screen=system-documentation&doc_view=item&doc_item=Template Format Reference'>Template Format Reference</a></div>":"")
                     //."<p><a href='?screen=therapist-filing-cabinet'>Back to Filing Cabinet</a></p>"
                     ."<a title='{$title}' href='?screen=therapist-viewVideos&dir=main'><p><div style='background-color: ".(array_key_exists("color", $raDirInfo)?$raDirInfo['color']:"grey")."; display: inline-block; min-width: min(500px,90vw); text-align: center; font-size: 18pt; color: #fff'>"
                        ."Back to Video Filing Cabinet"
                     ."</div></p></a>";
            } else {
                $s .= "<div><h3 style='display:inline;padding-right:50px'>Filing Cabinet</h3><i style='cursor:pointer' class='fa fa-search' onclick='$(\"#search_dialog\").modal(\"show\")' role='button' title='Search Filing Cabinet'></i></div>"
                     .($dir != 'papers'?"<div style='float:right'><a href='?screen=system-documentation&doc_view=item&doc_item=Template Format Reference'>Template Format Reference</a></div>":"")
                     //."<p><a href='?screen=therapist-filing-cabinet'>Back to Filing Cabinet</a></p>"
                     ."<a title='{$title}' href='?screen=therapist-filing-cabinet&dir=main'><p><div style='background-color: ".(array_key_exists("color", $raDirInfo)?$raDirInfo['color']:"grey")."; display: inline-block; min-width: min(500px,90vw); text-align: center; font-size: 18pt; color: #fff'>"
                        ."Back to Filing Cabinet"
                     ."</div></p></a>";
            }
            if($dir == 'papers'){
                include(CATSLIB."papers.php");
            }
            else {
                if( $this->sCabinet == 'videos' && SEEDInput_Str('cmd')=='viewVideo' ) {
                    $s .= $this->viewVideo( SEEDInput_Int('rr') );
                } else {
                    $s .= ResourcesDownload( $this->oApp, $raDirInfo['directory'], $this->sCabinet );
                }
            }
            $s .= $this->getSearchDialog();
        } else {
            if( $this->sCabinet=='videos' ) {
                $s .= "<div style='float:right;background:white;' id='uploadForm'>"
                     .FilingCabinetUpload::DrawUploadForm($this->sCabinet)
                     ."</div>"
                     ."<script>const upload = document.getElementById('uploadForm').innerHTML;</script>";
            } else {
                $s .= "<div style='float:right;' id='uploadForm'>"
                        .FilingCabinetUpload::DrawUploadForm($this->sCabinet)
                     ."</div>"
                     ."<script>const upload = document.getElementById('uploadForm').innerHTML;</script>";
            }

            // Show the "closed drawers" of the filing cabinet
            $s .= $this->sCabinet=='videos'
                    ? "<div><h3 style='display:inline;padding-right:50px'>Videos</h3><i style='cursor:pointer' class='fa fa-search' onclick='$(\"#search_dialog\").modal(\"show\")' role='button' title='Search Videos'></i></div>"
                    : "<div><h3 style='display:inline;padding-right:50px'>Filing Cabinet</h3><i style='cursor:pointer' class='fa fa-search' onclick='$(\"#search_dialog\").modal(\"show\")' role='button' title='Search Filing Cabinet'></i></div>";

            // Some of the directories in the array are not part of the filing cabinet. Remove them here.
            $ras = FilingCabinet::GetFilingCabinetDirectories( $this->sCabinet );
            foreach( $ras as $k => $ra ) {
                $bgcolor = "background-color: grey;";
                if (array_key_exists("color", $ra)) {
                    $bgcolor = "background-color: {$ra['color']};";
                }
                $title = "Open {$ra['name']} Drawer";
                if(stripos($ra['name'], "Drawer") !== false){
                    $title = "Open {$ra['name']}";
                }
                $new = FALSE;
                $newness = 0;
                foreach( ResourceRecord::GetResources($this->oApp, $this->sCabinet, $k,ResourceRecord::WILDCARD) as $rr){
                    if($rr->isNewResource()){
                        $new = TRUE;
                    }
                    if($rr->getNewness() > $newness){
                        $newness = $rr->getNewness();
                    }
                }
                $sBadge = $new?"<span class='badge badge{$newness}'> </span>":"";
                $sStyle = "{$bgcolor} display: inline-block; min-width: min(500px,90vw); text-align: center; font-size: 18pt; color: #fff;".($new?"position:relative":"");
                $s .= "<a href='?dir={$k}' title='{$title}'><p><div style='{$sStyle}'>{$sBadge}"
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
        $cmd = SEEDInput_Str('cmd');
        switch( $cmd ) {
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

    private function viewVideo( $rrid )
    {
        $s = "";

/*
    if( SEEDInput_Str('cmd') == 'view' ) {
        $oFCD = new FilingCabinetDownload( $oApp );
        $oFCD->DownloadFile();
        exit;
    }
*/

    $s = <<<viewVideo
    <style>
        html {
              width: 100vw;
              box-sizing: border-box;
              overflow-x: hidden;
        }
        .videoView {
            width: 90vw;
            margin: auto;
            position: relative;
        }
        .catsToolbar {
            margin-bottom:2px
        }
        .catsVideo {
            width: 100%;
            margin: auto;
            display: block;
        }
        #watchedIcon {
            margin-left: 20px;
        }
    </style>
    <script>
        var isWatched = [[watched]];
        function autoWatched(event){
            if(!isWatched && event.currentTime/event.duration >= 0.8){
                watched([[rrid]]);
            }
        }
        
        function watched(rrid){
            isWatched = true;
            document.getElementById('watchedIcon').innerHTML = "<i title='Processing...' class='fas fa-circle-notch fa-spin'></i>";
            $.ajax({
                type: "POST",
                data: {cmd:'therapist--watchedVideo',rrid:[[rrid]]},
                url: 'jx.php',
                success: function(data, textStatus, jqXHR) {
                    var jsData = JSON.parse(data);
                    if(jsData.bOk){
                        document.getElementById('watchedIcon').innerHTML = "<i title='Watched' class='far fa-check-circle'></i>";
                    }
                    else{
                        document.getElementById('watchedIcon').innerHTML = "<i title='Error' class='far fa-times-circle'></i>";
                    }
                },
                error: function(jqXHR, status, error) {
                    console.log(status + ": " + error);
                }
            });
        }
    </script>
    <div class='catsToolbar'><a href='?video_view=list'><button>Back to List</button></a>[[watchedIcon]]</div>
    <div class='videoView'>
        <video class='catsVideo' controls ontimeupdate='autoWatched(this)'>
            <source src="[[video]]" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
viewVideo;
    if( $rrid && ($oRR = ResourceRecord::GetRecordById($this->oApp, $rrid)) ) {
        if( SEED_isLocal ) {
            // use fpassthru because ./resources/videos is not necessarily in the web root
            $s = str_replace("[[video]]", "?cmd=view&rr={$oRR->getID()}", $s);
        } else {
            // use direct link because hostupon and/or php are buffering output so the download takes a very long time
            $s = str_replace("[[video]]", "./resources/videos/{$oRR->getId()} {$oRR->getFile()}", $s);
        }
        $oWatchList = new VideoWatchList($this->oApp, $this->oApp->sess->GetUID());
        $s = str_replace("[[rrid]]", $oRR->getID(), $s);
        if($oWatchList->hasWatched($oRR->getID())){
            $s = str_replace(["[[watched]]","[[watchedIcon]]"], ["true","<span id='watchedIcon'><i title='Watched' class='far fa-check-circle'></i></span>"], $s);
        }
        else{
            $s = str_replace(["[[watched]]","[[watchedIcon]]"], ["false","<span id='watchedIcon'><i title='Unwatched' class='far fa-dot-circle'></i></span>"], $s);
        }
    }


/*
    $listVideos = "<h3>View Uploaded Videos</h3>";
    $dirIterator = new DirectoryIterator(CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('videos')['directory']);
    if(iterator_count($dirIterator) == 2){
        $listVideos .= "<h2> No files in directory</h2>";
        goto brains;
    }

    $sFilter = SEEDInput_Str('resource-filter');

    $listVideos .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;z-index:-10;'><form method='post'>"
        ."<input type='text' name='resource-filter' value='$sFilter'/> <input type='submit' value='Filter'/>"
        ."</form></div>";

        $listVideos .= "<table border='0'>";
        foreach ($dirIterator as $fileinfo) {
            if( $fileinfo->isDot() ) continue;

            if( $sFilter ) {
                if( stripos( $fileinfo->getFilename(), $sFilter ) === false )  continue;
            }

            $listVideos .= "<tr><td valign='top'>"
                ."<a style='white-space: nowrap' href='?video_view=item&video_item=".pathinfo($fileinfo->getFilename(),PATHINFO_FILENAME)."' >"
                    .$fileinfo->getFilename()
                ."</a>"
                ."</td></tr>";
        }
        $listVideos .= "</table>";

        //Brains of operations
        brains:
        $view = $oApp->sess->SmartGPC("video_view",array("list","item"));
        $item = $oApp->sess->SmartGPC("video_item", array(""));

        switch ($view){
            case "item":
                //Complicated method to ensure the file is in the directory
                foreach (array_diff(scandir(CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('videos')['directory']), array('..', '.')) as $file){
                    if(pathinfo($file,PATHINFO_FILENAME) == $item){
                        // use FilingCabinetDownload to show file
                        if( ($oRR = ResourceRecord::GetRecordFromPath($oApp, 'videos', "videos", pathinfo($file,PATHINFO_BASENAME), "*" )) &&
                            ($oRR->getID()) )
                        {
                            return str_replace("[[video]]", "?cmd=view&rr={$oRR->getID()}", $viewVideo);
                        }
                    }
                }
                $oApp->sess->VarUnSet("video_item");
            case "list":
                return $listVideos;
        }
        return( "" );
*/

        return( $s );
    }
}
