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
