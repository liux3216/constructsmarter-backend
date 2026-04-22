<?php
require_once "/opt/bitnami/apache/conf/constants.php"; // $awsCredentials
require "/opt/bitnami/apache/htdocs/components/vendor/autoload.php";
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$s3Client = new S3Client([
    "region" => "us-west-1",
    "version" => "latest",
    "credentials" => $awsCredentials,
]);

function headFile($bucketName, $fileKey){
    global $s3Client;
    try{
        $result = $s3Client->headObject([
            "Bucket" => $bucketName,
            "Key"    => $fileKey,
        ]);
        $outputText = "";
        $outputText .= "AcceptRanges: " . $result["AcceptRanges"] . "\n";
        $outputText .= "ETag: " . $result["ETag"] . "\n";
        $outputText .= "LastModified: " . $result["LastModified"] . "\n";
        $outputText .= "ContentLength: " . $result["ContentLength"] ."\n";
        $outputText .= "ContentType: " . $result["ContentType"] . "\n";
        if(isset($result["Metadata"])){
            $outputText .=  "Custom Metadata:\n";
            foreach($result["Metadata"] as $key => $value){
                $outputText .= $key . ": " . $value . "\n";
            }
        }
        return true;
    }catch(AwsException $e){
        error_log("Error: " . $e->getMessage());
        return false;
    }
}

function editMetaData($bucketName, $fileKey, $newMetadata){
    global $s3Client;
    // $newMetadata = [
    //     "Xcustom1" => "new-value1",
    //     "Tcustom2" => "new-value2"
    // ]; 
    // automatically add "x-amz-meta-" as prefix to the key: when you check s3 console, you can see.
    // lowercase the key, meaning need access with all lowercase
    try{
        $result = $s3Client->headObject([
            "Bucket" => $bucketName,
            "Key"    => $fileKey,
        ]);
    
        $contentType = $result["ContentType"];
        $s3Client->copyObject([
            "Bucket"     => $bucketName, 
            "CopySource" => $bucketName . "/" . $fileKey, 
            "Key"        => $fileKey, 
            "Metadata"   => $newMetadata, 
            "MetadataDirective" => "REPLACE", 
            "ContentType" => $contentType
        ]);
        return true;
    }catch(AwsException $e){
        error_log("Error: " . $e->getMessage());
        return false;
    }
}

function uploadFile($bucketName, $fileKey, $filePath){
    global $s3Client;
    try{
        $result = $s3Client->putObject([
            "Bucket" => $bucketName, 
            "Key" => $fileKey, 
            "SourceFile" => $filePath, 
            // "Body" => String | Blob
            "Metadata" => [
                // "author" => "John Doe",
                // "description" => "Image of a sunset"
            ], 
            "Tagging" => http_build_query([
                // "author" => "John Doe",
                // "description" => "Image of a sunset"
            ]),
            // "ACL" => "public-read",
            // "ContentType" => "image/jpeg",
            // "StorageClass"=> "STANDARD",
            // "ServerSideEncryption" => "AES256",
            // "CacheControl" => "max-age=3600",
            // "ContentDisposition" => "inline; filename="myfile.jpg"",
            // "ContentEncoding" => "gzip"
        ]);
        return true;
    }catch(AwsException $e){
        error_log("Error uploading file: " . $e->getMessage());
        return false;
    }
}

function uploadFileWithBody($bucketName, $fileKey, $body, $contentType){
    global $s3Client;
     try {
        $result = $s3Client->putObject([
            'Bucket'      => $bucketName,
            'Key'         => $fileKey,
            'Body'        => $body,
            'ContentType' => $contentType
        ]);
        return true;
    } catch (AwsException $e) {
        error_log("S3 upload failed: " . $e->getMessage());
        return false;
    }
}

function uploadFolder($bucketName, $folderName){
    global $s3Client;
    try{
        $result = $s3Client->putObject([
            "Bucket" => $bucketName, 
            "Key" => $folderName, // Ensure it ends with "/"
            "Body" => ""
        ]);
        return true;
    }catch(AwsException $e){
        error_log("Error uploading file: " . $e->getMessage());
        return false;
    }
}

function deleteFile($bucketName, $fileKey){
    global $s3Client;
    try {
        $result = $s3Client->deleteObject([
            "Bucket" => $bucketName,
            "Key"    => $fileKey,
        ]);
        return true;
    } catch (AwsException $e) {
        error_log("Error deleting file: " . $e->getMessage());
        return false;
    }
}

function copyFile($bucketName, $oldFileKey, $newFileKey){
    global $s3Client;
    try {
        $s3Client->copyObject([
            "Bucket"     => $bucketName,
            "CopySource" => $bucketName . "/" . $oldFileKey,
            "Key"        => $newFileKey,
        ]);
        return true;
    } catch (AwsException $e) {
        error_log("Error copy file: " . $e->getMessage());
        return false;
    }
}

function renameFile($bucketName, $oldFileKey, $newFileKey){
    global $s3Client;
    try {
        $s3Client->copyObject([
            "Bucket"     => $bucketName,
            "CopySource" => $bucketName . "/" . $oldFileKey,
            "Key"        => $newFileKey,
        ]);
        $s3Client->deleteObject([
            "Bucket" => $bucketName,
            "Key"    => $oldFileKey,
        ]);
        return true;
    } catch (AwsException $e) {
        error_log("Error rename file: " . $e->getMessage());
        return false;
    }
}

function getObjectUrl($bucketName, $fileKey, $fileName = null, $expiration = "+10 minutes"){
    global $s3Client;
    if ($fileName === null) {
        $fileName = $fileKey;
    }
    try {
        $cmd = $s3Client->getCommand("getObject", [
            "Bucket" => $bucketName,
            "Key" => $fileKey,
            "ResponseContentDisposition" => "attachment; filename=\"" . $fileName . "\"",
        ]);
        $request = $s3Client->createPresignedRequest($cmd, $expiration);
        return (string)$request->getUri();
    } catch (AwsException $e) {
        echo "Error generating pre-signed URL: " . $e->getMessage();
        return null;
    }
}
/**
 * @param array{
 *   key: string,          // S3 object key (path + filename)
 *   mime?: string,        // Optional MIME type
 *   acl?: string,         // Optional ACL, default "private"
 *   expires?: string      // Relative time format, e.g. "+10 minutes"
 * } $config Configuration options for generating the URL.
 *
 * @return string A temporary presigned URL for direct upload.
 */
function putObjectUrl(array $config = []){
    $bucket = $config["bucket"];
    $key = $config["key"];
    $mimeType = $config["mime"] ?? "application/octet-stream";
    $acl = $config["acl"] ?? "private";
    $expiration = $config["exp"] ?? "+10 minutes";
    global $s3Client;
    try {
        $cmd = $s3Client->getCommand("putObject", [
            "Bucket" => $bucket,
            "Key" => $key,
            "ContentType" => $mimeType
        ]);
        $request = $s3Client->createPresignedRequest($cmd, $expiration);
        return (string)$request->getUri();
    } catch (AwsException $e) {
        echo "Error generating pre-signed URL: " . $e->getMessage();
        return null;
    }
}
