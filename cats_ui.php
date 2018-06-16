<?php

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
        return( "<div class='cats_header'>"
                   ."<img src='".CATSDIR_IMG."CATS.png' style='max-width:300px;float:left;'/>"
                   ."<div style='float:right'>"
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
    <script src='https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js' integrity='sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q' crossorigin='anonymous'></script>
    <script src='https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js' integrity='sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl' crossorigin='anonymous'></script>
    <style>
    a:link.toCircle, a:visited.toCircle, a:hover.toCircle, a:active.toCircle {
    	text-decoration: none;
    	display: flex;
    	justify-content: center;
    	align-items: center;
    	text-align: center;
    	margin-bottom: 20px;
    	margin-left: 10px;
    	border-style: inset outset outset inset;
    }
    @keyframes colorChange {
        from {background-color: #b3f0ff; border-color: #b3f0ff;}
        to {background-color: #99ff99; border-color: #99ff99;}
    }
    a.catsCircle1 {
    	height: 200px;
    	width: 200px;
    	border-radius: 100px;
    	color: blue;
    	animation: colorChange 10s linear infinite alternate;
    }
    a.catsCircle2 {
    	height: 200px;
    	width: 200px;
    	border-radius: 100px;
    	color: blue;
    	animation: colorChange 10s linear -5s infinite alternate;
    }
    </style>
    <script>
    function createCircle(elements, styles) {
    	for (var x in elements.values()) {
    	   	var diameter = styles[x][0], color = styles[x][1], textColor = styles[x][2];
    		x.style.height = diameter;
    		x.style.width = diameter;
    		x.style.color = textColor;
            x.style.backgroundColor = color;
            x.style.borderColor = color;
    		x.style.borderRadius = diameter;
    	}
    return true;
    }
    function run() {
        var x = document.querySelectorAll('a.toCircle:not([class*=\"catsCircle\"])');
        var elements = [], styles = [];
        for(var y = 0; y < x.length; y++) {
	       elements.push(x[y]);
	       styles.push(x[y].dataset.format.split(' '));
        }
        createCircle(elements, styles);

        $(document).ready( function () {
            $('.appt-newform').submit( function (e) {
                e.preventDefault();
                var gid = $(this).find('#appt-gid').val();
                var cid = $(this).find('#appt-clientid').val();
                var divSpecial = $(this).closest('.appt-special');

                $.ajax({
                    type: 'POST',
                    data: { cmd: 'therapist--appt_newform', appt_gid: gid, cid: cid },
                    url: 'jx.php',
                    success: function(data, textStatus, jqXHR) {
                        var jsData = JSON.parse(data);
                        var sSpecial = jsData.bOk ? jsData.sOut : 'No, something is wrong';
                        divSpecial.html( sSpecial );
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



    ."<script> run(); </script>"
    ."</body></html>";

        return( $s );
    }


    function SetScreen($screen){
        $this->screen = $screen;
    }

}


class CATS_MainUI extends CATS_UI
{
    function __construct( SEEDAppConsole $oApp )
    {
        parent::__construct( $oApp );
    }

    function Screen( $screen ) {
        $this->SetScreen( $screen );

        $s = "";

        if( substr( $screen, 0, 5 ) == 'admin' ) {
            $s = $this->DrawAdmin();
        } else if( substr( $screen, 0, 9 ) == "therapist" ) {
            $s = $this->DrawTherapist();
        } else if( $screen == "logout" ) {
            $s = $this->DrawLogout();
        } else {
            $s = $this->DrawHome();
        }

        return( $s );
    }


    function DrawHome()
    {
        $s = $this->Header()."<h2>Home</h2>"
            .($this->oApp->sess->CanRead('therapist') ? "<a href='?screen=therapist' class='toCircle catsCircle1'>Therapist</a>" : "")
            .($this->oApp->sess->CanRead('admin')     ? "<a href='?screen=admin' class='toCircle' data-format='200px red blue'>Admin</a>" : "");

        return( $s );
    }

    function DrawTherapist()
    {
        return( drawTherapist( $this->screen, $this->oApp ) );
    }

    function DrawAdmin()
    {
        return( drawAdmin( $this->oApp ) );
    }

    function DrawLogout()
    {
        $this->oApp->sess->LogoutSession();
        header( "Location: ".CATSDIR );
    }
}



function drawTherapist( $screen, $oApp )
{
    $raTherapistScreens = array(
        array( 'home',                      "Home" ),
        array( 'therapist-handouts',        "Print Handouts" ),
        array( 'therapist-formscharts',     "Print Forms for Charts" ),
        array( 'therapist-linedpapers',     "Print Different Lined Papers" ),
        array( 'therapist-entercharts',     "Enter Charts" ),
        array( 'therapist-ideas',           "Get Ideas" ),
        array( 'therapist-materials',       "Download Marketable Materials" ),
        array( 'therapist-team',            "Meet the Team" ),
        array( 'therapist-submitresources', "Submit Resources to Share" ),
        array( 'therapist-clientlist',      "Clients and Providers" ),
        array( 'therapist-calendar',        "Calendar" ),
    );


    global $oUI;
    $s = $oUI->Header()."<h2>Therapist</h2>";
    switch( $screen ) {
        case "therapist":
        default:
            $s .= "<div class='container-fluid'>";
            $i = 0;
            foreach( $raTherapistScreens as $ra ) {
                $circle = "catsCircle".($i % 2 + 1);

                if( $i % 4 == 0 ) $s .= "<div class='row'>";
                $s .= "<div class='col-md-3'><a href='?screen={$ra[0]}' class='toCircle $circle'>{$ra[1]}</a></div>";
                if( $i % 4 == 3 ) $s .= "</div>";   // row
                ++$i;
            }
            if( $i && $i % 4 != 0 ) $s .= "</div>"; // end row if it didn't complete in the loop

            break;

        case "therapist-handouts":
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= "PRINT HANDOUTS";
            break;
        case "therapist-formscharts":
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= "PRINT FORMS FOR CHARTS";
            break;
        case "therapist-linedpapers":
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= "PRINT DIFFERENT LINED PAPERS";
            break;
        case "therapist-entercharts":
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= "ENTER CHARTS";
            break;
        case "therapist-ideas":
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= "GET IDEAS";
            break;
        case "therapist-materials":
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= DownloadMaterials( $oApp );
            break;
        case "therapist-team":
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= "MEET THE TEAM";
            break;
        case "therapist-submitresources":
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= "SUBMIT RESOURCES";
            $s .= "<form action=\"share_resorces_upload.php\" method=\"post\" enctype=\"multipart/form-data\">
                Select resource to upload:
                <input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\">
                <br /><input type=\"submit\" value=\"Upload File\" name=\"submit\">
                </form>";
            break;
        case "therapist-clientlist":
            $o = new ClientList( $oApp->kfdb );
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= $o->DrawClientList();
            break;
        case "therapist-calendar":
            require_once CATSLIB."calendar.php";
            $o = new Calendar( $oApp );
            $s .= ($oApp->sess->CanAdmin('therapist')?"<a href='?screen=therapist' >Therapist</a><br />":"");
            $s .= $o->DrawCalendar();
    }
    return( $s );
}
function drawAdmin($oApp)
{
    global $oUI;
    $s = "";
    if(SEEDInput_Str("screen") == "admin-droptable"){
        $oApp->kfdb->Execute("drop table ot.clients");
        $oApp->kfdb->Execute("drop table ot.clients_pros");
        $oApp->kfdb->Execute("drop table ot.professionals");
        $oApp->kfdb->Execute("drop table ot.SEEDSession_Users");
        $oApp->kfdb->Execute("drop table ot.SEEDSession_Groups");
        $oApp->kfdb->Execute("drop table ot.SEEDSession_UsersXGroups");
        $oApp->kfdb->Execute("drop table ot.SEEDSession_Perms");
        $s .= "<div class='alert alert-success'> Oops I miss placed your data</div>";
    }
    $s .= $oUI->Header()."<h2>Admin</h2>";
    $s .= "<a href='?screen=home' class='toCircle catsCircle2'>Home</a><a href='?screen=therapist' class='toCircle catsCircle2'>Therapist</a>";
    if($oApp->sess->CanAdmin("DropTables")){
        $s .= "<button onclick='drop();' class='toCircle catsCircle2'>Drop Tables</button>"
        ."<script>function drop(){
          var password = prompt('Enter the admin password');
          $.ajax({
                url: 'administrator-password.php',
                type: 'POST',
                data: {'password':password},
                cache: 'false',
                success: function(result){
                    location.href = '?screen=admin-droptable';
                },
                error: function(jqXHR, status, error){
                    alert('You are not authorized to perform this action');
                }
          });
          }</script>";
    }
    if($oApp->sess->CanWrite("admin")){$s .= "<a href='review_resources.php' class='toCircle catsCircle2'>Review Resources</a>";}
        return( $s );
}

?>
