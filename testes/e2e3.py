import asyncio,json
from playwright.async_api import async_playwright
U='http://127.0.0.1:8088/'
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch(executable_path='/usr/bin/google-chrome');probs=[]
    async def nova(u,pw):
      c=await b.new_context(viewport={'width':1200,'height':900});pg=await c.new_page();pg.on('pageerror',lambda e:probs.append(str(e)))
      await pg.route('**/*fonts.g*',lambda r:r.abort());await pg.goto(U);await pg.wait_for_selector('#lu');await pg.fill('#lu',u);await pg.fill('#lp',pw);await pg.click('#lf button');await pg.wait_for_selector('.nav');return pg
    async def go(pg,r): await pg.evaluate(f"location.hash='{r}'");await pg.wait_for_timeout(350)
    d=await nova('duda','pixel2026xy')
    await go(d,'estoque');await d.click('text=+ Novo material');await d.fill('#f_nome','PETG');await d.fill('#f_cor','Lilás');await d.fill('#f_vIni','120');await d.click('#mok');await d.wait_for_timeout(900)
    await go(d,'produtos');await d.click('text=+ Novo produto');await d.fill('#f_nome','Porta-caneta Disquete')
    mid=await d.evaluate("[...document.querySelectorAll('#bom select option')].find(o=>o.textContent.includes('PETG')).value");s=await d.query_selector('#bom select');await s.select_option(mid);q=await d.query_selector_all('#bom input');await q[0].fill('100');await d.click('#mok');await d.wait_for_timeout(1200)
    bt=await nova('beto2','betosenha2026');await go(bt,'produtos');await go(d,'produtos');await d.wait_for_timeout(3000)
    async def produzir(pg,n):
      await pg.click('article:has-text("Disquete") button:has-text("Produzi")');await pg.fill('#f_q',str(n));await pg.click('#mok')
    await asyncio.gather(produzir(d,2),produzir(bt,3));await d.wait_for_timeout(5000)
    st=await d.evaluate("fetch('api.php?a=sync&since=0').then(r=>r.json()).then(j=>j.docs.filter(x=>/^(materiais|produtos)\\//.test(x.p)&&/PETG|Disquete/.test(x.d.nome)).map(x=>[x.d.nome,x.d.estoque,x.d.est&&x.d.est.pronto]))")
    print('2 PESSOAS AO MESMO TEMPO (2+3 peças × 100 g):',st,'| esperado: PETG 500 g e 5 peças');print('PROBLEMAS',probs);await b.close()
asyncio.run(main())
