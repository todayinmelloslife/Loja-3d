<?php
declare(strict_types=1);
if (PHP_VERSION_ID<80100) { header('Content-Type: text/plain; charset=utf-8'); exit('A Central precisa de PHP 8.2 ou mais novo (este servidor está com '.PHP_VERSION.'). hPanel → Avançado → Configuração do PHP → 8.2 → Salvar.'); }
require __DIR__.'/lib.php';
cabecalhos();
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'");
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
readfile(__DIR__.'/app.html');
