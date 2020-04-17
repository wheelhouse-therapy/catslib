<?php

/* FilingCabinetUpload
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * See FilingCabinet.php for information
 */

class FilingCabinetUpload
{
    const   fileid = "fileToUpload";     // the key for $_FILES
    private $oApp;
    private $oFC;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oFC = new FilingCabinet( $oApp );
    }

    static function DrawUploadForm()
    {
        // put a dot in front of each ext and commas in between them e.g. ".docx,.pdf,.mp4"
        $acceptedExts = SEEDCore_ArrayExpandSeries( FilingCabinet::GetSupportedExtensions(),
                                                    ".[[]],", true, ["sTemplateLast"=>".[[]]"] );
        return( "<form action='?screen=therapist-resources' method='post' enctype='multipart/form-data'>
                 Select file to upload:
                 <input type='file' name='".self::fileid."' id='".self::fileid."' accept='$acceptedExts'>
                 Max File size:".ini_get('upload_max_filesize')."b <br/>
                 <input type='submit' value='Upload File' name='submit'></form>" );
    }

    function UploadToPending()
    /*************************
        Following a _FILES upload, put the file in the "pending" folder.
     */
    {
        $s = "";

        FilingCabinet::EnsureDirectory("pending");

        $s .= "<a href='?screen=therapist-submitresources'><button>Back</button></a><br />";

        // check if a file was uploaded
        if( !$_FILES[self::fileid]["name"] || !$_FILES[self::fileid]['size'] ) {
            $s .= "Sorry, nothing was uploaded.<br/>";
            goto done;
        }

        $target_dir = CATSDIR_RESOURCES."pending/";
        $target_file = $target_dir . basename($_FILES[self::fileid]["name"]);
        $documentFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

        // Check if file already exists
        if (file_exists($target_file)) {
            $s .= "Sorry, file already exists.<br />";
            goto done;
        }
        // Check file size
        if ($_FILES[self::fileid]["size"] > max_file_upload_in_bytes()) {
            $s .= "Sorry, your file is too large.<br />";
            goto done;
        }
        // Allow certain file formats
        if(!in_array($documentFileType, FilingCabinet::GetSupportedExtensions())) {
            $s .= "Sorry, only ".implode(", ", FilingCabinet::GetSupportedExtensions())." files are allowed.<br />";
            goto done;
        }

        if (move_uploaded_file($_FILES[self::fileid]["tmp_name"], $target_file)) {
            $s .= "The file ". basename( $_FILES[self::fileid]["name"]). " has been uploaded and is awaiting review.";
            if($this->oApp->sess->CanWrite("admin")){
                $s .= "<br /><a href='?screen=admin-resources'><button>Review Now</button></a>";
            }
        } else {
            $s .= "Sorry, there was an error uploading your file.";
        }

        done:
        return( $s );
    }
}