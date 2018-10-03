<?php
require_once 'template_filler.php';
if(!$dir_name){
    $s .= "Directory not specified";
    return;
}
$cmd = SEEDInput_Str('cmd');
if($cmd == 'download'){
    $file = SEEDInput_Str('file');
    $filler = new template_filler($this->oApp);
    $filler->fill_resource($file);
}
if(substr_count($dir_name, CATSDIR_RESOURCES) == 0){
    $dir_name = CATSDIR_RESOURCES.$dir_name;
}
if(!file_exists($dir_name)){
    $s .= "<h2> No files in directory</h2>";
    return;
}
$dir = new DirectoryIterator($dir_name);
if(iterator_count($dir) == 2){
    $s .= "<h2> No files in directory</h2>";
    return;
}
$clinic = (new Clinics($this->oApp))->GetCurrentClinic();
$clients = (new PeopleDB($this->oApp))->KFRel("C")->GetRecordSetRA("clinic='$clinic'");
$s .= "<!-- the div that represents the modal dialog -->
        <div class=\"modal fade\" id=\"file_dialog\" role=\"dialog\">
            <div class=\"modal-dialog\">
                <div class=\"modal-content\">
                    <div class=\"modal-header\">
                        <h4 class=\"modal-title\">You need to select a client to download</h4>
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
        $s .= "<a href='javascript:void(0)' onclick=\"select_client('".$dir_name.$fileinfo->getFilename()."')\" >".$fileinfo->getFilename()."</a><br />";
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
       </script>"

?>