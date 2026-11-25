import asyncio,json
from playwright.async_api import async_playwright
Q=lambda c:f"JSON.stringify([...__store.entries()].filter(e=>e[0].startsWith('{c}/')).map(e=>e[1]))"
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch(executable_path='/usr/bin/google-chrome');pg=await b.new_page(viewport={'width':1280,'height':900})
    errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    await pg.route('**/*fonts.g*',lambda r:r.abort())
    await pg.goto('file://'+__import__('os').path.abspath('testes/t.html')+'');await pg.wait_for_timeout(700)
    await pg.fill('#f_u','duda');await pg.fill('#f_p','pixel2026');await pg.fill('#f_p2','pixel2026');await pg.click('#loginf button');await pg.wait_for_timeout(1500)
    async def go(r): await pg.evaluate(f"location.hash='{r}'");await pg.wait_for_timeout(250)
    async def sub():
      await pg.click('#mok');await pg.wait_for_timeout(350)
      if await pg.query_selector('#merr'):
        t=await pg.text_content('#merr')
        if t: print('FORMERR',t)
    print('MENU',await pg.evaluate("[...document.querySelectorAll('.nav a')].map(a=>a.textContent.trim().replace(/^\\S+/,'')).join(' | ')"))
    await go('custos');await pg.fill('#f_impValor','2500');await pg.click('text=Salvar parâmetros');await pg.wait_for_timeout(300)
    await go('estoque');await pg.click('text=+ Novo material');print('QINI',await pg.input_value('#f_qIni'))
    await pg.fill('#f_nome','PLA');await pg.fill('#f_marca','Voolt');await pg.fill('#f_cor','Rosa');await pg.fill('#f_vIni','110');await sub()
    await go('produtos');await pg.click('text=+ Novo produto');await pg.fill('#f_nome','Prateleira Cursor');await pg.fill('#f_colecao','Desktop');await pg.fill('#f_tempoImpH','3')
    mid=json.loads(await pg.evaluate("JSON.stringify([...__store.keys()].filter(k=>k.startsWith('materiais/')).map(k=>k.split('/')[1]))"))[0]
    s=await pg.query_selector('#bom select');await s.select_option('m:'+mid);q=await pg.query_selector_all('#bom input');await q[0].fill('150');await pg.fill('#f_margem','50');await pg.wait_for_timeout(300)
    print('COST',(await pg.text_content('#costbox'))[:400])
    await pg.click('[data-act=usarSug]');await pg.wait_for_timeout(150);print('PRECO',await pg.input_value('#f_preco'))
    await pg.screenshot(path='p4.png');await sub()
    await pg.click('button:has-text("Produzi") >> nth=0');await pg.fill('#f_q','2');await sub()
    # compra paga pela Duda
    await go('compras');await pg.click('text=Fornecedores');await pg.click('text=+ Novo fornecedor');await pg.fill('#f_nome','Loja 3D');await sub()
    await pg.click('text=Pedidos de compra');await pg.click('text=+ Nova compra')
    fid=json.loads(await pg.evaluate("JSON.stringify([...__store.keys()].filter(k=>k.startsWith('fornecedores/')).map(k=>k.split('/')[1]))"))[0]
    await pg.select_option('#f_fornId',fid);await pg.select_option('#f_pagoPor','u_owner');await pg.check('[name=pago]')
    s=await pg.query_selector('#bom select');await s.select_option(mid);q=await pg.query_selector_all('#bom input');await q[0].fill('1000');await q[1].fill('100');await sub()
    await go('financeiro');await pg.click('text=Acerto de contas');await pg.wait_for_timeout(200)
    print('ACERTO',(await pg.text_content('#view'))[:420])
    await pg.click('[data-act=acerto][data-tp=dev]');await pg.wait_for_timeout(200);await sub()
    print('ACERTO2',(await pg.text_content('#view'))[:200])
    # posts
    await go('posts');await pg.click('text=+ Novo post');await pg.fill('#f_titulo','Reels prateleira cursor');await pg.select_option('#f_rede','Instagram');await pg.fill('#f_ckNew','Gravar');await sub()
    await pg.click('[data-act=postMove][data-dir="1"]');await pg.wait_for_timeout(300)
    src=pg.locator('.post').first;dst=pg.locator('[data-drop="Agendado"]')
    await src.drag_to(dst);await pg.wait_for_timeout(400)
    print('POST',[ (x['titulo'],x['status'],x['check']) for x in json.loads(await pg.evaluate(Q('posts')))])
    await pg.screenshot(path='posts.png')
    await go('tarefas');await pg.click('text=+ Novo');print('AREAS',await pg.evaluate("[...document.querySelectorAll('#f_tipo option')].map(o=>o.textContent).join(',')"));await pg.fill('#f_titulo','Fotos da coleção');await sub()
    for r in ['painel','estoque','produtos','pedidos','producao','qualidade','logistica','compras','financeiro','custos','relatorios','tarefas','posts','chat','equipe','config','ajuda']:
      await go(r);n=len(await pg.query_selector_all('.tabs a'))
      for i in range(max(n,1)):
        if n: await pg.locator('.tabs a').nth(i).click();await pg.wait_for_timeout(120)
        t=await pg.evaluate("document.querySelector('#view')?.innerText.includes('Erro ao montar')?document.querySelector('#view').innerText.slice(0,200):''")
        if t: print('VIEWERR',r,i,t)
    print('ERR',errs)
    await b.close()
asyncio.run(main())
