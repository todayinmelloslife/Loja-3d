(()=>{
const API='api.php';let me=null,csrf='',cursor=0,users=[],usersAt=0;const cache=new Map(),subs=new Set();
const clone=o=>o==null?o:JSON.parse(JSON.stringify(o));
const par=p=>p.slice(0,p.lastIndexOf('/'));
async function call(a,body,qs=''){let r;try{r=await fetch(API+'?a='+a+qs,{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:body?{'Content-Type':'application/json','X-CSRF':csrf}:{},body:body?JSON.stringify(body):undefined})}catch(e){const x=new Error('Sem conexão com o servidor.');x.code='unavailable';throw x}
 let j={};try{j=await r.json()}catch(e){}
 if(r.status===401&&!['login','me'].includes(a)){location.reload();await new Promise(()=>{})}
 if(!r.ok){const x=new Error(j.erro||'Erro no servidor.');x.code=r.status===403&&/permiss/i.test(j.erro||'')?'invalid_argument':r.status>=500?'unavailable':'bad_request';throw x}return j}
const snapOf=(p,e)=>({id:p.split('/').pop(),exists:!!e,data:()=>e?clone(e.d):undefined,metadata:{fromCache:false,hasPendingWrites:false}});
function fire(){subs.forEach(s=>{try{s()}catch(e){console.error(e)}})}
function put(p,d,v){if(d==null)cache.delete(p);else cache.set(p,{d,v});}
let polling=false;
async function pull(){if(polling)return;polling=true;try{let more=true,ch=false;while(more){const j=await call('sync',null,'&since='+cursor);j.docs.forEach(d=>put(d.p,d.x?null:d.d,d.v));if(j.docs.length)ch=true;cursor=j.cursor;more=j.mais}if(ch)fire()}catch(e){}finally{polling=false}}
function loop(){setTimeout(async()=>{await pull();loop()},document.hidden?15000:2500)}
document.addEventListener('visibilitychange',()=>{if(!document.hidden)pull()});
async function write(p,op,data){const r=await call('write',{p,op,data});put(p,r.deleted?null:r.data,r.version);fire();return r}
function q(path,ord,lim){const run=()=>{let a=[...cache.entries()].filter(([k])=>par(k)===path).map(([k,e])=>({k,e}));
  if(ord){const[f,d]=ord;a.sort((x,y)=>{const u=x.e.d[f],v=y.e.d[f];const c=u===v?0:u===undefined?1:v===undefined?-1:u<v?-1:1;return d==='desc'&&u!==undefined&&v!==undefined?-c:c})}else a.sort((x,y)=>x.k<y.k?-1:1);
  if(lim)a=a.slice(0,lim);const docs=a.map(({k,e})=>snapOf(k,e));return{docs,size:docs.length,empty:!docs.length,metadata:{fromCache:false,hasPendingWrites:false},docChanges:()=>[]}};
 return{path,where(){return this},orderBy:(f,d)=>q(path,[f,d||'asc'],lim),limit:n=>q(path,ord,n),doc:id=>doc(path+'/'+(id||rid())),add:async d=>{const r=doc(path+'/'+rid());await r.set(d);return r},get:async()=>run(),
  onSnapshot(next){let sig=null;const f=()=>{const s=run(),g=s.docs.map(d=>d.id+':'+cache.get(path+'/'+d.id).v).join('|');if(g!==sig){sig=g;next(s)}};subs.add(f);setTimeout(f,0);return()=>subs.delete(f)}}}
function doc(p){return{id:p.split('/').pop(),path:p,
 get:async()=>{const j=await call('get',null,'&p='+encodeURIComponent(p));if(j.existe)put(p,j.d,j.v);return snapOf(p,j.existe?{d:j.d,v:j.v}:null)},
 set:d=>write(p,'set',d),update:d=>write(p,'update',d),delete:()=>write(p,'delete',null),
 acquire:async o=>call('acquire',{p,h:o.holder,ttl:o.ttlMs||30000}),
 collection:c=>q(p+'/'+c),
 onSnapshot(next){let v=-1;const f=()=>{const e=cache.get(p),nv=e?e.v:0;if(nv!==v){v=nv;next(snapOf(p,e))}};subs.add(f);setTimeout(f,0);return()=>subs.delete(f)}}}
function rid(){const a='abcdefghijklmnopqrstuvwxyz0123456789';let s='';const b=crypto.getRandomValues(new Uint8Array(20));b.forEach(x=>s+=a[x%36]);return s}
async function loadUsers(force){if(force||Date.now()-usersAt>60000){try{users=(await call('users')).users;usersAt=Date.now()}catch(e){}}return users}
const user={id:async()=>me.id,isOwner:async()=>me.owner,canEdit:async()=>me.nivel>=3,can:async n=>n==='data.write'?me.nivel>=2:null,me:async()=>({id:me.id,name:me.name}),
 profiles:async ids=>{await loadUsers();const o={};ids.forEach(i=>{const u=users.find(x=>x.id===i);o[i]={id:i,name:u?u.name:''}});return o},
 search:async t=>{await loadUsers(true);t=(t||'').toLowerCase();return users.filter(u=>!t||(u.name+' '+u.username).toLowerCase().includes(t)).map(u=>({id:u.id,name:u.name,email:u.username}))}};
const assets={upload:async blob=>{const fd=new FormData();fd.append('f',blob,blob.name||'arquivo');const r=await fetch(API+'?a=upload',{method:'POST',credentials:'same-origin',headers:{'X-CSRF':csrf},body:fd});const j=await r.json().catch(()=>({}));if(!r.ok)throw new Error(j.erro||'Falha no envio');return j}};
const downloads={save:async({filename,data})=>{const b=data instanceof Blob?data:new Blob([data],{type:'text/plain;charset=utf-8'});const u=URL.createObjectURL(b),a=document.createElement('a');a.href=u;a.download=filename;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(u),4000)}};
const db={doc,collection:p=>q(p)};
window.HOSTED=true;
window.HOSTED_API={me:()=>me,pendentes:()=>call('pendentes').then(r=>r.pendentes),aprovar:(id,roles,cargo)=>call('aprovar',{id,roles,cargo}).then(r=>{usersAt=0;return pull().then(()=>r)}),recusar:id=>call('recusar',{id}),seg:()=>call('seg'),revogar:()=>call('revogar',{}),logout:()=>call('logout',{}),passwd:(atual,nova)=>call('passwd',{atual,nova}),createUser:o=>call('user_create',o).then(r=>{usersAt=0;return pull().then(()=>r)}),reset:(id,password)=>call('user_reset',{id,password}),setActive:(id,ativo)=>call('user_active',{id,ativo}),reloadUsers:()=>loadUsers(true)};
window.claude={use:async n=>{await window.HOSTED_READY;return{db,user,assets,downloads}[n]||null}};
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const casca=(t,b)=>`<div class="gate" style="margin-top:7vh;max-width:440px"><div style="text-align:center;margin-bottom:14px"><svg style="width:64px;height:64px"><use href="#star"/></svg><h1 style="margin-top:6px">Central da Fábrica 3D</h1><p class="muted" style="margin:4px 0 0">gestão interna</p></div><div class="win"><div class="tb"><b>${t}</b><span class="dots"><span></span><span></span><span></span></span></div><div class="wb">${b}</div></div></div>`;
const campo=(id,l,t='text',ac='')=>`<label class="fl"><span>${l}</span><input id="${id}" type="${t}" ${ac?`autocomplete="${ac}"`:''} autocapitalize="none" required></label>`;
function tela(setup,done,modo='entrar'){const root=document.getElementById('root');
 if(setup){root.innerHTML=casca('Instalação pendente','<p>O sistema ainda não foi instalado.</p><a class="btn" href="install.php" style="width:100%">Instalar agora</a>');return}
 if(modo==='pedir'){root.innerHTML=casca('Pedir acesso',`<form id="lf" novalidate><p class="small">Depois de enviar, a administradora precisa aprovar. Só aí você consegue entrar.</p>${campo('ln','Seu nome')}${campo('lu','Usuário (para entrar)','text','username')}${campo('lp','Senha','password','new-password')}${campo('lp2','Repita a senha','password','new-password')}<p class="small muted">10+ caracteres, com letras e números.</p><div class="err" id="le" role="alert"></div><button class="btn" style="width:100%;margin-top:6px">Enviar pedido</button><p style="margin-bottom:0"><a href="#" id="lalt">Já tenho acesso — entrar</a></p></form>`);
  document.getElementById('lalt').onclick=e=>{e.preventDefault();tela(false,done)};document.getElementById('ln').focus();
  document.getElementById('lf').onsubmit=async e=>{e.preventDefault();const f=e.target,le=document.getElementById('le'),b=f.querySelector('button');le.textContent='';const n=f.ln.value.trim(),u=f.lu.value.trim().toLowerCase(),p=f.lp.value;
   if(!n||!u||!p){le.textContent='Preencha tudo.';return}if(p!==f.lp2.value){le.textContent='As senhas não são iguais.';return}
   b.disabled=true;try{await call('signup',{name:n,username:u,password:p});root.innerHTML=casca('Pedido enviado',`<p>Pronto, <b>${esc(n)}</b>! Seu pedido foi para a administradora. Assim que ela aprovar, entre com o usuário <b>${esc(u)}</b> e a senha que você criou.</p><button class="btn ghost" id="lv" style="width:100%">Voltar</button>`);document.getElementById('lv').onclick=()=>tela(false,done)}catch(x){le.textContent=x.message;b.disabled=false}};return}
 root.innerHTML=casca('Entrar',`<form id="lf" novalidate>${campo('lu','Usuário','text','username')}${campo('lp','Senha','password','current-password')}<div class="err" id="le" role="alert"></div><button class="btn" style="width:100%;margin-top:6px">Entrar</button><p class="small" style="margin-bottom:0">Ainda não tem acesso? <a href="#" id="lalt">Pedir acesso</a><br><span class="muted">Esqueceu a senha? Peça para a administradora redefinir.</span></p></form>`);
 document.getElementById('lalt').onclick=e=>{e.preventDefault();tela(false,done,'pedir')};document.getElementById('lu').focus();
 const f=document.getElementById('lf');f.onsubmit=async e=>{e.preventDefault();const le=document.getElementById('le'),b=f.querySelector('button');le.textContent='';const u=f.lu.value.trim(),p=f.lp.value;if(!u||!p){le.textContent='Preencha usuário e senha.';return}
  b.disabled=true;b.textContent='Entrando…';try{await call('login',{u,p});await iniciar(p);done()}catch(x){le.textContent=x.message;b.disabled=false;b.textContent='Entrar'}}}
function trocarSenha(atual){return new Promise(res=>{const root=document.getElementById('root');
 root.innerHTML=casca('Crie sua senha',`<form id="lf" novalidate><p class="small">Você entrou com uma senha provisória. Crie a sua agora — só você vai saber.</p>${atual?'':campo('la','Senha provisória','password','current-password')}${campo('lp','Nova senha','password','new-password')}${campo('lp2','Repita a nova senha','password','new-password')}<p class="small muted">10+ caracteres, com letras e números.</p><div class="err" id="le" role="alert"></div><button class="btn" style="width:100%">Salvar e entrar</button></form>`);
 const f=document.getElementById('lf');f.onsubmit=async e=>{e.preventDefault();const le=document.getElementById('le');le.textContent='';const a=atual||f.la.value,p=f.lp.value;if(p!==f.lp2.value){le.textContent='As senhas não são iguais.';return}
  try{await call('passwd',{atual:a,nova:p});res()}catch(x){le.textContent=x.message}}})}
async function iniciar(senhaDigitada){let m=await call('me');if(!m.logado)throw new Error('Não foi possível entrar.');me=m;csrf=m.csrf;if(m.trocar){await trocarSenha(senhaDigitada);m=await call('me');me=m;csrf=m.csrf}await loadUsers(true);await pull();loop()}
window.HOSTED_READY=new Promise(async res=>{try{const m=await call('me');if(m.logado){await iniciar();res()}else{const go=()=>tela(m.setup,res);document.readyState==='loading'?document.addEventListener('DOMContentLoaded',go):go()}}catch(e){const go=/instalad/.test(e.message||'')?()=>tela(true,res):()=>{document.getElementById('root').innerHTML='<div class="gate"><div class="note">Não consegui falar com o servidor. Confira a internet e recarregue a página.</div></div>'};document.readyState==='loading'?document.addEventListener('DOMContentLoaded',go):go()}});
})();
