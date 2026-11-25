<?php
$u=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$root=$_SERVER['DOCUMENT_ROOT'];
if (preg_match('#^/_blob/([a-z0-9]{24}\.(jpg|png|webp|gif|pdf))$#',$u,$m)) { $_GET['a']='file'; $_GET['id']=$m[1]; require $root.'/api.php'; return true; }
if (preg_match('#(^|/)\.|^/(config\.php|lib\.php|schema\.sql|app\.html|.*\.usado|.*\.md|.*\.bak|.*\.log|.*\.zip|uploads/.*)$#',$u)) { http_response_code(403); echo 'proibido'; return true; }
if ($u==='/'||$u==='') { require $root.'/index.php'; return true; }
return false;
