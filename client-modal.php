<?php

function drawModal($ra, $oPeopleDB, $pro_roles_name){
    $s = "
        <!-- the div that represents the modal dialog -->
        <div class=\"modal fade\" id=\"contact_dialog\" role=\"dialog\">
            <div class=\"modal-dialog\">
                <div class=\"modal-content\">
                    <div class=\"modal-header\">
                        <h4 class=\"modal-title\">Connect Providers to ".$ra['P_first_name']." ".$ra['P_last_name']."</h4>
                    </div>
                    <div class=\"modal-body\">
                        <form id=\"contact_form\" action=\"jx.php\" method=\"POST\">
                            <input type='hidden' name='cmd' value='therapist--modal' />
                            <input type='hidden' name='client_key' value='{$ra['_key']}' />";
             $otherless = array_filter($pro_roles_name,function($var){
                return($var != "Other");  
             });
             foreach ($pro_roles_name as $k => $role){
                 if($k == "Other"){
                     $s .= "$role <select name='$k'><option selected value='0'>Select Provider"
                     .SEEDCore_ArrayExpandRows($oPeopleDB->KFRel("PE")->GetRecordSetRA("pro_role NOT IN (".SEEDCore_ArrayExpandSeries($otherless, ",'[[]]'",TRUE,array("sTemplateFirst"=>"'[[]]'")).")"), "<option value='[[_key]]' />[[P_first_name]] [[P_last_name]] ([[pro_role]])")
                     ."</select><br />";
                 }else {
                     $s .= "$role <select name='$k'><option selected value='0'>Select Provider"
                     .SEEDCore_ArrayExpandRows($oPeopleDB->KFRel("PE")->GetRecordSetRA("pro_role='$role'"), "<option value='[[_key]]' />[[P_first_name]] [[P_last_name]]")
                     ."</select><br />";
                 }
              }
              $s .= "</form>
                    </div>
                    <div class=\"modal-footer\">
                        <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Cancel</button>
                        <button type=\"button\" id=\"submitForm\" class=\"btn btn-default\">Connect</button>
                    </div>
                </div>
            </div>
        </div>";
    return($s);
}

function drawModalButton(int $key):String{
    if(CATS_DEBUG){
        return <<<ModalButton
<button onClick='connectButton(event,$key)'>Connect Providers</button>
ModalButton;
    }
    else{
        return "Contact Developer to link providers";
    }
}
