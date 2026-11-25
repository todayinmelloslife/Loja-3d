import requests,re,time,subprocess,json
H='http://127.0.0.1:8088';B=H+'/api.php?a='
R=[]
def ok(item,nome,cond,det=''):
  R.append((item,nome,bool(cond),det));print(('PASSOU ' if cond else 'FALHOU ')+f'[{item}] {nome}'+(f' — {det}' if det and not cond else ''))
def sql(q): return subprocess.run(['mysql','-uroot','central','-N','-e',q],capture_output=True,text=True).stdout.strip()
def login(u,p):
  s=requests.Session();r=s.post(B+'login',json={'u':u,'p':p})
  if r.ok: s.headers['X-CSRF']=s.get(B+'me').json()['csrf']
  return s,r
adm,_=login('duda','pixel2026xy')
def w(s,p,op,d=None): return s.post(B+'write',json={'p':p,'op':op,'data':d})
# ---------- 1 autenticação
r=requests.post(B+'login',json={'u':'duda','p':'errada123'});r2=requests.post(B+'login',json={'u':'naoexiste','p':'errada123'})
ok(1,'mensagem igual para usuário inexistente e senha errada (não revela quem existe)',r.json()==r2.json() and r.status_code==401)
ok(1,'senhas com Argon2id',sql("SELECT LEFT(pass_hash,9) FROM users WHERE username='duda'")=='$argon2id')
s=requests.Session();s.get(B+'me');c1=s.cookies.get('c3dsid');s.post(B+'login',json={'u':'duda','p':'pixel2026xy'});ok(1,'ID de sessão trocado no login (anti-sequestro)',c1!=s.cookies.get('c3dsid'))
for i in range(9): requests.post(B+'login',json={'u':'vitima','p':f'tent{i}xxxxxx'})
r=requests.post(B+'login',json={'u':'vitima','p':'qualquer123'});ok(1,'trava após várias senhas erradas',r.status_code==429,r.text)
sql("DELETE FROM login_falhas")
r=adm.post(B+'user_create',json={'username':'fraca','name':'F','password':'1234567890','roles':['vendas']});ok(1,'recusa senha fraca/comum',r.status_code==400)
# ---------- 2 banco
r=requests.post(B+'login',json={'u':"duda' OR '1'='1",'p':"x' OR '1'='1"});ok(2,'login resiste a SQL injection',r.status_code==401)
r=w(adm,"tarefas/x' OR 1=1--",'set',{'a':1});ok(2,'caminho com SQL injection recusado',r.status_code==400)
r=adm.get(B+"get&p="+requests.utils.quote("tarefas/a' UNION SELECT pass_hash FROM users--"));ok(2,'leitura com SQL injection recusada',r.status_code==400)
for f in ['config.php','lib.php','schema.sql','app.html','LEIAME.md','install.php.usado','.htaccess','uploads/x.jpg']:
  r=requests.get(H+'/'+f);ok(17,f'arquivo protegido: {f}',r.status_code in (403,404),r.status_code)
r=adm.get(B+'get&p=a/'+'b'*3000);ok(2,'erro não vaza detalhes internos',('PDO' not in r.text and 'Stack' not in r.text and '.php' not in r.text),r.text[:80])
# ---------- 3 https
r=requests.post(B+'login',json={'u':'duda','p':'pixel2026xy'},headers={'Host':'meusite.com.br'});ok(3,'login recusado sem HTTPS fora do computador local',r.status_code==403,r.text)
# ---------- 4 validação no servidor
ok(4,'número inválido recusado',w(adm,'materiais/m1','set',{'nome':'PLA','estoque':'<b>muito</b>'}).status_code==400)
ok(4,'texto gigante recusado',w(adm,'produtos/p1','set',{'nome':'x'*500}).status_code==400)
ok(4,'campo especial "__" recusado',w(adm,'produtos/p1','set',{'nome':'ok','__proto__':{'a':1}}).status_code==400)
ok(4,'cor inválida recusada (evita CSS malicioso)',w(adm,'produtos/p1','set',{'nome':'ok','corHex':'red;background:url(x)'}).status_code==400)
ok(4,'arquivo de foto com caminho falso recusado',w(adm,'produtos/p1','set',{'nome':'ok','fotos':['../config.php']}).status_code==400)
ok(4,'valor financeiro negativo recusado',w(adm,'fin/f1','set',{'tipo':'despesa','valor':-50}).status_code==400)
ok(4,'função inexistente recusada',w(adm,'membros/zz','set',{'roles':['deus']}).status_code==400)
ok(4,'link de post sem https recusado',w(adm,'posts/p1','set',{'titulo':'a','link':'javascript:alert(1)'}).status_code==400)
r=w(adm,'tarefas/t1','set',{'titulo':'a','criadoPor':'OUTRA','atualizadoPor':'OUTRA'}).json();ok(4,'autor gravado pelo servidor (não dá para fingir ser outra pessoa)',r['data']['criadoPor']!='OUTRA' and r['data']['atualizadoPor']!='OUTRA')
# ---------- 7 rotas de admin + aprovação
r=requests.post(B+'signup',json={'name':'Ana','username':'ana','password':'senha2026ana'});ok(7,'pedido de acesso criado',r.ok,r.text)
s,r=login('ana','senha2026ana');ok(7,'quem só pediu acesso NÃO entra antes da aprovação',r.status_code==403,r.text)
pend=adm.get(B+'pendentes').json()['pendentes'];ok(7,'administradora vê o pedido',any(p['username']=='ana' for p in pend))
aid=[p for p in pend if p['username']=='ana'][0]['id']
r=adm.post(B+'aprovar',json={'id':aid,'roles':['vendas']});ok(7,'administradora aprova',r.ok,r.text)
ana,r=login('ana','senha2026ana');ok(7,'depois de aprovada, entra',r.ok,r.text)
for rota,body in [('user_create',{'username':'hack','name':'H','password':'senha2026hack','roles':['admin']}),('aprovar',{'id':'x','roles':['admin']}),('recusar',{'id':'x'}),('user_reset',{'id':'x','password':'senha2026xyz'}),('revogar',{}),('user_active',{'id':'x','ativo':1})]:
  ok(7,f'vendedora bloqueada na rota de admin: {rota}',ana.post(B+rota,json=body).status_code==403)
ok(7,'vendedora não vê registro de segurança',ana.get(B+'seg').status_code==403)
ok(7,'vendedora não vê pedidos de acesso',ana.get(B+'pendentes').json()['pendentes']==[])
ok(7,'vendedora não se promove a admin',w(ana,'membros/'+aid,'update',{'roles':['admin']}).status_code==403)
ok(7,'vendedora não lê o financeiro',ana.get(B+'get&p=fin/f1').json().get('existe')==False)
for i in range(3): requests.post(B+'signup',json={'name':f'Spam{i}','username':f'spam{i}','password':'senha2026spam'})
r=requests.post(B+'signup',json={'name':'Spam9','username':'spam9','password':'senha2026spam'});ok(7,'limite de pedidos de acesso por aparelho',r.status_code==429,r.text)
ok(7,'recusar pedido apaga a conta',adm.post(B+'recusar',json={'id':sql("SELECT id FROM users WHERE username='spam0'")}).ok and sql("SELECT COUNT(*) FROM users WHERE username='spam0'")=='0')
# ---------- 12 CSRF / CORS
s2=requests.Session();s2.post(B+'login',json={'u':'duda','p':'pixel2026xy'})
ok(12,'gravação sem token anti-falsificação bloqueada',w(s2,'tarefas/x','set',{'a':1}).status_code==403)
r=adm.post(B+'write',json={'p':'tarefas/x','op':'set','data':{'a':1}},headers={'Origin':'https://site-malicioso.com'});ok(12,'pedido vindo de outro site bloqueado',r.status_code==403)
r=requests.options(B+'me',headers={'Origin':'https://x.com','Access-Control-Request-Method':'POST'});ok(12,'CORS fechado (sem liberar outros sites)',r.status_code==405 and 'Access-Control-Allow-Origin' not in r.headers)
r=adm.post(B+'write',data='p=tarefas/x',headers={'Content-Type':'application/x-www-form-urlencoded'});ok(12,'formulário de outro site (sem JSON) recusado',r.status_code in (403,415))
# ---------- 10/11 cabeçalhos e cookie
r=requests.get(H+'/');hd=r.headers
ok(10,'CSP sem script embutido (bloqueia XSS)',"script-src 'self';" in hd.get('Content-Security-Policy',''),hd.get('Content-Security-Policy'))
for k in ['X-Content-Type-Options','X-Frame-Options','Referrer-Policy','Permissions-Policy','Cross-Origin-Opener-Policy']: ok(10,f'cabeçalho {k}',k in hd)
ok(10,'não expõe versão do PHP','X-Powered-By' not in hd)
r=requests.post(B+'login',json={'u':'duda','p':'pixel2026xy'});sc=r.headers.get('Set-Cookie','')
ok(11,'cookie HttpOnly',re.search('httponly',sc,re.I));ok(11,'cookie SameSite=Strict',re.search('samesite=strict',sc,re.I))
ok(11,'cookie Secure/__Host- ativado em HTTPS (confere no código)','__Host-c3d' in open('hostinger/lib.php').read() and "'secure'=>https()" in open('hostinger/lib.php').read())
# ---------- 13 senha provisória
r=adm.post(B+'user_create',json={'username':'beto','name':'Beto','password':'provisoria2026','roles':['producao']});bid=r.json()['id']
beto,r=login('beto','provisoria2026');ok(13,'senha provisória: entra, mas é obrigado a trocar antes de usar',w(beto,'tarefas/b','set',{'titulo':'x'}).status_code==403)
ok(13,'troca a senha e libera',beto.post(B+'passwd',json={'atual':'provisoria2026','nova':'minhasenha2026'}).ok and w(beto,'tarefas/b','set',{'titulo':'x'}).ok)
adm.post(B+'user_reset',json={'id':bid,'password':'outraprov2026'});sql(f"UPDATE users SET temp_expires=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id='{bid}'")
_,r=login('beto','outraprov2026');ok(13,'senha provisória expira (48 h)',r.status_code==401 and 'expirou' in r.text,r.text)
ok(13,'redefinir senha derruba a sessão antiga da pessoa',beto.get(B+'sync&since=0').status_code==401)
# ---------- 6 monitoramento
ev=adm.get(B+'seg').json();tipos={e['tipo'] for e in ev['eventos']}
for t in ['login_falhou','login_bloqueado','negado','csrf_invalido','origem_invalida','pedido_acesso','acesso_aprovado','senha_redefinida','senha_provisoria_expirada','login_pendente']: ok(6,f'registra evento: {t}',t in tipos)
# ---------- 4b XSS armazenado: conteúdo malicioso guardado como texto
r=w(adm,'produtos/xss','set',{'nome':'<img src=x onerror=alert(1)>','tipo':'produto','status':'Ativo','bom':[],'est':{'pronto':0}});ok(5,'texto malicioso pode ser salvo como texto (será neutralizado na tela)',r.ok)
open('/tmp/x.html','w').write('<script>alert(1)</script>')
r=adm.post(B+'upload',files={'f':('foto.jpg',open('/tmp/x.html','rb'),'image/jpeg')});ok(5,'upload de HTML disfarçado de foto recusado',r.status_code==400,r.text)
# ---------- 16 dependências
js=open('hostinger/app.js').read()+open('hostinger/app.html').read()
ok(16,'nenhum script de terceiros carregado',not re.search(r'<script[^>]+src=["\']https?://',js) and 'cdnjs' not in js and 'unpkg' not in js)
# ---------- 19 revogar
s3,_=login('ana','senha2026ana')
ok(19,'encerrar todas as sessões funciona',adm.post(B+'revogar',json={}).ok and s3.get(B+'sync&since=0').status_code==401)
json.dump(R,open('/tmp/sec_result.json','w'))
print('\nTOTAL',sum(1 for x in R if x[2]),'/',len(R))
