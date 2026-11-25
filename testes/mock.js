window.claude={use:async n=>{
 if(n==='db'){const store=new Map(),subs=new Set();const cid=()=>Math.random().toString(36).slice(2,12);
  const notify=()=>setTimeout(()=>subs.forEach(f=>f()),5);
  const docsIn=p=>[...store.entries()].filter(([k])=>k.startsWith(p+'/')&&k.slice(p.length+1).split('/').length===1).map(([k,v])=>({id:k.split('/').pop(),exists:true,data:()=>JSON.parse(JSON.stringify(v)),metadata:{fromCache:false,hasPendingWrites:false}}));
  const merge=(a,b)=>{for(const k in b){if(b[k]&&typeof b[k]==='object'&&!Array.isArray(b[k])&&a[k]&&typeof a[k]==='object')merge(a[k],b[k]);else a[k]=b[k]}return a};
  const docRef=p=>({id:p.split('/').pop(),path:p,get:async()=>({id:p.split('/').pop(),exists:store.has(p),data:()=>store.has(p)?JSON.parse(JSON.stringify(store.get(p))):undefined,metadata:{}}),set:async d=>{store.set(p,JSON.parse(JSON.stringify(d)));notify()},update:async d=>{if(!store.has(p))throw{code:'invalid_argument'};merge(store.get(p),JSON.parse(JSON.stringify(d)));notify()},delete:async()=>{store.delete(p);notify()},acquire:async o=>{const L=window.__locks=window.__locks||{};const n=Date.now();const c=L[p];if(c&&c.exp>n&&c.h!==o.holder)return{acquired:false};L[p]={h:o.holder,exp:n+(o.ttlMs||30000)};return{acquired:true}},collection:c=>colRef(p+'/'+c)});
  const colRef=(p,ord,lim)=>{const run=()=>{let d=docsIn(p);if(ord)d.sort((a,b)=>{const x=a.data()[ord[0]],y=b.data()[ord[0]];return (x<y?-1:x>y?1:0)*(ord[1]==='desc'?-1:1)});if(lim)d=d.slice(0,lim);return{docs:d,size:d.length,empty:!d.length,metadata:{fromCache:false}}};
   return{path:p,doc:id=>docRef(p+'/'+(id||cid())),add:async d=>{const r=docRef(p+'/'+cid());await r.set(d);return r},where(){return this},orderBy:(f,dir)=>colRef(p,[f,dir],lim),limit:n=>colRef(p,ord,n),get:async()=>run(),onSnapshot:(next)=>{const f=()=>next(run());subs.add(f);setTimeout(f,10);return()=>subs.delete(f)}}};
  window.__store=store;window.__push=(p,d)=>{store.set(p,d);notify()};return{doc:docRef,collection:p=>colRef(p)}}
 if(n==='user')return{id:async()=>'u_owner',isOwner:async()=>true,canEdit:async()=>true,can:async()=>true,profiles:async ids=>Object.fromEntries(ids.map(i=>[i,{name:'Duda'}])),search:async q=>[{id:'u2',name:'Ana Teste',email:'ana@x.com'}]};
 if(n==='downloads')return{save:async()=>{}};
 return null}};
