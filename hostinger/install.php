<?php
declare(strict_types=1);
if (PHP_VERSION_ID<80100) { header('Content-Type: text/plain; charset=utf-8'); exit('Este servidor está com PHP '.PHP_VERSION.'. A Central precisa de PHP 8.2 ou mais novo: hPanel → Avançado → Configuração do PHP → escolha 8.2 → Salvar. Depois recarregue esta página.'); }
if (!extension_loaded('pdo_mysql')) { header('Content-Type: text/plain; charset=utf-8'); exit('Falta a extensão pdo_mysql do PHP. hPanel → Avançado → Configuração do PHP → Extensões PHP → marque pdo_mysql → Salvar.'); }
require __DIR__.'/lib.php';
cabecalhos();
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; form-action 'self'; frame-ancestors 'none'");
$feito=is_file(__DIR__.'/config.php');
function erro_banco(PDOException $e,array $c): string {
  $n=(int)($e->errorInfo[1]??0); if (!$n&&preg_match('/\[(\d{4})\]/',$e->getMessage(),$m)) $n=(int)$m[1];
  $pre=preg_match('/^u\d+_/',$c['name'])&&preg_match('/^u\d+_/',$c['user'])?'':' Na Hostinger, o nome do banco e o do usuário começam com um prefixo, tipo u123456789_ — copie os nomes completos em hPanel → Bancos de dados → Gerenciamento.';
  return match(true) {
    $n===1045=>'O banco recusou o usuário ou a senha.'.$pre.' Se não lembrar a senha do banco, troque ela no hPanel e tente de novo.',
    $n===1049=>'Não existe banco com o nome "'.$c['name'].'".'.$pre,
    $n===1044=>'Esse usuário não tem acesso a esse banco. No hPanel, confira se o usuário foi ligado a esse banco.'.$pre,
    in_array($n,[2002,2005,2006],true)=>'Não achei o servidor do banco "'.$c['host'].'". Na Hostinger, use localhost.',
    default=>'O banco respondeu com erro (código '.$n.'). Confira nome do banco, usuário e senha em hPanel → Bancos de dados.'.$pre,
  };
}
$msg=''; $ok=false;
if (!$feito&&$_SERVER['REQUEST_METHOD']==='POST') {
  $c=['host'=>trim($_POST['host']??'localhost'),'name'=>trim($_POST['db']??''),'user'=>trim($_POST['dbuser']??''),'pass'=>(string)($_POST['dbpass']??'')];
  $un=strtolower(trim($_POST['u']??'')); $nm=trim($_POST['nome']??''); $pw=(string)($_POST['p']??'');
  try {
    if (!preg_match('/^[a-z0-9._-]{3,30}$/',$un)) throw new Exception('Usuário: 3 a 30 letras minúsculas, números, ponto ou traço.');
    if ($nm==='') throw new Exception('Informe seu nome.');
    if ($m=senha_ok($pw)) throw new Exception($m);
    if ($pw!==($_POST['p2']??'')) throw new Exception('As senhas não são iguais.');
    $pdo=new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    foreach (array_filter(array_map('trim',explode(';',file_get_contents(__DIR__.'/schema.sql')))) as $sql) $pdo->exec($sql);
    if ($pdo->query('SELECT 1 FROM users LIMIT 1')->fetch()) throw new Exception('Esse banco já tem um sistema instalado.');
    if (!is_writable(__DIR__)) throw new Exception('A pasta do site não aceita gravação, então não consigo criar o config.php. No Gerenciador de arquivos, deixe a pasta com permissão 755.');
    $conf="<?php\nreturn ".var_export($c,true).";\n";
    if (file_put_contents(__DIR__.'/config.php',$conf,LOCK_EX)===false) throw new Exception('Não consegui criar o config.php (permissão de pasta).');
    @chmod(__DIR__.'/config.php',0600);
    $id=rid();
    $pdo->prepare('INSERT INTO users (id,username,name,pass_hash,is_owner,active,created_at) VALUES (?,?,?,?,1,1,UTC_TIMESTAMP())')->execute([$id,$un,$nm,hashpw($pw)]);
    $pdo->exec('INSERT INTO seqgen () VALUES ()'); $seq=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO docs (path,col,data,version,seq,deleted,updated_at,updated_by) VALUES (?,?,?,1,?,0,UTC_TIMESTAMP(),?)')->execute(["membros/$id",'membros',json_encode(['roles'=>['admin'],'cargo'=>'Fundadora','ativo'=>true,'extra'=>[],'bloq'=>[],'criadoEm'=>agora(),'criadoPor'=>$id]),$seq,$id]);
    if (!is_dir(__DIR__.'/uploads')) @mkdir(__DIR__.'/uploads',0750,true);
    $ok=true;
  } catch (Throwable $e) {
    $msg=$e instanceof PDOException?erro_banco($e,$c):$e->getMessage();
    if (is_file(__DIR__.'/config.php')&&!$ok) @unlink(__DIR__.'/config.php');
  }
}
$e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES);
?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar a Central</title>
<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;background:linear-gradient(#bfe1ff,#e9f5ff);color:#1b1033}.w{max-width:520px;margin:6vh auto;padding:0 16px}.b{background:#fff;border:2px solid #1b1033;border-radius:16px;box-shadow:6px 6px 0 #ff4fb3;overflow:hidden}.t{background:linear-gradient(#6aa3ff,#2f6bff);color:#fff;padding:10px 16px;font-weight:800;border-bottom:2px solid #1b1033}form,.c{padding:16px}label{display:block;margin-bottom:12px;font-weight:700;font-size:14px}input{width:100%;box-sizing:border-box;min-height:44px;padding:8px 12px;border:2px solid #1b1033;border-radius:10px;font-size:16px;margin-top:4px}button,a.bt{display:inline-block;width:100%;min-height:46px;border:2px solid #1b1033;border-radius:12px;background:#ff4fb3;color:#fff;font-weight:800;font-size:16px;box-shadow:3px 3px 0 #1b1033;cursor:pointer;text-align:center;line-height:42px;text-decoration:none}h2{font-size:16px;margin:16px 0 8px}.e{background:#ffe0e5;color:#a1112c;border:2px solid #a1112c;border-radius:10px;padding:8px 12px;margin-bottom:12px;font-weight:700}small{color:#5d5080;font-weight:400}</style></head><body><div class="w"><div class="b">
<?php if ($ok): ?><div class="t">Instalado!</div><div class="c"><p>Pronto, o banco foi criado e sua conta de administradora também.</p><p>Por segurança, o instalador se desativou sozinho.</p><a class="bt" href="./">Abrir a Central</a></div>
<?php elseif ($feito): ?><div class="t">Já instalado</div><div class="c"><p>O sistema já está instalado. Por segurança, este instalador não roda de novo.</p><a class="bt" href="./">Abrir a Central</a></div>
<?php else: ?><div class="t">Instalar a Central da Fábrica</div><form method="post" autocomplete="off">
<?php if ($msg): ?><div class="e"><?=$e($msg)?></div><?php endif ?>
<h2>1. Banco de dados <small>(crie no hPanel → Bancos de dados MySQL)</small></h2>
<label>Servidor<input name="host" value="<?=$e($_POST['host']??'localhost')?>"></label>
<label>Nome do banco<input name="db" required value="<?=$e($_POST['db']??'')?>" placeholder="u123456789_central"></label>
<label>Usuário do banco<input name="dbuser" required value="<?=$e($_POST['dbuser']??'')?>" placeholder="u123456789_duda"></label>
<label>Senha do banco<input name="dbpass" type="password" required></label>
<h2>2. Sua conta de administradora</h2>
<label>Seu nome<input name="nome" required value="<?=$e($_POST['nome']??'')?>"></label>
<label>Usuário <small>(para entrar no sistema)</small><input name="u" required autocapitalize="none" value="<?=$e($_POST['u']??'')?>"></label>
<label>Senha <small>(10+ caracteres, letras e números)</small><input name="p" type="password" required autocomplete="new-password"></label>
<label>Repita a senha<input name="p2" type="password" required autocomplete="new-password"></label>
<button>Instalar</button></form><?php endif ?></div></div></body></html>
