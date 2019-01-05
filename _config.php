<?php

if( @$_SERVER['HTTP_HOST'] == 'localhost' ) {
    /* Enable all error reporting on development machines (when your url starts with http://localhost).
     * This is not true on the real server (the 'HOST' variable is catherapyservices.ca)
     */
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
    ini_set('html_errors', 1);
}


/* CATSDIR is the location of the cats root directory. If you're doing something weird like running cats from
 * by including cats/index.php from another directory, define CATSDIR relative to that place before your include.
 * Otherwise, this default will make things work for the normal case.
 */
if( !defined("CATSDIR") ) { define( "CATSDIR", "./" ); }

if( !defined("W_CORE") )       define( "W_CORE", SEEDROOT."wcore/" );       // use for php include, fileopen()
if( !defined("W_CORE_URL") )   define( "W_CORE_URL", W_CORE );              // use for references to files that the browser has to find (e.g. js, css in <head>)
if( !defined("W_CORE_FPDF") )  define( "W_CORE_FPDF", W_CORE."os/fpdf181/" );

// deprecate W_ROOT: SEEDGoogleService uses W_ROOT, but it should use W_CORE instead
if( !defined("W_ROOT") )   define( "W_ROOT", "./w/" );


if( !file_exists(SEEDROOT."seedcore/SEEDCore.php") ) die( "SEEDROOT is not correct: ".SEEDROOT );

define( "SEEDCORE", SEEDROOT."seedcore/" );
define( "SEEDAPP", SEEDROOT."seedapp/" );
define( "SEEDLIB", SEEDROOT."seedlib/" );

// include everything that SEEDROOT gets via composer
require_once SEEDROOT."vendor/autoload.php";

require_once SEEDCORE."SEEDCore.php";
require_once SEEDCORE."SEEDApp.php" ;
require_once SEEDCORE."SEEDCoreForm.php";
require_once SEEDCORE."SEEDMetaTable.php";
require_once SEEDROOT."Keyframe/KeyframeForm.php" ;
require_once SEEDROOT."Keyframe/KeyframeDB.php" ;
require_once SEEDROOT."DocRep/DocRepDB.php" ;

require_once "assessments.php";
require_once 'Clinics.php';
require_once "database.php";
require_once "cats_ui.php";
require_once "documents.php";
require_once "people.php";
require_once 'therapist-clientlist.php';


if( !defined("CATSDIR_IMG") ) { define( "CATSDIR_IMG", CATSDIR."w/img/" ); }
if( !defined("CATSDIR_JS") ) { define( "CATSDIR_JS", CATSDIR."w/js/" ); }
if( !defined("CATSDIR_CSS") ) { define( "CATSDIR_CSS", CATSDIR."w/css/" ); }
if( !defined("CATSDIR_RESOURCES") ) { define( "CATSDIR_RESOURCES", CATSDIR."resources/" ); }
if( !defined("CATSDIR_DOCUMENTATION")){ define( "CATSDIR_DOCUMENTATION", CATSDIR."w/documentation/");}

$dirImg = CATSDIR_IMG;
//Directory to the logo used on the CATS server
define("CATS_LOGO", CATSDIR_IMG."cats_wide.png");

// $email_processor is a global that must be defined in catsdef.php
// The default server is set here
if( !isset($email_processor['emailServer']) ) {
    $email_processor['emailServer'] = "catherapyservices.ca";
}


// Create oApp for all files to use
$oApp = new SEEDAppConsole(
                array_merge( $catsDefKFDB,
                             array( 'sessPermsRequired' => array(),
                                    //'sessParms' => array( 'logfile' => CATSDIR_LOG."seedsession.log"),
                                    'logdir' => CATSDIR_LOG )
                           )
);


if( @$_SERVER['HTTP_HOST'] == 'localhost' ) {
    $oApp->kfdb->SetDebug(1);
}

?>