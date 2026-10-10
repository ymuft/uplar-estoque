/**
 * M3 — editor de categorias canônicas, independente do editor legado da vitrine.
 * Uma categoria por produto; permissões e vínculos são validados no backend.
 */
(function(){
'use strict';
const root=document.getElementById('canonicalCategoriesAdmin');
if(!root)return;
const csrf=root.dataset.csrf||'';
const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let categories=[],products=[],editing=0,deleting=0,page=0,query='',audit=null,busy=false;
const categoryById=id=>categories.find(c=>Number(c.id)===Number(id));
const loading=()=>{busy=true;root.classList.add('is-busy')};
const finished=()=>{busy=false;root.classList.remove('is-busy')};
const notice=(message,err=false)=>{let el=document.getElementById('ccNotice');if(el){el.textContent=message;el.classList.toggle('error',err);el.hidden=false;}};
const post=async(action,data={})=>{
 const body=new FormData();body.append('csrf_token',csrf);
 Object.entries(data).forEach(([k,v])=>body.append(k,String(v??'')));
 const r=await fetch('catalogo_categorias_api.php?acao='+encodeURIComponent(action),{method:'POST',credentials:'same-origin',body});
 const j=await r.json().catch(()=>({ok:false,error:'O servidor retornou uma resposta inválida.'}));
 if(!r.ok||!j.ok){const e=new Error(j.error||'Não foi possível concluir a operação.');e.report=j.relatorio;throw e;}return j;
};
async function reload(){
 const [a,b]=await Promise.all([
   fetch('catalogo_categorias_api.php?acao=listar',{credentials:'same-origin'}),
   fetch('vitrine_api.php?acao=listar_produtos_admin',{credentials:'same-origin'})
 ]);
 const c=await a.json(),p=await b.json();
 if(!a.ok||!c.ok)throw Error(c.error||'Categorias indisponíveis.');
 if(!b.ok||!p.ok)throw Error(p.error||'Não foi possível carregar os produtos.');
 categories=c.categorias||[];products=p.produtos||[];
}
const actions=(id)=>'<div class="cc-row-actions">'+
  '<button type="button" data-cc="rename" data-id="'+id+'">Renomear</button>'+
  '<button type="button" data-cc="up" data-id="'+id+'" aria-label="Subir categoria">↑</button>'+
  '<button type="button" data-cc="down" data-id="'+id+'" aria-label="Descer categoria">↓</button>'+
  '<button type="button" data-cc="delete" data-id="'+id+'">Excluir</button></div>';
function render(){
 const source=root.dataset.source||'legado';
 root.innerHTML='<div class="cc-title"><div><h4>Categorias do catálogo</h4><p class="hint">Uma classificação por produto, compartilhada com o cadastro e a vitrine.</p></div></div>'+
 '<p id="ccNotice" class="cc-notice" role="status" hidden></p>'+
 (source==='canonico'?'<p class="cc-status">A vitrine utiliza as categorias deste catálogo.</p>':
 '<p class="cc-help">Sua vitrine ainda usa a organização anterior. A migração não modifica sua vitrine até você ativar a nova origem.</p>')+
 '<div class="cc-toolbar"><input id="ccNewName" maxlength="80" placeholder="Nova categoria, ex.: Hambúrgueres" aria-label="Nome da nova categoria">'+
 '<button type="button" data-cc="create">Criar categoria</button>'+
 '<button type="button" data-cc="preset">Aplicar sugestões do perfil</button></div>'+
 '<div class="cc-list">'+(categories.length?categories.map(c=>{
 const id=Number(c.id);
 if(editing===id)return '<div class="cc-row"><input id="ccRename" maxlength="80" value="'+escape(c.nome)+'" aria-label="Novo nome da categoria"><button type="button" data-cc="rename-save" data-id="'+id+'">Salvar</button><button type="button" data-cc="cancel">Cancelar</button></div>';
 if(deleting===id)return '<div class="cc-row cc-delete"><strong>Excluir '+escape(c.nome)+'?</strong><label>Destino dos '+Number(c.total_produtos)+' produtos <select id="ccTransfer"><option value="">Deixar sem categoria</option>'+categories.filter(x=>Number(x.id)!==id).map(x=>'<option value="'+Number(x.id)+'">'+escape(x.nome)+'</option>').join('')+'</select></label><button type="button" data-cc="delete-confirm" data-id="'+id+'">Confirmar exclusão</button><button type="button" data-cc="cancel">Cancelar</button></div>';
 return '<div class="cc-row"><div><strong>'+escape(c.nome)+'</strong><small>'+Number(c.total_produtos)+' produto(s)</small></div>'+actions(id)+'</div>';
 }).join(''):'<p class="cc-empty">Nenhuma categoria criada. Você pode adicionar uma acima ou aplicar as sugestões do seu perfil.</p>')+'</div>'+
 '<div class="cc-products"><h4>Produtos e categorias</h4><label class="cc-search">Buscar produto <input type="search" id="ccSearch" value="'+escape(query)+'" placeholder="Nome ou código"></label><div id="ccProductRows"></div><div class="cc-pager" id="ccPager"></div></div>'+
 '<div class="cc-legacy"><h4>Transição da organização anterior</h4><p class="hint">Audite a vitrine publicada antes de importar categorias e vínculos. O processo não exclui produtos.</p>'+
 '<button type="button" data-cc="audit">Analisar divergências</button>'+
 '<button type="button" data-cc="import">Importar associações antigas</button>'+
 (source==='canonico'?'':'<button type="button" data-cc="activate" class="cc-primary">Ativar categorias canônicas na vitrine</button>')+
 '<div id="ccAudit" aria-live="polite"></div></div>';
 renderProducts();if(audit)renderAudit();
}
function renderProducts(){
 const filtered=products.filter(p=>(String(p.nome||'')+' '+String(p.codigo||'')).toLocaleLowerCase('pt-BR').includes(query.toLocaleLowerCase('pt-BR')));
 const pages=Math.max(1,Math.ceil(filtered.length/25));page=Math.max(0,Math.min(page,pages-1));
 const rows=filtered.slice(page*25,page*25+25);
 const el=document.getElementById('ccProductRows');if(!el)return;
 el.innerHTML=rows.length?rows.map(p=>'<label class="cc-product"><span><strong>'+escape(p.nome)+'</strong><small>'+escape(p.codigo||'Sem SKU')+'</small></span><select data-cc-product="'+Number(p.id)+'" aria-label="Categoria de '+escape(p.nome)+'"><option value="">Sem categoria</option>'+
 categories.map(c=>'<option value="'+Number(c.id)+'" '+(Number(p.categoria_id)===Number(c.id)?'selected':'')+'>'+escape(c.nome)+'</option>').join('')+
 '</select></label>').join(''):'<p class="cc-empty">Nenhum produto encontrado.</p>';
 document.getElementById('ccPager').innerHTML='<span>Página '+(page+1)+' de '+pages+' · '+filtered.length+' produtos</span>'+
 (page>0?'<button type="button" data-cc="prev">Anterior</button>':'')+
 (page<pages-1?'<button type="button" data-cc="next">Próxima</button>':'');
}
function renderAudit(){
 const el=document.getElementById('ccAudit');if(!el||!audit)return;
 el.innerHTML='<p class="cc-audit-meta">'+audit.produtos+' produtos · '+audit.classificaveis+' com categoria identificada · '+audit.conflitos+' divergências</p>'+
 (audit.conflitos?'<div class="cc-conflicts">'+(audit.amostra_conflitos||[]).slice(0,20).map(c=>
 '<div><strong>'+escape(c.produto)+'</strong><span>Cadastro: '+escape(c.categoria_cadastro||'—')+' · Vitrine: '+escape(c.categoria_vitrine||'—')+' · Atual: '+escape(c.categoria_canonica||'—')+'</span></div>').join('')+'</div>':
 '<p class="cc-help">Nenhuma divergência detectada na amostra analisada.</p>');
}
root.addEventListener('input',e=>{if(e.target.id==='ccSearch'){query=e.target.value;page=0;renderProducts();}});
root.addEventListener('change',async e=>{
 const select=e.target.closest('[data-cc-product]');if(!select||busy)return;
 const pid=Number(select.dataset.ccProduct),previous=products.find(p=>Number(p.id)===pid)?.categoria_id??'';
 loading();
 try{await post('atribuir',{produto_id:pid,categoria_id:select.value});
      const p=products.find(p=>Number(p.id)===pid);if(p)p.categoria_id=select.value?Number(select.value):null;
      await reload();render();notice('Categoria atualizada para o produto.');}
 catch(err){select.value=String(previous||'');finished();notice(err.message,true);return;}
 finished();
});
root.addEventListener('click',async e=>{
 const b=e.target.closest('[data-cc]');if(!b||busy)return;
 const action=b.dataset.cc,id=Number(b.dataset.id)||0;
 if(action==='rename'){editing=id;deleting=0;render();document.getElementById('ccRename')?.focus();return;}
 if(action==='delete'){deleting=id;editing=0;render();return;}
 if(action==='cancel'){editing=0;deleting=0;render();return;}
 if(action==='prev'||action==='next'){page+=action==='next'?1:-1;renderProducts();return;}
 if(action==='up'||action==='down'){
   const index=categories.findIndex(c=>Number(c.id)===id),to=index+(action==='up'?-1:1);
   if(to<0||to>=categories.length)return;
   const reordered=categories.map(c=>Number(c.id));[reordered[index],reordered[to]]=[reordered[to],reordered[index]];
   loading();try{await post('ordenar',{ids:JSON.stringify(reordered)});await reload();render();}catch(err){notice(err.message,true);}finally{finished();}return;
 }
 if(action==='import'||action==='activate'){
   if(!audit){notice('Analise as divergências antes de continuar.',true);return;}
   if(audit.conflitos>0&&!confirm('Foram encontradas '+audit.conflitos+' divergências. Revise o relatório e confirme para continuar sem substituir categorias canônicas já atribuídas.'))return;
   if(action==='activate'&&!confirm('Ativar a classificação canônica nesta vitrine? Confira a organização antes de disponibilizá-la aos clientes.'))return;
   if(action==='import'&&!confirm('Importar categorias e vínculos antigos para a nova estrutura? A vitrine publicada continuará igual.'))return;
 }
 loading();
 try{
   let response;
   if(action==='create')response=await post('criar',{nome:document.getElementById('ccNewName').value});
   else if(action==='rename-save')response=await post('renomear',{categoria_id:id,nome:document.getElementById('ccRename').value});
   else if(action==='delete-confirm')response=await post('excluir',{categoria_id:id,confirmar:'1',transferir_para:document.getElementById('ccTransfer').value});
   else if(action==='preset')response=await post('aplicar_sugestoes');
   else if(action==='audit'){
      const r=await fetch('catalogo_categorias_api.php?acao=auditoria',{credentials:'same-origin'});response=await r.json();if(!r.ok||!response.ok)throw Error(response.error||'Falha na auditoria.');audit=response.relatorio;
   }
   else if(action==='import'){response=await post('importar_legado',{confirmar_conflitos:audit?.conflitos?'1':'0'});audit=null;}
   else if(action==='activate'){response=await post('ativar_canonico',{confirmar:'1',confirmar_conflitos:audit?.conflitos?'1':'0'});root.dataset.source='canonico';audit=null;}
   else return;
   editing=0;deleting=0;await reload();render();notice(action==='audit'?'Auditoria concluída.': 'Alterações salvas.');
 }catch(err){if(err.report)audit=err.report;render();notice(err.message,true);}finally{finished();}
});
(async()=>{loading();try{await reload();render();}catch(err){root.innerHTML='<p class="cc-empty">Não foi possível carregar o catálogo de categorias.</p>';notice(err.message,true);}finally{finished();}})();
})();
