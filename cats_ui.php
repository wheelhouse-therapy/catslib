<?php

/* Classes to help draw the user interface
 */
class CATS_UI
{

    private $screen = "";

    function __construct() {}

    function Header()
    {
        if( !($kfdb = new KeyframeDatabase( "ot", "ot" )) ||
            !$kfdb->Connect( "ot" ) )
        {
            die( "Cannot connect to database<br/><br/>You probably have to execute these two MySQL commands<br/>"
                ."CREATE DATABASE ot;<br/>GRANT ALL ON ot.* to 'ot'@'localhost' IDENTIFIED BY 'ot'" );
        }

        $sess = new SEEDSessionAccount( $kfdb, array(), array( 'logfile' => "seedsession.log") );
        if(!$sess->IsLogin()){
            echo "<head><meta http-equiv=\"refresh\" content=\"0; URL=".CATSDIR."\"></head><body>You have Been Logged out<br /><a href=".CATSDIR."\"\">Back to Login</a></body>";
            exit;
        }
        return( "<div class='cats_header'>"
               ."<img src='".CATSDIR_IMG."CATS.png' style='max-width:300px;float:left;'/>"
            ."<div style='float:right'>"."Welcome, ".$sess->GetName()." ".($this->screen != "home"?"<a href='".CATSDIR."?screen=home'><button>Home</button></a>":"<a href='".CATSDIR."?screen=logout'><button>Logout</button></a>"."</div>")
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
    <script src=\"https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js\"></script>
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
                    data: { cmd: 'appt-newform', appt_gid: gid, cid: cid },
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
