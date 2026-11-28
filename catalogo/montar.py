import json,base64,os,sys
d=os.path.dirname(os.path.abspath(__file__))
j=json.load(open(os.path.join(d,'modelos.json')))
for m in j['modelos']:
    f=os.path.join(d,'fotos',m.get('foto') or '')
    m['foto']='data:image/jpeg;base64,'+base64.b64encode(open(f,'rb').read()).decode() if m.get('foto') and os.path.isfile(f) else ''
out=sys.argv[1] if len(sys.argv)>1 else os.path.join(d,'importar-central.json')
json.dump(j,open(out,'w'),ensure_ascii=False)
print(len(j['modelos']),'modelo(s) ->',out)
