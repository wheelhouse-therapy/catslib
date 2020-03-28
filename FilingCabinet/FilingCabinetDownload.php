<?php

/* FilingCabinetDownload
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * See FilingCabinet.php for information
 */

class FilingCabinetDownload
{
    private $oApp;
    private $oFC;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oFC = new FilingCabinet( $oApp );
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
