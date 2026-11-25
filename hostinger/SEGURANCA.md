# Relatório de segurança — Central da Fábrica 3D

Data: 24/11/2026 · Escopo: pacote da Hostinger (PHP + MySQL) e o app do claude.ai.

## Resumo
- **Falhas encontradas na auditoria: 16.** Todas corrigidas no pacote da Hostinger.
- **Testes automáticos de ataque: 76 de 76 bloqueados**, mais 3 testes no navegador (XSS armazenado, fluxo de aprovação, duas pessoas ao mesmo tempo), todos passando.
- **Riscos que continuam** (não dá para resolver só com código): estão no fim deste relatório, junto com o que **você** precisa fazer.

## Falhas encontradas e corrigidas

| # | Falha | Gravidade | Correção |
|---|---|---|---|
| 1 | Resultado da ordem de produção (aprovadas/reprovadas) ia para a tela sem neutralizar → XSS possível para quem já tinha acesso de gravação | Média | Valor agora é convertido para número antes de aparecer |
| 2 | A política de segurança do navegador (CSP) permitia scripts embutidos, ou seja, não havia segunda barreira se algum XSS escapasse | Média | App separado em `app.js`; CSP agora é `script-src 'self'` (nenhum script embutido ou de outro site roda) |
| 3 | Biblioteca de Excel (SheetJS 0.18.5) carregada de CDN, com vulnerabilidades conhecidas (CVE-2023-30533, CVE-2024-22363) | Média | Biblioteca removida; o app gera o Excel sozinho. Zero scripts de terceiros |
| 4 | Servidor aceitava qualquer dado com permissão: texto em campo de número, cor com CSS, caminho de arquivo falso, valor financeiro negativo | Alta | Validação por coleção no servidor (tipos, tamanhos, cores, fotos, links, funções) |
| 5 | Dava para gravar um registro fingindo ser outra pessoa (campos "criado por"/"atualizado por" vinham do navegador) | Alta | Autor e data agora são carimbados **pelo servidor** |
| 6 | Senhas em bcrypt, mínimo de 8 caracteres | Baixa | **Argon2id** (padrão atual recomendado), mínimo de 10, bloqueio de senhas óbvias; hashes antigos são atualizados no próximo login |
| 7 | Trocar ou redefinir senha não derrubava as sessões já abertas | Alta | Cada conta tem uma "versão de sessão": mudou a senha, todas as sessões antigas caem |
| 8 | Não existia "encerrar todas as sessões" para o caso de vazamento | Média | Botão em Equipe → Segurança |
| 9 | Senha provisória criada pela administradora nunca expirava e não obrigava troca | Média | Expira em **48 h** e obriga a pessoa a criar a própria senha antes de usar qualquer coisa |
| 10 | Nenhum registro de atividade suspeita | Média | Registro de segurança (senhas erradas, travas, tentativas sem permissão, ataques bloqueados, aprovações, trocas de senha) + tela com resumo das últimas 24 h |
| 11 | Erros internos podiam mostrar detalhes do PHP/banco, dependendo da configuração da Hostinger | Média | Erros genéricos para quem usa; detalhe só no log do servidor |
| 12 | Cabeçalhos de segurança dependiam do `.htaccess` (se o módulo estivesse desligado, sumiam) | Baixa | `index.php` envia os cabeçalhos sempre |
| 13 | Proteção contra pedido forjado (CSRF) só por token | Baixa | Também confere a **origem** do pedido; aceita só JSON |
| 14 | Se o redirecionamento para HTTPS falhasse, o login funcionava sem criptografia | Alta | O servidor **recusa login sem HTTPS** (exceto no computador de teste) + HSTS |
| 15 | Relatórios exportados podiam carregar fórmulas maliciosas para o Excel (CSV injection) | Baixa | Células que começam com `= + - @` são neutralizadas |
| 16 | Cookie sem prefixo `__Host-` e sessão sem prazo máximo | Baixa | Cookie `__Host-` em HTTPS, HttpOnly, SameSite=Strict, Secure; sessão cai com 12 h parada ou 7 dias no total |

Também entrou o **pedido de acesso com aprovação**: quem se cadastra fica bloqueado no servidor até uma administradora aprovar. São no máximo 3 pedidos por hora por aparelho e 30 pendentes no total.

## Checklist, item por item

| Item | Situação | Como foi testado |
|---|---|---|
| 1. Autenticação adequada | ✅ Argon2id, mensagem igual para usuário inexistente e senha errada, novo ID de sessão no login, trava após 8 erros (15 min) | 5 testes |
| 2. Banco protegido | ✅ Todas as consultas são parametrizadas (prepared statements, sem emulação); `config.php` bloqueado e não imprime nada nem se for executado; erros sem detalhes | 4 testes de SQL injection e vazamento |
| 3. HTTPS obrigatório | ✅ Redirecionamento 301 + HSTS + login recusado sem HTTPS | 1 teste (o redirecionamento depende do SSL ativo na Hostinger) |
| 4. Validação no servidor | ✅ Por coleção: tipos, tamanhos, faixas, cores, fotos, links, funções; autor carimbado no servidor | 9 testes |
| 5. XSS | ✅ Tudo que vem do banco é neutralizado + CSP sem scripts embutidos + upload confere o tipo real do arquivo (HTML disfarçado de foto é recusado) | Nome de produto `<img onerror=…>` aparece como texto, 0 imagens injetadas, 0 alertas |
| 6. Monitorar atividade suspeita | ✅ Registro + aviso + aba Segurança | 10 tipos de evento conferidos |
| 7. Rotas de admin restritas | ✅ Criar conta, aprovar, recusar, redefinir senha, desativar, ver log e encerrar sessões: só administradores, checado no servidor | 17 testes (vendedora tentando cada uma) |
| 10. Cabeçalhos de segurança | ✅ CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP/CORP, HSTS; esconde a versão do PHP | 7 testes |
| 11. Cookies de sessão | ✅ HttpOnly, SameSite=Strict, Secure + `__Host-` em HTTPS | 3 testes |
| 12. CSRF | ✅ Token por sessão + checagem de origem + só JSON | 4 testes |
| 13. Links de redefinição expiram | ✅ Não há envio de link por e-mail; a redefinição é por **senha provisória que expira em 48 h** e obriga a troca. Redefinir derruba a sessão antiga | 4 testes |
| 16. Dependências vulneráveis | ✅ Nenhuma biblioteca de terceiros no código; só as fontes do Google (sem script) | 1 teste |
| 17. Arquivos expostos | ✅ `config.php`, `lib.php`, `schema.sql`, `app.html`, `.md`, `.htaccess`, `uploads/` e o instalador usado bloqueados; listagem de pastas desligada | 8 testes |
| 19. Revogar chaves comprometidas | ✅ Não existe chave de API no sistema. Para sessões: "Encerrar todas as sessões". Para a senha do banco: veja abaixo | 1 teste |
| Chaves de API escondidas | ✅ Nenhum segredo vai para o navegador | — |
| CORS restrito | ✅ Nenhum outro site pode chamar a API (sem cabeçalhos CORS; OPTIONS recusado; origem conferida) | 1 teste |
| 20. Testar proteções | ✅ `testes/sec_test.py` (76 ataques) + testes no navegador | — |

## O que continua de risco (honestidade)
1. **Não tem verificação em duas etapas (2FA).** Se alguém descobrir a senha de uma administradora, entra. Mitigação: senha forte e única, a trava de tentativas e a aba Segurança. Dá para adicionar 2FA por aplicativo autenticador numa próxima versão.
2. **Limite de velocidade só no login e no pedido de acesso.** Uma pessoa já logada poderia gravar muita coisa em sequência; tudo fica registrado com o nome dela na auditoria.
3. **App do claude.ai:** lá, quem você convidou pelo Compartilhar como Contributor consegue ler os dados operacionais, mesmo antes de ser aprovado na tela. O controle real é a lista do Compartilhar. Na Hostinger isso **não** acontece: sem aprovação, o servidor não entrega nada.
4. **Segurança da hospedagem:** atualizações do PHP, do servidor e backups dependem da Hostinger e das configurações que você escolher.

## O que você precisa fazer
- [ ] Ativar o **SSL** no hPanel **antes** de instalar.
- [ ] PHP **8.2+**.
- [ ] Senha do banco **forte**, e esse usuário do banco só com acesso a este banco.
- [ ] Dar a função **Administrador** só a quem precisa (você e o João).
- [ ] Olhar **Equipe → Segurança** uma vez por semana. Muitas senhas erradas ou tentativas sem permissão = investigar.
- [ ] Baixar o backup toda sexta (Configurações → Baixar backup).

### Se a senha do banco vazar
1. hPanel → Bancos de dados → trocar a senha do usuário do banco.
2. Gerenciador de arquivos → editar `config.php` → trocar o valor de `'pass'` pela senha nova.
3. Equipe → Segurança → **Encerrar todas as sessões**.

### Se a senha de alguém vazar
Equipe → a pessoa → **Redefinir senha** (as sessões dela caem na hora). Se não souber de quem é: **Encerrar todas as sessões**.
