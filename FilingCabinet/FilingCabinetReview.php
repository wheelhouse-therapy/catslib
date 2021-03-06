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
    private $dirPending = CATSDIR_RESOURCES."pending/";

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oFC = new FilingCabinet( $oApp );
    }

    function DrawReviewUI()
    {
        $s = "";

        FilingCabinet::EnsureDirectory("*");

        /* Handle commands
         */
        switch( SEEDInput_Str('cmd') ) {
            case 'accept':    $s .= $this->cmdAccept();   break;
            case 'reject':    $s .= $this->cmdReject();   break;
            case 'download':  $s .= $this->cmdDownload(); break;
        }

        /* Show pending files
         */
        $dir = new DirectoryIterator($this->dirPending);
        if(iterator_count($dir) == 2) {
            $s .= "<h2> No files awaiting review</h2>";
            goto footer;
        }
        foreach ($dir as $fileinfo) {
            if( $fileinfo->isDot() ) continue;

            /* If a file was uploaded it will have a ResourceRecord.
             * This also allows a file to be dropped (e.g. by ftp) into the pending directory and ResourceRecord created on demand here.
             * Use general cabinet for files in pending folder
             */
            if( !($oRR = ResourceRecord::GetRecordFromRealPath($this->oApp, 'general', $fileinfo->getRealPath())) ) {
                $oRR = ResourceRecord::CreateFromRealPath($this->oApp, $fileinfo->getRealPath(),'general', 0);
                $oRR->StoreRecord();
            }

// Since this is a UI for moving files from pending to somewhere else, this should be able to use the same code as Manage Resources

            $s .= "<div style='position: relative'><a href='?cmd=download&rrID={$oRR->getID()}' target='_blank'>".$fileinfo->getFilename()."</a>
                   <form id='form".$oRR->getID()."' style='display:inline' onsubmit='disable({$oRR->getID()},event)'>
                   <input type='hidden' name='cmd' value='accept' />
                   <input type='hidden' name='rrID' value='{$oRR->getID()}' />";
            $excluded = [];
            $options = "<option selected value=''>Select a directory</option>";
            foreach(FilingCabinet::GetCabinets() as $cabinet){
                if(!in_array($fileinfo->getExtension(), FilingCabinet::GetSupportedExtensions($cabinet))){
                    // Skip cabinets that don't support the file
                    continue;
                }
                $options .= "<optgroup label='".ucfirst($cabinet)."'>";
                foreach(FilingCabinet::GetFilingCabinetDirectories($cabinet) as $dir=>$dirInfo){
                    if($dir=="papers"){
                        //TODO Remove once papers files are accessable
                        continue;
                    }
                    if(!in_array($fileinfo->getExtension(), $dirInfo['extensions'])){
                        // Skip folders that don't support the file
                        continue;
                    }
                    if(ResourceRecord::GetRecordFromPath($this->oApp, $cabinet, $dir, $fileinfo->getFilename())){
                        array_push($excluded,"$cabinet/$dir");
                    }
                    $options .= "<option value='$cabinet/$dir'>{$dirInfo['name']}</option>";
                    foreach(FilingCabinet::GetSubFolders($dir,$cabinet) as $subdir){
                        if(ResourceRecord::GetRecordFromPath($this->oApp, $cabinet, $dir, $fileinfo->getFilename(),$subdir)){
                            array_push($excluded,"$cabinet/$dir/$subdir");
                        }
                        $options .= "<option value='$cabinet/$dir/$subdir'>{$dirInfo['name']}/$subdir</option>";
                    }
                }
                $options .= "</optgroup>";
            }
            $s .= "<select name='dir' onchange='".$this->js($excluded)."' required>".$options."</select>
                   </form>
                   <div style='display: inline-flex; position: absolute; top: 0; margin-left: 10px; align-items: center'>
                        <button type='submit' form='form".$oRR->getID()."' data-tooltip='Accept Resource' value=''
                           style='background: url(".CATSDIR_IMG."accept-resource.png) 0px/24px no-repeat; width: 24px; height: 24px;border: none; position: relative;'
                           class='resource{$oRR->getID()}'></button>
                        <a href='?cmd=reject&rrID=".$oRR->getID()."' data-tooltip='Reject Resource'
                            class='resource{$oRR->getID()}' onclick='disable({$oRR->getID()},event)'>
                            <img src='".CATSDIR_IMG."reject-resource.png'
                                style='max-width:24px; position: relative; vertical-align: top; margin-left: 5px'/>
                        </a>
                   </div><br />
                   Uploaded By: {$oRR->getUploader(true)['realname']}</div>";
        }

$s .= "
<script>
function replace(event, ra) {
    var index = event.target.selectedIndex;
    var options = event.target.options;
    var submit = event.target.parentElement.nextElementSibling.firstElementChild;
    if($.inArray(options[index].value,ra) !== -1){
        $(submit).css('background-image','url(".CATSDIR_IMG."overwrite-resource.png)');
        submit.firstElementChild.innerHTML = 'Overwrite Resource';
    }else{
        $(submit).css('background-image','url(".CATSDIR_IMG."accept-resource.png)');
        submit.firstElementChild.innerHTML = 'Accept Resource';
    }
}
function disable(rrid,event){
    let className = 'resource'+rrid;
    if($('a.'+className)[0] != event.currentTarget){
        $('a.'+className)[0].href = '';
    }
    $('button.'+className)[0].disabled = true;
}
</script>
";

        footer:
        $s .= "<a href='".CATSDIR."admin-manageresources'><- Go To manage resources</a>";

        return( $s );
    }

    private function cmdAccept()
    {
// Since this is just a move from pending to somewhere else, this should be able to use the same code as Manage Resources

        $s = "";

        if( !($rrID = SEEDInput_Str( 'rrID' )) ||
            !($oRR = ResourceRecord::GetRecordByID($this->oApp, $rrID)) )  goto done;

        // (dir) contains cabinet/dir/subdir
        $ra = explode("/",SEEDInput_Str('dir'));
        $cabinet = $ra[0];
        $dir = $ra[1];
        $subdir = @$ra[2]? "{$ra[2]}/" : "";

        if( !FilingCabinet::GetDirInfo($dir, $cabinet) )  goto done;

        $file = $oRR->getFile();
        if( !file_exists($this->dirPending.$file) )  goto done;

        if( $cabinet == 'videos' ) {
            // move the file to 'videos/' directory with the _key as prefix
            $oldFname = $oRR->getPath();
            $newFname = CATSDIR_RESOURCES."videos/{$oRR->getId()} {$oRR->getFile()}";

            if( rename($oldFname, $newFname) ) {
                $oRR->setCabinet( $cabinet );
                $oRR->setDirectory($dir);
                $oRR->setSubDirectory($subdir);
                if($oRR->StoreRecord()) {
                    $s .= "<div class='alert alert-success'> File ".$file." has been accepted as a resource</div>";
                } else {
                    $s .= "<div class='alert alert-danger'>Unable to update the index. Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
                }
            } else {
                $s .= "<div class='alert alert-danger'>An error occurred while accepting File ".$file."</div>";
            }

        } else {
            if( rename($this->dirPending.$file, CATSDIR_RESOURCES.FilingCabinet::GetDirInfo($dir)['directory'].$subdir.$file)){
                if(!ResourceRecord::GetRecordFromPath($this->oApp, $cabinet, $dir, $file, $subdir)){
                    $s .= "<div class='alert alert-success'> File ".$file." has been accepted as a resource</div>";
                    $oRR->setCabinet( $cabinet );
                    $oRR->setDirectory($dir);
                    $oRR->setSubDirectory($subdir);
                    if(!$oRR->StoreRecord()){
                        $s .= "<div class='alert alert-danger'>Unable to update the index. Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
                    }
                } else {
                    $oRRExisting = ResourceRecord::GetRecordFromPath($this->oApp, $cabinet, $dir, $file, $subdir);
                    if(is_array($oRRExisting)){
                        $oRRExisting = $oRRExisting[0]; // Take the first record
                    }
                    $s .= "<div class='alert alert-success'> Resource ".$file." has been overwritten. This CANNOT be undone</div>";
                    if(!$oRRExisting->merge($oRR)){
                        $s .= "<div class='alert alert-danger'>Unable to update the index. Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
                    }
                    $oRR = $oRRExisting; // The new record should be deleted reassign with the old record for thumbnail creating
                }
                $oRR->CreateThumbnail();
            } else {
                $s .= "<div class='alert alert-danger'>An error occurred while accepting File ".$file."</div>";
            }
        }

        done:
        return( $s );
    }

    private function cmdReject()
    {
        $s = "";

        if( !($rrID = SEEDInput_Str( 'rrID' )) ||
            !($oRR = ResourceRecord::GetRecordByID($this->oApp, $rrID)) )  goto done;

        $file = $oRR->getFile();
        if(unlink($this->dirPending.$file)){
            $s .= "<div class='alert alert-danger'> Pending file ".$file." has been removed.</div>";
            if(!$oRR->DeleteRecord() && $oRR->getStatus() != 1){
                    $s .= "<div class='alert alert-danger'>Unable to update the index. Contact a System Administrator Immediately (Code 504-{$oRR->getID()})</div>";
            }
        } else {
            $s .= "<div class='alert alert-danger'>An error occured while rejecting File ".$file."</div>";
        }

        done:
        return( $s );
    }

    private function cmdDownload()
    {
        ob_implicit_flush();
        $oFCD = new FilingCabinetDownload( $this->oApp );
        $oFCD->OutputResource( SEEDInput_Int('rrID'), false );
        // OutputResource only returns if it can't serve the file
    }

    private function js($replace)
    {
        $s = "replace(event, " . json_encode($replace) . ");";
        return $s;
    }
}
