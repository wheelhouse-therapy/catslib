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
        $clinics = new Clinics($this->oApp);
        return( "<div class='cats_header' style='overflow:auto'>"
                   ."<img src='".CATSDIR_IMG."CATS.png' style='max-width:300px;float:left;'/>"
                   ."<div style='float:none'>".$clinics->displayUserClinics()."</div>"
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
    a.toCircle, button.toCircle {
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
        from {background-color: var(--color1); border-color: var(--color1);}
        to {background-color: var(--color2); border-color: var(--color2);}
    }
    a.catsCircle1, button.catsCircle1 {
        box-sizing: border-box;
    	height: 200px;
    	width: 200px;
    	border-radius: 100px;
    	color: var(--textColor);
    	animation: colorChange 10s ease-in-out infinite alternate;
    }
    a.catsCircle2, button.catsCircle2 {
    	box-sizing: border-box;
        height: 200px;
    	width: 200px;
    	border-radius: 100px;
    	color: var(--textColor);
    	animation: colorChange 10s ease-in-out -5s infinite alternate;
    }
    span.selectedClinic {
        font-size: 20pt;
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
		  elements[x].style.borderRadius = diameter;
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
            $('.appt-newform').submit( function (e) {
                e.preventDefault();
                var gid = $(this).find('#appt-gid').val();
                var cid = $(this).find('#appt-clientid').val();
                var divSpecial = this.appt();

                $.ajax({
                    type: 'POST',
                    data: { cmd: 'catsappt--reviewed', google_cal_ev_id: gid, fk_clients: cid },
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
    function __construct( SEEDAppConsole $oApp )
    {
        parent::__construct( $oApp );
    }

    function Screen( $screen ) {
        $this->SetScreen( $screen );

        $s = $this->Header();
        $clinics = new Clinics($this->oApp);
        if($clinics->GetCurrentClinic() == NULL){
            $s = $this->Header()."<h2>Please Select a clinic to continue</h2>"
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
        $s = "<h2>Home</h2>"
            .($this->oApp->sess->CanRead('therapist') ? $this->DrawTherapist() : "")
            .($this->oApp->sess->CanRead('admin')     ? $this->DrawAdmin() : "")
            .($this->oApp->sess->CanRead('administrator')     ? $this->DrawDeveloper() : "");


        return( $s );
    }

    function DrawTherapist()
    {
        $raTherapistScreens = array(
            array( 'therapist-calendar',        "Calendar" ),
            array( 'therapist-clientlist',      "Enter or Edit Clients and Providers" ),
            array( 'therapist-handouts',        "Print Handouts" ),
            array( 'therapist-formscharts',     "Print Forms for Charts" ),
            array( 'therapist-linedpapers',     "Print Different Lined Papers" ),
            array( 'therapist-ideas',           "Get Ideas" ),
            array( 'therapist-materials',       "Download Marketing Materials" ),
            array( 'therapist-team',            "Meet the Team" ),
            array( 'therapist-submitresources', "Submit Resources to Share" ),
        );

        $s = "";
        switch( $this->screen ) {
            case "therapist":
            default:
                $s .= $this->drawCircles( $raTherapistScreens );
                break;

            case "therapist-handouts":
                $s .= "PRINT HANDOUTS";
                break;
            case "therapist-formscharts":
                $s .= "PRINT FORMS FOR CHARTS";
                break;
            case "therapist-linedpapers":
                $s .= "PRINT DIFFERENT LINED PAPERS";
                break;
            case "therapist-entercharts":
                $s .= "ENTER CHARTS";
                break;
            case "therapist-ideas":
                $s .= "GET IDEAS";
                break;
            case "therapist-materials":
                $s .= DownloadMaterials( $this->oApp );
                break;
            case "therapist-team":
                $s .= "MEET THE TEAM";
                break;
            case "therapist-submitresources":
                $s .= "SUBMIT RESOURCES";
                $s .= "<form action=\"share_resorces_upload.php\" method=\"post\" enctype=\"multipart/form-data\">
                    Select resource to upload:
                    <input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\">
                    <br /><input type=\"submit\" value=\"Upload File\" name=\"submit\">
                    </form>";
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
                $oApp->kfdb->Execute("drop table $db.clients");
                $oApp->kfdb->Execute("drop table $db.clients_pros");
                $oApp->kfdb->Execute("drop table $db.professionals");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_Users");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_Groups");
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
            default:
                    $s .= "<button onclick='drop();' class='toCircle catsCircle2'>Drop Tables</button>
                           <script>
                               function drop() {
                                   if (confirm('Are you sure? THIS CANNOT BE UNDONE')) {
                                       window.location.href = '".CATSDIR."?screen=developer-droptable';
                                   }
                               }
                           </script>";
                    $s .= "<a href='?screen=developer-clinics' class='toCircle catsCircle1'>Manage Clinics</a>";
        }
        return( $s );
    }

    private function drawCircles( $raScreens )
    {
        $s = "<div class='container-fluid'>";
        $i = 0;
        foreach( $raScreens as $ra ) {
            $circle = "catsCircle".($i % 2 + 1);

            if( $i % 4 == 0 ) $s .= "<div class='row'>";
            $s .= "<div class='col-md-3'><a href='?screen={$ra[0]}' class='toCircle $circle'>{$ra[1]}</a></div>";
            if( $i % 4 == 3 ) $s .= "</div>";   // row
            ++$i;
        }
        if( $i && $i % 4 != 0 ) $s .= "</div>"; // end row if it didn't complete in the loop

        return( $s );
    }

    private function drawAdminUsers()
    {
        $s = "";

        $oUI = new MySEEDUI();
        $oComp = new MySEEDUIComponent( $oUI );
        $oComp->Update();



        $oList = new SEEDUIWidget_List( $oComp );
        $oSrch = new SEEDUIWidget_SearchControl( $oComp, array('filters'=> array('First Name'=>'firstname','Last Name'=>'lastname')) );
    // should the search control config:filters use the same format as list:cols - easier and extendible
        $oComp->Start();

        $raParms['cols'] = array(
            array( 'label'=>'First Name', 'col'=>'firstname' ),
            array( 'label'=>'Last Name',  'col'=>'lastname'  ),
            array( 'label'=>'Address',    'col'=>'address'   ),
            array( 'label'=>'Child',      'col'=>'child'     ),
        );
        $raView = array(
            array( 'firstname'=>'Fred',   'lastname'=>'Flintstone', 'address'=>'33 Rocky Road', 'child'=>'Pebbles' ),
            array( 'firstname'=>'Wilma',  'lastname'=>'Flintstone', 'address'=>'33 Rocky Road', 'child'=>'Pebbles' ),
            array( 'firstname'=>'Betty',  'lastname'=>'Rubble',     'address'=>'34 Rocky Road', 'child'=>'Bam Bam' ),
            array( 'firstname'=>'Barney', 'lastname'=>'Rubble',     'address'=>'34 Rocky Road', 'child'=>'Bam Bam' ),
        );
        $sList = $oList->ListDrawInteractive( $raView, $raParms );

        $sSrch = $oSrch->Draw();

        $s = $oList->Style()
            ."<table width='100%'><tr>"
            ."<td><h3>I am a Search Control</h3>"
            ."<div style='width:90%;height:300px;border:1px solid:#999'>".$sSrch."</div>"
            ."</td>"
            ."<td><h3>I am an Interactive List</h3>"
            ."<div style='width:90%;height:300px;border:1px solid:#999'>".$sList."</div>"
            ."</td>"
            ."</tr><tr>"
            ."<td><h3>I am a Form</h3>"
            ."<div style='width:90%;height:300px;border:1px solid:#999'></div>"
            ."</td>"
            ."<td><h3>I am a Stegosaurus</h3>"
            ."<div style='width:90%;height:300px;border:1px solid:#999'></div>"
            ."</td>"
            ."</tr></table>";


        return( $s );
    }
}


require_once SEEDCORE."SEEDUI.php";
class MySEEDUI extends SEEDUI
{
    function __construct() { parent::__construct(); }
}


class MySEEDUIComponent extends SEEDUIComponent
{
    function __construct( SEEDUI $o ) { parent::__construct( $o ); }

}

?>
