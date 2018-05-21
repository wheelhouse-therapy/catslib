<?php
require_once '_config.php';
require_once "cats_ui.php" ;
$dir_name = "pending_resources";
$dir_accept = "accepted_resources";

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

$oUI = new CATS_UI();
echo "<head><link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css' integrity='sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm' crossorigin='anonymous'></head>";
echo $oUI->Header();
echo "<a href='".CATSDIR."?screen=admin'><button>Back</button></a><br />";
$cmd = SEEDInput_Str( 'cmd' );
if($cmd == "accept"){
    $file = SEEDInput_Str( 'file' );
    if(rename($dir_name."/".$file, $dir_accept."/".$file)){
        echo "<div class='alert alert-success'> File ".$file." has been accepted as a resource</div>";
    }
    else{
        echo "<div class='alert alert-error'>An error occured while accepting File ".$file."</div>";
    }
}
elseif ($cmd == "reject"){
    $file = SEEDInput_Str( 'file' );
    if(unlink($dir_name."/".$file)){
        echo "<div class='alert alert-success'> File ".$file." has been rejected as a resource. This CANNOT be undone</div>";
    }
    else{
        echo "<div class='alert alert-error'>An error occured while rejecting File ".$file."</div>";
    }
}
$dir = new DirectoryIterator($dir_name);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        echo "<a href='".$fileinfo->getPath()."/".$fileinfo->getFilename()."'>".$fileinfo->getFilename()."</a>
        <a href='?cmd=accept&file=".$fileinfo->getFilename()."'><img src='".CATSDIR_IMG."accept-resource.png' style='max-width:20px;'/></a>
        <a href='?cmd=reject&file=".$fileinfo->getFilename()."'><img src='".CATSDIR_IMG."reject-resource.png' style='max-width:20px;'/></a>
        <br />";
    }
}