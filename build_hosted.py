import re,os,sys
base=os.path.dirname(os.path.abspath(__file__))
src=sys.argv[1] if len(sys.argv)>1 else os.path.join(base,'erp','index.html')
dst=sys.argv[2] if len(sys.argv)>2 else os.path.join(base,'hostinger')
h=open(src).read()
scripts=re.findall(r'<script>([\s\S]*?)</script>',h)
assert len(scripts)==2,len(scripts)
h=re.sub(r'<script>[\s\S]*?</script>\n?','',h)
h=h.replace('</body>','<script src="shim.js"></script>\n<script src="app.js"></script>\n</body>')
js='\n'.join(scripts)
js=js.replace('"/_blob/"','"_blob/"').replace('/_blob/${','_blob/${')
h=h.replace('/_blob/','_blob/')
assert '/_blob/' not in js and '<script>' not in h
open(os.path.join(dst,'app.html'),'w').write(h)
open(os.path.join(dst,'app.js'),'w').write(js)
print('ok',len(h),len(js))
import shutil,glob
cat=os.path.join(os.path.dirname(os.path.abspath(__file__)),'catalogo')
if os.path.isdir(cat):
    out=os.path.join(dst,'catalogo');shutil.rmtree(out,ignore_errors=True);os.makedirs(os.path.join(out,'fotos'))
    shutil.copy(os.path.join(cat,'modelos.json'),out)
    for f in glob.glob(os.path.join(cat,'fotos','*.jpg')):shutil.copy(f,os.path.join(out,'fotos'))
