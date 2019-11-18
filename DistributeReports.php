<?php

function distributeReports($oApp) {
    $oPeopleDB = new PeopleDB( $oApp );
    $clinics = new Clinics($oApp);
    $condClinic = $clinics->isCoreClinic() ? "" : ("clinic = ".$clinics->GetCurrentClinic());
    /* $s here makes a template with a dropdown list (with HTML id="clientlist")
     * to select a client, which makes an AJAX call (calling this function) back to the server whenever
     * it changes. This function also calls drawForm and sticks the return from that in a div.
     * drawForm takes a client id and generates an HTML table with the client, their parents
     * and all their providers as rows, and checkboxes for address labels, faxes and emails.
     * It then searches the database to determine if we have enough data to determine address labels,
     * fax cover sheets and emails for each person. It disables the checkbox if we don't have enough
     * data, and it prints the data underneath the checkbox if we do.
     *
     * All js and css are located in extensions.twig under the distributeReports template
     *
     * TODO: add buttons to generate the address labels, fax cover sheets and emails.
     */
    $s = <<<Form
        <select id="clientlist" onchange="jxCall()">
            [[options]]
        </select>
        <div id="content">
            [[content]]
        </div>
        <script>
            document.querySelector("option[value='[[_key]]']").setAttribute("selected", "selected");
        </script>

Form;
    $clientId = $oApp->sess->SmartGPC("idOut");
    $raClients = $oPeopleDB->GetList(Clientlist::CLIENT, $condClinic, array("iStatus" => -1));
    $s = str_replace("[[_key]]", $clientId, $s);
    $s = str_replace("[[content]]", drawForm($oApp, $clientId), $s);
    $s = str_replace("[[options]]", SEEDCore_ArrayExpandRows($raClients,
        "<option value='[[_key]]'>[[P_first_name]] [[P_last_name]]</option>"), $s);
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
    $out .= "<tr><th class='borderless'></th><th>Address Label</th><th>Fax</th><th>Email</th></tr>";
    //Columns are: Person Data, Address Label, Fax, Email.

    //Add client row
    $out .= "<tr data-id='" . ClientList::createID(ClientList::CLIENT, $clientId) . "'>";
    $out .= "<td>Client: ". $kfr->Expand("[[P_first_name]] [[P_last_name]]") . "</td>";
    //Add Address Label column
    $out .= "<td><input class='label' type='checkbox'"; //don't close the tag so we can disable it
    $address = $kfr->Value("P_address");
    $postal_code = $kfr->Value("P_postal_code");
    $city = $kfr->Value("P_city");
    $province = $kfr->Value("P_province");
    if (!$address || !$postal_code ||
        !$city || !$province) {
        $out .= " disabled />"; //disable the checkbox if we don't have all the necessary data
    }
    else {
        $out .= " />";
    }
    $out .= "<div>"
    ."<span>" . ($address ?: "(no address entered)") . "</span><br />"
    ."<span>" . ($postal_code ?: "(no postal code entered)") . "</span><br />"
    ."<span>" . ($city ?: "(no city entered)") . "</span><br />"
    ."<span>" . ($province ?: "(no province entered)") . "</span>"
    ."</div>";

    $out .=" </td>"; //close address label column

    //We don't even store client faxes, so just add a disabled checkbox
    $out .= "<td><input class='fax' type='checkbox' disabled /><br/>"
    ."<span>n/a</span></td>";

    //Add email column
    $email = $kfr->Value("P_email"); //Need the database column for email
    $out .= "<td><input class='email' type='checkbox'";
    if ($email) {
        $out .= " />"; //close checkbox
    }
    else {
        $out .= " disabled />"; //disable and close checkbox
    }
    //print email address underneath checkbox
    $out .= "<br /><span>". ($email?: "(no email entered)") . "</span>";
    $out .= "</td>"; //close email column
    $out .= "</tr>"; //close client row

    //Add parents row
    $parent_name = $kfr->Value("parents_name");
    if ($parent_name) {
        $out .= "<tr data-id='" . ClientList::createID(ClientList::CLIENT, $clientId). "p'>"
        ."<td>Parent: $parent_name</td>";
        //add address label cell
        $out .= "<td><input class='label' type='checkbox'";
        if (!$address || !$postal_code ||
          !$city || !$province) {
              $out .= " disabled />"; //disable the checkbox if we don't have all the necessary data
        }
        else {
            $out .= " />";
        }
        $out .= "<div>"
        ."<span>" . ($address ?: "(no address entered)") . "</span><br />"
        ."<span>" . ($postal_code ?: "(no postal code entered)") . "</span><br />"
        ."<span>" . ($city ?: "(no city entered)") . "</span><br />"
        ."<span>" . ($province ?: "(no province entered)") . "</span>"
        ."</div>";
        $out .= "</td>"; //close parent address label cell

        //We don't even store parent faxes, so just add a disabled checkbox
        $out .= "<td><input class='fax' type='checkbox' disabled /><br />"
        ."<span>n/a</span></td>";

        //Open parent email cell
        $out .= "<td><input class='email' type='checkbox'";
        //We assume parent and client emails are the same since we only store 1 email
        if ($email) {
            $out .= " />"; //close checkbox
        } else {
            $out .= " disabled />"; //disable and close checkbox
        }
        //print email address underneath checkbox
        $out .= "<br /><span>". ($email?: "(no email entered)") . "</span>";
        $out .= "</td>"; //close parent email cell
        $out .= "</tr>"; //close parent row

    }

    //get array of all the providers for the current client
    $prosList = ($clientId?$oPeopleDB->GetList('CX', "fk_clients2='{$clientId}'"):array());
    foreach($prosList as $pro) {
        $pro['fk_pros_internal'] && ($kfr = $oPeopleDB->GetKFR( 'PI', $pro['fk_pros_internal'] ));
        $pro['fk_pros_internal'] && $type = ClientList::INTERNAL_PRO;
        $pro['fk_pros_external'] && ($kfr = $oPeopleDB->GetKFR( 'PE', $pro['fk_pros_external'] ));
        $pro['fk_pros_external'] && $type = ClientList::EXTERNAL_PRO;

        $cell = $kfr->Expand(
            "<div><span>Provider: [[P_first_name]] [[P_last_name]]</span><br/>
            <span>Role: [[pro_role]]</span></div>" );
        $out .= "<tr data-id='" . ClientList::createID($type, $kfr->Value("_key")) . "'>"
        ."<td>". $cell ."</td>"; //open and close person description cell


        $address = $kfr->Value("P_address");
        $postal_code = $kfr->Value("P_postal_code");
        $city = $kfr->Value("P_city");
        $province = $kfr->Value("P_province");

        //add address label column
        $out .="<td><input class='label' type='checkbox'";

        if (!$address || !$postal_code ||
          !$city || !$province) {
            $out .= " disabled />"; //disable the checkbox if we don't have all the necessary data
        }
        else {
            $out .= " />"; //close checkbox without disabling
        }
        $out .= "<br />"
        ."<div>"
        ."<span>" . ($address ?: "(no address entered)") . "</span><br />"
        ."<span>" . ($postal_code ?: "(no postal code entered)") . "</span><br />"
        ."<span>" . ($city ?: "(no city entered)") . "</span><br />"
        ."<span>" . ($province ?: "(no province entered)") . "</span>"
        ."</div>";

        $out .=" </td>"; //close address label column

        $fax = $kfr->Value("fax_number");
        //add fax column
        $out .= "<td><input class='fax' type='checkbox'";
        if ($fax) {
            $out .= " />"; //close checkbox without disabling
        }
        else {
            $out .= " disabled />"; //disable and close the checkbox
        }
        $out .= "<br/><span>" . ($fax ?: "(no fax entered)") . "</span>"; //put fax number in a span
        $out .= "</td>"; //close fax cell

        $email = $kfr->Value("P_email");
        //add email column
        $out .= "<td><input class='email' type='checkbox'";
        if ($email) {
            $out .= "/>";
        }
        else {
            $out .= " disabled />";
        }
        $out .= "<br /><span>" . ($email ?: "(no email entered)") ."</span>";
        $out .= "</td>";

        $out .= "</tr>"; //close provider row
    }

    //add a row for the "generate ..." buttons
    $out .= "<tr><td class='borderless'></td>"
    ."<td><button class='generate' onclick='DistributeReports.generateLabels();'>Generate Address Labels</button></td>"
    ."<td><button class='generate' onclick='DistributeReports.generateFaxes();'>Generate Fax Cover Sheets</button></td>"
    ."<td><button class='generate' onclick='generateEmails();'>Generate Emails</button></td>"
    ."</tr>";

    $out .= "</tbody></table>";
    return $out;
}

class DistributeReports
{
    private $oApp;

    function __construct( SEEDAppSession $oApp )
    {
        $this->oApp = $oApp;
    }

    function OutputAddressLabels( array $info )
    {
        require_once CATSLIB."template_filler.php";

        $filler = new template_filler($this->oApp, array(), $info);
        $filler->fill_resource(CATSLIB . "ReportsTemplates/Address Labels Template.docx");
        exit;
    }

    function OutputFaxCover( string $info )
    {
        require_once CATSLIB."template_filler.php";

        $filler = new template_filler($this->oApp, array(), [$info]);
        $filler->fill_resource(CATSLIB . "ReportsTemplates/Fax Cover Sheet Template.docx");
        exit;
    }
}