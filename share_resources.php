<?php

// organize the upload, review, download, and move/rename functionality for resources into these classes
include_once( "FilingCabinet/FilingCabinet.php" );
include_once( "FilingCabinet/FilingCabinetDownload.php" );
include_once( "FilingCabinet/FilingCabinetUpload.php" );
include_once( "FilingCabinet/FilingCabinetReview.php" );
include_once( "FilingCabinet/FilingCabinetTools.php" );

function checkFileSystem(SEEDAppConsole $oApp){
    $FileSystemVersion = 2;
    $oBucket = new SEEDMetaTable_StringBucket( $oApp->kfdb );
    $currFileSystemVersion = intval($oBucket->GetStr( 'cats', 'FileSystemVersion') );
    if( $currFileSystemVersion != $FileSystemVersion ) {
        $oBucket->PutStr( 'cats', 'FileSystemVersion', $FileSystemVersion );
    }
    if ($currFileSystemVersion < 2) {
        FilingCabinet::EnsureDirectory('old');
        foreach(array('handouts/','forms/','marketing/','clinic/') as $folder){
            $directory_iterator = new DirectoryIterator(CATSDIR_RESOURCES.$folder);
            foreach ($directory_iterator as $fileinfo){
                if($fileinfo->isDot()){
                    continue;
                }
                rename(CATSDIR_RESOURCES.$folder."/".$fileinfo->getFilename(), CATSDIR_RESOURCES.FilingCabinet::GetDirInfo('old')['directory'].$fileinfo->getFilename());
                //TODO Use ResourceRecord instead.
                // This is legacy update code for updating a legacy filesystem.
                // All (or most) of the file systems should be updated now.
                $oApp->kfdb->Execute("UPDATE resources_files SET folder = '".addslashes(rtrim(FilingCabinet::GetDirInfo('old')['directory'],"/"))."' WHERE folder='".addslashes(rtrim($folder,"/\\"))."' AND filename='".addslashes($fileinfo->getFilename())."'");
            }
            rmdir(realpath(CATSDIR_RESOURCES.$folder));
        }
    }
}

function return_bytes($val) {
    $val = trim($val);

    $last = strtolower(substr($val, -1));
    $val = substr($val, 0,-1);
    switch($last)
    {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

function max_file_upload_in_bytes() {
    //select maximum upload size
    $max_upload = return_bytes(ini_get('upload_max_filesize'));
    //select post limit
    $max_post = return_bytes(ini_get('post_max_size'));
    //select memory limit
    $memory_limit = return_bytes(ini_get('memory_limit'));
    // return the smallest of them, this defines the real limit
    return min($max_upload, $max_post, $memory_limit);
}
