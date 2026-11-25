<?php
declare(strict_types=1);
if (PHP_VERSION_ID<80100) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); exit('{"erro":"O servidor precisa de PHP 8.2 ou mais novo (hPanel → Configuração do PHP)."}'); }
require __DIR__.'/lib.php';
cabecalhos();
header('Cache-Control: no-store');
if (!is_file(__DIR__.'/config.php')) erro('sistema não instalado — abra install.php',503);
if ($_SERVER['REQUEST_METHOD']==='OPTIONS') erro('método não permitido',405);
if ((int)($_SERVER['CONTENT_LENGTH']??0)>16*1024*1024) erro('envio grande demais',413);
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
$a=$_GET['a']??'';
$post=$_SERVER['REQUEST_METHOD']==='POST';
if ($post&&$a!=='upload'&&!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')) erro('formato inválido',415);
$raw=$post&&$a!=='upload'?file_get_contents('php://input',false,null,0,1048577):'';
if (strlen($raw)>1048576) erro('envio grande demais',413);
$in=$raw!==''?(json_decode($raw,true)??[]):[];
if (!is_array($in)) erro('dados inválidos');
sessao();

switch ($a) {
case 'me':
  if (empty($_SESSION['uid'])) out(['logado'=>false,'setup'=>!q('SELECT 1 FROM users LIMIT 1')->fetch()]);
  $u=usuario();
  out(['logado'=>true,'id'=>$u['id'],'name'=>$u['name'],'username'=>$u['username'],'owner'=>$u['owner'],'nivel'=>$u['nivel'],'trocar'=>$u['trocar'],'admin'=>$u['owner']||in_array('admin',$u['roles'],true),'csrf'=>$_SESSION['csrf']]);

case 'login':
  if (!$post) erro('método',405);
  if (!https()&&!in_array(explode(':',$_SERVER['HTTP_HOST']??'')[0],['localhost','127.0.0.1'],true)) erro('Use o endereço com https://',403);
  if (!origem_ok()) { evento('origem_invalida','login'); erro('origem não permitida',403); }
  $user=strtolower(trim((string)($in['u']??''))); $pw=(string)($in['p']??'');
  q('DELETE FROM login_falhas WHERE em<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
  $f=(int)q('SELECT COUNT(*) c FROM login_falhas WHERE (ip=? OR username=?) AND em>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)',[ip(),$user])->fetch()['c'];
  if ($f>=8) { evento('login_bloqueado',$user); erro('Muitas tentativas. Espere 15 minutos.',429); }
  $r=q('SELECT id,pass_hash,active,pendente,sess_ver,must_change,temp_expires FROM users WHERE username=?',[$user])->fetch();
  $ok=$r&&password_verify($pw,$r['pass_hash']);
  if (!$r) password_verify($pw,password_hash('x',PASSWORD_DEFAULT));
  if ($ok&&$r['pendente']) { evento('login_pendente',$user,$r['id']); erro('Seu pedido de acesso ainda não foi aprovado. Avise a administradora.',403); }
  if (!$ok||!$r['active']) { q('INSERT INTO login_falhas (ip,username,em) VALUES (?,?,UTC_TIMESTAMP())',[ip(),$user]); evento('login_falhou',mb_substr($user,0,40),$r['id']??null); usleep(400000); erro('Usuário ou senha incorretos.',401); }
  if ($r['must_change']&&$r['temp_expires']&&strtotime($r['temp_expires'].' UTC')<time()) { evento('senha_provisoria_expirada',$user,$r['id']); erro('Sua senha provisória expirou. Peça uma nova para a administradora.',401); }
  if (password_needs_rehash($r['pass_hash'],HASH)) q('UPDATE users SET pass_hash=? WHERE id=?',[hashpw($pw),$r['id']]);
  session_regenerate_id(true);
  $_SESSION=['uid'=>$r['id'],'csrf'=>bin2hex(random_bytes(32)),'vis'=>time(),'ini'=>time(),'sv'=>(int)$r['sess_ver'],'ep'=>(int)ajuste('epoca','0')];
  evento('login',$user,$r['id']);
  q('UPDATE users SET last_login=UTC_TIMESTAMP() WHERE id=?',[$r['id']]);
  q('DELETE FROM login_falhas WHERE username=?',[$user]);
  out(['ok'=>true]);

case 'signup':
  if (!$post) erro('método',405);
  if (!https()&&!in_array(explode(':',$_SERVER['HTTP_HOST']??'')[0],['localhost','127.0.0.1'],true)) erro('Use o endereço com https://',403);
  if (!origem_ok()) { evento('origem_invalida','signup'); erro('origem não permitida',403); }
  $n=(int)q("SELECT COUNT(*) c FROM eventos_seg WHERE tipo='pedido_acesso' AND ip=? AND em>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)",[ip()])->fetch()['c'];
  if ($n>=3) { evento('pedido_acesso_bloqueado',''); erro('Muitos pedidos deste aparelho. Tente mais tarde.',429); }
  if ((int)q('SELECT COUNT(*) c FROM users WHERE pendente=1')->fetch()['c']>=30) erro('Muitos pedidos aguardando. Fale com a administradora.',429);
  $un=strtolower(trim((string)($in['username']??''))); $nm=trim((string)($in['name']??'')); $pw=(string)($in['password']??'');
  if (!preg_match('/^[a-z0-9._-]{3,30}$/',$un)) erro('Usuário: 3 a 30 letras minúsculas, números, ponto ou traço.');
  if ($nm===''||mb_strlen($nm)>80||preg_match('/[<>]/',$nm)) erro('Informe seu nome.');
  if ($m=senha_ok($pw)) erro($m);
  if (q('SELECT 1 FROM users WHERE username=?',[$un])->fetch()) erro('Esse usuário já existe. Escolha outro.');
  $id=rid();
  q('INSERT INTO users (id,username,name,pass_hash,is_owner,active,pendente,pedido_em,created_at,sess_ver) VALUES (?,?,?,?,0,0,1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),0)',[$id,$un,$nm,hashpw($pw)]);
  evento('pedido_acesso',$un,$id);
  out(['ok'=>true]);

case 'logout':
  if (!empty($_SESSION['uid'])) evento('logout','');
  $_SESSION=[]; session_destroy(); out(['ok'=>true]);
}

$u=usuario();
if ($post) csrf();
if ($u['trocar']&&!in_array($a,['passwd','users'],true)) erro('Troque sua senha provisória primeiro.',403);

switch ($a) {
case 'sync':
  $since=max(0,(int)($_GET['since']??0));
  if ($since===0) $rs=q("SELECT path,col,data,version,seq,deleted FROM docs WHERE deleted=0 AND NOT ((col LIKE 'audit/%' AND updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 DAY)) OR (col IN ('movs','chat') AND updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 365 DAY))) ORDER BY seq LIMIT 5000")->fetchAll();
  else $rs=q('SELECT path,col,data,version,seq,deleted FROM docs WHERE seq>? ORDER BY seq LIMIT 3000',[$since])->fetchAll();
  $max=$since; $docs=[];
  foreach ($rs as $r) { $max=max($max,(int)$r['seq']); if (!pode_ler($r['path'],$u)) continue;
    $docs[]=['p'=>$r['path'],'v'=>(int)$r['version'],'x'=>(bool)$r['deleted'],'d'=>$r['deleted']?null:json_decode($r['data'],true)]; }
  out(['docs'=>$docs,'cursor'=>$max,'mais'=>count($rs)>=($since===0?5000:3000)]);

case 'get':
  $p=(string)($_GET['p']??''); valida_caminho($p);
  if (!pode_ler($p,$u)) out(['existe'=>false]);
  $r=q('SELECT data,version,deleted FROM docs WHERE path=?',[$p])->fetch();
  out($r&&!$r['deleted']?['existe'=>true,'d'=>json_decode($r['data'],true),'v'=>(int)$r['version']]:['existe'=>false]);

case 'write':
  if (!$post) erro('método',405);
  $op=$in['op']??''; if (!in_array($op,['set','update','merge','delete'],true)) erro('operação inválida');
  $d=$in['data']??null; if ($op!=='delete'&&!is_array($d)) erro('dados inválidos');
  out(grava((string)($in['p']??''),$op,$d,$u));

case 'acquire':
  if (!$post) erro('método',405);
  $p=(string)($in['p']??''); valida_caminho($p);
  if (!pode_escrever($p,$u)) erro('sem permissão',403);
  $h=substr((string)($in['h']??''),0,100); $ttl=min(600000,max(1000,(int)($in['ttl']??30000)));
  db()->beginTransaction();
  $r=q('SELECT holder,expires FROM locks WHERE path=? FOR UPDATE',[$p])->fetch();
  $now=(int)floor(microtime(true)*1000);
  if ($r&&(int)$r['expires']>$now&&$r['holder']!==$h) { db()->commit(); out(['acquired'=>false,'expiresAt'=>gmdate('c',intdiv((int)$r['expires'],1000))]); }
  q('INSERT INTO locks (path,holder,expires) VALUES (?,?,?) ON DUPLICATE KEY UPDATE holder=VALUES(holder),expires=VALUES(expires)',[$p,$h,$now+$ttl]);
  db()->commit();
  out(['acquired'=>true,'holder'=>$h]);

case 'users':
  $rs=q('SELECT id,name,username FROM users WHERE active=1 AND pendente=0 ORDER BY name')->fetchAll();
  out(['users'=>$rs]);

case 'passwd':
  if (!$post) erro('método',405);
  $r=q('SELECT pass_hash FROM users WHERE id=?',[$u['id']])->fetch();
  if (!password_verify((string)($in['atual']??''),$r['pass_hash'])) { evento('senha_atual_errada',''); erro('Senha atual incorreta.'); }
  if ($m=senha_ok((string)($in['nova']??''))) erro($m);
  if (hash_equals((string)$in['atual'],(string)$in['nova'])) erro('A senha nova precisa ser diferente da atual.');
  q('UPDATE users SET pass_hash=?,must_change=0,temp_expires=NULL,sess_ver=sess_ver+1 WHERE id=?',[hashpw((string)$in['nova']),$u['id']]);
  $_SESSION['sv']=(int)q('SELECT sess_ver FROM users WHERE id=?',[$u['id']])->fetch()['sess_ver'];
  session_regenerate_id(true); evento('senha_trocada','');
  out(['ok'=>true]);

case 'user_create':
  if (!$post) erro('método',405);
  if (!$u['owner']&&!in_array('admin',$u['roles'],true)) erro('só administradores',403);
  $un=strtolower(trim((string)($in['username']??''))); $nm=trim((string)($in['name']??'')); $pw=(string)($in['password']??'');
  $roles=array_values(array_intersect((array)($in['roles']??[]),['admin','socio','producao','estoque','vendas','financeiro']));
  if (!preg_match('/^[a-z0-9._-]{3,30}$/',$un)) erro('Usuário: 3 a 30 letras minúsculas, números, ponto ou traço.');
  if ($nm===''||mb_strlen($nm)>80) erro('Informe o nome.');
  if ($m=senha_ok($pw)) erro($m);
  if (!$roles) erro('Marque pelo menos uma função.');
  if (q('SELECT 1 FROM users WHERE username=?',[$un])->fetch()) erro('Esse usuário já existe.');
  $id=rid();
  q('INSERT INTO users (id,username,name,pass_hash,is_owner,active,created_at,sess_ver,must_change,temp_expires) VALUES (?,?,?,?,0,1,UTC_TIMESTAMP(),0,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 48 HOUR))',[$id,$un,$nm,hashpw($pw)]);
  evento('usuario_criado',$un.' '.implode(',',$roles));
  grava("membros/$id",'set',['roles'=>$roles,'cargo'=>trim((string)($in['cargo']??'')),'ativo'=>true,'extra'=>[],'bloq'=>[],'criadoEm'=>agora(),'criadoPor'=>$u['id']],$u);
  out(['ok'=>true,'id'=>$id]);

case 'user_reset':
  if (!$post) erro('método',405);
  if (!$u['owner']&&!in_array('admin',$u['roles'],true)) erro('só administradores',403);
  $id=(string)($in['id']??''); $pw=(string)($in['password']??'');
  if ($m=senha_ok($pw)) erro($m);
  $t=q('SELECT is_owner FROM users WHERE id=?',[$id])->fetch(); if (!$t) erro('pessoa não encontrada');
  if ($t['is_owner']&&!$u['owner']) erro('só a dona pode trocar a própria senha',403);
  q('UPDATE users SET pass_hash=?,must_change=1,temp_expires=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 48 HOUR),sess_ver=sess_ver+1 WHERE id=?',[hashpw($pw),$id]);
  evento('senha_redefinida',$id);
  out(['ok'=>true]);

case 'user_active':
  if (!$post) erro('método',405);
  if (!$u['owner']&&!in_array('admin',$u['roles'],true)) erro('só administradores',403);
  $id=(string)($in['id']??''); if ($id===$u['id']) erro('você não pode se desativar');
  q('UPDATE users SET active=?,sess_ver=sess_ver+1 WHERE id=? AND is_owner=0',[empty($in['ativo'])?0:1,$id]);
  evento(empty($in['ativo'])?'usuario_desativado':'usuario_ativado',$id);
  out(['ok'=>true]);

case 'pendentes':
  if (!$u['owner']&&!in_array('admin',$u['roles'],true)) out(['pendentes'=>[]]);
  out(['pendentes'=>q('SELECT id,name,username,pedido_em FROM users WHERE pendente=1 ORDER BY pedido_em')->fetchAll()]);

case 'aprovar':
  if (!$post) erro('método',405);
  if (!$u['owner']&&!in_array('admin',$u['roles'],true)) { evento('negado','aprovar'); erro('só administradores',403); }
  $id=(string)($in['id']??''); $roles=array_values(array_intersect((array)($in['roles']??[]),PAPEIS_OK));
  if (!$roles) erro('Marque pelo menos uma função.');
  $t=q('SELECT username FROM users WHERE id=? AND pendente=1',[$id])->fetch(); if (!$t) erro('pedido não encontrado');
  q('UPDATE users SET pendente=0,active=1,sess_ver=sess_ver+1 WHERE id=?',[$id]);
  grava("membros/$id",'set',['roles'=>$roles,'cargo'=>txt((string)($in['cargo']??''),80),'ativo'=>true,'extra'=>[],'bloq'=>[]],$u);
  evento('acesso_aprovado',$t['username'].' '.implode(',',$roles));
  out(['ok'=>true]);

case 'recusar':
  if (!$post) erro('método',405);
  if (!$u['owner']&&!in_array('admin',$u['roles'],true)) { evento('negado','recusar'); erro('só administradores',403); }
  $t=q('SELECT username FROM users WHERE id=? AND pendente=1',[(string)($in['id']??'')])->fetch(); if (!$t) erro('pedido não encontrado');
  q('DELETE FROM users WHERE id=? AND pendente=1',[(string)$in['id']]);
  evento('acesso_recusado',$t['username']);
  out(['ok'=>true]);

case 'seg':
  if (!$u['owner']&&!in_array('admin',$u['roles'],true)) { evento('negado','seg'); erro('só administradores',403); }
  $ev=q('SELECT tipo,uid,ip,ua,detalhe,em FROM eventos_seg ORDER BY id DESC LIMIT 300')->fetchAll();
  $res=q("SELECT tipo,COUNT(*) n FROM eventos_seg WHERE em>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) AND tipo IN ('login_falhou','login_bloqueado','negado','csrf_invalido','origem_invalida','senha_atual_errada') GROUP BY tipo")->fetchAll();
  out(['eventos'=>$ev,'resumo'=>$res]);

case 'revogar':
  if (!$post) erro('método',405);
  if (!$u['owner']&&!in_array('admin',$u['roles'],true)) { evento('negado','revogar'); erro('só administradores',403); }
  q("INSERT INTO ajustes (k,v) VALUES ('epoca','1') ON DUPLICATE KEY UPDATE v=CAST(v AS UNSIGNED)+1");
  evento('sessoes_revogadas','todas'); out(['ok'=>true]);

case 'upload':
  if (!$post) erro('método',405);
  $f=$_FILES['f']??null;
  if (!$f||$f['error']!==UPLOAD_ERR_OK) erro('envio falhou');
  if ($f['size']>15*1024*1024) erro('arquivo maior que 15 MB');
  if (!is_uploaded_file($f['tmp_name'])) erro('envio inválido');
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','application/pdf'=>'pdf'][$mime]??null;
  if (!$ext) erro('tipo de arquivo não permitido (use JPG, PNG, WEBP, GIF ou PDF)');
  $id=rid(24).'.'.$ext; $dir=__DIR__.'/uploads';
  if (!is_dir($dir)) mkdir($dir,0750,true);
  if (!move_uploaded_file($f['tmp_name'],"$dir/$id")) erro('não foi possível salvar o arquivo',500);
  q('INSERT INTO files (id,mime,size,uid,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())',[$id,$mime,$f['size'],$u['id']]);
  out(['id'=>$id,'url'=>'_blob/'.$id,'sizeBytes'=>$f['size'],'contentType'=>$mime]);

case 'file':
  $id=(string)($_GET['id']??'');
  if (!preg_match('/^[a-z0-9]{24}\.(jpg|png|webp|gif|pdf)$/',$id)) erro('não encontrado',404);
  $r=q('SELECT mime FROM files WHERE id=?',[$id])->fetch(); $fp=__DIR__."/uploads/$id";
  if (!$r||!is_file($fp)) erro('não encontrado',404);
  header_remove('Content-Type'); header('Content-Type: '.$r['mime']); header('Content-Length: '.filesize($fp));
  header('Cache-Control: private, max-age=86400'); header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
  header('Content-Disposition: inline; filename="'.$id.'"');
  readfile($fp); exit;
}
erro('ação desconhecida',404);
