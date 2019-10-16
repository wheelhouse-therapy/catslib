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
    $s = str_replace("[[content]]", drawForm($oApp, $clientId), $s);
    return $s;
}

function drawForm($oApp, $clientId) {
    if (!$clientId) {
        return "";
    }
    $oPeopleDB = new PeopleDB($oApp);
    $kfr = $oPeopleDB->GetKFR(Clientlist::CLIENT, $clientId);
    $out = "<table><tbody id='tableBody'";    
    
    //Add header row
    $out .= "<tr><th></th><th>Address Label</th><th>Fax</th><th>Email</th></tr>";
    //Columns are: Address Label, Fax, Email.
            
    //Add client row
    $out .= "<tr>";
    //Add Address Label column
    $out .= "<td>Client:</td><td><input type='checkbox'"; //don't close the tag so we can disable it
    $address = $kfr->Value("P_address");
    $postal_code = $kfr->Value("P_postal_code");
    $city = $kfr->Value("P_city");
    $province = $kfr->Value("P_province");
    if (!$address || !$postal_code || 
        !$city || !$province) {
        $out .= " disabled />"; //disable the checkbox if we don't have all the necessary data
    }
    else {
        $out .= " />" //close checkbox without disabling
        ."<br />"
        ."<div>"
            ."<span>" . $address . "</span><br />"
            ."<span>" . $postal_code . "</span><br />"
            ."<span>" . $city . "</span><br />"
            ."<span>" . $province . "</span>"
        ."</div>";
    }
    $out .=" </td>"; //close address label column
    
    //We don't even store client faxes, so just add a disabled checkbox
    $out .= "<td><input type='checkbox' disabled /></td>";
    
    //Add email column
    $email = $kfr->Value("P_email"); //Need the database column for email
    $out .= "<td><input type='checkbox'";
    if ($email) {
        $out .= " />" //close checkbox
        ."<br />"
        ."<span>". $email . "</span>"; //print email address underneath checkbox
    }
    else {
        $out .= " disabled />"; //disable and close checkbox
    }
    $out .= "</td>"; //close email column
    $out .= "</tr>"; //close client row
    
    //Add parents row
    if ($kfr->Value("parents_name")) {
        
    }
    $out .= "</tbody></table>"; //close table
    return $out;
}