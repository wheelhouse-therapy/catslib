<?php
function distributeReports($oApp) {
    $s = <<<Form
        <select id="clientlist" onchange="jxCall()">
            [[options]]
        </select>
        <div id="content">
            [[content]]
        </div>
        <script>
            function jxCall() {
                $.ajax({
                    type: "POST",
                    data: {idOut: document.getElementById("clientlist").value},
                    url: "jx.php",
                    success: function(data, textStatus, jqXHR) {
                        document.getElementById("content").innerHTML = JSON.parse(data).sOut;
                    },
                    error: function(jqXHR, status, error) {
                        console.log(status + ": " + error);
                    }
                });
            }
        </script>
Form;
    $clientId = $oApp->sess->SmartGPC("idOut");
}

function drawForm($oApp, $clientId) {
    if (!clientId) {
        return "";
    }
    $oPeopleDB = new PeopleDB($oApp);
    $kfr = $oPeopleDB->GetKFR(Clientlist::CLIENT, $clientId);
    $out = "<table>
            <tr><th></th><th>Address Label</th><th>Fax</th><th>Email</th></tr>" //header row
            ."<tr><td>Client:</td><td><input type='checkbox'"; //don't close the tag so we can disable it
    if (!$kfr->Value("p_address") || !$kfr->Value("p_postal_code") || 
        !$kfr->Value("p_city") || !$kfr->Value("p_province")){
        $out .= " disabled"; //disable the checkbox if we don't have all the necessary data
    }
    $out .=">"; //close checkbox
    if ($kfr->Value("parents_name")) {
        //add parents row
    }
}