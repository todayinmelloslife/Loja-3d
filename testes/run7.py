import asyncio,json
from playwright.async_api import async_playwright
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch(executable_path='/usr/bin/google-chrome');pg=await b.new_page(viewport={'width':1200,'height':900})
    errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    await pg.route('**/*fonts.g*',lambda r:r.abort())
    await pg.goto('file://'+__import__('os').path.abspath('testes/t.html')+'');await pg.wait_for_timeout(700)
    await pg.fill('#f_u','duda');await pg.fill('#f_p','pixel2026');await pg.fill('#f_p2','pixel2026');await pg.click('#loginf button');await pg.wait_for_timeout(1500)
    async def go(r): await pg.evaluate(f"location.hash='{r}'");await pg.wait_for_timeout(250)
    async def sub():
      await pg.click('#mok');await pg.wait_for_timeout(400)
      if await pg.query_selector('#merr'):
        t=await pg.text_content('#merr')
        if t: print('FORMERR',t)
    await go('estoque')
    for cor,hexv,preco,quem in [('Rosa','#ff4fb3','110','u_owner'),('Azul','#3b6cff','95','')]:
      await pg.click('text=+ Novo material');print('UNIT default',await pg.input_value('[name=qIni_u]'),await pg.input_value('#f_qIni'))
      await pg.fill('#f_nome','PLA');await pg.fill('#f_cor',cor);await pg.evaluate(f"document.querySelector('#f_hex').value='{hexv}'");await pg.fill('#f_vIni',preco);await pg.select_option('#f_pagoPor',quem);await sub()
    await go('estoque');print('KPI',(await pg.text_content('.kpis')))
    mats=json.loads(await pg.evaluate("JSON.stringify([...__store.entries()].filter(e=>e[0].startsWith('materiais/')).map(e=>[e[0].split('/')[1],e[1].cor,e[1].estoque,e[1].custoUnit]))"));print('MATS',mats)
    rosa=[m for m in mats if m[1]=='Rosa'][0][0];azul=[m for m in mats if m[1]=='Azul'][0][0]
    # movimentar 500 g
    await pg.click(f'[data-act=movNew][data-id="{rosa}"]');await pg.select_option('#f_tipo','saida');await pg.fill('#f_q','850');await pg.select_option('[name=q_u]','g');await sub()
    await go('estoque');print('ALERTA', await pg.evaluate("document.body.innerText.includes('acabando')"), (await pg.text_content('.bell')))
    await pg.click(f'[data-act=movNew][data-id="{rosa}"]');await pg.fill('#f_q','1');await pg.fill('#f_v','105');await sub()
    print('ROSA after +1kg',json.loads(await pg.evaluate(f"JSON.stringify(__store.get('materiais/{rosa}'))"))['estoque'])
    # produto com cor
    await go('produtos');await pg.click('text=+ Novo produto');await pg.fill('#f_nome','Prateleira Cursor')
    s=await pg.query_selector('#bom select');await s.select_option('m:'+rosa);q=await pg.query_selector_all('#bom input');await q[0].fill('150');await sub()
    pid=await pg.evaluate("[...__store.entries()].find(e=>e[1].nome==='Prateleira Cursor')[0].split('/')[1]")
    await pg.click(f'article[data-id="{pid}"] .cb b');await pg.wait_for_timeout(300);await pg.click('[data-act=varNew]');await pg.wait_for_timeout(600)
    print('NEW VAR modal',await pg.text_content('.mwin .tb b'))
    s=await pg.query_selector('#bom select');await s.select_option('m:'+azul);await pg.wait_for_timeout(200);await sub()
    await go('produtos');print('CARDS',await pg.evaluate("document.querySelectorAll('article.card').length"),'DOTS',await pg.evaluate("document.querySelectorAll('article.card .dot').length"))
    prods=json.loads(await pg.evaluate("JSON.stringify([...__store.values()].filter(x=>x.nome==='Prateleira Cursor').map(x=>[x.corNome,x.corHex,x.grupo]))"));print('VARS',prods)
    dots=await pg.query_selector_all('article.card .dot');await dots[1].click();await pg.wait_for_timeout(200);print('SEL',await pg.text_content('article.card .dots2'))
    await pg.screenshot(path='cores.png',clip={'x':240,'y':0,'width':700,'height':560})
    # chat notificação
    await pg.evaluate("__push('chat/zz1',{uid:'u2',t:'Chegou o rolo novo!',em:new Date(Date.now()+1000).toISOString()})");await pg.wait_for_timeout(400)
    print('NOTIF',await pg.evaluate("!document.querySelector('#notif').hidden&&document.querySelector('#notif').innerText"),'BADGE',await pg.evaluate("document.querySelector('.nav a[href=\"#chat\"] .c')?.textContent"))
    await go('chat');print('BADGE after open',await pg.evaluate("document.querySelector('.nav a[href=\"#chat\"] .c')?.textContent||'none'"))
    await pg.click('[data-act=mudo]');await pg.wait_for_timeout(200);await go('painel')
    await pg.evaluate("document.querySelector('#notif').hidden=true;__push('chat/zz2',{uid:'u2',t:'Outra',em:new Date(Date.now()+2000).toISOString()})");await pg.wait_for_timeout(400)
    print('MUTED notif shown?',await pg.evaluate("!document.querySelector('#notif').hidden"),'BADGE',await pg.evaluate("document.querySelector('.nav a[href=\"#chat\"] .c')?.textContent"))
    await go('pedidos');await pg.click('text=+ Novo pedido');print('PED opts',await pg.evaluate("[...document.querySelectorAll('[name=pi_p] option')].map(o=>o.textContent).join('|')"))
    print('ERR',errs);await b.close()
asyncio.run(main())
