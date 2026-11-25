import os
b=os.path.dirname(os.path.abspath(__file__))
h=open(os.path.join(b,'..','app','index.html')).read()
open(os.path.join(b,'t.html'),'w').write(h.replace('<script>\n{','<script>'+open(os.path.join(b,'mock.js')).read()+'</script><script>\n{',1))
print('testes/t.html gerado')
