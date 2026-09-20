<?php
require __DIR__ . '/_bootstrap.php';
requireAuth();
$file=basename((string)($_GET['file']??''));
if($file==='' || !preg_match('/^[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp)$/i',$file)){http_response_code(400);exit;}
$path=dirname(__DIR__).'/private_uploads/ines/'.$file;
if(!is_file($path)){http_response_code(404);exit;}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);header('Cache-Control: private, max-age=3600');readfile($path);exit;
