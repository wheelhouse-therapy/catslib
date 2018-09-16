<?php
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