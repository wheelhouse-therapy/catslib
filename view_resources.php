<?php

require_once 'template_filler.php';

function ResourcesDownload( SEEDAppConsole $oApp, $dir_name, $sCabinet = 'general' )
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

    $oFCD = new FilingCabinetDownload( $oApp );

// TODO: the Filing Cabinet handles its own download cmd but this is still used by Reports (which should use DrawFilingCabinet('reports/') instead some day)
    if( SEEDInput_Str('cmd') == 'download' || SEEDInput_Str('cmd') == 'view' ) {
        $oFCD->DownloadFile();
        exit;
    }

    if( $sCabinet != 'videos' ) {
        if(isset($_SESSION['mailResult'])){
            if($_SESSION['mailResult']){
                $s .= "<div class='alert alert-success alert-dismissible'>Email Sent Successfully to {$_SESSION['mailTarget']}!</div>";
            }
            else{
                $s .= "<div class='alert alert-danger alert-dismissible'>Could Not Send Email</div>";
            }
            unset($_SESSION['mailResult']);
            unset($_SESSION['mailTarget']);
            $s .= "<script>hideAlerts()</script>";
        }

        // make sure dir_name is the full path
        if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
            $dir_name = CATSDIR_RESOURCES.$dir_name;
        }
        if(!file_exists($dir_name)){
            $s .= "<h2>Unknown directory $dir_name</h2>";
            return $s;
        }

        if(ResourceRecord::GetRecordFromPath($oApp, $sCabinet, $dir_short, ResourceRecord::WILDCARD,ResourceRecord::WILDCARD) == NULL){
            $s .= "<h2> No files in drawer</h2>";
            return $s;
        }
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
                                <input type='hidden' name='rr' id='rr' value='' />
                                <select name='client' id='fcd_clientSelector' required onchange='emailAvalible()'>
                                    <option selected value=''>Select a Client</option>"
                                .SEEDCore_ArrayExpandRows($clients, "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>")
                                ."</select>
                                <br /><span id='filename'></span>
                            </div><div class='col-sm-6'>
                                <div class='filingcabinetdownload_downloadmodeselector' style='font-size:small'>
                                    <p style='margin-left:20px' [[title]] id='emailTitle'><input type='radio' name='resource-mode' namex='fcd_downloadmode' id='email' value='email' onclick='fcd_clientselect_enable(true);' [[disabled]] id='email' onchange='buttonUpdate(\"Email\")' />Substitute client details and email</p>
                                    <p style='margin-left:20px'><input type='radio' name='resource-mode' namex='fcd_downloadmode' value='replace' onclick='fcd_clientselect_enable(true);' checked id='replace' onchange='buttonUpdate(\"default\")' />Substitute client details into document</p>
                                    <p style='margin-left:20px'><input type='radio' name='resource-mode' namex='fcd_downloadmode' value='no_replace' onclick='fcd_clientselect_enable(false);'  onchange='buttonUpdate(\"Download\",true)' />Save file with no substitution (with tags)</p>
                                    <p style='margin-left:20px'><input type='radio' name='resource-mode' namex='fcd_downloadmode' value='blank' onclick='fcd_clientselect_enable(false);'  onchange='buttonUpdate(\"Download\",true)' />Fill document tags with blanks</p>
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
    $s .= "<!-- the div that represents the modal dialog -->
            <div class='modal fade' id='pdf_dialog' role='dialog'>
                <div class='modal-dialog'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h4 class='modal-title'>Please select an option</h4>
                        </div>
                        <div class='modal-body'>
                            <span id='pdfname'></span>
                        </div>
                        <div class='modal-footer'>
                            <a id='view' target='_blank' href=''><button>View</button></a>
                            <a id='download' href=''><button>Download</button></a>
                        </div>
                    </div>
                </div>
            </div>";

    $manageUsers = new ManageUsers($oApp);
    $kfr = $manageUsers->getClinicRecord($oApp->sess->GetUID());
    if($dir_short == 'reports'){
        // Disable Email Option on Reports screen
        $s = str_replace(["[[title]]","[[disabled]]"], ["title='Option disabled when printing reports'","disabled"], $s);
    }
    else if(!CATS_DEBUG && $kfr && $kfr->Value('P_email')){
        $s = str_replace(["[[title]]","[[disabled]]"], "", $s);
    }
// yeah but what if I want to see how it composes the email? Instead I just put CATS_DEBUG around the mail() function
//    else if(CATS_DEBUG){
//        // Developer mechines aren't configured to talk with SMTP servers.
//        $s = str_replace(["[[title]]","[[disabled]]"], ["title='Option disabled on dev machines'","disabled"], $s);
//    }
    else if(!CATS_DEBUG) {
        $s = str_replace(["[[title]]","[[disabled]]"], ["title='Your account lacks an email address'","disabled"], $s);
    }

    $sFilter = SEEDInput_Str('resource-filter');

    $s .= "<div style='background-color:#def;margin:auto;padding:10px;position:relative;'><form method='post'>"
         ."<input type='text' name='resource-filter' value='".SEEDCore_HSC($sFilter)."'/>"
         ."<input type='hidden' name='dir' value='$dir_short' />"
         ."<input type='submit' value='Filter'/>"
         ."</form></div>";

    $raOut = [];
    foreach(FilingCabinet::GetSubFolders($dir_short, $sCabinet) as $folder){
        $raOut += [$folder=>""];
    }
    $raOut += [''=>""];

    if( $sCabinet == 'videos' ) {
        // videos are stored by _key in the videos directory, so look up folders/subfolders via database
        $raRR = ResourceRecord::GetRecordFromPath($oApp, $sCabinet, $dir_short, ResourceRecord::WILDCARD, ResourceRecord::WILDCARD);
        if( is_array($raRR) ) {
            foreach( $raRR as $oRR ) {
                $raOut = addFileToSubfolderVideos( $oApp, $oRR, $sFilter, $raOut, $oFCD, $sCabinet );
            }
        } else if( $raRR ) {
            $oRR = $raRR;
            $raOut = addFileToSubfolderVideos( $oApp, $oRR, $sFilter, $raOut, $oFCD, $sCabinet );
        }

    } else {
        // non-videos are stored as a filesystem
        $raRR = ResourceRecord::GetResources($oApp, $sCabinet, $dir_short);
        foreach ($raRR as $oRR) {
            $raOut = addFileToSubfolder( $oRR, $sFilter, $raOut, $oApp, $dir_short, "", $oFCD, $sCabinet );
        }

        foreach(FilingCabinet::GetSubFolders($dir_short) as $subfolder) {
            if(!file_exists($dir_name.$subfolder)) continue;
            $raRRSub = ResourceRecord::GetResources($oApp, $sCabinet, $dir_short,$subfolder);
            foreach( $raRRSub as $oRR ) {
                $raOut = addFileToSubfolder( $oRR, $sFilter, $raOut, $oApp, $dir_short.'/'.$subfolder, $subfolder, $oFCD, $sCabinet );
            }
        }
    }

    $s .= "<div>";
    foreach($raOut as $k=>$v){
        if($k){
            $new = FALSE;
            $newness = 0;
            foreach( ResourceRecord::GetResources($oApp, $sCabinet, $dir_short, $k) as $rr){
                if($rr->isNewResource()){
                    $new = TRUE;
                }
                if($rr->getNewness() > $newness){
                    $newness = $rr->getNewness();
                }
            }
            $sBadge = $new?"<span class='badge badge{$newness}'> </span>":"";
            $s.= "<div class='folder' style='position:relative;'>{$sBadge}<div class='folder-title' onclick='toggleCollapse(this)' data-folder='$k'><i class='far fa-folder-open folder-icon'></i><span class='folder-title-span'><strong>$k</strong></span></div>";
        }
        else if(count($raOut) > 1 && $v){
            $new = FALSE;
            $newness = 0;
            foreach( ResourceRecord::GetResources($oApp, $sCabinet, $dir_short,"") as $rr){
                if($rr->isNewResource()){
                    $new = TRUE;
                }
                if($rr->getNewness() > $newness){
                    $newness = $rr->getNewness();
                }
            }
            $sBadge = $new?"<span class='badge badge{$newness}'> </span>":"";
            $s.= "<div class='folder' style='position:relative;'>{$sBadge}<div class='folder-title' onclick='toggleCollapse(this)' data-folder='other'><i class='far fa-folder-open folder-icon'></i><span class='folder-title-span'><strong>Loose Files</strong></span></div>";
        }
        if($v){
            $id = "";
            if($k){
                $id = $k;
            }
            else{
                $id = "other";
            }
            $s.= "<div class='folder-contents' ontransitionend='clearHeight(event)' id='$id'>".$v."</div>";
        }
        else if($k){
            $s .= "<div class='folder-contents' ontransitionend='clearHeight(event)' id='$k'>Empty Folder</div>";
        }
        if($k){
            $s .= "</div>";
        }
        else if(count($raOut) > 1){
            $s .= "</div>";
        }
    }
    $s .= "</div>";
    
    $s .= <<<ContextMenu
<nav id="context-menu" class="context-menu">
    <ul class="context-menu__items">
        <li class="context-menu__item">
            <a href="#" class="context-menu__button" data-action="rename">Rename</a>
        </li>
        <li class="context-menu__item">
            <a href="#" class="context-menu__button" data-action="delete">Delete</a>
        </li>
        <li class="context-menu__item">
            <a href="#" class="context-menu__button" data-action="reorder-left">Reorder Left</a>
        </li>
        <li class="context-menu__item">
            <a href="#" class="context-menu__button" data-action="reorder-right">Reorder Right</a>
        </li>
    </ul>
</nav>
ContextMenu;
    
    $s .= "<script src='".CATSDIR_JS."rightClickMenu.js'></script>
            <link rel='stylesheet' href='".CATSDIR_CSS."rightClickMenu.css'>
            <script>
            const modal = document.getElementById('file_dialog').innerHTML;
            const disabledByServer = document.getElementById('email').disabled;
            const buttonValue = document.getElementById('submitVal').value;
            function select_client(rr,name){
                document.getElementById('file_dialog').innerHTML = modal;
                document.getElementById('rr').value = rr;
                document.getElementById('filename').innerHTML = name;
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
            function fcd_clientselect_enable( bEnable )
            {
                $('#fcd_clientSelector').prop('disabled', !bEnable);
            }
            function emailAvalible(){
                if(disabledByServer){
                    return;
                }
                let select = document.getElementById('fcd_clientSelector');
                kClient = select.options[select.selectedIndex].value;
                $.ajax({
                    type: \"POST\",
                    data: {cmd:'therapist-fcd-canEmail',client:kClient},
                    url: 'jx.php',
                    success: function(data, textStatus, jqXHR) {
                        var jsData = JSON.parse(data);
                        if(jsData.bOk){
                            $('#email').prop('disabled', false);
                            $('#emailTitle').prop('title', '');
                        }
                        else{
                            $('#email').prop('disabled', true);
                            $('#emailTitle').prop('title', 'The selected client does not have an email address stored');
                            $('#replace').prop('checked', true);
                        }
                    },
                    error: function(jqXHR, status, error) {
                        console.log(status + \": \" + error);
                    }
                });
            }
            function buttonUpdate(value,override=false){
                if(buttonValue == 'Download' || override){
                    if(value == 'default'){
                        value = buttonValue
                    }
                    document.getElementById('submitVal').value = value;
                }
                else{
                    document.getElementById('submitVal').value = buttonValue;
                }
            }
            function viewPDF(rr,name){
                document.getElementById('view').href = '?cmd=view&rr='+rr;
                document.getElementById('download').href = '?cmd=download&rr='+rr+'&resource-mode=no_replace';
                document.getElementById('pdfname').innerHTML = name;
                $('#pdf_dialog').modal('show');
            }
            ".((SEEDInput_Int("rr")&&ResourceRecord::GetRecordByID($oApp, SEEDInput_Int('rr'))->templateFillerSupported())?"$(document).ready(function() {select_client(".SEEDInput_Int("rr").",'".ResourceRecord::GetRecordByID($oApp, SEEDInput_Int('rr'))->getFile()."');});":"")."
            </script>";

    done:
    return( $s );
}

function addFileToSubfolder(ResourceRecord $oRR, $sFilter, $raOut, $oApp, $dir_short, $kSubfolder,FilingCabinetDownload $oFCD, $sCabinet )
{

        if( $sFilter ) {
            // list this file if sFilter matches part of its filename, or part of one of its tags
            if( stripos( $oRR->getFile(), $sFilter ) === false &&
                !in_array($sFilter, $oRR->getTags()))
            {
                goto done;
            }
        }

        // docx files get a link to the modal dialog; other files get a link for simple download
        if( $oRR->templateFillerSupported() ) {
            $link = $oFCD->GetDownloadPath('replace', $oRR, $oRR->getFile(), $dir_short );
        } else {
            $link = $oFCD->GetDownloadPath("no_replace", $oRR );
        }

        $sTemplate =
              "<div class='file-preview-container contextable' id='[[ID]]' data-tooltip='".addslashes($oRR->getDescription())."'>
                  [[BADGE]]
                  <a style='' [[LINK]] >
                    <div>
                        <img src='data:image/jpg;base64,[[PREVIEW]]' style='width:100%;max-width:200px;padding-bottom:2px' />
                    </div>
                    <div>
                        [[FILENAME]]
                    </div>
                </a>
                <div data-id='".$oRR->getID()."'>
                    [[TAGS]]
                </div>
            </div>";
            // Fallback if a Preview doesn't exist
        $preview = "iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAYAAABw4pVUAAAABmJLR0QAAAAAAAD5Q7t/AAAACXBIWXMAAA7CAAAOwgEVKEqAAAAAB3RJTUUH5AseAS4F/Lh1HQAAAsdJREFUeNrtnOtyhCAMRtHS93/gtmt/OWOzIoSEi/Z8M53p7CKXHBMQsi4hhC2gabRiAoCgC0X5wbYRwXpqWRY8hJCFAAIQBBCAIIAABAEEAQQgCCAAQQB5nOT2+ysH6bhdfLZVn/se6TzkR3Ox3MtHvkC2EMIXJrk5kFovwbvyc8hLG7KOxi2ZLyQE5pv8HPLq1bAEgMe8A1lCCB+1Rs0Z9Mwb8ApnIDVGleWBkgayhhA+LZURdvznkOgxH6DBQCxehFddA6m2Ts5L5AJg/8PLroG4LmVLvwfGX4/YMM5AAOT2/qOQhQACEAQQgCCAIIAABAEEIAggz5L6/CN3fjHT5uQdt/fXlkZAHTwk5Q1HEKV5Wq11x6OEdcTgr7xIniT2CGszeXXsNegd2v6/9KLUWXuqTCrRTlu+5nptW9OuslJ3ohxMi4F6tSdDs3eYjC0MfNWp0u+O3pRqey9fG3I07d12ldV6Iq2F3XL16AkyzmT8moFZjFFyrcZzPOwRw0TSDsg6v9S2dwyV3mFu6q2T1IBbhaYZlsDqH32OWIWNbq8kbHndJNL4Qx5tayZquUK6w8Kg6CYREL63bZtqXnm6cpmL7Aw+7TkE+QIB0GRACFmELAQQgCCAAAQBBCCotcz7Vr2T0c7as/RBc22Psa5exkGTeIgENPJMnTmkYVjy9D5tfZbXFlr7HS2DPN61ZwlwlsS2kjKamJ+qL1U+V9bSTlMP8Tp9K01ea12f9tWD3v3ulttbkvEnB2Ttj7Y+y6sHLUfKZiA171fUQBw9h4xcPLg+h1jKey+hZ81gcQdS2vDZErjEc6wxuHV9rT3KPZU0NT/c7SHS2t/alVb0viOsxh8ZuixZkl79XlsaKxWizgZZ+pnlhmmRIOddJ6/4myw0sv0+mQACEAQQgCCAAAQBBCAIIABBAEEAuYvednsRHoIAAhBUqF9Nt5gKDugLygAAAABJRU5ErkJggg==";
        if($oRR->getPreview()){
            // a preview exists use it instead of the fallback
            $preview = $oRR->getPreview();
        }

        $filename = SEEDCore_HSC($oRR->getFile());

        $raOut[$kSubfolder] .= str_replace( ["[[ID]]","[[LINK]]","[[FILENAME]]","[[TAGS]]","[[PREVIEW]]","[[BADGE]]"],
                                           [$oRR->getID(),
                                            $link,
                                            $filename,
                                            $oFCD->oResourcesFiles->DrawTags($oRR),
                                            $preview,
                                            $oRR->isNewResource()?"<span class='badge badge{$oRR->getNewness()}'> </span>":""
                                           ],
                                           $sTemplate);

        done:
        return( $raOut );
}

function addFileToSubfolderVideos( SEEDAppConsole $oApp, ResourceRecord $oRR, $sFilter, $raOut, FilingCabinetDownload $oFCD, $sCabinet )
{
    if( $sFilter ) {
        // list this file if sFilter matches part of its filename, or part of one of its tags
        if( stripos( $oRR->getFilename(), $sFilter ) === false &&
            !in_array($sFilter, $oRR->getTags()))
        {
            goto done;
        }
    }

    // docx files get a link to the modal dialog; other files get a link for simple download
    $link = $oFCD->GetDownloadPath( $oRR->templateFillerSupported() ? 'replace' : "no_replace", $oRR );
    $link = "href='?cmd=viewVideo&rr={$oRR->getId()}'";

    $sTemplate =
          "<div class='file-preview-container contextable' id='[[ID]]' data-tooltip='".addslashes($oRR->getDescription())."'>
              [[BADGE]]
              <a style='' [[LINK]] >
                <div>
                    <img src='data:image/jpg;base64,[[PREVIEW]]' style='width:100%;max-width:200px;padding-bottom:2px' />
                </div>
                <div>
                    [[FILENAME]]
                </div>
            </a>
            <div data-id='".$oRR->getID()."'>
                [[TAGS]]
            </div>
        </div>";

    // Fallback if a Preview doesn't exist
    $preview = "iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAYAAABw4pVUAAAABmJLR0QAAAAAAAD5Q7t/AAAACXBIWXMAAA7CAAAOwgEVKEqAAAAAB3RJTUUH5AseAS4F/Lh1HQAAAsdJREFUeNrtnOtyhCAMRtHS93/gtmt/OWOzIoSEi/Z8M53p7CKXHBMQsi4hhC2gabRiAoCgC0X5wbYRwXpqWRY8hJCFAAIQBBCAIIAABAEEAQQgCCAAQQB5nOT2+ysH6bhdfLZVn/se6TzkR3Ox3MtHvkC2EMIXJrk5kFovwbvyc8hLG7KOxi2ZLyQE5pv8HPLq1bAEgMe8A1lCCB+1Rs0Z9Mwb8ApnIDVGleWBkgayhhA+LZURdvznkOgxH6DBQCxehFddA6m2Ts5L5AJg/8PLroG4LmVLvwfGX4/YMM5AAOT2/qOQhQACEAQQgCCAIIAABAEEIAggz5L6/CN3fjHT5uQdt/fXlkZAHTwk5Q1HEKV5Wq11x6OEdcTgr7xIniT2CGszeXXsNegd2v6/9KLUWXuqTCrRTlu+5nptW9OuslJ3ohxMi4F6tSdDs3eYjC0MfNWp0u+O3pRqey9fG3I07d12ldV6Iq2F3XL16AkyzmT8moFZjFFyrcZzPOwRw0TSDsg6v9S2dwyV3mFu6q2T1IBbhaYZlsDqH32OWIWNbq8kbHndJNL4Qx5tayZquUK6w8Kg6CYREL63bZtqXnm6cpmL7Aw+7TkE+QIB0GRACFmELAQQgCCAAAQBBCCotcz7Vr2T0c7as/RBc22Psa5exkGTeIgENPJMnTmkYVjy9D5tfZbXFlr7HS2DPN61ZwlwlsS2kjKamJ+qL1U+V9bSTlMP8Tp9K01ea12f9tWD3v3ulttbkvEnB2Ttj7Y+y6sHLUfKZiA171fUQBw9h4xcPLg+h1jKey+hZ81gcQdS2vDZErjEc6wxuHV9rT3KPZU0NT/c7SHS2t/alVb0viOsxh8ZuixZkl79XlsaKxWizgZZ+pnlhmmRIOddJ6/4myw0sv0+mQACEAQQgCCAAAQBBCAIIABBAEEAuYvednsRHoIAAhBUqF9Nt5gKDugLygAAAABJRU5ErkJggg==";
    if($oRR->getPreview()){
        // a preview exists use it instead of the fallback
        $preview = $oRR->getPreview();
    }

    $raOut[$oRR->getSubDirectory()] .= str_replace( ["[[ID]]","[[LINK]]","[[FILENAME]]","[[TAGS]]","[[PREVIEW]]","[[BADGE]]"],
                                       [$oRR->getID(),
                                        $link,
                                        SEEDCore_HSC($oRR->getFile()),
                                        $oFCD->oResourcesFiles->DrawTags($oRR),
                                        $preview,
                                        $oRR->isNewResource()?"<span class='badge badge{$oRR->getNewness()}'> </span>":""
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