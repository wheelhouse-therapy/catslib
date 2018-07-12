<?php

/* While in development, enable all error reporting. When this code is in real-world use, this should be commented out.
 */
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
ini_set('html_errors', 1);


/* CATSDIR is the location of the cats root directory. If you're doing something weird like running cats from
 * by including cats/index.php from another directory, define CATSDIR relative to that place before your include.
 * Otherwise, this default will make things work for the normal case.
 */
if( !defined("CATSDIR") ) { define( "CATSDIR", "./" ); }

if( !defined("W_CORE") )       define( "W_CORE", "../seeds/wcore/" );       // use for php include, fileopen()
if( !defined("W_CORE_URL") )   define( "W_CORE_URL", W_CORE );              // use for references to files that the browser has to find (e.g. js, css in <head>)
if( !defined("W_CORE_FPDF") )  define( "W_CORE_FPDF", W_CORE."os/fpdf181/" );

// deprecate W_ROOT: SEEDGoogleService uses it but it should use W_CORE instead
if( !defined("W_ROOT") )   define( "W_ROOT", "./w/" );


if( !file_exists(SEEDROOT."seedcore/SEEDCore.php") ) die( "SEEDROOT is not correct: ".SEEDROOT );

define( "SEEDCORE", SEEDROOT."seedcore/" );
define( "SEEDAPP", SEEDROOT."seedapp/" );
define( "SEEDLIB", SEEDROOT."seedlib/" );

require_once SEEDCORE."SEEDCoreForm.php";
require_once SEEDCORE."SEEDCore.php";
require_once SEEDROOT."seedapp/SEEDApp.php" ;
require_once SEEDROOT."Keyframe/KeyframeForm.php" ;
require_once SEEDROOT."Keyframe/KeyframeDB.php" ;
require_once SEEDROOT."DocRep/DocRepDB.php" ;


require_once "database.php";
require_once "cats_ui.php";
require_once "documents.php";



if( !defined("CATSDIR_IMG") ) { define( "CATSDIR_IMG", CATSDIR."w/img/" ); }
if( !defined("CATSDIR_JS") ) { define( "CATSDIR_JS", CATSDIR."w/js/" ); }

$dirImg = CATSDIR_IMG;


// Create oApp for all files to use
$oApp = new SEEDAppConsole(
                array_merge( $catsDefKFDB,
                             array( 'sessPermsRequired' => array(),
                                    'sessParms' => array( 'logfile' => CATSDIR_LOG."seedsession.log"))
                           )
);
$oApp->kfdb->SetDebug(1);

?>