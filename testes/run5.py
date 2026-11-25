import asyncio,json
from playwright.async_api import async_playwright
async def main():
  async with async_playwright() as p:
    b=await p.chromium.launch(executable_path='/usr/bin/google-chrome');pg=await b.new_page(viewport={'width':1100,'height':800})
    errs=[];pg.on('pageerror',lambda e:errs.append(str(e)))
    await pg.route('**/*fonts.g*',lambda r:r.abort())
    await pg.goto('file://'+__import__('os').path.abspath('testes/t.html')+'');await pg.wait_for_timeout(700)
    await pg.fill('#f_u','duda');await pg.fill('#f_p','pixel2026');await pg.fill('#f_p2','pixel2026');await pg.click('#loginf button');await pg.wait_for_timeout(1500)
    async def go(r): await pg.evaluate(f"location.hash='{r}'");await pg.wait_for_timeout(250)
    await go('estoque')
    for n in ['PLA','PETG']:
      await pg.click('text=+ Novo material');await pg.fill('#f_nome',n);await pg.fill('#f_cor','Rosa');await pg.fill('#f_vIni','100');await pg.click('#mok');await pg.wait_for_timeout(300)
    async def swipe(sel,dist):
      bx=await pg.locator(sel).first.bounding_box();y=bx['y']+bx['height']/2;x=bx['x']+bx['width']-30
      await pg.mouse.move(x,y);await pg.mouse.down()
      for i in range(1,11): await pg.mouse.move(x-dist*i/10,y);await pg.wait_for_timeout(15)
      await pg.mouse.up();await pg.wait_for_timeout(500)
    await swipe('tr[data-sw]',60)
    print('SHORT', await pg.evaluate("document.querySelectorAll('tr[data-sw]').length"), await pg.evaluate("!document.querySelector('#modal').hidden"))
    await swipe('tr[data-sw]',400)
    print('AFTER', await pg.evaluate("document.querySelectorAll('tr[data-sw]').length"), await pg.text_content('#toast'), await pg.evaluate("!document.querySelector('#modal').hidden"))
    await pg.click('[data-act=undo]');await pg.wait_for_timeout(400)
    print('UNDO', await pg.evaluate("document.querySelectorAll('tr[data-sw]').length"))
    await swipe('tr[data-sw]',400);await go('lixeira');print('LIX',(await pg.text_content('#view'))[:160])
    await pg.click('[data-act=restaurar]');await pg.wait_for_timeout(300);await go('estoque');print('REST',await pg.evaluate("document.querySelectorAll('tr[data-sw]').length"))
    await pg.click('tr[data-sw]');await pg.wait_for_timeout(200);print('CLICK opens modal',await pg.evaluate("!document.querySelector('#modal').hidden"))
    print('ERR',errs)
    await b.close()
asyncio.run(main())
