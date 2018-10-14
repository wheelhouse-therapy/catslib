<?php
require_once 'template_filler.php';

function ResourcesDownload( SEEDAppConsole $oApp, $dir_name )
/************************************************************
    Show the documents from the given directory, and if one is clicked download it through the template_filler
 */
{
    $s = "";

    $s .= "<style>
           .resources-files-tag { display:inline-block;
                                  font-size:9pt; background-color:#def; margin:0px 3px; padding:0px 3px;
                                  border:1px solid #aaa; border-radius:2px;
                                }
           </style>";

    $s .= <<<ResourcesFileScript
       <script>
       $(document).ready(function() {
           $('.resources-files-tag-new').click( function() {
               /* The [+] new-tag button opens an input control where the user can type a new tag
                */
               var tagNew = $("<form class='resources-files-tag-new-form' style='display:inline-block'>"
                             +"<input class='resources-files-tag' type='text' value='' placeholder='New tag'/>"
                             +"</form>" );

               /* Put the new-tag form in front of the [+] button and put focus on its input.
                * Apparently before() returns the unmodified jQuery i.e. $(this) so we have to use parent() to make this work.
                */
               $(this).before( tagNew ).parent().find('input').focus();
               $(this).parent().find('.resources-files-tag-new-form').submit(
                        function(e) {
                            e.preventDefault();
                            var v = $(this).find('input').val();
                            alert('Send the new tag ['+v+'] by ajax!');
                            $(this).html("<div class='resources-files-tag'>"+v+"</div>");
                        });
           });
       });
       </script>
ResourcesFileScript;

    if(!$dir_name){
        $s .= "Directory not specified";
        return;
    }
    if( SEEDInput_Str('cmd') == 'download' && ($file = SEEDInput_Str('file')) ) {
        $filler = new template_filler($oApp);
        $filler->fill_resource($file);
        exit;   // actually fill_resource exits, but it's nice to have a reminder of that here
    }

    $oResourcesFiles = new ResourcesFiles( $oApp );

    $folder = str_replace( '/', '', $dir_name );        // resources, handouts, etc, for looking up the related tags

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
    $s .= "<table border='0'>";
    foreach ($dir as $fileinfo) {
        if( $fileinfo->isDot() ) continue;
        $s .= "<tr>"
                 ."<td valign='top'>"
                     ."<a style='white-space: nowrap' href='javascript:void(0)' target='_blank' onclick=\"select_client('".$dir_name.$fileinfo->getFilename()."')\" >"
                         .$fileinfo->getFilename()
                     ."</a>"
                 ."</td>"
                 ."<td style='padding-left:20px' valign='top'>"
                     .$oResourcesFiles->DrawTags( $folder, $fileinfo->getFilename() )
                 ."</td>"
             ."</tr>";
    }
    $s .= "</table>";

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

        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM resources_files WHERE folder='".addslashes($folder)."' AND filename='".addslashes($filename)."'" );
        $raTags = explode( "\t", $ra['tags'] );
        foreach( $raTags as $tag ) {
            if( !$tag ) continue;
            $s .= "<div class='resources-files-tag'>$tag</div> ";
        }
        $s .= "<div class='resources-files-tag resources-files-tag-new'>+</div>";
        return( $s );
    }
}

?>