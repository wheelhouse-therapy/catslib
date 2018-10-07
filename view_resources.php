<?php
require_once 'template_filler.php';

function ResourcesDownload( SEEDAppConsole $oApp, $dir_name )
/************************************************************
    Show the documents from the given directory, and if one is clicked download it through the template_filler
 */
{
    $s = "";

    if(!$dir_name){
        $s .= "Directory not specified";
        return;
    }
    if( SEEDInput_Str('cmd') == 'download' && ($file = SEEDInput_Str('file')) ) {
        $filler = new template_filler($oApp);
        $filler->fill_resource($file);
        exit;   // actually fill_resource exits, but it's nice to have a reminder of that here
    }

    if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
        $dir_name = CATSDIR_RESOURCES.$dir_name;
    }
    if(!file_exists($dir_name)){
        $s .= "<h2>Unknown directory $dir_name</h2>";
        return;
    }

    $dir = new DirectoryIterator($dir_name);
    if(iterator_count($dir) == 2){
        $s .= "<h2> No files in directory</h2>";
        return;
    }
    if( !($oClinics = new Clinics($oApp)) || !($iClinic = $oClinics->GetCurrentClinic()) ) {
        return;
    }
    $clients = (new PeopleDB($oApp))->GetList( 'C', $oClinics->IsCoreClinic() ? "" : "clinic='$iClinic'");
    $s .= "<!-- the div that represents the modal dialog -->
            <div class=\"modal fade\" id=\"file_dialog\" role=\"dialog\">
                <div class=\"modal-dialog\">
                    <div class=\"modal-content\">
                        <div class=\"modal-header\">
                            <h4 class=\"modal-title\">Please select a client</h4>
                        </div>
                        <div class=\"modal-body\">
                            <form id='client_form'>
                                <input type='hidden' name='cmd' value='download' />
                                <input type='hidden' name='file' id='file' value='' />
                                <select name='client' required>
                                    <option selected value=''>Select a Client</option>"
                                .SEEDCore_ArrayExpandRows($clients, "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>")
                                ."</select>
                            </form>
                        </div>
                        <div class=\"modal-footer\">
                            <input type='submit' value='Download' form='client_form' />
                        </div>
                    </div>
                </div>
            </div>";
    foreach ($dir as $fileinfo) {
        if (!$fileinfo->isDot()) {
            $s .= "<a href='javascript:void(0)' target='_blank' onclick=\"select_client('".$dir_name.$fileinfo->getFilename()."')\" >".$fileinfo->getFilename()."</a><br />";
        }
    };

    $s .= "<script>
            function select_client(file){
                document.getElementById('file').value = file;
                $('#file_dialog').modal('show');
            }
            $(document).ready(function () {
                $(\"#client_form\").on(\"submit\", function() {
                    $('#file_dialog').modal('hide');
                });
                $(\"#file_dialog\").on(\"hidden.bs.modal\", function(){
                    document.getElementById('client_form').reset();
                });
            });
           </script>";

    return( $s );
}

?>