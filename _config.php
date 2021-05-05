<?php

/**
 * CATS_DEBUG is a constant which allows code to only work on development machines.
 * It is true when the host starts with localhost. This is important because serving on port 8080
 * breaks normal @$_SERVER['HTTP_HOST'] == 'localhost' checks.
 * This occures because HTTP_HOST is localhost:8080 when you serve on port 8080
 */
define( "CATS_DEBUG", explode(":",@$_SERVER['HTTP_HOST'],2)[0] == 'localhost');

if( CATS_DEBUG ) {
    /* Enable all error reporting on development machines (when your url starts with http://localhost).
     * This is not true on the real server (the 'HOST' variable is catherapyservices.ca)
     */
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
    ini_set('html_errors', 1);
}

/* CATSDIR is the location of the cats root directory. If you're doing something weird like running cats
 * by including cats/index.php from another directory, define CATSDIR relative to that place before your include.
 *
 * CATSDIR_URL is used for <a href>, <img src>, <script src>, etc links i.e. where the browser will fetch a file.
 * Often this is the same as CATSDIR but you could configure CATSDIR with an absolute filesystem dir which wouldn't work for http links.
 * Hint: if you set an absolute CATSDIR you probably want "./" for CATSDIR_URL
 *
 * Otherwise, this default will make things work for the normal case.
 */
if( !defined("CATSDIR") )     { define( "CATSDIR", "./" ); }            // filesystem location of root dir
if( !defined("CATSDIR_URL") ) { define( "CATSDIR_URL", CATSDIR ); }     // http link location of root dir

define( "SITE_LOG_ROOT", CATSDIR_LOG); // "Deprecated" but also not

// SEEDROOT has to be configured; seedConfig.php should be able to find everything from there. If not, predefine what it can't find.
if( !file_exists(SEEDROOT."seedConfig.php") ) die( "SEEDROOT is not correct: ".SEEDROOT );

// SEEDCONFIG_DIR has to be set or seedConfig.php will search in its default locations.
// This is where google api config is found.
define( "SEEDCONFIG_DIR", CATSDIR_CONFIG );

require_once SEEDROOT."seedConfig.php";

if( !defined("W_CORE_FPDF") )  define( "W_CORE_FPDF", W_CORE."os/fpdf181/" );

// deprecate W_ROOT: SEEDGoogleService uses W_ROOT, but it should use W_CORE instead
if( !defined("W_ROOT") )   define( "W_ROOT", "./w/" );

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
require_once 'manage_users.php';

require_once "FilingCabinet/FilingCabinet.php";
require_once "FilingCabinet/FilingCabinetUI.php";
require_once "FilingCabinet/FilingCabinetDownload.php";
require_once "FilingCabinet/FilingCabinetUpload.php";
require_once "FilingCabinet/FilingCabinetReview.php";
require_once "FilingCabinet/FilingCabinetTools.php";

if( !defined("CATSDIR_IMG") ) { define( "CATSDIR_IMG", CATSDIR."w/img/" ); }
if( !defined("CATSDIR_JS") ) { define( "CATSDIR_JS", CATSDIR."w/js/" ); }
if( !defined("CATSDIR_CSS") ) { define( "CATSDIR_CSS", CATSDIR."w/css/" ); }
if( !defined("CATSDIR_RESOURCES") )     { define( "CATSDIR_RESOURCES", CATSDIR."resources/" ); }            // filesystem
if( !defined("CATSDIR_URL_RESOURCES") ) { define( "CATSDIR_URL_RESOURCES", CATSDIR_URL."resources/" ); }    // http

if( !defined("CATSDIR_DOCUMENTATION")){ define( "CATSDIR_DOCUMENTATION", CATSDIR."w/documentation/");}
if( !defined("CATSDIR_AKAUNTING")){ define( "CATSDIR_AKAUNTING", CATSDIR."w/akaunting/");}
if( !defined("CATSDIR_FONTS")){ define( "CATSDIR_FONTS", CATSDIR."w/fonts/");}

//$dirImg = CATSDIR_IMG;
//Directory to the logo used on the CATS server
define("CATS_LOGO", CATSDIR_IMG."cats_wide.png");

// $email_processor is a global that must be defined in catsdef.php
// The default server for email is set here
if( !isset($email_processor['emailServer']) ) {
    $email_processor['emailServer'] = "catherapyservices.ca";
}
// $email_processor is a global that must be defined in catsdef.php
// The default server for akaunting is set here
if( !isset($email_processor['akauntingServer']) ) {
    $email_processor['akauntingServer'] = "https://catherapyservices.ca";
}

// set this in catsdef.php if your Akaunting installation is not at http://localhost/akaunting
if( !isset($email_processor['akauntingBaseUrl']) ) {
    $email_processor['akauntingBaseUrl'] = "/akaunting";
}

// Create oApp for all files to use
$oApp = new SEEDAppConsole(
                array_merge( $config_KFDB['cats'],
                             array( 'sessPermsRequired' => array(),
                                    'logdir' => CATSDIR_LOG )
                           )
);


/**
 * CATS_SYSADMIN is true IF AND ONLY IF the user can read the administrator permission.
 * Users with this permission are to be considered system administratiors (eg. dev) and get access to extra features.
 */
define("CATS_SYSADMIN", $oApp->sess->CanRead('administrator'));

if( CATS_SYSADMIN ) {
    /* Enable all error reporting on development dev account
     */
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
    ini_set('html_errors', 1);
    $oApp->kfdb->SetDebug(1);
}
if( CATS_DEBUG ) {
    $oApp->kfdb->SetDebug(1);
}

FilingCabinet::checkFileSystem($oApp);
