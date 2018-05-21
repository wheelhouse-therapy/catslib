<?php
require_once '_config.php';
require_once "cats_ui.php" ;

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
echo $oUI->Header();
$target_dir = "pending_resources/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$documentFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
// Check if file already exists
echo "<a href='".CATSDIR."?screen=therapist-submitresources'><button>Back</button></a><br />";
if (file_exists($target_file)) {
    echo "Sorry, file already exists.";
    $uploadOk = 0;
}
// Check file size
if ($_FILES["fileToUpload"]["size"] > 500000) {
    echo "Sorry, your file is too large.";
    $uploadOk = 0;
}
// Allow certain file formats
if($documentFileType != "pdf" && $documentFileType != "doc" && $documentFileType != "docx" && $documentFileType != "txt" && $documentFileType != "rtf" ) {
    echo "Sorry, only PDF, doc, docx, rtf & txt files are allowed.";
    $uploadOk = 0;
}
// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
    echo "Sorry, your file was not uploaded.";
    // if everything is ok, try to upload file
} else {
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        echo "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded and is awaiting review.";
        if($sess->CanWrite("admin")){
            echo "<br /><a href='review_resources.php'><button>Review Now</button></a>";
        }
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}
?>