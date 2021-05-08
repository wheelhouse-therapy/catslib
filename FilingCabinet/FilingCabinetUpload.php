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

    private const modal = <<<Modal
<!-- the div that represents the modal dialog -->
<div class="modal fade" id="details_dialog" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add Details</h4>
            </div>
            <div class="modal-body" id='details_body'>
                <form id="details_form" onsubmit='event.preventDefault();' action="jx.php" method="POST" enctype="multipart/form-data">
                    <input type='hidden' name='cmd' value='therapist-resourceDetails' />
                    <input type='hidden' name='rrid' value='[[id]]' />
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <label for='description'>Description:</label><br />
                                <textarea id='description' name='description' style='width:100%'></textarea>
                            </div>
                            <div class="col">
                                <label for='created_by'>Created By:</label><br />
                                <input type='text' id='created_by' name='created_by' />
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button onclick='closeDetails()' class="btn btn-default">Close</button>
                <input type='submit' id='details_submit' form='details_form' class="btn btn-default" value='Add' onclick='submitModal(event)' />
            </div>
        </div>
    </div>
</div>
Modal;

    function __construct( SEEDAppConsole $oApp, String $sCabinet )
    {
        $this->oApp = $oApp;
        $this->sCabinet = $sCabinet;
        $this->oFC = new FilingCabinet( $oApp );
    }

    static function DrawUploadForm( $sCabinet )
    {
        $label = $sCabinet=='videos' ? 'Video' : 'File';

        // put a dot in front of each ext and commas in between them e.g. ".docx,.pdf,.mp4"
        $acceptedExts = SEEDCore_ArrayExpandSeries( FilingCabinet::GetSupportedExtensions($sCabinet),  // not $this->sCabinet because static
            ".[[]],", true, ["sTemplateLast"=>".[[]]"] );
        $s = "<div>
                <form method='post' id='upload-file-form' onsubmit='event.preventDefault();' enctype='multipart/form-data'>
                    <input type='hidden' name='cmd' value='therapist-resource-upload' />
                    Select $label to upload:

                    <input type='file' name='".self::fileid."' id='".self::fileid."' accept='$acceptedExts' required /><br />"
                    // <span> is necessary to make the required attribute change the button's colour
                  ."<span><input type='submit' id='upload-file-button' value='Upload $label' name='submit' onclick='submitForm(event)'></span>
                    Max Upload size:".(CATS_DEBUG?ini_get('upload_max_filesize'):"700M")."b
                </form>
                <div id='upload-bar'>
                    <div id='progress-bar'><div id='filled-bar'></div></div>
                    <span id='progress-percentage'>0%</span>
                </div>
              </div>";
        return( $s );
    }

    function UploadToPending()
    /*************************
     Following a _FILES upload, put the file in the "pending" folder.
     */
    {
        $s = "";

        FilingCabinet::EnsureDirectory("pending");

        $s .= "<button onclick='resetForm()'>Reset</button><br />";

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
            $s .= "Sorry, file is already awaiting review";
            if($this->oApp->sess->CanWrite("admin")){
                $s .= "<br /><a href='?screen=admin-resources'><button>Review Now</button></a>";
            }
            goto done;
        }

        // Allow certain file formats
        if(!in_array($documentFileType, FilingCabinet::GetSupportedExtensions())) {
            $s .= "Sorry, only ".implode(", ", FilingCabinet::GetSupportedExtensions())." files are allowed. (Code 415)<br />";
            goto done;
        }

        if (move_uploaded_file($_FILES[self::fileid]["tmp_name"], $target_file)) {
            $oRR = ResourceRecord::CreateFromRealPath($this->oApp, realpath($target_file), $this->sCabinet);
            $stored = $oRR->StoreRecord();
            if(!$stored){
                $s .= "<div class='alert alert-danger'>Unable to index the file. Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
            }
            $s .= "The file ". basename( $_FILES[self::fileid]["name"]). " has been uploaded and is awaiting review.";
            if($stored){$s .= "<br /><button id='details_button' onclick='addDetails()'>Add Details</button>";}
            if($this->oApp->sess->CanWrite("admin")){
                $s .= "<a style='margin-left:5px' href='?screen=admin-resources'><button>Review Now</button></a>";
            }
            if($stored){
                $s .= str_replace("[[id]]", $oRR->getID(), self::modal);
            }
        } else {
            $s .= "Sorry, there was an error uploading your file.";
        }

        done:
        return( $s );
    }
}
