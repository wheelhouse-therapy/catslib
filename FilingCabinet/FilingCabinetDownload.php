<?php

/* FilingCabinetDownload
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * See FilingCabinet.php for information
 */

require_once CATSLIB.'client_code_generator.php';
require_once CATSLIB.'Clinics.php';

class FilingCabinetDownload
{
    private $oApp;
    private $oFC;
    var $oResourcesFiles;   // make this private when its referers are moved into this class

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oFC = new FilingCabinet( $oApp );
        $this->oResourcesFiles = new ResourcesFiles( $oApp );
    }

    function DownloadFile()
    {
        if( SEEDInput_Str('cmd') == 'download' ) {

            if( !($rrid = SEEDInput_Int('rr')) ||
                !($oRR = ResourceRecord::GetRecordByID($this->oApp,$rrid)) ||
                !($file = $oRR->getPath()) )
            {
                $this->oApp->oC->AddErrMsg( "Could not retrieve file $rrid" );
                return;
            }

            $resmode = SEEDInput_Str('resource-mode');

            if($resmode == "email"){
                $kClient = SEEDInput_Int('client');
                $oPeopleDB = new PeopleDB($this->oApp);
                $kfr = $oPeopleDB->GetKFR(ClientList::CLIENT, $kClient);
                $to = $kfr->Value('P_email');
                $user = $oPeopleDB->getKFRCond("P","uid='{$this->oApp->sess->GetUID()}'");
                $from = $user->Value('email');
                $fromName = $user->Value('first_name')." ".$user->Value("last_name");
                
                // Message Content
                $subject = "Resource from CATS";
                $body = "Please find a therapy resource specifically for you attached, as we discussed in the session.\n\nSincerely\n[[therapist name]]";
                
                $body = str_replace("[[therapist name]]", $fromName, $body);
                
                // Brains behind message sending and Attachment
                $headers = "From: $fromName <$from>"; // Sender info
                $semi_rand = md5(time());
                $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";
                // Headers for attachment
                $headers .= "\nMIME-Version: 1.0\nContent-Type: multipart/mixed;\n boundary=\"{$mime_boundary}\"";
                $message = "--{$mime_boundary}\nContent-Type: text/plain; charset=\"UTF-8\"\n"
                ."Content-Transfer-Encoding: 7bit\n\n".$body."\n\n";
                if(!empty($file) > 0){
                    if(is_file($file)){
                        $filler = new template_filler($this->oApp, @$_REQUEST['assessments']?:[]);
                        $filename = $filler->fill_resource($file, ['client'=>$kClient],template_filler::RESOURCE_GROUP);
                        $code = (new ClientCodeGenerator($this->oApp))->getClientCode($kClient);
                        $message .= "--{$mime_boundary}\n";
                        $fp = @fopen($filename, "rb");
                        $data = @fread($fp, filesize($filename));
                        @fclose($fp);
                        $data = chunk_split(base64_encode($data));
                        $message .= "Content-Type: application/octet-stream; name=\"".$code.($code?"-":"").basename($file)."\"\n"
                            ."Content-Description: ".$code.($code?"-":"").basename($file)."\n"
                                ."Content-Disposition: attachment;\n filename=\"".$code.($code?"-":"").basename($file)."\"; size=".filesize($filename).";\n"
                                    ."Content-Transfer-Encoding: base64\n\n".$data."\n\n";
                    }
                }
                $message .= "--{$mime_boundary}--";
                $returnpath = "-f".$from;
                $mail = @mail($to, $subject, $message, $headers,$returnpath);
                $_SESSION['mailResult'] = $mail;
                $_SESSION['mailTarget'] = $to;
                header("HTTP/1.1 303 SEE OTHER");
                header("Location: ?dir=".SEEDInput_Str('dir'));
                exit();
            }
            else if( $resmode != "no_replace" ) {
                // mode blank is implemented by telling template_filler that client=0
                $kClient = ($resmode == 'blank' ) ? 0 : SEEDInput_Int('client');
                $filler = new template_filler($this->oApp, @$_REQUEST['assessments']?:[]);
                $filler->fill_resource($file, ['client'=>$kClient]);
            }
            else{
                 header('Content-Type: '.(@mime_content_type($file)?:"application/octet-stream"));
                 header('Content-Description: File Transfer');
                 header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                 header('Content-Transfer-Encoding: binary');
                 if( ($fp = fopen( $file, "rb" )) ) {
                     fpassthru( $fp );
                     fclose( $fp );
                 }
                 exit;
            }
            exit;   // actually fill_resource exits, but it's nice to have a reminder of that here
        }
    }

    function GetDownloadPath( $mode, ResourceRecord $oRR, $filename = "", $dir_short = "" )
    {
        $rrid = $oRR->getID();
        $dbFname = addslashes($dir_short.'/'.$filename);
        switch( $mode ) {
            case 'replace':
                return "href='javascript:void(0)' onclick=\"select_client('$rrid')\"";
            case 'no_replace':
                return "href='?cmd=download&rr=$rrid&resource-mode=no_replace'";    // &file=$dbFname
            case 'blank':
                return "href='?cmd=download&rr=$rrid&client=0'";                    // &file=$dbFname
        }
    }


    function GetDownloadMode( $download_modes, $dir_name )
    {
        $s = "";

    $resourceMode = <<<DownloadMode
        <div id='break'>
        <div class='alert alert-info' style='[display] flex-basis: 75%; min-height: 50px;'>Some files cannot be downloaded in the current mode. <a class='alert-link' href='?resource-mode=no_replace'>Click Here to view all files</a></div>
        <div id='ResourceMode'>
            <div id='modeText'><div data-tooltip='[tooltip]'><nobr>Current Mode:</nobr> [mode]</div></div>
            [[button1]]
            [[button2]]
        </div>
        </div>
DownloadMode;
        if( count($this->MODES) >= strlen($download_modes) && strlen($download_modes) > 1){
            $mode = $this->oApp->sess->SmartGPC("resource-mode");
            switch ($mode){
                case $this->MODES['s']['code']:
                    $tooltip = "Program replaces tags with data";
                    $resourceMode = str_replace("[mode]", "Substitution", $resourceMode);
                    $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
                    break;
                case $this->MODES['n']['code']:
                    $tooltip = "Download files with the substitution tags";
                    $resourceMode = str_replace("[mode]", $this->MODES['n']['title'], $resourceMode);
                    $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
                    break;
                case $this->MODES['b']['code']:
                    $tooltip = "No tags or data.<br />Use this if you are stocking your paper filing cabinet with a handout";
                    $resourceMode = str_replace("[mode]", "Blank", $resourceMode);
                    $resourceMode = str_replace("[tooltip]", $tooltip, $resourceMode);
                    break;
            }
            $s .= getModeOptions($resourceMode, $download_modes, $mode, $dir_name);
        }
        else if(strlen($download_modes) == 1){
            $mode = $this->MODES[$download_modes]['code'];
        }

        return( [$mode,$s] );
    }

    /**Array of valid Download Modes
     *
     * This array contains information for the various download modes, indexed by their respective internal access code
     * The internal access code is one letter long and is use in $download_modes for ResourcesDownload().
     * Each letter in that string is parsed to determine the modes which are applicable for the current folder.
     *
     * The 'code' entry in the array defines the code used when changing mode via HTTP.
     * The 'title' entry in the array defines the button label displayed over HTTP
     */
    private $MODES = array('s' => array("code" => "replace"   , "title" => "Substitute Tags"    ),
                   'n' => array("code" => "no_replace", "title" => "Original Version" ),
                   'b' => array("code" => "blank"     , "title" => "Blank Mode"           )

    );



}
