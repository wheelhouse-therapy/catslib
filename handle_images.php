<?php
function getImageData($imagePath,int $imageType, bool $isResource = FALSE):String{
    if($isResource){
        $im = $imagePath;
    }
    else{
        $im = imagecreatefromstring(file_get_contents($imagePath));
    }
    ob_start();
    switch ($imageType){
        case IMAGETYPE_PNG:
            $background = imagecolorallocate($im, 0, 0, 0);
            // remove the black
            imagecolortransparent($im, $background);
            // turn off alpha blending
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagepng($im);
            break;
        case IMAGETYPE_JPEG:
            $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagealphablending($bg, TRUE);
            imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
            imagejpeg($bg);
            imagedestroy($bg);
            break;
        case IMAGETYPE_GIF:
            imagesavealpha($im, true);
            imagecolortransparent($im, 127<<24);
            imagegif($im);
            break;
    }
    $data = ob_get_clean();
    imagedestroy($im);
    return $data;
}