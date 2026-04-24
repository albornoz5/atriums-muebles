<?php
$f = basename($_GET['f'] ?? '');
if (!$f) { http_response_code(404); exit; }
$path = __DIR__ . '/data/img/' . $f;
if (!file_exists($path)) { http_response_code(404); exit; }
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext] ?? 'image/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($path);
