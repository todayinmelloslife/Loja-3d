# Do GitHub para a Hostinger

Repositório: **https://github.com/todayinmelloslife/Loja-3d**

## Como o repositório está organizado
- Ramo **`main`**: o projeto inteiro (código-fonte, testes, documentação). É onde se trabalha.
- Ramo **`deploy`**: **só** os arquivos que vão para o servidor (o que está em `hostinger/`, já na raiz). É criado e atualizado sozinho pela automação em `.github/workflows/deploy.yml` toda vez que algo entra no `main`. Testes, anotações e senhas de teste **nunca** vão para o `deploy`.

> Importante: a Hostinger tem que puxar o ramo **`deploy`**, não o `main`. No `main` o site fica dentro da pasta `hostinger/`, então a raiz do subdomínio fica sem `index.php` e o site "não abre".

## 1. Conferir o GitHub (uma vez)
1. Em **Actions**, confira que o "Publicar ramo deploy" rodou com sucesso (bolinha verde). Vai aparecer o ramo `deploy` na lista de ramos.
2. Se o Actions der erro de permissão: Settings → Actions → General → Workflow permissions → **Read and write** → Salvar → Actions → "Publicar ramo deploy" → **Run workflow**.

## 2. Ligar a Hostinger ao GitHub
1. Crie o subdomínio: hPanel → **Domínios → Subdomínios** → `central`. Ele ganha uma pasta própria, por exemplo `public_html/central`.
2. **A pasta precisa estar vazia.** Se você já extraiu algum zip lá (ou a Hostinger criou um `default.php`), apague tudo dela antes. O banco de dados não é afetado.
3. hPanel → **Avançado → Git**:
   - **Repositório:**
     - repositório **público**: `https://github.com/todayinmelloslife/Loja-3d.git`
     - repositório **privado**: use o endereço SSH `git@github.com:todayinmelloslife/Loja-3d.git`. A própria tela da Hostinger mostra uma **chave SSH**: copie e cole em GitHub → Loja-3d → Settings → **Deploy keys** → Add deploy key (não precisa marcar "write").
   - **Ramo:** `deploy`
   - **Diretório:** a pasta do subdomínio (ex.: `central`)
   - Clique em **Criar** e depois em **Implantar**.
4. (Opcional) Na mesma tela, ative a **implantação automática** e cole o webhook em GitHub → Settings → Webhooks. Aí cada mudança enviada ao GitHub entra no site sozinha.

## 3. Instalar (uma vez)
1. Ative o **SSL** do `central.dudamello.com.br` (Segurança → SSL). Sem SSL o login é recusado de propósito.
2. PHP **8.2+** (Avançado → Configuração do PHP).
3. Crie o banco em hPanel → **Bancos de dados → Gerenciamento** e anote os nomes **completos** (com o prefixo `u123456789_`) do banco e do usuário.
4. Abra `https://central.dudamello.com.br/install.php`:
   - Servidor: `localhost`
   - Nome do banco e usuário **completos, com o prefixo** `u123456789_`
   - A senha do banco
   - Sua conta de administradora
5. Se der erro, o instalador agora diz o motivo exato (senha do banco errada, nome do banco errado, usuário sem acesso ao banco, PHP antigo…).

O instalador cria o `config.php` no servidor. Esse arquivo **não** está no Git (e nem pode estar), e as próximas implantações não mexem nele nem na pasta `uploads/`.

## Atualização automática (recomendado)
1. hPanel → **Avançado → Git** → no repositório da lista, clique em **Implantação automática** (Auto Deployment) e copie a **URL do webhook**.
2. GitHub → Loja-3d → **Settings → Webhooks → Add webhook**:
   - Payload URL: a URL copiada
   - Content type: `application/json`
   - Evento: **Just the push event**
   - **Add webhook**
3. Pronto: toda mudança que entra no `main` vai para o site sozinha em 1 ou 2 minutos.

A configuração (`central-config.php`) e as fotos (`central-uploads/`) ficam **fora** da pasta do site, então implantar ou reimplantar não apaga mais nada. Se algum dia aparecer "sistema não instalado", abra `install.php` e use os mesmos dados do banco e o mesmo usuário/senha de administradora: ele só reconecta.

## Atualizar depois
Mudou algo no `app/index.html` (ou no PHP)? Envie para o `main` → a automação monta o `deploy` → na Hostinger clique em **Implantar** (ou deixe automático).
