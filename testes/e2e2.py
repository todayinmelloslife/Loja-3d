import asyncio,openpyxl
from playwright.async_api import async_playwright
U='http://127.0.0.1:8088/'
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch(executable_path='/usr/bin/google-chrome');probs=[]
    async def ctx(w=1200):
      c=await b.new_context(viewport={'width':w,'height':900},accept_downloads=True);pg=await c.new_page()
      pg.on('dialog',lambda d:(probs.append('XSS DIALOG: '+d.message),asyncio.ensure_future(d.dismiss())))
      pg.on('pageerror',lambda e:probs.append('pageerror '+str(e)))
      pg.on('console',lambda m:probs.append('console '+m.text) if m.type=='error' and 'fonts' not in m.text and 'ERR_TUNNEL' not in m.text and '401' not in m.text and '403' not in m.text else None)
      await pg.route('**/*fonts.g*',lambda r:r.abort());await pg.goto(U);await pg.wait_for_selector('#lu');return pg
    async def entrar(pg,u,pw):
      await pg.fill('#lu',u);await pg.fill('#lp',pw);await pg.click('#lf button')
    async def go(pg,r): await pg.evaluate(f"location.hash='{r}'");await pg.wait_for_timeout(350)
    d=await ctx();await entrar(d,'duda','pixel2026xy');await d.wait_for_selector('.nav')
    for r in ['produtos','estoque','pedidos','producao','relatorios','custos','lixeira']: await go(d,r)
    await go(d,'estoque');await d.click('text=Produtos acabados');await d.wait_for_timeout(300)
    print('XSS: nome malicioso aparece como texto?',await d.evaluate("document.body.innerText.includes('<img src=x onerror=alert(1)>')"),'| imagens injetadas:',await d.evaluate("document.querySelectorAll('img[src=\"x\"]').length"))
    # pedir acesso pela tela
    n=await ctx(390);await n.click('#lalt');await n.fill('#ln','Joana Teste');await n.fill('#lu','joana');await n.fill('#lp','joana2026abc');await n.fill('#lp2','joana2026abc');await n.click('#lf button');await n.wait_for_timeout(600)
    print('PEDIDO:',(await n.text_content('.wb'))[:70])
    await n.click('#lv');await entrar(n,'joana','joana2026abc');await n.wait_for_timeout(700);print('ANTES DE APROVAR:',await n.text_content('#le'))
    # admin aprova
    await d.reload();await d.wait_for_selector('.nav');await d.wait_for_timeout(1500)
    print('AVISO ADMIN:',await d.evaluate("!document.querySelector('#notif').hidden&&document.querySelector('#notif').innerText"),'| alerta:',await d.text_content('.bell'))
    await go(d,'equipe');await d.click('text=Pedidos de acesso');await d.click('[data-act=aprovar]');await d.check('[name=role_vendas]');await d.click('#mok');await d.wait_for_timeout(900)
    await entrar(n,'joana','joana2026abc');await n.wait_for_selector('.nav',timeout=10000);print('JOANA ENTROU:',await n.text_content('h1'))
    # admin cria conta com senha provisória
    await go(d,'equipe');await d.click('.tabs a >> nth=0');await d.click('text=+ Adicionar pessoa');await d.fill('#f_name','Caio');await d.fill('#f_username','caio');await d.fill('#f_password','provisoria99');await d.check('[name=role_estoque]');await d.click('#mok');await d.wait_for_timeout(900);await d.keyboard.press('Escape')
    c=await ctx();await entrar(c,'caio','provisoria99');await c.wait_for_selector('#lp2',timeout=8000);print('TROCA OBRIGATÓRIA:',await c.text_content('.tb b'))
    await c.fill('#lp','caiosenha2026');await c.fill('#lp2','caiosenha2026');await c.click('#lf button');await c.wait_for_selector('.nav',timeout=10000);print('CAIO ENTROU:',await c.text_content('h1'))
    # segurança
    await go(d,'equipe');await d.click('text=Segurança');await d.wait_for_timeout(900);print('ABA SEGURANÇA:',(await d.text_content('#view'))[60:260])
    await d.screenshot(path='/tmp/seg.png')
    # Excel
    await go(d,'relatorios')
    async with d.expect_download() as dl:
      await d.click('[data-act=exp][data-fmt=xlsx] >> nth=0')
    dv=await dl.value;f='/tmp/rel.xlsx';await dv.save_as(f);import zipfile;print('ZIP ok:',zipfile.ZipFile(f).testzip() is None);wb=openpyxl.load_workbook(f);ws=wb.active;print('EXCEL abre:',ws.max_row,'linhas, cabeçalho',[x.value for x in ws[1]])
    print('PROBLEMAS:',probs[:10]);await b.close()
asyncio.run(main())
