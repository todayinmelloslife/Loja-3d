# Histórico do projeto — conversa com Claude (24/11/2026)

Resumo fiel, em ordem, de tudo que a Duda pediu e do que foi decidido e feito. Serve para quem continuar o projeto (Claude Code ou outra pessoa) entender o porquê de cada coisa.

## Contexto
- A Duda está criando uma marca própria de objetos/acessórios **Y2K impressos em 3D** (o nome ainda está em aberto; o produto que já faz sucesso é uma **prateleira em formato de cursor**). Vende só online: site, Instagram, marketplace, TikTok Shop.
- Sócio citado: **João** (vai ser administrador junto com ela).
- Ela já tinha feito antes um app de custos para biscoitos de Natal ("Custos da Fornada"), e o jeito de "dar baixa pela receita" desse app virou referência.

## 1. Pedido inicial
Ela mandou um prompt completo de ERP (21 seções: login e permissões, dashboard, produtos, ficha técnica 3D, estoque, compras, BOM, produção, receitas, custos e precificação, vendas, financeiro, qualidade, kits, logística, relatórios, tarefas, automações, segurança, design e regras) mais 6 referências visuais Y2K: pôster rosa e amarelo, celular flip rosa, "YAP TECH" com estrelas cromadas, monitor CRT, câmeras digitais com adesivos e um quarto com computador prata.
- **Feito:** app web de uma página com banco persistente (artifact do claude.ai), visual de "sistema operacional Y2K": janelas com barra azul, menu rosa, fonte pixel (Pixelify Sans) e estrela cromada.
- Começa **vazio de propósito**, sem dado inventado.
- Opinião dada para ela: o escopo é grande demais para uma marca que ainda não vendeu. É melhor começar por Estoque, Produtos, Produção, Pedidos e Custos.

## 2. Ajustes de uso
- **Filamento dentro da ficha do produto:** dá para cadastrar o filamento (cor e quantidade) sem sair do produto.
- **Fluxo igual ao do biscoito:** primeiro cadastra a matéria-prima, depois o produto escolhendo as cores e os gramas, e o botão **"Produzi"** desconta do estoque.
- **Chat da equipe, tela de login (usuário e senha) e página "Como usar".**
- Etiqueta: ela pediu e logo desistiu ("não quero não").

## 3. Simplificação (ela achou complicado demais)
- **Saíram:** SKU, status do produto, lote, data de compra, estoque mínimo, previsão de entrega na compra e DRE.
- **Ordem do menu:** Painel → Estoque e matéria-prima → Produtos → Pedidos → Produção 3D → Qualidade → Logística → Compras → Financeiro → Custos → Relatórios → Tarefas → Posts → Chat → Equipe → Configurações → Lixeira → Como usar.
- **Material:** tipo, material, marca, cor, quanto vem e preço pago.
- **Produto:**
  - sem preço obrigatório;
  - ela define o **% de lucro** e o sistema sugere o preço (botão "usar");
  - tem coleção, dimensões, peso, descrição e tempo de impressão.
- **Impressora:** ela só tem uma, então os dados dela (potência, valor, vida útil) ficam em Custos.
- **Compras:** têm o campo **"quem pagou"**, que alimenta o **Acerto de contas** (quanto cada um investiu, quanto já foi devolvido e o lucro a dividir).
- **Tarefas:** organizadas por **área** (Marketing, Financeiro…).
- **Novo: quadro de Posts estilo Trello**, com colunas, arrastar, checklist, imagens, comentários e calendário.
- **Arrastar para a esquerda joga no lixo**, com Desfazer, e existe uma página Lixeira com restaurar.

## 4. Análise de "está pronto?"
- **Bugs achados e corrigidos:**
  - "1.000" era lido como 1 g;
  - pagamento depois das 21 h caía no dia seguinte;
  - a foto do produto não aparecia depois do envio.
- **Novo:** itens de kit **feitos por ela** (cartinha, adesivo, embalagem), cada um com custo e estoque próprios.

## 5. Mais pedidos
- **kg ou g** na entrada de material (padrão 1 kg = 1 rolo).
- **"Quem pagou"** já no cadastro do material, que gera uma compra automática e soma no investimento de cada pessoa.
- **Variações de cor:** **um card por produto**, com bolinhas de cor e foto por cor. Cada cor tem o custo do próprio filamento.
- **Alerta automático** de filamento abaixo de **200 g**.
- **Chat com aviso:** som, contador no menu, opção de silenciar e aviso do navegador.
- **Estoque à prova de duas pessoas mexendo ao mesmo tempo:** trava por item. No teste, sem a trava se perdiam 20 de 50 lançamentos; com a trava, deu 50 de 50.
- **Sem preço diferente por canal** (ela recusou).
- **Pacote para a Hostinger** (PHP + MySQL), com instalador que cria o banco, e segurança feita no servidor.

## 6. Segurança (checklist dela, com 21+ itens)
- **Auditoria:** 16 falhas encontradas e corrigidas (detalhes em `hostinger/SEGURANCA.md`).
- **O que mudou:**
  - Argon2id;
  - sessão com versão (trocar a senha derruba as sessões antigas);
  - "encerrar todas as sessões";
  - senha provisória que expira em 48 h e obriga a troca;
  - validação por coleção no servidor, com o autor carimbado pelo servidor;
  - CSP sem script embutido (o app fica em `app.js`);
  - a biblioteca de Excel com falhas conhecidas foi removida (o Excel agora é gerado pelo próprio app);
  - checagem de origem;
  - login recusado sem HTTPS;
  - registro de atividade suspeita, com uma aba de Segurança;
  - proteção contra fórmula maliciosa em CSV.
- **Pedido dela no meio da auditoria:** "qualquer um pode criar conta". A solução: **pedido de acesso + aprovação por administrador** (ela ou o João), bloqueado no servidor.
- **Testes:** 76 de 76 ataques bloqueados, mais 3 testes no navegador.

## 7. Instalação
- Passo a passo enviado: PHP 8.2, SSL, criar o banco, subir o zip e rodar o `install.php`.
- **Onde ela parou:**
  - Criou o banco (aparece "Loja 3D" no phpMyAdmin).
  - Primeiro tentou `dudamelo.com.br/install.php` (com um L só) e deu "page does not exist". Depois o instalador abriu, mas ela viu a mensagem sobre o banco de dados.
- **Recomendações:**
  - Usar o **subdomínio `central.dudamello.com.br`**, para não quebrar o site pessoal dela (o `.htaccess` do sistema não pode ficar na raiz do portfólio).
  - Usar o nome do banco e do usuário **com o prefixo `u…_`**.
- Ela quer mandar tudo para o **GitHub** e de lá para a Hostinger. Veja `GITHUB-E-HOSTINGER.md`.

## Preferências da Duda (para quem continuar)
- Chamar de **Duda** ou **Dudinha**; português direto, sem jargão.
- Ela prefere simples a completo: se não sabe o que é um campo, ele não deve existir.
- Código compacto e sem comentários desnecessários.
- Quer opinião honesta, não concordância automática.

## 8. "O site não vai pro ar / não dá para entrar" (25/11/2026, Claude Code)
- O código do app e do login estava funcionando: instalação, login e painel testados de ponta a ponta (com HTTP e com HTTPS simulado) e os 76 ataques continuam bloqueados.
- O problema era no caminho até a Hostinger:
  - o repositório no GitHub estava vazio, então não existia o ramo `deploy` para a Hostinger puxar;
  - se a Hostinger puxar o `main` (ou o zip do projeto inteiro), o site fica dentro de `hostinger/` e a raiz do subdomínio não abre;
  - o instalador mostrava "não consegui conectar ao banco" para qualquer erro; agora diz o motivo exato (senha, nome sem prefixo `u…_`, usuário sem acesso, PHP antigo, pasta sem permissão);
  - o instalador se renomeava depois de usado, o que quebra o `git pull` da Hostinger; agora ele só trava (já existe `config.php`);
  - o `.htaccess` bloqueava `/.well-known/`, que a Hostinger usa para emitir e renovar o SSL; sem SSL o login é recusado. Liberado só o `acme-challenge`.
