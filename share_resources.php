<?php

// Array of arrays containing directory information of resource folders
// The key of the first array defines the intermal key for the directory
// The directory value of the second array defines the path to the directory
// ALL directories are stored in the resources folder
// The name value of the second array is the name displayed in the select element
// It should be a discriptive name indicating what goes in the folder
// The extensions value of the second array is an array of all files extensions that are excepted in the directory
// DO NOT include the dot in the file extension
global $directories;
$directories= array("papers"    => array("directory" => "papers/",    "name" => "Papers",             "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "handouts"  => array("directory" => "handouts/",  "name" => "Handouts",           "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "reports"   => array("directory" => "reports/",   "name" => "Client Reports",     "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "forms"     => array("directory" => "forms/",     "name" => "Forms",              "extensions" => array("docx", "pdf", "txt", "rtf", "doc") ),
                    "marketing" => array("directory" => "marketing/", "name" => "Marketing Materials","extensions" => array("docx", "pdf", "txt", "rtf", "doc") )
);

function getExtensions(){
    $exts = array();
    foreach($GLOBALS['directories'] as $k => $v){
        foreach ($v['extensions'] as $ext){
            array_push($exts, trim($ext, ". \t\n\r\0\x0B"));
        }
    }
    return $exts;
}

function share_resources(){
    return "<form action=\"?screen=therapist-resources\" method=\"post\" enctype=\"multipart/form-data\">
                    Select resource to upload:
                    <input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\"> Max File size:".ini_get('upload_max_filesize')."b"."
                    <br /><input type=\"submit\" value=\"Upload File\" name=\"submit\">
                    </form>";
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