<?php
function isJson($string){
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}
function weeknum($date_string){
    if(!$date_string || $date_string === "0000-00-00"){
        return false;
    }
    $date = new DateTime();
    $date_string = explode("-", $date_string);
    $date->setDate(intval($date_string[0]), intval($date_string[1]), intval($date_string[2]));
    $tempDate = new DateTime();
    $tempDate->setDate(intval($date_string[0]), intval($date_string[1]), intval($date_string[2]));
    $week = $date->format("W");
    $month = $date->format("m");
    $tempDate->modify('+7 day');
    $tempWeek = $tempDate->format("W");
    $year = $date->format("Y");
    if(intval($week) > intval($tempWeek) && $month === "01"){
        $year = strval(intval($year) - 1);
    }
    return "w".$year."-".$week;
}

function compressImage($max_width, $max_height, $original_image_path, $thumbnail_image_path){
    ini_set('memory_limit', '512M');
    // Get the MIME type of the original image
    $mime_type = mime_content_type($original_image_path);
    // Create a new image resource based on the original image
    switch($mime_type){
        case 'image/jpeg':
            $original_image = imagecreatefromjpeg($original_image_path);
            // Read the EXIF data from the JPEG file
            $exif = exif_read_data($original_image_path);
            // Determine the orientation of the image
            $orientation = isset($exif['Orientation']) ? $exif['Orientation'] : null;
            // Rotate the image if necessary
            if ($orientation === 3) {
                $original_image = imagerotate($original_image, 180, 0);
            } elseif ($orientation === 6) {
                $original_image = imagerotate($original_image, -90, 0);
            } elseif ($orientation === 8) {
                $original_image = imagerotate($original_image, 90, 0);
            }
            break;
        case 'image/png':
            $original_image = imagecreatefrompng($original_image_path);
            break;
        case 'image/gif':
            $original_image = imagecreatefromgif($original_image_path);
            break;
        default:
            return 'Unsupported image type: '.$mime_type;
    }
    // Get the width and height of the original image
    $original_width = imagesx($original_image);
    $original_height = imagesy($original_image);
    // Calculate the aspect ratio of the original image
    $aspect_ratio = $original_width / $original_height;
    // Calculate the width and height of the thumbnail based on the aspect ratio and the maximum dimensions
    if ($max_width / $max_height > $aspect_ratio) {
        $thumbnail_width = $max_height * $aspect_ratio;
        $thumbnail_height = $max_height;
    } else {
        $thumbnail_width = $max_width;
        $thumbnail_height = $max_width / $aspect_ratio;
    }
    // Create a new image resource for the thumbnail
    $thumbnail_image = imagecreatetruecolor($thumbnail_width, $thumbnail_height);
    switch($mime_type){
        case 'image/gif':
        case 'image/png':
            // integer representation of the color black (rgb: 0,0,0)
            $background = imagecolorallocate($thumbnail_image , 0, 0, 0);
            // removing the black from the placeholder
            imagecolortransparent($thumbnail_image, $background);
    
            // turning off alpha blending (to ensure alpha channel information
            // is preserved, rather than removed (blending with the rest of the
            // image in the form of black))
            imagealphablending($thumbnail_image, false);
    
            // turning on alpha channel information saving (to ensure the full range
            // of transparency is preserved)
            imagesavealpha($thumbnail_image, true);
            break;
        default:
            break;
    }
    
    // Copy and resize the original image to the thumbnail image
    imagecopyresampled($thumbnail_image, $original_image, 0, 0, 0, 0, $thumbnail_width, $thumbnail_height, $original_width, $original_height);
    // Save the thumbnail image to a file
    switch($mime_type){
        case 'image/jpeg':
            imagejpeg($thumbnail_image, $thumbnail_image_path);
            break;
        case 'image/png':
            imagepng($thumbnail_image, $thumbnail_image_path);
            break;
        case 'image/gif':
            imagegif($thumbnail_image, $thumbnail_image_path);
            break;
        default:
            break;
    }
}

function post($key, $default = null, $type = 'string'){
    if(!array_key_exists($key, $_POST)) return $default;
    $value = $_POST[$key];
    // Trim text values
    if(is_string($value)){
        $value = trim($value);
    }
    // --- Type handling ---
    switch($type){
        case 'int':
            return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;
        case 'float':
            return filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? $default;
        case 'bool':
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
        case 'string':
            return (string)$value;
        case 'json':  // auto-decode JSON bodies
            $decoded = json_decode($value, true);
            return $decoded === null ? $default : $decoded;
        default:
            return $value;
    }
}