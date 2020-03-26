<?php

require_once 'template_filler.php';
require_once 'share_resources.php';

/**Array of valid Download Modes
 *
 * This array contains information for the various download modes, indexed by their respective internal access code
 * The internal access code is one letter long and is use in $download_modes for ResourcesDownload().
 * Each letter in that string is parsed to determine the modes which are applicable for the current folder.
 *
 * The 'code' entry in the array defines the code used when changing mode via HTTP.
 * The 'title' entry in the array defines the button label displayed over HTTP
 */
$MODES = array('s' => array("code" => "replace"   , "title" => "Substitution Mode"    ),
               'n' => array("code" => "no_replace", "title" => "Tagged Version" ),
               'b' => array("code" => "blank"     , "title" => "Blank Mode"           )

);

function FilingCabinet( SEEDAppConsole $oApp )
{
    $s = "";

    global $directories;

    if( ($dir = SEEDInput_Str('dir')) && (@$directories[$dir]) ) {
        $s .= ResourcesDownload( $oApp, $directories[$dir]['directory'] );
    } else {
        $s .= "<h3>Filing Cabinet</h3>";
        foreach( $directories as $k => $ra ) {
            $bgcolor = "background-color: grey;";
            if (array_key_exists("color", $ra)) {
                $bgcolor = "background-color: {$ra['color']};";
            }
            $s .= "<p><div style='{$bgcolor} display: inline-block; min-width: 500px; text-align: center'><a style='font-size: 18pt; color: #fff' href='?dir={$k}'>{$ra['name']}</a></div></p>";
        }
    }
    return( $s );
}


function ResourcesDownload( SEEDAppConsole $oApp, $dir_name, $download_modes = "snb" )
/************************************************************
    Show the documents from the given directory, and if one is clicked download it through the template_filler
 */
{
    global $MODES;
    $s = "";

    $s .= <<<ResourcesTagStyle
        <style>
            /* Every resources tag and control
             */
            .resources-tag {
                    display:inline-block;
                    font-size:9pt; background-color:#def; margin:0px 2px; padding:0px 3px;
                    border:1px solid #aaa; border-radius:2px;
                  }
            /* [+] new tag button
             */
            .resources-tag-new {
                  }
            /* New tag input control and containing form
             */
            .resources-tag-new-form {
                     display:inline-block;
                  }
            .resources-tag-new-input {
                  }
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
        $(document).ready(function() {
            $('.resources-tag-new').click( function() {
                /* If someone clicks on the new tag button while adding another tag
                 * The Old behavior caused the old tag to not be submitted.
                 * This method prevents that from hapening
                 */
                $('.resources-tag-new-form').each(function(){
                    $(this).submit();
                });
                /* The [+] new-tag button opens an input control where the user can type a new tag
                 */
                var tagNew = $("<form class='resources-tag-new-form'>"
                              +"<input class='resources-tag-new-input resources-tag' type='text' value='' placeholder='New tag' />"
                              +"</form>" );

                /* Put the new-tag form after the [+] button and put focus on its input.
                 * Apparently after() returns the unmodified jQuery i.e. $(this) so we have to use parent().
                 */
                $(this).after( tagNew );
                $(this).parent().find('.resources-tag-new-input').focus();
                /* When the user types something and hits Enter, send their text to the server and draw the new tag
                 * (it will be drawn by the server when the page is refreshed).
                 */
                $(this).parent().find('.resources-tag-new-form').submit(
                    function(e) {
                        e.preventDefault();
                        var tag = $(this).find('input').val();
                        var folder = $(this).parent().data('folder');
                        var filename = $(this).parent().data('filename');
                        SEEDJXAsync( "jx.php", {cmd:"resourcestag--newtag",folder:folder,filename:filename,tag:tag}, function(){}, function(){} );
/* Todo: this puts the tag in place, and it should look the same when the server draws it on the next page refresh.
         But, this is putting the tag into the <form> which isn't there after the page refresh so the spacing is just a little off.
         Replace the <form> with the div.resources-tag, not just its innerhtml.
*/
                        $(this).html("<div class='resources-tag'>"+tag+"</div>");
                    });
            });
        });
        </script>
ResourcesTagScript;

    $resourceMode = <<<DownloadMode
        <div id='break'>
        <div class='alert alert-info' style='[display] flex-basis: 75%; min-height: 50px;'>Some files cannot be downloaded in the current mode. <a class='alert-link' href='?resource-mode=no_replace'>Click Here to view all files</a></div>
        <div id='ResourceMode'>
            <div id='modeText'><div data-tooltip='[tooltip]'><nobr>Current Mode:</nobr> [mode]</div></div>
            [[button1]]
            [[button2]]
        </div>
        </div>
DownloadMode;
    if( count($MODES) >= strlen($download_modes) && strlen($download_modes) > 1){
        $mode = $oApp->sess->SmartGPC("resource-mode");
        switch ($mode){
            case $MODES['s']['code']:
                $tooltip = "Program replaces tags with data";
                $resourceMode = str_replace("[mode]", "Substitution", $resourceMode);
                $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
                break;
            case $MODES['n']['code']:
                $tooltip = "Download files with the substitution tags";
                $resourceMode = str_replace("[mode]", $MODES['n']['title'], $resourceMode);
                $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
                break;
            case $MODES['b']['code']:
                $tooltip = "No tags or data.<br />Use this if you are stocking your paper filing cabinet with a handout";
                $resourceMode = str_replace("[mode]", "Blank", $resourceMode);
                $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
                break;
        }
        $s .= getModeOptions($resourceMode, $download_modes, $mode, $dir_name);
    }
    else if(strlen($download_modes) == 1){
        $mode = $MODES[$download_modes]['code'];
    }
    if(!$dir_name){
        $s .= "Directory not specified";
        return;
    }
    if( SEEDInput_Str('cmd') == 'download' && ($file = SEEDInput_Str('file')) ) {
        if($mode!="no_replace"){
            $filler = new template_filler($oApp, @$_REQUEST['assessments']?:array());
            $filler->fill_resource($file);
        }
        else{
             header('Content-Type: '.(@mime_content_type($file)?:"application/octet-stream"));
             header('Content-Description: File Transfer');
             header('Content-Disposition: attachment; filename="' . basename($file) . '"');
             header('Content-Transfer-Encoding: binary');
             if( ($fp = fopen( $file, "rb" )) ) {
                 fpassthru( $fp );
                 fclose( $fp );
             }
            die();
        }
        exit;   // actually fill_resource exits, but it's nice to have a reminder of that here
    }

    $oResourcesFiles = new ResourcesFiles( $oApp );

    $folder = str_replace( '/', '', $dir_name );        // resources, handouts, etc, for looking up the related tags

    // make sure dir_name is the full path
    $dir_short = trim($dir_name,'/');
    if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
        $dir_name = CATSDIR_RESOURCES.$dir_name;
    }
    if(!file_exists($dir_name)){
        $s .= "<h2>Unknown directory $dir_name</h2>";
        return $s;
    }

    $s .= "<a href='".CATSDIR_DOCUMENTATION."Template%20Format%20Reference.html'>Template Format Reference</a><br />";

    $dir = new DirectoryIterator($dir_name);
    if(iterator_count($dir) == 2){
        $s .= "<h2> No files in directory</h2>";
        return $s;
    }
    if( !($oClinics = new Clinics($oApp)) || !($iClinic = $oClinics->GetCurrentClinic()) ) {
        return;
    }
    $clients = (new PeopleDB($oApp))->GetList( 'C', $oClinics->IsCoreClinic() ? "" : "clinic='$iClinic'", array("sSortCol" => "P_first_name,_key"));
    $s .= " <!-- the div that represents the modal dialog -->
            <div class='modal fade' id='file_dialog' role='dialog'>
                <div class='modal-dialog'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h4 class='modal-title'>Please select a client</h4>
                        </div>
                        <div class='modal-body'>
                            <form id='client_form' onsubmit='modalSubmit(event)'>
                                <input type='hidden' name='cmd' value='download' />
                                <input type='hidden' name='dir' id='dir' value='$dir_short' />
                                <input type='hidden' name='file' id='file' value='' />
                                <select name='client' required>
                                    <option selected value=''>Select a Client</option>"
                                .SEEDCore_ArrayExpandRows($clients, "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>")
                                ."</select>
                            </form>
                        </div>
                        <div class='modal-footer'>
                            <input type='submit' id='submitVal' value='".(CATSDIR_RESOURCES.$GLOBALS['directories']['reports']['directory'] == $dir_name?"Next":"Download")."' form='client_form' />
                        </div>
                    </div>
                </div>
            </div>";

    $sFilter = SEEDInput_Str('resource-filter');

    $s .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;'><form method='post'>"
         ."<input type='text' name='resource-filter' value='$sFilter'/> <input type='submit' value='Filter'/>"
         ."</form></div>";

    $s .= "<table border='0'>";
    $sTemplate = "<tr [[CLASS]]>
                    <td valign='top'>
                        <a style='white-space: nowrap' [[LINK]] >
                            [[FILENAME]]
                        </a>
                    </td>
                    <td style='padding-left:20px' valign='top' data-folder='".SEEDCore_HSC($folder)."' data-filename='[[FILENAME]]'>
                        [[TAGS]]
                    </td>
                  </tr>
                 ";
    foreach ($dir as $fileinfo) {
        $class = "";
        $link = NULL;
        if( $fileinfo->isDot() ) continue;

        if( $sFilter ) {
            if( stripos( $fileinfo->getFilename(), $sFilter ) !== false )  goto found;
            $dbFilename = addslashes($fileinfo->getFilename());
            $dbFilter = addslashes($sFilter);
            if( $oApp->kfdb->Query1( "SELECT _key FROM resources_files "
                                    ."WHERE folder='$folder' AND filename='$dbFilename' AND tags LIKE '%$dbFilter%'" ) ) goto found;
            continue;
        }

        if($mode!='no_replace' && $fileinfo->getExtension()!="docx"){
            $s = str_replace("[display]", "display:inline-block;", $s);
            $class = "class='btn disabled'";
            if(stripos($download_modes, 'n') !== false){
                $link = "href='?resource-mode=".$MODES['n']['code']."'";
            }
            else{
                $link = "";
            }
        }

        found:
        $oApp->kfdb->SetDebug(0);

         $link = ($link !== NULL?$link:downloadPath($mode, $dir_name, $fileinfo, $dir_short));
         $filename = SEEDCore_HSC($fileinfo->getFilename());
         $tags = $oResourcesFiles->DrawTags( $folder, $fileinfo->getFilename() );
         $s .= str_replace(array("[[CLASS]]","[[LINK]]","[[FILENAME]]","[[TAGS]]"), array($class,$link,$filename,$tags), $sTemplate);
    }
    $s .= "</table>";

    //Replace the display if it has not already been replaced
    $s = str_replace("[display]", "display:none;", $s);

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

    return( $s );
}

function getModeOptions($resourcesMode, $downloadModes, $mode, $dir){
    global $MODES;
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

function downloadPath($mode, $dir_name, $fileinfo, $dir_short){
    switch($mode){
        case 'replace':
            return "href='javascript:void(0)' onclick=\"select_client('".$dir_name.$fileinfo->getFilename()."')\"";
        case 'no_replace':
            return "href='?cmd=download&file=".$dir_name.$fileinfo->getFilename()."&resource-mode=no_replace&dir=$dir_short'";
        case 'blank':
            return "href='?cmd=download&file=".$dir_name.$fileinfo->getFilename()."&client=0&dir=$dir_short'";
    }
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
    ensureDirectory("SOP");
    $listSOPs = "<h3>View Standard Operating Procedures</h3>";
    $dir = new DirectoryIterator(CATSDIR_RESOURCES.$GLOBALS['directories']['SOP']['directory']);
    if(iterator_count($dir) == 2){
        $listSOPs .= "<h2> No files in directory</h2>";
        goto brains;
    }

    $sFilter = SEEDInput_Str('resource-filter');

    $listSOPs .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;'><form method='post'>"
               ."<input type='text' name='resource-filter' value='$sFilter'/> <input type='submit' value='Filter'/>"
               ."</form></div>";

    $listSOPs .= "<table border='0'>";
    foreach ($dir as $fileinfo) {
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
            foreach (array_diff(scandir(CATSDIR_RESOURCES.$GLOBALS['directories']['SOP']['directory']), array('..', '.')) as $file){
                if(pathinfo($file,PATHINFO_FILENAME) == $item){
                    // show file
                    return str_replace("[[SOP]]", CATSDIR_RESOURCES.$GLOBALS['directories']['SOP']['directory'].$file, $viewSOP);
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
    ensureDirectory("videos");
    $listVideos = "<h3>View Uploaded Videos</h3>";
    $dir = new DirectoryIterator(CATSDIR_RESOURCES.$GLOBALS['directories']['videos']['directory']);
    if(iterator_count($dir) == 2){
        $listVideos .= "<h2> No files in directory</h2>";
        goto brains;
    }

    $sFilter = SEEDInput_Str('resource-filter');

    $listVideos .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;'><form method='post'>"
        ."<input type='text' name='resource-filter' value='$sFilter'/> <input type='submit' value='Filter'/>"
        ."</form></div>";

        $listVideos .= "<table border='0'>";
        foreach ($dir as $fileinfo) {
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
                foreach (array_diff(scandir(CATSDIR_RESOURCES.$GLOBALS['directories']['videos']['directory']), array('..', '.')) as $file){
                    if(pathinfo($file,PATHINFO_FILENAME) == $item){
                        // show file
                        return str_replace("[[video]]", CATSDIR_RESOURCES.$GLOBALS['directories']['videos']['directory'].$file, $viewVideo);
                    }
                }
                $oApp->sess->VarUnSet("video_item");
            case "list":
                return $listVideos;
        }

}

class ResourcesFiles
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function DrawTags( $folder, $filename )
    {
        $s = "";

        $s .= "<div class='resources-tag resources-tag-new'>+</div>";

        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM resources_files WHERE folder='".addslashes($folder)."' AND filename='".addslashes($filename)."'" );
        $raTags = explode( "\t", $ra['tags'] );
        foreach( $raTags as $tag ) {
            if( !$tag ) continue;
            $s .= "<div class='resources-tag'>$tag</div> ";
        }
        return( $s );
    }
}

?>