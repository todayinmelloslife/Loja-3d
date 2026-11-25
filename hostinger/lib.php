<?php
declare(strict_types=1);
ini_set('display_errors','0');ini_set('log_errors','1');error_reporting(E_ALL);
set_exception_handler(function(Throwable $e){error_log('[central] '.get_class($e).': '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());if(!headers_sent()){http_response_code(500);header('Content-Type: application/json; charset=utf-8');}echo '{"erro":"Erro interno. Tente de novo."}';});
define('HASH',defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);
const MODULOS=['painel','estoque','produtos','pedidos','producao','qualidade','logistica','compras','financeiro','custos','relatorios','tarefas','posts','chat','equipe','config','lixeira','ajuda'];
const PAPEIS_OK=['admin','socio','producao','estoque','vendas','financeiro'];
const NIV=['view'=>1,'interact'=>2,'admin'=>3,'owner'=>4];
const REGRAS=[[''=>['interact','interact']],['fin'=>['admin','admin']],['config'=>['interact','admin']],['membros'=>['interact','admin']],['audit'=>['admin','admin']],['audit/{self}'=>['interact','interact']],['acessos'=>['admin','admin']],['acessos/{self}'=>['interact','interact']],['data/users'=>['owner','owner']],['data/users/{self}'=>['interact','interact']]];
const COMUM=['tarefas','posts','chat','locks','audit','acessos','data'];
const PAPEIS=[
 'socio'=>['materiais','produtos','impressoras','ordens','clientes','pedidos','fornecedores','compras','movs','fin','config'],
 'producao'=>['materiais','produtos','impressoras','ordens','movs'],
 'estoque'=>['materiais','produtos','compras','fornecedores','movs'],
 'vendas'=>['pedidos','clientes','produtos','ordens','movs'],
 'financeiro'=>['fin','compras','fornecedores','movs','materiais']];

function cfg_fora(): string { return dirname(__DIR__).'/central-config.php'; }
function cfg_arq(): ?string { foreach ([cfg_fora(),__DIR__.'/config.php'] as $f) if (@is_file($f)) return $f; return null; }
function cfg(): array { static $c; return $c ??= require cfg_arq(); }
function up_dir(): string { $d=dirname(__DIR__).'/central-uploads'; return (@is_dir($d)||@mkdir($d,0750,true))?$d:__DIR__.'/uploads'; }
function db(): PDO {
  static $p;
  if (!$p) { $c=cfg(); $p=new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); }
  return $p;
}
function q(string $sql,array $a=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($a); return $s; }
function out($d,int $code=200): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d,JSON_UNESCAPED_UNICODE); exit; }
function erro(string $m,int $code=400): never { out(['erro'=>$m],$code); }
function rid(int $n=20): string { $a='abcdefghijklmnopqrstuvwxyz0123456789'; $s=''; for($i=0;$i<$n;$i++) $s.=$a[random_int(0,35)]; return $s; }
function agora(): string { return gmdate('Y-m-d\TH:i:s.v\Z'); }
function https(): bool { return (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'||($_SERVER['SERVER_PORT']??'')==='443'; }
function cabecalhos(): void {
  header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: same-origin');
  header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
  header('Cross-Origin-Opener-Policy: same-origin'); header('Cross-Origin-Resource-Policy: same-origin');
  header_remove('X-Powered-By');
  if (https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
function sessao(): void {
  if (session_status()===PHP_SESSION_ACTIVE) return;
  session_name(https()?'__Host-c3d':'c3dsid');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>https(),'httponly'=>true,'samesite'=>'Strict']);
  ini_set('session.use_strict_mode','1');
  session_start();
  if (isset($_SESSION['uid'])&&((time()-($_SESSION['vis']??0))>12*3600||(time()-($_SESSION['ini']??0))>7*86400)) { $_SESSION=[]; session_destroy(); session_start(); }
  $_SESSION['vis']=time();
}
function valida_caminho(string $p,bool $doc=true): array {
  $s=explode('/',$p);
  if ($p===''||count($s)>16||strlen($p)>700) erro('caminho inválido');
  foreach ($s as $x) if ($x===''||$x==='.'||$x==='..'||!preg_match('/^[A-Za-z0-9_\-.~:@+]{1,200}$/',$x)) erro('caminho inválido');
  if ($doc&&count($s)%2) erro('caminho de documento inválido');
  return $s;
}
function membro(string $uid): ?array {
  $r=q('SELECT data,deleted FROM docs WHERE path=?',["membros/$uid"])->fetch();
  return ($r&&!$r['deleted'])?json_decode($r['data'],true):null;
}
function usuario(): array {
  sessao();
  if (empty($_SESSION['uid'])) erro('não autenticado',401);
  static $u;
  if ($u) return $u;
  $r=q('SELECT id,username,name,is_owner,active,sess_ver,must_change FROM users WHERE id=?',[$_SESSION['uid']])->fetch();
  if (!$r||!$r['active']) { $_SESSION=[]; erro('acesso desativado',401); }
  if ((int)$r['sess_ver']!==(int)($_SESSION['sv']??-1)||(int)ajuste('epoca','0')!==(int)($_SESSION['ep']??-1)) { $_SESSION=[]; erro('sessão encerrada, entre de novo',401); }
  $m=membro($r['id']);
  $roles=$m['roles']??[];
  $ativo=$m&&($m['ativo']??true)!==false&&empty($m['excluido']);
  if ($r['is_owner']) $lv=4; elseif (!$ativo) $lv=1; elseif (array_intersect($roles,['admin','socio','financeiro'])) $lv=3; else $lv=2;
  return $u=['id'=>$r['id'],'username'=>$r['username'],'name'=>$r['name'],'owner'=>(bool)$r['is_owner'],'nivel'=>$lv,'roles'=>$r['is_owner']?['admin']:($ativo?$roles:[]),'ativo'=>$ativo||$r['is_owner'],'trocar'=>(bool)$r['must_change']];
}
function origem_ok(): bool {
  $o=$_SERVER['HTTP_ORIGIN']??($_SERVER['HTTP_REFERER']??'');
  if ($o==='') return true;
  return strcasecmp((string)parse_url($o,PHP_URL_HOST),explode(':',$_SERVER['HTTP_HOST']??'')[0])===0;
}
function csrf(): void {
  $t=$_SERVER['HTTP_X_CSRF']??'';
  if (!origem_ok()) { evento('origem_invalida',$_SERVER['HTTP_ORIGIN']??''); erro('origem não permitida',403); }
  if (!$t||empty($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],$t)) { evento('csrf_invalido',''); erro('sessão expirada, entre de novo',403); }
}
function ajuste(string $k,string $d=''): string { try { $r=q('SELECT v FROM ajustes WHERE k=?',[$k])->fetch(); return $r?$r['v']:$d; } catch (Throwable $e) { return $d; } }
function evento(string $tipo,string $det='',?string $uid=null): void {
  try { q('INSERT INTO eventos_seg (tipo,uid,ip,ua,detalhe,em) VALUES (?,?,?,?,?,UTC_TIMESTAMP())',[$tipo,$uid??($_SESSION['uid']??null),ip(),substr($_SERVER['HTTP_USER_AGENT']??'',0,200),mb_substr($det,0,300)]); } catch (Throwable $e) {}
}
function regra(array $seg,string $uid): array {
  $r=['interact','interact'];
  foreach (REGRAS as $x) { $k=array_key_first($x); [$rd,$wr]=$x[$k];
    $ks=$k===''?[]:explode('/',$k); if (count($ks)>count($seg)) continue; $ok=true;
    foreach ($ks as $i=>$p) if (!($p===$seg[$i]||($p==='{self}'&&$seg[$i]===$uid))) { $ok=false; break; }
    if ($ok) $r=[$rd,$wr];
  }
  return $r;
}
function pode_ler(string $path,array $u): bool { $s=explode('/',$path); if ($s[0]==='cred') return false; return $u['nivel']>=NIV[regra($s,$u['id'])[0]]; }
function pode_escrever(string $path,array $u): bool {
  $s=explode('/',$path);
  if ($s[0]==='cred'||!$u['ativo']) return false;
  if ($u['nivel']<NIV[regra($s,$u['id'])[1]]) return false;
  if ($u['owner']||in_array('admin',$u['roles'],true)) return true;
  if (in_array($s[0],COMUM,true)) return true;
  foreach ($u['roles'] as $r) if (in_array($s[0],PAPEIS[$r]??[],true)) return true;
  return false;
}
function limpa($v,int $prof=0) {
  if ($prof>12) erro('dados aninhados demais');
  if (is_string($v)) { if (mb_strlen($v)>20000) erro('texto longo demais'); return $v; }
  if (is_int($v)||is_bool($v)||$v===null) return $v;
  if (is_float($v)) { if (!is_finite($v)) erro('número inválido'); return $v; }
  if (is_array($v)) { if (count($v)>2000) erro('lista grande demais'); $o=[];
    foreach ($v as $k=>$x) { if (is_string($k)&&(str_starts_with($k,'__')||strlen($k)>80)) erro('campo inválido'); $o[$k]=limpa($x,$prof+1); }
    return $o; }
  erro('tipo de dado inválido');
}
function numf($v,float $min=-1e12,float $max=1e12): float { if (!is_int($v)&&!is_float($v)) erro('valor numérico inválido'); if ($v<$min||$v>$max) erro('valor fora do permitido'); return (float)$v; }
function txt($v,int $max=500): string { if (!is_string($v)) erro('texto inválido'); if (mb_strlen($v)>$max) erro('texto longo demais'); return $v; }
function valida_doc(array $seg,array $d,?array $old,array $u): array {
  $d=limpa($d); $c=$seg[0];
  $nums=['materiais'=>['estoque','custoUnit','estMin'],'produtos'=>['preco','peso','tempoImpMin','pecasImp','maoObraMin','custoManual'],'fin'=>['valor'],'compras'=>['total','frete'],'pedidos'=>['total','desconto','frete','freteCusto'],'ordens'=>['qtdSol','qtdProg','pesoPrev','tempoPrev','aprov','reprov','tempoReal','pesoReal'],'impressoras'=>['potenciaW','valor','vidaUtilH','manutH']];
  foreach ($nums[$c]??[] as $k) if (array_key_exists($k,$d)&&$d[$k]!==''&&$d[$k]!==null) numf($d[$k]);
  foreach (['nome','titulo','desc','numero','cor','marca','corNome','colecao','categoria','cat','status','tipo'] as $k) if (isset($d[$k])) txt($d[$k],$k==='desc'||$k==='titulo'?300:120);
  if ($c==='produtos'&&isset($d['est'])) { if (!is_array($d['est'])) erro('estoque inválido'); foreach ($d['est'] as $k=>$v) { if (!in_array($k,['pronto','reservado','defeito','expo'],true)) erro('estoque inválido'); numf($v,-1e7,1e7); } }
  if (in_array($c,['pedidos','compras'],true)&&isset($d['itens'])) { if (!is_array($d['itens'])||!array_is_list($d['itens'])||count($d['itens'])>300) erro('itens inválidos'); foreach ($d['itens'] as $i) { if (!is_array($i)) erro('item inválido'); foreach (['q','pu','v','cu','res'] as $k) if (isset($i[$k])) numf($i[$k],0,1e9); } }
  if ($c==='fin') { if (isset($d['valor'])) numf($d['valor'],0,1e10); if (isset($d['tipo'])&&!in_array($d['tipo'],['receita','despesa','cfg'],true)) erro('tipo inválido'); }
  if ($c==='membros') {
    if (isset($d['roles'])&&(!is_array($d['roles'])||array_diff($d['roles'],PAPEIS_OK))) erro('função inválida');
    foreach (['extra','bloq'] as $k) if (isset($d[$k])&&(!is_array($d[$k])||array_diff($d[$k],MODULOS))) erro('módulo inválido');
  }
  if ($c==='chat') { $d['uid']=$u['id']; txt($d['t']??'',2000); if ($old&&($old['uid']??'')!==$u['id']&&!in_array('admin',$u['roles'],true)) erro('só quem escreveu pode mudar a mensagem',403); }
  if ($c==='audit') { $d['uid']=$u['id']; }
  if ($c==='posts'&&!empty($d['link'])&&!preg_match('#^https://#i',(string)$d['link'])) erro('o link precisa começar com https://');
  foreach (['hex','corHex'] as $k) if (!empty($d[$k])&&!preg_match('/^#[0-9a-fA-F]{6}$/',(string)$d[$k])) erro('cor inválida');
  foreach (['fotos','anexos'] as $k) if (isset($d[$k])) { if (!is_array($d[$k])) erro('arquivos inválidos'); foreach ($d[$k] as $f) if (!is_string($f)||!preg_match('/^[a-z0-9]{24}\.(jpg|png|webp|gif|pdf)$/',$f)) erro('arquivo inválido'); }
  if (!in_array($c,['audit','locks','acessos'],true)) {
    $d['atualizadoPor']=$u['id']; $d['atualizadoEm']=agora();
    if ($old) { if (isset($old['criadoPor'])) $d['criadoPor']=$old['criadoPor']; if (isset($old['criadoEm'])) $d['criadoEm']=$old['criadoEm']; }
    else { $d['criadoPor']=$u['id']; $d['criadoEm']=$d['criadoEm']??agora(); }
  }
  return $d;
}
function prox_seq(): int { q('INSERT INTO seqgen () VALUES ()'); return (int)db()->lastInsertId(); }
function mescla(array $a,array $b): array {
  foreach ($b as $k=>$v) {
    if (is_array($v)&&array_key_exists('__delete__',$v)&&count($v)===1) { unset($a[$k]); continue; }
    $a[$k]=(is_array($v)&&!array_is_list($v)&&isset($a[$k])&&is_array($a[$k])&&!array_is_list($a[$k]))?mescla($a[$k],$v):$v;
  }
  return $a;
}
function grava(string $path,string $op,?array $data,array $u): array {
  $seg=valida_caminho($path);
  if (!pode_escrever($path,$u)) { evento('negado',"$op $path"); erro('sem permissão para isso',403); }
  if ($data!==null&&strlen(json_encode($data))>262144) erro('registro grande demais');
  $col=implode('/',array_slice($seg,0,-1));
  db()->beginTransaction();
  try {
    $r=q('SELECT data,version,deleted FROM docs WHERE path=? FOR UPDATE',[$path])->fetch();
    $existe=$r&&!$r['deleted'];
    if ($op==='update') { if (!$existe) erro('registro não existe'); $data=mescla(json_decode($r['data'],true)?:[],$data??[]); }
    elseif ($op==='merge') $data=mescla($existe?(json_decode($r['data'],true)?:[]):[],$data??[]);
    if ($op!=='delete') $data=valida_doc($seg,$data,$existe?(json_decode($r['data'],true)?:[]):null,$u);
    $seq=prox_seq(); $v=($r['version']??0)+1;
    $json=$op==='delete'?null:json_encode($data,JSON_UNESCAPED_UNICODE);
    q('INSERT INTO docs (path,col,data,version,seq,deleted,updated_at,updated_by) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(),?) ON DUPLICATE KEY UPDATE data=VALUES(data),version=VALUES(version),seq=VALUES(seq),deleted=VALUES(deleted),updated_at=VALUES(updated_at),updated_by=VALUES(updated_by)',
      [$path,$col,$json,$v,$seq,$op==='delete'?1:0,$u['id']]);
    db()->commit();
  } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
  return ['path'=>$path,'data'=>$op==='delete'?null:$data,'version'=>$v,'seq'=>$seq,'deleted'=>$op==='delete'];
}
function senha_ok(string $p): ?string {
  if (strlen($p)<10) return 'A senha precisa ter pelo menos 10 caracteres.';
  if (strlen($p)>200) return 'Senha longa demais.';
  if (in_array(strtolower($p),['1234567890','senha12345','senhasenha','qwertyuiop','abcdefghij','0123456789'],true)) return 'Essa senha é fácil demais.';
  if (!preg_match('/\d/',$p)||!preg_match('/[A-Za-z]/',$p)) return 'Use letras e números na senha.';
  return null;
}
function hashpw(string $p): string { return password_hash($p,HASH); }
function ip(): string { return substr($_SERVER['REMOTE_ADDR']??'?',0,45); }
