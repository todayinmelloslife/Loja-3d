# Central da Fábrica 3D — contexto para o Claude Code

Leia também: `HISTORICO.md` (tudo que foi pedido e decidido, em ordem) e `GITHUB-E-HOSTINGER.md` (deploy).

ERP interno da marca de objetos Y2K impressos em 3D da Duda (Maria Eduarda Mello). Tudo em português do Brasil.
Fale com ela de forma direta e simples ("Duda" ou "Dudinha"); ela não é dev de backend — explique sem jargão.
Código compacto, limpo, sem comentários desnecessários.

## Arquivos
- `app/index.html` — o app inteiro (HTML+CSS+JS num arquivo só). É a **fonte da verdade**.
  Roda como artifact do claude.ai (usa `claude.use('db'|'user'|'assets'|'downloads')`).
- `hostinger/` — pacote para hospedar (PHP 8.2 + MySQL/MariaDB):
  - `app.html` + `app.js` — **gerados** por `build_hosted.py` a partir de `app/index.html` (não edite direto). Os scripts saem do HTML para a CSP ser `script-src 'self'`.
  - `index.php` — serve `app.html` com todos os cabeçalhos de segurança.
  - `SEGURANCA.md` — relatório de segurança (falhas, correções, riscos que continuam).
  - `shim.js` — implementa a mesma interface `claude.use(...)` falando com `api.php` (sync por polling a cada 2,5 s, cache local, listeners).
  - `api.php` + `lib.php` — login (bcrypt, sessão, CSRF, limite de tentativas), store de documentos JSON (`docs`), regras de leitura/escrita, permissões por papel no servidor, travas (`locks`), uploads privados.
  - `install.php` + `schema.sql` — instalador que cria tabelas e a conta dona. Grava a configuração em `../central-config.php` (fora da pasta do site, porque o "Reimplantar" da Hostinger apaga a pasta); fotos ficam em `../central-uploads/`. Se a configuração sumir, rodar o instalador com o usuário/senha da dona **reconecta** sem apagar nada. Instalado, ele se recusa a rodar.
  - `.htaccess` — HTTPS, rewrite `_blob/<id>` → `api.php?a=file`, bloqueio de arquivos internos, cabeçalhos/CSP.
- `build_hosted.py` — tira os scripts embutidos para `app.js`, injeta `shim.js` e troca `/_blob/` por `_blob/`. Uso: `python3 build_hosted.py app/index.html hostinger`.
- `testes/` — `mock.js` + `gerar_mock.py` (banco falso em memória → `testes/t.html`), Playwright `run4/5/7.py` (modo artifact), `e2e2.py`/`e2e3.py` (navegador contra o PHP real), `sec_test.py` (76 ataques), `router.php` (emula o .htaccess no `php -S`). Rode tudo da raiz do repositório.
- `.github/workflows/deploy.yml` — a cada push no `main`, gera `hostinger/app.*` e publica só `hostinger/` no ramo `deploy` (o que a Hostinger puxa).

## Como testar
```
python3 build_hosted.py app/index.html hostinger
mysqld_safe & ; criar banco `central` e usuário
php -S 127.0.0.1:8088 -t hostinger testes/router.php   # rode install.php uma vez (dono: duda / pixel2026xy nos testes)
python3 testes/sec_test.py   # 76 ataques
python3 testes/e2e2.py && python3 testes/e2e3.py   # navegador: XSS, aprovação, concorrência
```
Modo artifact: `python3 testes/gerar_mock.py` e depois `python3 testes/run4.py` (e 5, 7). Os testes usam `/usr/bin/google-chrome`; ajuste se precisar.

Regra de ouro: edite **`app/index.html`** (fonte única). Rode `build_hosted.py` antes de commitar ou deixe o Actions gerar.

## Modelo de dados (coleções)
materiais (estoque em g/un, custoUnit = custo médio ponderado), produtos (tipo produto|kit|insumo; `bom` = [{t:'m'|'p', id, q}]; `placas` = [{n, min, f:[{id,g}]}] (produto: filamento e tempo por placa, igual ao fatiador; ao salvar, a soma vira as linhas de filamento do `bom` ÷ `pecasImp` e o `tempoImpMin`); `est` {pronto,reservado,defeito,expo}; variações de cor = produtos irmãos com mesmo `grupo`, `corNome`, `corHex`), pedidos (itens {pid,q,pu,cu,res}), ordens (OPs), compras (pagoPor = quem pagou do bolso → acerto de contas), fin (lançamentos; só nível admin), movs (histórico), clientes, fornecedores, impressoras, tarefas, posts (quadro tipo Trello), chat, membros (roles por pessoa), config/geral, audit/<uid>/e, acessos/<uid>, locks.
Exclusão é lógica (`excluido:true`) → Lixeira. Toda gravação passa por `save()` que audita antes/depois.

## Regras de negócio decididas com a Duda
- Ordem do menu: Painel, Estoque e matéria-prima, Produtos, Pedidos, Produção 3D, Qualidade, Logística, Compras, Financeiro, Custos, Relatórios, Tarefas, Posts, Chat, Equipe, Configurações, Lixeira, Como usar.
- **Sem**: SKU, status do produto, lote, data de compra, estoque mínimo manual, previsão de entrega, DRE, preço diferente por canal.
- Material: tipo, material, marca, cor, quanto vem (kg ou g; 1 rolo = 1 kg), preço pago, **quem pagou** (gera uma compra "Recebida" → investimento da pessoa).
- Filamento < 200 g = alerta automático + aviso.
- Produto: nome, coleção, materiais por peça (cor + gramas), tempo de impressão; ela define **% de lucro** e o sistema sugere o preço (botão "usar"). Preço pode ficar vazio.
- Uma impressora só: dados dela ficam em Custos (impPot, impValor, impVida, impManut).
- Botão **Produzi** = baixa por receita (igual ao app de biscoitos "Custos da Fornada" dela).
- Kits aceitam produtos, materiais e "itens feitos por mim" (tipo insumo: cartinha, adesivo, embalagem).
- Acerto de contas: investiu (compras/despesas pagas pela pessoa + aportes) − devolvido (Reembolso a sócio) → depois divide o lucro.
- Arrastar para a esquerda = lixo (com Desfazer). Chat com aviso, som, contador e silenciar.
- Estoque usa trava por item (`locked()` + leitura fresca) — testado: 2 pessoas × 25 somas = 50 exatos.

## Segurança (não regredir)
- Senhas Argon2id (`hashpw`), mínimo 10. Sessão: cookie `__Host-` em HTTPS, HttpOnly, SameSite=Strict; 12 h parada / 7 dias no total; `users.sess_ver` + `ajustes.epoca` derrubam sessões.
- Todo POST: token CSRF no cabeçalho `X-CSRF` + origem conferida + só JSON. Login e cadastro recusados sem HTTPS.
- Escrita genérica passa por `pode_escrever` (nível + papel por coleção) e `valida_doc` (tipos, tamanhos, cores, fotos, links; autor carimbado no servidor).
- Cadastro público (`signup`) cria conta `pendente`; só `aprovar` (admin) libera. Senha provisória (`user_create`/`user_reset`) expira em 48 h e obriga troca (`must_change`).
- `eventos_seg` registra tudo que é suspeito; aba Equipe → Segurança.
- Front: todo dado do banco passa por `esc()`; nada de script de terceiros (Excel é gerado por `xlsxBlob`); exportação neutraliza fórmulas.

## Pendências conhecidas (por prioridade)
0. 2FA (aplicativo autenticador) para administradores; limite de velocidade nas gravações.
1. Editar pedido já confirmado com o mesmo produto em duas linhas pode bagunçar a reserva.
2. Sem integração com marketplace / TikTok Shop / nota fiscal (entrada manual).
3. Relatórios automáticos por e-mail não existem (só no painel).
4. No servidor, `sync` inicial traz audit de 90 dias e movs/chat de 1 ano — revisar se crescer muito.
5. Notificação fora da aba depende da permissão do navegador.
