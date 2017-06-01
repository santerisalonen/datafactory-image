<?php

/****
 
 
 
 
 
****/
require('vendor/autoload.php');

DEFINE('BASE_DIR', __DIR__);

require('ImageHelper.php');

header('Content-Type: application/json; charset=utf8'); 
 
$S3_BUCKET = 'datafactory-image-service';
$S3_REGION = 'eu-west-1';

// dev setup
$localhost = 'localhost:99';
$rm_path = ($_SERVER['HTTP_HOST'] == $localhost) ? 'klikki-datafactory-image' : '';


$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// remove app folder and version id from path
$rm_path = strtolower( $rm_path );
if( $rm_path !== '/' ) {
  $path = str_replace( $rm_path, '/', strtolower($path));
  // remove extra slashes just in case
  $path = '/' . ltrim( rtrim($path, '/'), '/') ;
}
$path = array_values(array_filter(explode('/', $path)));





if( !isset($path[0]) )  {
  http_response_code(404);
  die( '{"error" : "missing client"}' ); 
}
if( !isset($path[1]) ) {
  http_response_code(404);
  die( '{"error" : "missing key"}' ); 
}
if( !isset($_GET['q']) ) {
  http_response_code(404);
  die( '{"error" : "missing q"}' ); 
}


$client = $path[0];
$key = $path[1];
$q = base64_decode($_GET['q']);

if( $key !== md5($q) ) {
  http_response_code(400);
  die( '{"error" : "non matching hash"}' ); 
}

$params = json_decode($q, true);

if( !isset($params['origin'] ) ) {
  http_response_code(400);
  die( '{"error" : "missing origin"}' ); 
}

$tmp_name = BASE_DIR . '/' . uniqid();

$img = @fopen($params['origin'], 'r');
if(!$img) {
  http_response_code(500);
  die( '{"error" : "unable to read origin"}' );   
}

file_put_contents($tmp_name, $img);

if( exif_imagetype($tmp_name) ) {
  
  try {
    $contentType = mime_content_type($tmp_name);
    ImageHelper::validateImage($tmp_name);
    ImageHelper::fitToSize($tmp_name, $params['fitToSize']['x'], $params['fitToSize']['y']);
    if( isset($params['badge']) && is_array($params['badge']) ) {
      ImageHelper::attachBadge($tmp_name, $params['badge']);
    }
    
    // PUT to S3
    $s3 = new Aws\S3\S3Client([
        'version' => 'latest',
        'region'  => $S3_REGION
    ]);

    $result = $s3->putObject(array(
        'Bucket' => $S3_BUCKET,
        'Key'  => $key,
        'ContentType' => $contentType,
        'SourceFile' => $tmp_name,
        'ACL' => 'public-read'
    )); 
    unlink($tmp_name);
    header('Location: ' . $result['ObjectURL'] );
  }
  catch(Exception $e) {
    http_response_code(500);
    die('{"error" : '. json_encode($e->getMessage()) . '}');
  }
}
else {
  http_response_code(500);
  die('{"error" : "unable to read image info"}');
}
