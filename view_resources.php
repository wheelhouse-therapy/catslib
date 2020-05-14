<?php

require_once 'template_filler.php';
require_once 'share_resources.php';


function ResourcesDownload( SEEDAppConsole $oApp, $dir_name, $download_modes = "snb" )
/************************************************************
    Show the documents from the given directory, and if one is clicked download it through the template_filler
 */
{
    $s = "";

    if( !$dir_name ) {
        $s .= "Directory not specified";
        goto done;
    }

    $dir_short = trim($dir_name,'/');

    $s .= <<<ResourcesTagStyle
        <style>
            #break {
                display: flex;
                justify-content: space-around;
                align-items: flex-start;
                min-height: 100px;
                height: auto;
            }
            #ResourceMode {
                box-sizing: border-box;
                display: inline-flex;
                flex-direction: column;
                padding: 7px 10px;
                border: 2px outset #ccc;
                border-style: inset outset outset inset;
                border-radius: 5px;
                justify-content: space-between;
                flex-wrap: wrap;
                background-color: #ccc;
                flex-basis: 20%;
                align-content: space-between;
            }
            #modeText {
                display: flex;
                height: 30px;
                align-items: center;
                padding: 5px;
                margin-bottom: 5px;
            }
            #mode1 {
                margin-bottom: 5px;
            }
            #mode2 {
            }
        </style>
ResourcesTagStyle;

    $s .= <<<ResourcesTagScript
        <script>
        </script>
ResourcesTagScript;

// TODO: the Filing Cabinet handles its own download cmd but this is still used by Reports (which should use DrawFilingCabinet('reports/') instead some day)
    if( SEEDInput_Str('cmd') == 'download' ) {
        $oFCD = new FilingCabinetDownload( $oApp );
        $oFCD->DownloadFile();
        exit;
    }

    $oFCD = new FilingCabinetDownload( $oApp );

    // make sure dir_name is the full path
    if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
        $dir_name = CATSDIR_RESOURCES.$dir_name;
    }
    if(!file_exists($dir_name)){
        $s .= "<h2>Unknown directory $dir_name</h2>";
        return $s;
    }

    $dirIterator = new DirectoryIterator($dir_name);
    if(iterator_count($dirIterator) == 2){
        $s .= "<h2> No files in directory</h2>";
        return $s;
    }
    if( !($oClinics = new Clinics($oApp)) || !($oClinics->GetCurrentClinic()) ) {
        return;
    }
    $clients = (new ClientList($oApp))->getMyClients();
    $s .= " <!-- the div that represents the modal dialog -->
            <div class='modal fade' id='file_dialog' role='dialog'>
                <div class='modal-dialog'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h4 class='modal-title'>Please select a client</h4>
                        </div>
                        <div class='modal-body'>
                            <form id='client_form' onsubmit='modalSubmit(event)'>
                            <div class='row'><div class='col-sm-6'>
                                <input type='hidden' name='cmd' value='download' />
                                <input type='hidden' name='dir' id='dir' value='$dir_short' />
                                <input type='hidden' name='file' id='file' value='' />
                                <select name='client' id='fcd_clientSelector' required>
                                    <option selected value=''>Select a Client</option>"
                                .SEEDCore_ArrayExpandRows($clients, "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>")
                                ."</select>
                            </div><div class='col-sm-6'>
                                <div class='filingcabinetdownload_downloadmodeselector' style='font-size:small'>
                                    <p style='margin-left:20px'><input type='radio' name='resource-mode' namex='fcd_downloadmode' value='replace' onclick='fcd_clientselect_enable(true);' checked />Substitute client details into document</p>
                                    <p style='margin-left:20px'><input type='radio' name='resource-mode' namex='fcd_downloadmode' value='no_replace' onclick='fcd_clientselect_enable(false);'/>Save file with no substitution</p>
                                    <p style='margin-left:20px'><input type='radio' name='resource-mode' namex='fcd_downloadmode' value='blank' onclick='fcd_clientselect_enable(false);'/>Fill document tags with blanks</p>
                                </div>
                            </div></div>
                            </form>
                        </div>
                        <div class='modal-footer'>
                            <input type='submit' id='submitVal' value='".(CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('reports')['directory'] == $dir_name?"Next":"Download")."' form='client_form' />
                        </div>
                    </div>
                </div>
            </div>";

    $sFilter = SEEDInput_Str('resource-filter');

    $s .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;'><form method='post'>"
         ."<input type='text' name='resource-filter' value='".SEEDCore_HSC($sFilter)."'/>"
         ."<input type='hidden' name='dir' id='dir' value='$dir_short' />"
         ."<input type='submit' value='Filter'/>"
         ."</form></div>";

    $raOut = [];
    foreach(FilingCabinet::GetSubFolders($dir_short) as $folder){
        $raOut += [$folder=>""];
    }
    $raOut += [''=>""];

    foreach ($dirIterator as $fileinfo) {
        $raOut = addFileToSubfolder( $fileinfo, $sFilter, $raOut, $oApp, $dir_short, "", $oFCD );
    }

    foreach(FilingCabinet::GetSubFolders($dir_short) as $subfolder) {
        if(!file_exists($dir_name.$subfolder)) continue;
        $subdir = new DirectoryIterator($dir_name.$subfolder);
        foreach( $subdir as $fileinfo ) {
            $raOut = addFileToSubfolder( $fileinfo, $sFilter, $raOut, $oApp, $dir_short.'/'.$subfolder, $subfolder, $oFCD );
        }
    }
    $s .= "<div>";
    foreach($raOut as $k=>$v){
        if($k){
            $s.= "<div class='folder'><div class='folder-title' data-folder='$k'><i class='far fa-folder-open'></i><strong>$k</strong></div>";
        }
        else if(count($raOut) > 1 && $v){
            $s.= "<div class='folder'><div class='folder-title' data-folder='other'><i class='far fa-folder-open'></i><strong>Loose Files</strong></div>";
        }
        if($v){
            $id = "";
            if($k){
                $id = $k;
            }
            else{
                $id = "other";
            }
            $s.= "<div id='$id'>".$v."</div>";
        }
        else if($k){
            $s .= "<div id='$k'>Empty Folder</div>";
        }
        if($k){
            $s .= "</div>";
        }
        else if(count($raOut) > 1){
            $s .= "</div>";
        }
    }
    $s .= "</div>";

    $s .= "<script>
            const modal = document.getElementById('file_dialog').innerHTML;
            function select_client(file){
                document.getElementById('file_dialog').innerHTML = modal;
                document.getElementById('file').value = file;
                $('#file_dialog').modal('show');
            }
            function modalSubmit(e) {
                var target  = $(e.currentTarget);
                var postData = target.serializeArray();
                postData = postData.map(function(value, index, array){
                    if(value.name == 'cmd'){
                        return {name: 'cmd', value: 'therapist-resourcemodal'};
                    }
                    return value;
                });
                postData.push({name: 'submitVal', value: document.getElementById('submitVal').value});
                var preventDefault = true;
                $.ajax({
                    type: \"POST\",
                    data: postData,
                    async: false,
                    url: 'jx.php',
                    success: function(data, textStatus, jqXHR) {
                        var jsData = JSON.parse(data);
                        if(jsData.bOk){
                            var returnedContent = JSON.parse(jsData.sOut);
                            var body = target.closest('.modal-body')[0];
                            body.previousElementSibling.innerHTML = returnedContent['header'];
                            body.innerHTML = returnedContent['body'];
                            body.nextElementSibling.innerHTML = returnedContent['footer'];
                        }
                        else{
                            $('#file_dialog').modal('hide');
                            preventDefault = false;
                        }
                    },
                    error: function(jqXHR, status, error) {
                        console.log(status + \": \" + error);
                    }
                });
                if(preventDefault){
                    e.preventDefault();
                }
            }
           </script>";

    $s .= <<<fcdScript
<script>
function fcd_clientselect_enable( bEnable )
{
    $('#fcd_clientSelector').prop("disabled", !bEnable);
}
</script>
fcdScript;


    done:
    return( $s );
}

function addFileToSubfolder( $fileinfo, $sFilter, $raOut, $oApp, $dir_short, $kSubfolder, $oFCD )
{
        if( $fileinfo->isDot() || $fileinfo->isDir() ) goto done;

        $oRR = ResourceRecord::GetRecordFromRealPath($oApp, realpath($fileinfo->getPathname()));

        if(!$oRR){
            // The file does not have a record yet, create one
            $oRR = ResourceRecord::CreateFromRealPath($oApp, realpath($fileinfo->getPathname()));
            $oRR->StoreRecord();
        }

        if( $sFilter ) {
            // list this file if sFilter matches part of its filename, or part of one of its tags
            if( stripos( $fileinfo->getFilename(), $sFilter ) === false &&
                !in_array($sFilter, $oRR->getTags()))
            {
                goto done;
            }
        }

        // docx files get a link to the modal dialog; other files get a link for simple download
        if( $fileinfo->getExtension() == "docx" ) {
            $link = $oFCD->GetDownloadPath('replace', $oRR, $fileinfo->getFilename(), $dir_short );
        } else {
            $link = $oFCD->GetDownloadPath("no_replace", $oRR );
        }

        $sTemplate =
            "<div style='display:inline-block;margin-right:10px;border: solid 2px;padding:5px;padding-bottom:0px'>
                <div>
                    <a style='white-space: nowrap' [[LINK]] >[[FILENAME]]</a>
                </div>
                <div data-id='".$oRR->getID()."'>
                    [[TAGS]]
                </div>
            </div>";
        
        // Fallback if a Preview doesn't exist
        $filename = SEEDCore_HSC($fileinfo->getFilename());
        if($oRR->getPreview()){
            // a preview exists use it instead of the fallback
            $filename = "<img src='data:image/jpg;base64,".$oRR->getPreview()."' style='width:100%;padding-bottom:2px' />";
        }

        $raOut[$kSubfolder] .= str_replace( ["[[LINK]]","[[FILENAME]]","[[TAGS]]"],
                                           [$link,
                                            $filename,
                                            $oFCD->oResourcesFiles->DrawTags($oRR)
                                           ],
                                           $sTemplate);

        done:
        return( $raOut );
}

function getModeOptions($resourcesMode, $downloadModes, $mode, $dir)
{
    $MODES = array('s' => array("code" => "replace"   , "title" => "Substitute Tags"    ),
                   'n' => array("code" => "no_replace", "title" => "Original Version" ),
                   'b' => array("code" => "blank"     , "title" => "Blank Mode"           )

    );

    $raModes = str_split($downloadModes);
    $firstMode = current($raModes);
    $midMode = next($raModes);
    $lastMode = end($raModes);
    $dir = trim($dir,'/');
    switch(strlen($downloadModes)){
        case 3:
            $resmode = ($mode==$MODES[$lastMode]['code']?$MODES[$midMode]['code']:$MODES[$lastMode]['code']);
            $resourcesMode = str_replace("[[button2]]","<a id='mode2' href='?resource-mode=$resmode&dir=$dir'><button>"
            .($mode==$MODES[$lastMode]['code']?$MODES[$midMode]['title']:$MODES[$lastMode]['title'])."</button></a>",$resourcesMode);
            // also do case 2

        case 2:
            $resmode = ($mode==$MODES[$firstMode]['code']?$MODES[$midMode]['code']:$MODES[$firstMode]['code']);
            $resourcesMode = str_replace("[[button1]]","<a id='mode1' href='?resource-mode=$resmode&dir=$dir'><button>"
            .($mode==$MODES[$firstMode]['code']?$MODES[$midMode]['title']:$MODES[$firstMode]['title'])."</button></a>",$resourcesMode);
            break;
    }

    $resourcesMode = str_replace("[[button2]]", "", $resourcesMode);

    return $resourcesMode;
}

function viewSOPs(SEEDAppConsole $oApp){
    $viewSOP = <<<viewSOP
    <style>
        html, body {
            height: 100vh;
            overflow:hidden;
        }
        .SOPview {
            height: 88%;
        }
        .catsToolbar {
            margin-bottom:2px
        }
    </style>
    <div class='catsToolbar'><a href='?SOP_view=list'><button>Back to List</button></a></div>
    <div class='SOPview'>
        <embed src='[[SOP]]#navpanes=0&statusbar=0&scrollbar=0&view=fitH,100' type='application/pdf' style='width:100%;height:100%;'>
    </div>
viewSOP;
    FilingCabinet::EnsureDirectory("SOP");
    $listSOPs = "<h3>View Standard Operating Procedures</h3>";
    $dirIterator = new DirectoryIterator(CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('SOP')['directory']);
    if(iterator_count($dirIterator) == 2){
        $listSOPs .= "<h2> No files in directory</h2>";
        goto brains;
    }

    $sFilter = SEEDInput_Str('resource-filter');

    $listSOPs .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;'><form method='post'>"
               ."<input type='text' name='resource-filter' value='$sFilter'/> <input type='submit' value='Filter'/>"
               ."</form></div>";

    $listSOPs .= "<table border='0'>";
    foreach ($dirIterator as $fileinfo) {
        if( $fileinfo->isDot() ) continue;

        if( $sFilter ) {
            if( stripos( $fileinfo->getFilename(), $sFilter ) === false )  continue;
        }

        $listSOPs .= "<tr>"
            ."<td valign='top'>"
                ."<a style='white-space: nowrap' href='?SOP_view=item&SOP_item=".pathinfo($fileinfo->getFilename(),PATHINFO_FILENAME)."' >"
                    .$fileinfo->getFilename()
                ."</a>"
            ."</td>"
            ."</tr>";
    }
    $listSOPs .= "</table>";

    //Brains of operations
    brains:
    $view = $oApp->sess->SmartGPC("SOP_view",array("list","item"));
    $item = $oApp->sess->SmartGPC("SOP_item", array(""));

    switch ($view){
        case "item":
            //Complicated method to ensure the file is in the directory
            foreach (array_diff(scandir(CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('SOP')['directory']), array('..', '.')) as $file){
                if(pathinfo($file,PATHINFO_FILENAME) == $item){
                    // show file
                    return str_replace("[[SOP]]", CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('SOP')['directory'].$file, $viewSOP);
                }
            }
            $oApp->sess->VarUnSet("SOP_item");
        case "list":
            return $listSOPs;
    }

}

function viewVideos(SEEDAppConsole $oApp){
    $viewVideo = <<<viewVideo
    <style>
        html, body {
            height: 100vh;
            overflow:hidden;
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
    FilingCabinet::EnsureDirectory("videos");
    // put a dot in front of each ext and commas in between them e.g. ".docx,.pdf,.mp4"
    $acceptedExts = SEEDCore_ArrayExpandSeries( FilingCabinet::GetDirInfo('videos')['extensions'],
        ".[[]],", true, ["sTemplateLast"=>".[[]]"] );
    $listVideos = "<div style='float:right;' id='uploadForm'>"
                    ."<form method='post' onsubmit='event.preventDefault();' enctype='multipart/form-data'>
                        Select video to upload:
                        <input type='file' name='".FilingCabinetUpload::fileid."' id='".FilingCabinetUpload::fileid."' accept='$acceptedExts'><br />
                        <span><input type='submit' id='upload-file-button' value='Upload Video' name='submit' onclick='submitForm(event)'></span> Max Upload size:".ini_get('upload_max_filesize')."b</form>"
                 ."</div><script src='".CATSDIR_JS."fileUpload.js'></script><link rel='stylesheet' href='".CATSDIR_CSS."fileUpload.css'><script>const upload = document.getElementById('uploadForm').innerHTML;</script>";

    $listVideos .= "<h3>View Uploaded Videos</h3>";
    $dirIterator = new DirectoryIterator(CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('videos')['directory']);
    if(iterator_count($dirIterator) == 2){
        $listVideos .= "<h2> No files in directory</h2>";
        goto brains;
    }

    $sFilter = SEEDInput_Str('resource-filter');

    $listVideos .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;'><form method='post'>"
        ."<input type='text' name='resource-filter' value='$sFilter'/> <input type='submit' value='Filter'/>"
        ."</form></div>";

        $listVideos .= "<table border='0'>";
        foreach ($dirIterator as $fileinfo) {
            if( $fileinfo->isDot() ) continue;

            if( $sFilter ) {
                if( stripos( $fileinfo->getFilename(), $sFilter ) === false )  continue;
            }

            $listVideos .= "<tr>"
                ."<td valign='top'>"
                    ."<a style='white-space: nowrap' href='?video_view=item&video_item=".pathinfo($fileinfo->getFilename(),PATHINFO_FILENAME)."' >"
                        .$fileinfo->getFilename()
                        ."</a>"
                            ."</td>"
                                ."</tr>";
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
                        // show file
                        return str_replace("[[video]]", CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('videos')['directory'].$file, $viewVideo);
                    }
                }
                $oApp->sess->VarUnSet("video_item");
            case "list":
                return $listVideos;
        }

}

class ResourcesFiles
{

    function DrawTags( ResourceRecord $oRR )
    {
        $s = "";

        $s .= "<div class='resources-tag resources-tag-new'>+</div>";

        $raTags = $oRR->getTags();
        foreach( $raTags as $tag ) {
            if( !$tag ) continue;
            $s .= "<div class='resources-tag resources-tag-filled'>$tag</div> ";
        }
        return( $s );
    }
}

?>