<?php

/* FilingCabinetReview
 *
 * Copyright (c) 2018-2020 Collaborative Approach Therapy Services
 *
 * See FilingCabinet.php for information
 */

class FilingCabinetReview
{
    private $oApp;
    private $oFC;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oFC = new FilingCabinet( $oApp );
    }

    function CreateThumbnail( ResourceRecord $oRR )
    {
        $ok = false;

        $srcfname = realpath($oRR->getPath());
        $bPdf = strtolower(substr($srcfname,-4)) == '.pdf';
        if( !$bPdf )  return;


        $tmpfname = tempnam("", "thumb_");
        //rename($tmpfname,$tmpfname.".jpg");
        //$tmpfname .= ".jpg";
        //copy("/home/bob/junk/imgtest/sweet-peas.jpeg", $tmpfname );
        $raDummy = [];
        $iRet = 0;
//        $sExec = "convert /home/bob/junk/imgtest/sweet-peas.jpeg -resize 200x200\\> $tmpfname";
        // srcfname[0] means convert the first page
        $sExec = "convert \"{$srcfname}[0]\" -background white -alpha remove -resize 200x200\\> \"JPEG:$tmpfname\"";
        exec( $sExec, $raDummy, $iRet );
        //var_dump($sExec,$iRet);

        if( ($img = file_get_contents( $tmpfname )) ) {
            $oRR->setPreview( $img );
            $ok = $oRR->StoreRecord();
        }
        //rename($tmpfname,"/home/bob/catsstuff/a.jpg");
        unlink($tmpfname);

        return( $ok );
    }
}
