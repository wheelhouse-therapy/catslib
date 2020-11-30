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
                     ."<a title='{$title}' href='?screen=therapist-viewVideos&dir=main'><p><div style='background-color: ".(array_key_exists("color", $raDirInfo)?$raDirInfo['color']:"grey")."; display: inline-block; min-width: 500px; text-align: center; font-size: 18pt; color: #fff'>"
                        ."Back to Video Filing Cabinet"
                     ."</div></p></a>";
            } else {
                $s .= "<div><h3 style='display:inline;padding-right:50px'>Filing Cabinet</h3><i style='cursor:pointer' class='fa fa-search' onclick='$(\"#search_dialog\").modal(\"show\")' role='button' title='Search Filing Cabinet'></i></div>"
                     .($dir != 'papers'?"<div style='float:right'><a href='?screen=system-documentation&doc_view=item&doc_item=Template Format Reference'>Template Format Reference</a></div>":"")
                     //."<p><a href='?screen=therapist-filing-cabinet'>Back to Filing Cabinet</a></p>"
                     ."<a title='{$title}' href='?screen=therapist-filing-cabinet&dir=main'><p><div style='background-color: ".(array_key_exists("color", $raDirInfo)?$raDirInfo['color']:"grey")."; display: inline-block; min-width: 500px; text-align: center; font-size: 18pt; color: #fff'>"
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
                     ."<script src='".CATSDIR_JS."fileUpload.js'></script>
                       <link rel='stylesheet' href='".CATSDIR_CSS."fileUpload.css'>
                       <script>const upload = document.getElementById('uploadForm').innerHTML;</script>";
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
        html, body {
          /*  not sure why these were here but they cut off the bottom of the video
              height: 100vh;
              overflow:hidden; 
           */
        }
        .videoView {
            height: 88%;
        }
        .catsToolbar {
            margin-bottom:2px
        }
    </style>
    <div class='catsToolbar'><a href='?video_view=list'><button>Back to List</button></a></div>
    <div class='videoView'>
        <video width="100%" height="100%" controls>
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
