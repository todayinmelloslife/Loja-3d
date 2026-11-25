# Central da Fábrica 3D — como colocar na Hostinger

Leva uns 15 minutos. Você só precisa do hPanel da Hostinger aberto.

## 1. Ajuste o PHP (1 min)
hPanel → **Avançado → Configuração do PHP** → escolha **PHP 8.2** ou mais novo → Salvar.

## 2. Crie o banco de dados (3 min)
hPanel → **Bancos de dados → Gerenciamento** (MySQL):
- Nome do banco: por exemplo `central`
- Usuário: por exemplo `duda`
- Senha: crie uma forte e **anote**

A Hostinger coloca um prefixo na frente (tipo `u123456789_central`). Anote os nomes **completos** que aparecerem na lista.

## 3. Envie os arquivos (3 min)
hPanel → **Arquivos → Gerenciador de arquivos** → entre em `public_html` (ou numa subpasta, ex.: `public_html/central`).
- **Enviar** o arquivo `central-hostinger.zip`
- Clique com o botão direito no zip → **Extrair** → depois apague o zip

Confira que o arquivo **.htaccess** está lá (ele protege o sistema). Se não aparecer, ative "mostrar arquivos ocultos".

## 4. Ligue o HTTPS (1 min)
hPanel → **Segurança → SSL** → instale o SSL gratuito no seu domínio. Sem isso o navegador não protege a senha.

## 5. Instale (2 min)
Abra no navegador: `https://seudominio.com.br/install.php` (ou `/central/install.php`).
- Preencha os dados do banco do passo 2 (servidor: `localhost`)
- Crie **seu** usuário e senha de administradora (10+ caracteres, letras e números)
- Clique em **Instalar**

O instalador cria todas as tabelas e sua conta. Depois disso ele **fica travado** (ninguém consegue rodar de novo, porque o `config.php` já existe).

## 6. Use
Abra `https://seudominio.com.br/` e entre com seu usuário e senha.
Para a equipe, dois jeitos:
- **A pessoa pede acesso** na tela de login → você recebe um aviso → **Equipe → Pedidos de acesso** → Aprovar (escolhendo as funções) ou Recusar. Ninguém entra sem aprovação.
- **Você cria a conta**: Equipe → + Adicionar pessoa → nome, usuário, senha provisória e funções. A senha provisória vale 48 h, e no primeiro acesso a pessoa é obrigada a criar a própria.

Para o João também aprovar pedidos, dê a ele a função **Administrador**.

---

## Segurança
O relatório completo está em **SEGURANCA.md**: o que foi feito, como foi testado e o checklist do que é com você.

## Backup
- A Hostinger faz backup automático (hPanel → **Arquivos → Backups**).
- No sistema: **Configurações → Baixar backup** guarda uma cópia sua. Faça toda sexta.

## Atualizar para uma versão nova
Substitua só `index.php`, `app.html`, `app.js`, `shim.js`, `api.php` e `lib.php`. **Nunca apague** `config.php` nem a pasta `uploads/`.

## Deu problema?
- **Erro de banco no instalador**: a mensagem agora diz o motivo (senha errada, banco com outro nome, usuário sem acesso). Quase sempre é faltar o prefixo: use os nomes completos (`u123…_central`).
- **"Use o endereço com https://"** ou aviso de site inseguro: o SSL do domínio ainda não está ativo (Segurança → SSL).
- **"Muitas tentativas. Espere 15 minutos."**: o login trava por 15 min depois de 8 erros seguidos. Espere e tente de novo.
- **Página em branco / erro 500**: confira se o PHP está em 8.2+ (passo 1).
- **Fotos não aparecem**: confira se o `.htaccess` foi extraído junto.
