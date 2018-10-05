<?php

include_once( "assessments.php" );
require_once 'Clinics.php';
require_once 'therapist-clientlist.php';

/* Classes to help draw the user interface
 */
class CATS_UI
{
    protected $oApp;
    protected $screen = "";

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
    }

    function Header()
    {
        if( !$this->oApp->sess->IsLogin() ) {
            echo "<head><meta http-equiv=\"refresh\" content=\"0; URL=".CATSDIR."\"></head><body>You have Been Logged out<br /><a href=".CATSDIR."\"\">Back to Login</a></body>";
            exit;
        }
        $clinics = new Clinics($this->oApp);
        return( "<div class='cats_header'>"
                   ."<a href='?screen=home'><img src='".CATS_LOGO."' style='max-width:300px;float:left;'/></a>"
                   ."<div style='float:none;top: 5px;position: relative;display: inline-block;margin-left: 20%;margin-right: 10px;'>".$clinics->displayUserClinics()."</div>"
                   ."<div style='float:right;top: 5px;position: relative;'>"
                       ."Welcome ".$this->oApp->sess->GetName()." "
                       .($this->screen != "home" ? "<a href='".CATSDIR."?screen=home'><button>Home</button></a>" : "")
                       ." <a href='".CATSDIR."?screen=logout'><button>Logout</button></a>"
                   ."</div>"
               ."</div>"
               ."<div style='clear:both'>&nbsp;</div>"
              );
     }

     function OutputPage( $body )
     {
    $s =
    "<!DOCTYPE html>
    <html lang='en'>
    <head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
    <link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css' integrity='sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm' crossorigin='anonymous'>
    <script src='https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js'></script>
    <script src='w/js/appointments.js'></script>
    <script src='".W_CORE_URL."js/SEEDCore.js'></script>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js' integrity='sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q' crossorigin='anonymous'></script>
    <script src='https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js' integrity='sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl' crossorigin='anonymous'></script>
    <link rel='stylesheet' href='w/css/tooltip.css'>
    <style>
    :root {
        --color1: #63cdfc;
        --color2: #388ed4;
        --textColor: black;
    }
    body {
        margin: 0 8px;
    }
    .toCircle {
    	text-decoration: none;
    	display: flex;
    	justify-content: center;
    	align-items: center;
    	text-align: center;
    	margin-bottom: 20px;
    	margin-left: 10px;
    	border-style: inset outset outset inset;
        border-width: 3px;
        border-radius: 50%;
    }
    @keyframes colorChange {
        from {background-color: var(--color1); border-color: var(--color1);}
        to {background-color: var(--color2); border-color: var(--color2);}
    }
    [class *= catsCircle] {
        box-sizing: border-box;
        height: 200px;
    	width: 200px;
    	color: var(--textColor) !important;
    }
    .catsCircle1 {
    	animation: colorChange 10s ease-in-out infinite alternate;
    }
    .catsCircle2 {
    	animation: colorChange 10s ease-in-out -5s infinite alternate;
    }
    span.selectedClinic {
        font-size: 20pt;
    }
    div.cats_header {
        overflow: visible;
        position: sticky;
        background: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.5));
        top: 0;
        z-index: 1;
        display: inline-block;
        width: 100%;
    }
    </style>
    <script>
    function createCircle(elements, styles) {
    	for (var x in elements) {
		  var diameter = styles[x][0], color = styles[x][1], textColor = styles[x][2];
		  elements[x].style.height = diameter;
		  elements[x].style.width = diameter;
		  elements[x].style.color = textColor;
		  elements[x].style.backgroundColor = color;
		  elements[x].style.borderColor = color;
	   }
    return true;
    }
    function run() {
        var x = document.querySelectorAll('.toCircle:not([class*=\"catsCircle\"])');
        var elements = [], styles = [];
        for(var y = 0; y < x.length; y++) {
	       elements.push(x[y]);
	       styles.push(x[y].dataset.format.split(' '));
        }
        createCircle(elements, styles);

        $(document).ready( function () {

            /* Generic seedjx submission
             */
            $('.seedjx-submit').click( function () { SEEDJX_Form1( 'jx.php', $(this) ); } );

            /* the Appointment Review button launches catsappt--reviewd
             */
            $('.appt-newform').submit( function (e) {
                e.preventDefault();
                var gid = $(this).find('#appt-gid').val();
                var cid = $(this).find('#appt-clientid').val();
                var divSpecial = this.appt();

                $.ajax({
                    type: 'POST',
                    data: { cmd: 'catsappt--review', google_cal_ev_id: gid, fk_clients: cid },
                    url: 'jx.php',
                    success: function(data, textStatus, jqXHR) {
                        var jsData = JSON.parse(data);
                        var sSpecial = jsData.bOk ? jsData.sOut : 'No, something is wrong';
                        divSpecial.outerHTML = sSpecial;
                    },
                    error: function(jqXHR, status, error) {
                        console.log(status + \": \" + error);
                    }
                });
            });
        });

    }
    </script>
    </head>
    <body>"

    .$body

    ."<script> SEEDCore_CleanBrowserAddress(); </script>"

    ."<script> run(); </script>"
    ."<script src='w/js/tooltip.js'></script>"
    ."</body></html>";

        return( $s );
    }


    function SetScreen($screen){
        $this->screen = $screen;
    }

}


class CATS_MainUI extends CATS_UI
{

    private $i = 0;

    function __construct( SEEDAppConsole $oApp )
    {
        parent::__construct( $oApp );
    }

    function Screen( $screen ) {
        $this->SetScreen( $screen );

        $s = $this->Header();
        $clinics = new Clinics($this->oApp);
        if($clinics->GetCurrentClinic() == NULL){
            $s .= "<h2>Please Select a clinic to continue</h2>"
                 .$clinics->displayUserClinics();
        }
        else if( substr($screen,0,9) == "developer" ) {
            $s .= $this->DrawDeveloper();
        }else if( substr( $screen, 0, 5 ) == 'admin' ) {
            $s .= $this->DrawAdmin();
        } else if( substr( $screen, 0, 9 ) == "therapist" ) {
            $s .= $this->DrawTherapist();
        } else if( $screen == "logout" ) {
            $s .= $this->DrawLogout();
        } else {
            $s .= $this->DrawHome();
        }

        return( $s );
    }


    function DrawHome()
    {
        $s = "<div class='container-fluid'>"
            .($this->oApp->sess->CanRead('therapist') ? $this->DrawTherapist() : "")
            .($this->oApp->sess->CanRead('admin')     ? $this->DrawAdmin() : "")
            .($this->oApp->sess->CanRead('administrator')     ? $this->DrawDeveloper() : "")
            ."</div>";


        return( $s );
    }

    function DrawTherapist()
    {
        $raTherapistScreens = array(
            array( 'therapist-calendar',        "Calendar" ),
            array( 'therapist-clientlist',      "Clients, Therapists, and External Providers" ),
            array( 'therapist-handouts',        "Print Handouts" ),
            array( 'therapist-formscharts',     "Print Forms for Charts" ),
            array( 'therapist-linedpapers',     "Print Different Lined Papers" ),
            array( 'therapist-ideas',           "Get Ideas" ),
            array( 'therapist-materials',       "Download Marketable Materials" ),
            array( 'therapist-team',            "Meet the Team" ),
            array( 'therapist-submitresources', "Submit Resources to Share" ),
            array( 'therapist-reports',         "Print Client Reports"),
            array( 'therapist-assessments',     "Score Assessments"),
        );

        $s = "";
        switch( $this->screen ) {
            case "therapist":
            default:
                $s .= $this->drawCircles( $raTherapistScreens );
                break;

            case "therapist-handouts":
                include('view_resources.php');
                $s .= ResourcesDownload( $this->oApp, "handouts/" );
                break;
            case "therapist-formscharts":
                include('view_resources.php');
                $s .= ResourcesDownload( $this->oApp, "forms/" );
                break;
            case 'therapist-reports':
                include('view_resources.php');
                $s .= ResourcesDownload( $this->oApp, "reports/" );
                break;
            case "therapist-linedpapers":
                include("papers.php");
                break;
            case "therapist-entercharts":
                $s .= "ENTER CHARTS";
                break;
            case "therapist-ideas":
                $s .= "GET IDEAS";
                break;
            case "therapist-assessments":
                $s .= AssessmentsScore( $this->oApp );
                break;
            case "therapist-materials":
                $s .= DownloadMaterials( $this->oApp );
                break;
            case "therapist-team":
                $s .= "MEET THE TEAM";
                break;
            case "therapist-submitresources":
                $s .= "SUBMIT RESOURCES";
                include('share_resources.php');
                $s .= share_resources();
                break;
            case 'therapist-resources':
                include('share_resorces_upload.php');
                break;
            case "therapist-clientlist":
                $o = new ClientList( $this->oApp );
                $s .= $o->DrawClientList();
                break;
            case "therapist-calendar":
                require_once CATSLIB."calendar.php";
                $o = new Calendar( $this->oApp );
                $s .= $o->DrawCalendar();
        }
        return( $s );
    }

    function DrawAdmin()
    {
        $s = "";

        $oApp = $this->oApp;
        switch( $this->screen ) {
            case 'admin-users':
                $s .= $this->drawAdminUsers();
                break;
            case 'admin-resources':
                include('review_resources.php');
                break;
            default:
                $raScreens = array(
                    array( 'admin-users',             "Manage Users" ),
                    array( 'admin-resources',        "Review Resources" ),
                );
                $s .= $this->drawCircles( $raScreens );

                break;
        }
        return( $s );
    }

    function DrawLogout()
    {
        $this->oApp->sess->LogoutSession();
        header( "Location: ".CATSDIR );
    }

    function DrawDeveloper(){
        $s = "";
        switch($this->screen){
            case 'developer-droptable':
                global $catsDefKFDB;
                $db = $catsDefKFDB['kfdbDatabase'];
                $oApp = $this->oApp;
                $oApp->kfdb->Execute("drop table $db.clients2");
                $oApp->kfdb->Execute("drop table $db.pros_internal");
                $oApp->kfdb->Execute("drop table $db.pros_external");
                $oApp->kfdb->Execute("drop table $db.clientsxpros");
// remove soon
$oApp->kfdb->Execute("drop table $db.clients");
$oApp->kfdb->Execute("drop table $db.clients_pros");
$oApp->kfdb->Execute("drop table $db.professionals");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_Users");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_UsersMetadata");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_Groups");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_GroupsMetadata");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_UsersXGroups");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_Perms");
                $oApp->kfdb->Execute("drop table $db.cats_appointments");
                $oApp->kfdb->Execute("drop table $db.clinics");
                $oApp->kfdb->Execute("drop table $db.users_clinics");
                $s .= "<div class='alert alert-success'> Oops I miss placed your data</div>";
                break;
            case 'developer-clinics':
                $s .= (new Clinics($this->oApp))->manageClinics();
                break;
            case 'developer-confirmdrop':
                $s .= "<h3>Are you sure you want to drop the tables?</h3>"
                      ."<br /><h1>THIS CANNOT BE UNDONE</h1>"
                      ."<br /><a href='?screen=developer-droptable'><button>Yes</button></a>"
                      ."&nbsp&nbsp&nbsp&nbsp&nbsp<a href='?screen=home'><button>No</button></a>";
                      break;
            default:
                    $raScreens = array(
                        array( 'developer-confirmdrop',             "Drop Tables" ),
                        array( 'developer-clinics',        "Manage Clinics" ),
                    );
                    $s .= $this->drawCircles( $raScreens );
        }
        return( $s );
    }

    private function drawCircles( $raScreens )
    {
        $s = "";
        foreach( $raScreens as $ra ) {
            $circle = "catsCircle".($this->i % 2 + 1);

            if( $this->i % 4 == 0 ) $s .= "<div class='row'>";
            $s .= "<div class='col-md-3'><a href='?screen={$ra[0]}' class='toCircle $circle'>{$ra[1]}</a></div>";
            if( $this->i % 4 == 3 ) $s .= "</div>";   // row
            ++$this->i;
        }

        return( $s );
    }

    private function drawAdminUsers()
    {
        $o = new UsersGroupsPermsUI( $this->oApp );
        return( $o->DrawUI() );
    }
}

class UsersGroupsPermsUI
{
    private $oApp;
    private $oAcctDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oAcctDB = new SEEDSessionAccountDBRead2( $this->oApp->kfdb, $this->oApp->sess->GetUID(), array('logdir'=>$this->oApp->logdir) );
    }

    function DrawUI()
    {
        $s = "";

        $mode = $this->oApp->oC->oSVA->SmartGPC( 'adminUsersMode', array('Users','Groups','Permissions') );

        $raListParms = array( 'bUse_key' => true );

        switch( $mode ) {
            case "Users":
                $cid = "U";
                $kfrel = $this->oAcctDB->GetKfrel('U');
                $raListParms['cols'] = array(
                    array( 'label'=>'User #',  'col'=>'_key' ),
                    array( 'label'=>'Name',    'col'=>'realname' ),
                    array( 'label'=>'Email',   'col'=>'email'  ),
                    array( 'label'=>'Status',  'col'=>'eStatus'  ),
                    array( 'label'=>'Group1',  'col'=>'G_groupname'  ),
                );
                $raListParms['fnRowTranslate'] = array($this,"usersListRowTranslate");
                // Not the same format as listcols because these actually need the column names not aliases.
                // For groups and perms it happens to work but when _key is included in the WHERE it is ambiguous
                $raSrchParms['filters'] = array(
                    array( 'label'=>'User #',  'col'=>'U._key' ),
                    array( 'label'=>'Name',    'col'=>'U.realname' ),
                    array( 'label'=>'Email',   'col'=>'U.email'  ),
                    array( 'label'=>'Status',  'col'=>'U.eStatus'  ),
                    array( 'label'=>'Group1',  'col'=>'G.groupname'  ),
                );
                $formTemplate = $this->getUsersFormTemplate();
                break;
            case "Groups":
                $cid = "G";
                $kfrel = $this->oAcctDB->GetKfrel('G');
                $raListParms['cols'] = array(
                    array( 'label'=>'k',          'col'=>'_key' ),
                    array( 'label'=>'Group Name', 'col'=>'groupname'  ),
                    array( 'label'=>'Inherited',  'col'=>'gid_inherited'  ),
                );
                $raSrchParms['filters'] = $raListParms['cols'];     // conveniently the same format
                $formTemplate = $this->getGroupsFormTemplate();
                break;
            case "Permissions":
                $cid = "P";
                $kfrel = $this->oAcctDB->GetKfrel('P');
                $raListParms['cols'] = array(
                    array( 'label'=>'Permission', 'col'=>'perm'  ),
                    array( 'label'=>'Modes',      'col'=>'modes'  ),
                    array( 'label'=>'User',       'col'=>'U_realname'  ),
                    array( 'label'=>'Group',      'col'=>'G_groupname'  ),
                );
                $raSrchParms['filters'] = $raListParms['cols'];     // conveniently the same format
                $formTemplate = $this->getPermsFormTemplate();
                break;
        }

        $oUI = new MySEEDUI( $this->oApp, "Stegosaurus" );
        $oComp = new KeyframeUIComponent( $oUI, $kfrel, $cid );
        $oComp->Update();

//$this->oApp->kfdb->SetDebug(2);
        $oList = new KeyframeUIWidget_List( $oComp );
        $oSrch = new SEEDUIWidget_SearchControl( $oComp, $raSrchParms );
        $oForm = new KeyframeUIWidget_Form( $oComp, array('sTemplate'=>$formTemplate) );

        $oComp->Start();    // call this after the widgets are registered

        list($oView,$raWindowRows) = $oComp->GetViewWindow();
        $sList = $oList->ListDrawInteractive( $raWindowRows, $raListParms );

        $sSrch = $oSrch->Draw();
        $sForm = $oForm->Draw();

        // Have to do this after Start() because it can change things like kCurr
        switch( $mode ) {
            case 'Users':       $sInfo = $this->drawUsersInfo( $oComp );    break;
            case 'Groups':      $sInfo = $this->drawGroupsInfo( $oComp );   break;
            case 'Permissions': $sInfo = $this->drawPermsInfo( $oComp );    break;
        }

        $s = $oList->Style()
            ."<form method='post'>"
                ."<input type='submit' name='adminUsersMode' value='Users'/>&nbsp;&nbsp;"
                ."<input type='submit' name='adminUsersMode' value='Groups'/>&nbsp;&nbsp;"
                ."<input type='submit' name='adminUsersMode' value='Permissions'/>"
            ."</form>"
            ."<h2>$mode</h2>"
            ."<div class='container-fluid'>"
                ."<div class='row'>"
                    ."<div class='col-md-6'>"
                        ."<div>".$sSrch."</div>"
                        ."<div>".$sList."</div>"
                    ."</div>"
                    ."<div class='col-md-6'>"
                        ."<div style='width:90%;padding:20px;border:2px solid #999'>".$sForm."</div>"
                    ."</div>"
                ."</div>"
                .$sInfo
            ."</div>";


//         $s .= "<div class='seedjx' seedjx-cmd='test'>"
//                  ."<div class='seedjx-err alert alert-danger' style='display:none'></div>"
//                  ."<div class='seedjx-out'>"
//                      ."<input name='a'/>"
//                      ."<select name='test'/><option value='good'>Good</option><option value='bad'>Bad</option></select>"
//                      ."<button class='seedjx-submit'>Go</button>"
//                  ."</div>"
//              ."</div>";

        return( $s );
    }

    private function drawUsersInfo( KeyframeUIComponent $oComp )
    {
        $s = "";
        $s .= $this->ugpStyle();

        if( !($kUser = $oComp->Get_kCurr()) )  goto done;

        $raGroups = $this->oAcctDB->GetGroupsFromUser( $kUser, array('bNames'=>true) );
        $raPerms = $this->oAcctDB->GetPermsFromUser( $kUser );
        $raMetadata = $this->oAcctDB->GetUserMetadata( $kUser );

        /* Groups list
         */
        $sG = "<p><b>Groups</b></p>"
             ."<div class='ugpBox'>";
        foreach( $raGroups as $kGroup => $sGroupname ) {
            $sG .= "$sGroupname &nbsp;<span style='float:right'>($kGroup)</span><br/>";
        }
        $sG .= "</div>";

        // group add/remove
        $oFormB = new SEEDCoreForm( "B" );
        $sG .= "<div>"
              ."<form action='{$_SERVER['PHP_SELF']}' method='post'>"
              //.$this->oComp->EncodeHiddenFormParms()
              //.SEEDForm_Hidden( 'uid', $kUser )
              //.SEEDForm_Hidden( 'form', "UsersXGroups" )
              .$oFormB->Text( 'gid', '' )
              ."<input type='submit' name='cmd' value='Add'/><INPUT type='submit' name='cmd' value='Remove'/>"
              ."</form></div>";


        /* Perms list
         */
        $sP = "<p><b>Permissions</b></p>"
             ."<div class='ugpBox'>";
        ksort($raPerms['perm2modes']);
        foreach( $raPerms['perm2modes'] as $k => $v ) {
            $sP .= "$k &nbsp;<span style='float:right'>( $v )</span><br/>";
        }
        $sP .= "</div>";


        /* Metadata list
         */
        $sM = "<p><b>Metadata</b></p>"
             ."<div class='ugpBox'>";
        foreach( $raMetadata as $k => $v ) {
            $sM .= "$k &nbsp;<span style='float:right'>( $v )</span><br/>";
        }
        $sM .= "</div>";
/*
            // Metadata Add/Remove
            $s .= "<BR/>"
                 ."<FORM action='{$_SERVER['PHP_SELF']}' method='post'>"
                 .$this->oComp->EncodeHiddenFormParms()
                 .SEEDForm_Hidden( 'uid', $kUser )
                 .SEEDForm_Hidden( 'form', "UsersMetadata" )
                 ."k ".SEEDForm_Text( 'meta_k', '' )
                 ."<br/>"
                 ."v ".SEEDForm_Text( 'meta_v', '' )
                 ."<INPUT type='submit' name='cmd' value='Set'/><INPUT type='submit' name='cmd' value='Remove'/>"
                 ."</FORM></TD>";
*/

        $s .= "<div class='row'>"
                 ."<div class='col-md-4'>$sG</div>"
                 ."<div class='col-md-4'>$sP</div>"
                 ."<div class='col-md-4'>$sM</div>"
             ."</div>";

        done:
        return( $s );
    }

    private function drawGroupsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );
    }

    private function drawPermsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );

    }

    function ugpStyle()
    {
        $s = "<style>"
             .".ugpForm { font-size:14px; }"
             .".ugpBox { height:200px; border:1px solid gray; padding:3px; font-family:sans serif; font-size:11pt; overflow-y:scroll }"
            ."</style>";
        return( $s );
    }

    private function getUsersFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| User #|| [[Text:_key | readonly]]\n"
            ."||| Name  || [[Text:realname]]\n"
            ."||| Email || [[Text:email]]\n"
            ."||| Status|| <select name='eStatus'>".$this->getUserStatusSelectionFormTemplate()."</select>\n"
            ."||| Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid1", "groupname")."\n"
                ;

        return( $s );
    }

    private function getGroupsFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| Name            || [[Text:groupname]]\n"
            ."||| Inherited Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid_inherited", "groupname", TRUE)."\n"
                ;

        return( $s );
    }

    private function getPermsFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| Name  || [[Text:perm]]\n"
            ."||| Mode  || [[Text:modes]]\n"
            ."||| User  || ".$this->getSelectTemplate("SEEDSession_Users", "uid", "realname", TRUE)."\n"
            ."||| Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid", "groupname", TRUE)."\n"
                ;

        return( $s );
    }

    private function getSelectTemplate($table, $col, $name, $bEmpty = FALSE)
    /****************************************************
     * Generate a template of that defines a select element
     *
     * table - The database table to get the options from
     * col - The database collum that the options are associated with.
     * name - The database collum that contains the user understandable name for the option
     * bEmpty - If a None option with value of NULL should be included in the select
     *
     * eg. table = SEEDSession_Groups, col = gid, name = groupname
     * will result a select element with the groups as options with the gid of kfrel as the selected option
     */
    {
        $options = $this->oApp->kfdb->QueryRowsRA("SELECT * FROM ".$table);
        $s = "<select name='".$col."'>";
        if($bEmpty){
            $s .= "<option value='NULL'>None</option>";
        }
        foreach($options as $option){
            $s .= "<option [[ifeq:[[value:".$col."]]|".$option["_key"]."|selected| ]] value='".$option["_key"]."'>".$option[$name]."</option>";
        }
        $s .= "</select>";
        return $s;
    }

    private function getUserStatusSelectionFormTemplate(){
        global $catsDefKFDB;
        $db = $catsDefKFDB['kfdbDatabase'];
        $options = $this->oApp->kfdb->Query1("SELECT SUBSTRING(COLUMN_TYPE,5) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".$db."' AND TABLE_NAME='SEEDSession_Users' AND COLUMN_NAME='eStatus'");
        $options = substr($options, 1,strlen($options)-2);
        $options_array = str_getcsv($options, ',', "'");
        $s = "";
        foreach($options_array as $option){
            $s .= "<option [[ifeq:[[value:eStatus]]|".$option."|selected| ]]>".$option."</option>";
        }
        return $s;
    }

    function usersListRowTranslate( $raRow )
    {
        if( $raRow['gid1'] && $raRow['G_groupname'] ) {
            // When displaying the group name it's helpful to show the gid too
            $raRow['G_groupname'] .= " (".$raRow['gid1'].")";
        }

        return( $raRow );
    }

}


require_once SEEDCORE."SEEDUI.php";
class MySEEDUI extends SEEDUI
{
    private $oSVA;

    function __construct( SEEDAppSession $oApp, $sApplication )
    {
        parent::__construct();
        $this->oSVA = new SEEDSessionVarAccessor( $oApp->sess, $sApplication );
    }

    function GetUIParm( $cid, $name )      { return( $this->oSVA->VarGet( "$cid|$name" ) ); }
    function SetUIParm( $cid, $name, $v )  { $this->oSVA->VarSet( "$cid|$name", $v ); }
    function ExistsUIParm( $cid, $name )   { return( $this->oSVA->VarIsSet( "$cid|$name" ) ); }
}


class KeyframeUIComponent extends SEEDUIComponent
{
    private $kfrel;
    private $raViewParms = array();

    function __construct( SEEDUI $o, Keyframe_Relation $kfrel, $cid = "A", $raCompConfig = array() )
    {
         $this->kfrel = $kfrel;     // set this before the parent::construct because that uses the factory_SEEDForm
         parent::__construct( $o, $cid, $raCompConfig );
    }

    protected function factory_SEEDForm( $cid, $raSFParms )
    {
        // Any widget can find this KeyframeForm at $this->oComp->oForm
        return( new KeyframeForm( $this->kfrel, $cid, $raSFParms ) );
    }

    function Start()
    {
        parent::Start();

        /* Now the Component is all set up with its uiparms and widgets, but the oForm is not initialized to
         * the current key (unless it got loaded during Update).
         */

        if( $this->Get_kCurr() && ($kfr = $this->kfrel->GetRecordFromDBKey($this->Get_kCurr())) ) {
            $this->oForm->SetKFR( $kfr );
        }
    }

    function GetViewWindow()
    {
        $raViewParms = array();

        $raViewParms['sSortCol']  = $this->GetUIParm('sSortCol');
        $raViewParms['bSortDown'] = $this->GetUIParm('bSortDown');
        $raViewParms['sGroupCol'] = $this->GetUIParm('sGroupCol');
        $raViewParms['iStatus']   = $this->GetUIParm('iStatus');

        $oView = new KeyframeRelationView( $this->kfrel, $this->sSqlCond, $raViewParms );
        $raWindowRows = $oView->GetDataWindowRA( $this->Get_iWindowOffset(), $this->Get_nWindowSize() );
        return( array( $oView, $raWindowRows ) );
    }
}

class KeyframeUIWidget_List extends SEEDUIWidget_List
{
    function __construct( KeyframeUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }
}

class KeyframeUIWidget_Form extends SEEDUIWidget_Form
{
    function __construct( KeyframeUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function Draw()
    {
        $s = "";

        if( $this->oComp->oForm->GetKey() ) {
            $o = new SEEDFormExpand( $this->oComp->oForm );
            $s = $o->ExpandForm( $this->raConfig['sTemplate'] );
        }
        return( $s );
    }
}


?>
