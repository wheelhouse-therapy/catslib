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
                            <input type='hidden' name='cmd' value='therapist--modal' id='cmd' />
                            <input type='hidden' name='client_key' value='{$ra['_key']}' />";
             $otherless = array_filter($pro_roles_name,function($var){
                return($var != "Other");  
             });
             foreach ($pro_roles_name as $k => $role){
                 if($k == "Other"){
                     $s .= "$role <select class='searchable' name='$k'><option></option>"
                     .SEEDCore_ArrayExpandRows($oPeopleDB->KFRel(ClientList::EXTERNAL_PRO)->GetRecordSetRA("pro_role NOT IN (".SEEDCore_ArrayExpandSeries($otherless, ",'[[]]'",TRUE,array("sTemplateFirst"=>"'[[]]'")).")"), "<option value='[[_key]]' />[[P_first_name]] [[P_last_name]] ([[pro_role]])")
                     ."</select>";
                 }else {
                     $s .= "$role <select class='searchable' name='$k'><option></option>"
                     .SEEDCore_ArrayExpandRows($oPeopleDB->KFRel(ClientList::EXTERNAL_PRO)->GetRecordSetRA("pro_role='$role'"), "<option value='[[_key]]' />[[P_first_name]] [[P_last_name]]")
                     ."</select><br />";
                 }
              }
              $s .= "</form><br />
                     <form onsubmit='event.preventDefault()'>
                        <input type='hidden' name='cmd' value='linkNew' />
                        <input type='hidden' name='client_key' value='{$ra['_key']}' />
                        <input type='submit' value='New Provider' onclick='$(\"#contact_dialog\").modal(\"hide\");submitForm(event)' />
                     </form>
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
  return <<<ModalButton
<button style='margin-top:10px' onClick='connectButton(event,$key)'>Connect Providers</button>
ModalButton;
}

function drawStaffModal($ra, $oPeopleDB, SEEDAppConsole $oApp){
    $clinics = new Clinics($oApp);
    $clinicsDB = new ClinicsDB($oApp->kfdb);
    $condStaff = "P.uid in (SELECT fk_SEEDSession_users FROM users_clinics WHERE fk_clinics = {$clinics->GetCurrentClinic()}) AND clinic = {$clinics->GetCurrentClinic()}";
    if($clinics->isCoreClinic()){
        $condStaff = "";
    }
    $raStaff = $oPeopleDB->GetList(ClientList::INTERNAL_PRO, $condStaff, array("sSortCol" => "P.first_name,_key"));
    
    foreach(array_keys($raStaff) as $k){
        if($clinics->isCoreClinic()){
            $raStaff[$k]['clinic'] = " (".$clinicsDB->GetClinic($raStaff[$k]['clinic'])->Value('clinic_name').")";
        }
        else{
            $raStaff[$k]['clinic'] = "";
        }
    }
    
    $s = "
        <!-- the div that represents the modal dialog -->
        <div class=\"modal fade\" id=\"staff_dialog\" role=\"dialog\">
            <div class=\"modal-dialog\">
                <div class=\"modal-content\">
                    <div class=\"modal-header\">
                        <h4 class=\"modal-title\">Connect Staff to ".$ra['P_first_name']." ".$ra['P_last_name']."</h4>
                    </div>
                    <div class=\"modal-body\">
                        <form id=\"staff_form\" action=\"jx.php\" method=\"POST\">
                            <input type='hidden' name='cmd' value='therapist--clientlist-link' id='cmd' />
                            <input type='hidden' name='add_client_key' value='{$ra['_key']}' />";
                    $s .= "<select class='searchable' name='add_internal_key'><option></option>"
                        .SEEDCore_ArrayExpandRows($raStaff,"<option value='[[_key]]' />[[P_first_name]] [[P_last_name]] ([[pro_role]])[[clinic]]")
                        ."</select>";
                    $s .= "</form>
                    </div>
                    <div class=\"modal-footer\">
                        <button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Cancel</button>
                        <button type=\"button\" id=\"submitStaffForm\" class=\"btn btn-default\">Connect</button>
                    </div>
                </div>
            </div>
        </div>";
        return($s);
}

function drawStaffModalButton(int $key):String{
    return <<<ModalButton
<button style='margin-top:10px;margin-left:5px' onClick='connectStaffButton(event,$key)'>Connect Staff</button>
ModalButton;
}