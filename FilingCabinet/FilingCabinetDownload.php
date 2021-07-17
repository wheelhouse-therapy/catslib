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
        $rrid = SEEDInput_Int('rr');

        if( SEEDInput_Str('cmd') == 'view' ){
            $this->OutputResource( $rrid, false );
            // OutputResource() only returns if it can't serve the file
            return;

        } else { // if( SEEDInput_Str('cmd') == 'download' ) {
            if( !($oRR = ResourceRecord::GetRecordByID($this->oApp,$rrid)) ||
                !($file = $oRR->getPath()) )
            {
                $this->oApp->oC->AddErrMsg( "Could not retrieve file $rrid" );
                return;
            }

            switch( SEEDInput_Str('resource-mode') ) {
                case 'email':
                    $this->sendByEmail( $oRR );
                    exit;
                case 'no_replace':
                    $oRR->countDownload();
                    (new FileDownloadsList($this->oApp, $this->oApp->sess->GetUID()))->countDownload($oRR->getID());
                    $this->OutputResource( $rrid, true );
                    // OutputResource() only returns if it can't serve the file
                    return;
                case 'blank':
                    $bBlank = true;
                    // fall through
                default:
                    $oRR->countDownload();
                    (new FileDownloadsList($this->oApp, $this->oApp->sess->GetUID()))->countDownload($oRR->getID());
                    // mode blank is implemented by telling template_filler that client=0
                    $kClient = @$bBlank ? 0 : SEEDInput_Int('client');
                    $filler = new template_filler($this->oApp, @$_REQUEST['assessments']?:[]);
                    $filler->fill_resource($file, ['client'=>$kClient]);
                    exit;   // actually fill_resource exits, but it's nice to have a reminder of that here
            }
        }
    }

    function OutputResource( int $rrid, $bDownload )
    /***********************************************
        Serve the content of the given resource file to the browser.

         $bDownload = tell the browser to save the file (browser dependent anyway)
        !$bDownload = tell the browser to show the file (browser dependent anyway)
     */
    {
        if( !($rrid) ||
            !($oRR = ResourceRecord::GetRecordByID($this->oApp,$rrid)) ||
            !($file = $oRR->getPath()) ||
            !(file_exists($file)) )
        {
            $this->oApp->oC->AddErrMsg( "Could not retrieve file $rrid" );
            return;
        }

        header('Content-Type: '.(@mime_content_type($file)?:"application/octet-stream"));
        header('Content-Length: '.filesize($file));
        header('Content-Disposition: '.($bDownload ? 'attachment' : 'inline').'; filename="'.$oRR->getFile().'"');  // the user-friendly name, not with _key prefix
        header('Content-Transfer-Encoding: binary');
        if( $bDownload ) {
            header('Content-Description: File Transfer');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        if( ($fp = fopen( $file, "rb" )) ) {
            fpassthru( $fp );
            fclose( $fp );
        }
        exit;
    }

    private function sendByEmail( ResourceRecord $oRR )
    {
        $oPeopleDB = new PeopleDB($this->oApp);
        $manageUsers = new ManageUsers2($this->oApp);

        if( !($kClient = SEEDInput_Int('client')) ||
            !($kfr = $oPeopleDB->GetKFR(ClientList::CLIENT, $kClient)) ||
            !($user = $manageUsers->getClinicProfile($this->oApp->sess->GetUID())['kfr'])
          )  goto done;

        $to = $kfr->Value('P_email');
        $from = $user->Value('P_email');
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

        $file = $oRR->getPath();

        $filler = new template_filler($this->oApp, @$_REQUEST['assessments']?:[]);
        $filename = $filler->fill_resource($file, ['client'=>$kClient],template_filler::RESOURCE_GROUP);
        $code = (new ClientCodeGenerator($this->oApp))->getClientCode($kClient);
        $filecodename = $code.($code?"-":"").basename($file);
        $message .= "--{$mime_boundary}\n";
        if( ($fp = @fopen($filename, "rb")) ) {
            $data = fread($fp, filesize($filename));
            fclose($fp);
            $data = chunk_split(base64_encode($data));
            $message .= "Content-Type: application/octet-stream; name=\"$filecodename\"\n"
                       ."Content-Description: $filecodename\n"
                       ."Content-Disposition: attachment;\n filename=\"$filecodename\"; size=".filesize($filename).";\n"
                       ."Content-Transfer-Encoding: base64\n\n".$data."\n\n";
        }
        $message .= "--{$mime_boundary}--";
        $returnpath = "-f".$from;
        if( CATS_DEBUG ) {
            // dev machines are typically not set up for smtp
            echo "<pre style='background-color:#ccc'>To: $to<br/>Reply-to: $returnpath<br/>Subject: $subject<br/>"
                ."<br/>----- HEADERS -----<br/><br/>".$headers."<br/>"
                ."<br/>----- MESSAGE -----<br/><br/>".substr($message,0,1000)."...<br/></pre>";
            return;
        } else {
            $mail = @mail($to, $subject, $message, $headers,$returnpath);
            $_SESSION['mailResult'] = $mail;
            $_SESSION['mailTarget'] = $to;
            header("HTTP/1.1 303 SEE OTHER");
            header("Location: ?dir=".SEEDInput_Str('dir'));
        }
        done:
        exit();
    }


    function GetDownloadPath( $mode, ResourceRecord $oRR, $filename = "", $dir_short = "" )
    {
        $rrid = $oRR->getID();
        switch( $mode ) {
            case 'replace':
                return "href='javascript:void(0)' onclick=\"select_client($rrid, '".addslashes($oRR->getFile())."')\"";
            case 'no_replace':
                return "href='javascript:void(0)' onclick=\"viewPDF($rrid, '".addslashes($oRR->getFile())."')\"";
            case 'blank':
                return "href='?cmd=download&rr=$rrid&client=0'";
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

class FilingCabinetHandout {

    private $oApp;
    private $oRR;
    private $oPeopleDB;
    private $oClinics;

    public function __construct(SEEDAppConsole $oApp, ResourceRecord $oRR){
        $this->oApp = $oApp;
        $this->oRR = $oRR;
        $this->oPeopleDB = new PeopleDB($oApp);
        $this->oClinics = new Clinics($oApp);
    }

    public function renderHandout(int $client_key,bool $blank = false):string{
        if(!($file = $this->oRR->getPath()) ||
            !(file_exists($file)) )
        {
            $this->oApp->oC->AddErrMsg( "Could not retrieve file $rrid" );
            return "";
        }
        if(!in_array(strtolower(pathinfo($this->oRR->getPath(),PATHINFO_EXTENSION)),["png","jpg","gif"])){
            return "Not a handout";
        }

        if($blank){
            $kfr = $this->oPeopleDB->GetKfrel(ClientList::CLIENT)->CreateRecord();
        }
        else{
            $kfr = $this->oPeopleDB->GetKFR(ClientList::CLIENT, $client_key);
        }

        if(!$kfr){
            $this->oApp->oC->AddErrMsg( "Could not retrieve record for client $client_key" );
            return "";
        }

        require_once CATSLIB.'handle_images.php';
        $header = $this->oClinics->getImage(Clinics::LOGO_WIDE);
        $footer = $this->oClinics->getImage(Clinics::FOOTER);
        $s = "<style>
              /* The whole handout is in a rectangle 8x10.5 inches with a 0.25 margin around it (total size is 8.5x11)
               * Inner divs can be absolute-positioned relative to this rect.
               */
              #pagerect      { position:relative; width:8in; height:10.5in; margin:0.25in }

              #headerImg     { text-align: center }
              #headerImg img { max-width:100% }

              #mainImg       { text-align: center }
              #mainImg img   { max-width:95vw; max-height:7.25in }

              /* the footer is glued to the bottom of the pagerect
               */
              #footerImg     { text-align: center; position:absolute; bottom:0px }
              #footerImg img { max-width:100% }
              </style>";

        $s .= "<div id='headerBox' >";
        if($header !== FALSE){
            switch(strtolower(pathinfo($header,PATHINFO_EXTENSION))){
                case "png":
                    $imageType = IMAGETYPE_PNG;
                    break;
                case "jpg":
                    $imageType = IMAGETYPE_JPEG;
                    break;
                case "gif":
                    $imageType = IMAGETYPE_GIF;
                    break;
                default:
                    $s .= "Could Not Render Header Image";
                    goto main;
            }
            $i = getImageData($header, $imageType);
            $s .= "<div id='headerImg'><img src='data:".image_type_to_mime_type($imageType).";base64," . base64_encode( $i )."'></div>";
        }
        main:
        $name = $kfr->Value("P_first_name")." ".$kfr->Value("P_last_name");
        $date = "";
        if(!$blank){
            $date = date("M d, Y");
        }
        $s .= "<div style='display:flex;justify-content: space-between;'><div>$name</div><div>$date</div></div>";
        $s .= "</div>"; // End header Box
        $img = null;
        switch(strtolower(pathinfo($this->oRR->getPath(),PATHINFO_EXTENSION))){
            case "png":
                $imageType = IMAGETYPE_PNG;
                $img = imagecreatefrompng($this->oRR->getPath());
                break;
            case "jpg":
                $imageType = IMAGETYPE_JPEG;
                $img = imagecreatefromjpeg($this->oRR->getPath());
                break;
            case "gif":
                $imageType = IMAGETYPE_GIF;
                $img = imagecreatefromgif($this->oRR->getPath());
                break;
            default:
                $s .= "Could Not Render Main Image";
                goto footer;
        }
        $im = imagecropauto($img);
        if($im){
           $img = $im;
        }
        $i = getImageData($img, $imageType,true);
        $s .= "<div id='mainImg'><img src='data:".image_type_to_mime_type($imageType).";base64," . base64_encode( $i )."'></div>";
        footer:
        if($footer === FALSE){
            goto done;
        }
        switch(strtolower(pathinfo($footer,PATHINFO_EXTENSION))){
            case "png":
                $imageType = IMAGETYPE_PNG;
                break;
            case "jpg":
                $imageType = IMAGETYPE_JPEG;
                break;
            case "gif":
                $imageType = IMAGETYPE_GIF;
                break;
            default:
                $s .= "Could Not Render Footer Image";
                goto done;
        }
        $i = getImageData($footer, $imageType);
        $s .= "<div id='footerImg'><img src='data:".image_type_to_mime_type($imageType).";base64," . base64_encode( $i )."'></div>";
        done:

        // the whole handout is in a relative-positioned rect that fills a letter-size page and allows inner parts to be absolute positioned
        $s = "<div id='pagerect'>$s</div>";
        return $s;
    }

}
