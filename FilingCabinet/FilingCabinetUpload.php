<?php

/* FilingCabinetUpload
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * See FilingCabinet.php for information
 */

class FilingCabinetUpload
{
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
                 <input type='file' name='fileToUpload' id='fileToUpload' accept='$acceptedExts'>
                 Max File size:".ini_get('upload_max_filesize')."b <br/>
                 <input type='submit' value='Upload File' name='submit'></form>" );
    }
}
