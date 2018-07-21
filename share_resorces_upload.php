<?php
require_once '_start.php';

$target_dir = "pending_resources/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$documentFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
// Check if file already exists
$s .= "<a href='".CATSDIR."?screen=therapist-submitresources'><button>Back</button></a><br />";
if (file_exists($target_file)) {
    $s .= "Sorry, file already exists.";
    $uploadOk = 0;
}
// Check file size
if ($_FILES["fileToUpload"]["size"] > 500000) {
    $s .= "Sorry, your file is too large.";
    $uploadOk = 0;
}
// Allow certain file formats
if($documentFileType != "pdf" && $documentFileType != "doc" && $documentFileType != "docx" && $documentFileType != "txt" && $documentFileType != "rtf" ) {
    $s .= "Sorry, only PDF, doc, docx, rtf & txt files are allowed.";
    $uploadOk = 0;
}
// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
    $s .= "Sorry, your file was not uploaded.";
    // if everything is ok, try to upload file
} else {
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        $s .= "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded and is awaiting review.";
        if($this->oApp->sess->CanWrite("admin")){
            $s .= "<br /><a href='?screen=admin-resources'><button>Review Now</button></a>";
        }
    } else {
        $s .= "Sorry, there was an error uploading your file.";
    }
}
?>