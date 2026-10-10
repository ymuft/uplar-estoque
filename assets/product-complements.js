/* Shared administrative editor: product drafts and reusable group library. */
class ProductComplementsEditor {
    constructor(root,csrf,productId=0,options={}){
        Object.assign(this,{root,csrf,productId:Number(productId)||0,libraryOnly:Boolean(options.libraryOnly),groups:[],library:[],ready:false,dirty:false,edit:null,saving:false,saveToken:ProductComplementsEditor.token(),search:'',status:'all'});
        root.complementsEditor=this;root.classList.add('ce');
        root.addEventListener('click',e=>{if(root.complementsEditor!==this)return;const b=e.target.closest('[data-ce]');if(!b||!root.contains(b))return;e.preventDefault();Promise.resolve(this.action(b.dataset.ce,Number(b.dataset.index),Number(b.dataset.option))).catch(error=>{this.error(error.message);this.message(error.message);});});
        root.addEventListener('submit',e=>{if(root.complementsEditor!==this)return;e.preventDefault();if(this.edit?.kind==='option')this.commitOption(e.submitter?.dataset.another==='1');else this.commitGroup();});
        root.addEventListener('input',e=>{if(root.complementsEditor!==this)return;if(this.edit&&['group','option'].includes(this.edit.kind))this.edit.changed=true;if(e.target.matches('[data-search]')){this.search=e.target.value;this.applyFilter();}if(this.edit?.kind==='group'&&e.target.name==='minimo'){root.querySelector('[name="required"]').checked=Number(e.target.value)>0;}});
        root.addEventListener('change',e=>{
            if(root.complementsEditor!==this)return;
            if(this.edit&&['group','option'].includes(this.edit.kind))this.edit.changed=true;
            if(e.target.matches('[data-status]')){this.status=e.target.value;this.applyFilter();}
            if(e.target.matches('[data-pick]')){const id=Number(e.target.dataset.pick);if(e.target.checked)this.edit.selected.add(id);else this.edit.selected.delete(id);}
            if(this.edit?.kind!=='group')return;
            const f=root.querySelector('form').elements;
            if(e.target.name==='required')f.namedItem('minimo').value=e.target.checked?Math.max(1,Number(f.namedItem('minimo').value)):0;
            if(e.target.name==='repeat'){this.collect();this.edit.group.opcoes.forEach(o=>o.max_quantidade=e.target.checked?Math.max(2,Number(o.max_quantidade)):1);this.edit.changed=true;}
        });
    }
    static esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
    static clone(v){return JSON.parse(JSON.stringify(v));}
    static token(){return Array.from(crypto.getRandomValues(new Uint8Array(16)),v=>v.toString(16).padStart(2,'0')).join('');}
    static cents(v){const m=String(v).match(/^(\d+)(?:\.(\d{1,2}))?$/);if(!m||Number(m[1])>99999999)throw Error('Informe um preço válido, inclusive 0 para opções gratuitas.');return Number(m[1])*100+Number((m[2]||'').padEnd(2,'0'));}
    static money(v){return Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});}
    static problem(g){
        if(!String(g.nome||'').trim())return 'Informe o nome do grupo.';
        if(!Number.isInteger(Number(g.minimo))||!Number.isInteger(Number(g.maximo))||g.minimo<0||g.maximo<1||g.maximo>99||g.minimo>g.maximo)return 'O máximo deve ser de 1 a 99 e não pode ser menor que o mínimo.';
        if(g.opcoes.length>100)return 'O grupo aceita até 100 opções.';
        for(const o of g.opcoes){if(!String(o.nome||'').trim())return 'Existe uma opção sem nome.';try{this.cents(o.preco_adicional);if(o.custo_adicional!==null&&o.custo_adicional!=='')this.cents(o.custo_adicional);}catch(e){return e.message;}if(!Number.isInteger(Number(o.max_quantidade))||o.max_quantidade<1||o.max_quantidade>99)return 'O limite individual deve ser de 1 a 99.';}
        const active=g.opcoes.filter(o=>o.ativo),capacity=active.reduce((sum,o)=>sum+Number(o.max_quantidade),0);
        if(g.ativo&&(!active.length||capacity<g.minimo))return 'As opções ativas não atendem ao mínimo. Adicione opções ou ajuste as regras.';
        return '';
    }
    async request(action,data=null){
        const options={credentials:'same-origin'};
        if(data){options.method='POST';options.body=new FormData();Object.entries(data).forEach(([k,v])=>options.body.append(k,typeof v==='boolean'?(v?'1':'0'):v));options.body.append('csrf_token',this.csrf);}
        const r=await fetch('api.php?acao='+action,options),j=await r.json().catch(()=>({ok:false,error:'Resposta inválida. Suas alterações continuam em tela.'}));
        if(!r.ok||!j.ok){const e=new Error(j.error||'Não foi possível salvar.');e.status=r.status;throw e;}return j;
    }
    async load(){
        this.ready=false;this.emit('complementsready');this.root.innerHTML='<p class="ce-intro" role="status">Carregando grupos…</p>';
        try{const j=await this.request(this.libraryOnly?'complementos_biblioteca':'complementos_grupos&produto_id='+this.productId);if(this.root.complementsEditor!==this)return;this.library=j.grupos||[];this.groups=this.libraryOnly?this.library:ProductComplementsEditor.clone(this.library.filter(g=>g.associado).sort((a,b)=>a.vinculo_ordem-b.vinculo_ordem));this.groups.forEach(g=>g.alterado=false);this.ready=true;this.render();this.emit('complementsready');}
        catch(e){if(this.root.complementsEditor!==this)return;this.root.innerHTML='<div class="ce-error" role="alert">'+this.esc(e.message)+'</div>'+this.button('reload','Tentar novamente');}
    }
    emit(name='complementsstate'){this.root.dispatchEvent(new Event(name));}
    esc(v){return ProductComplementsEditor.esc(v);}
    button(action,text,index='',option='',primary=false){return '<button type="button" class="ce-btn'+(primary?' primary':'')+'" data-ce="'+action+'" data-index="'+index+'" data-option="'+option+'">'+text+'</button>';}
    group(seed='multi'){
        const presets={single:{nome:'',minimo:1,maximo:1,repeat:false},multi:{nome:'',minimo:0,maximo:3,repeat:false},quantities:{nome:'',minimo:0,maximo:3,repeat:true},remove:{nome:'Deseja retirar algo?',minimo:0,maximo:3,repeat:false},addons:{nome:'Adicionais',minimo:0,maximo:3,repeat:false},point:{nome:'Ponto da carne',minimo:1,maximo:1,repeat:false},drink:{nome:'Escolha sua bebida',minimo:1,maximo:1,repeat:false},cutlery:{nome:'Talheres e descartáveis',minimo:0,maximo:3,repeat:false}};
        return {id:0,descricao:'',ativo:true,opcoes:[],alterado:true,qtd_produtos:0,produtos:[],...presets[seed]};
    }
    independent(g){const copy=ProductComplementsEditor.clone(g);copy.id=0;copy.nome=copy.nome.slice(0,91)+' (cópia)';copy.opcoes.forEach(o=>{o.id=0;delete o.grupo_id;});Object.assign(copy,{qtd_produtos:0,produtos:[],alterado:true,confirmar_compartilhado:false});delete copy.versao;return copy;}
    rules(g){return !g.ativo?'Inativo':g.minimo>0?'Obrigatório · '+(Number(g.maximo)===1?'escolher 1':'de '+g.minimo+' a '+g.maximo+' unidades'):'Opcional · até '+g.maximo+' '+(Number(g.maximo)===1?'escolha':'unidades');}
    groupCard(g,i,preview=false){
        const error=ProductComplementsEditor.problem(g),count=g.opcoes.filter(o=>o.ativo).length;
        return '<section class="ce-card"><div class="ce-card-head"><div><h3>'+this.esc(g.nome)+'</h3><div class="ce-meta">'+this.esc(this.rules(g))+' · '+count+' opções</div>'+(g.qtd_produtos>1?'<small class="ce-shared">Compartilhado com '+Number(g.qtd_produtos)+' produtos</small>':'')+'</div>'+(preview?'':this.button('edit-group','Editar',i))+'</div>'+(error?'<div class="ce-error">'+this.esc(error)+'</div>':'')+(preview?'<div class="ce-options">'+g.opcoes.filter(o=>o.ativo).map(o=>'<div class="ce-option-line"><span>'+this.esc(o.nome)+(o.max_quantidade>1?' × até '+Number(o.max_quantidade):'')+'</span><strong>'+ProductComplementsEditor.money(o.preco_adicional)+'</strong></div>').join('')+'</div>':'<div class="ce-card-actions">'+this.button('copy-product','Criar cópia independente',i)+'<details class="ce-more"><summary>Mais ações</summary><div class="ce-more-actions">'+this.button('up','Subir',i)+this.button('down','Descer',i)+this.button('unlink','Remover vínculo',i)+this.button('linked','Ver produtos',i)+'</div></details></div>')+'</section>';
    }
    render(){
        if(this.edit)return this.renderEdit();
        if(this.libraryOnly)return this.renderLibrary();
        this.root.innerHTML='<p class="ce-intro">Organize as escolhas em grupos. Crie uma configuração ou reutilize o trabalho de outro produto.</p><div class="ce-toolbar">'+this.button('new','+ Criar grupo','','',true)+this.button('picker','+ Usar grupo existente')+'</div>'+(this.groups.length?this.groups.map((g,i)=>this.groupCard(g,i)).join(''):'<div class="ce-empty"><strong>Nenhum grupo vinculado</strong><p>Ex.: escolha obrigatória, adicionais pagos ou preferências gratuitas.</p></div>')+(this.groups.length?'<div class="ce-toolbar">'+this.button('preview','Pré-visualizar personalização')+'</div>':'')+'<p class="ce-status" role="status">'+(this.dirty?'Alterações em rascunho. Serão publicadas ao salvar o produto.':'')+'</p>';this.emit();
    }
    filters(){return '<div class="ce-filter"><input type="search" data-search aria-label="Buscar grupos" placeholder="Buscar grupo…" value="'+this.esc(this.search)+'"><select data-status aria-label="Estado do grupo"><option value="all" '+(this.status==='all'?'selected':'')+'>Todos</option><option value="active" '+(this.status==='active'?'selected':'')+'>Ativos</option><option value="inactive" '+(this.status==='inactive'?'selected':'')+'>Inativos</option></select></div>';}
    applyFilter(){let found=0;this.root.querySelectorAll('[data-group-name]').forEach(el=>{const match=el.dataset.groupName.toLocaleLowerCase('pt-BR').includes(this.search.toLocaleLowerCase('pt-BR'))&&(this.status==='all'||(this.status==='active')===(el.dataset.active==='1'));el.hidden=!match;if(match)found++;});const empty=this.root.querySelector('[data-filter-empty]');if(empty)empty.hidden=found>0;}
    renderLibrary(){
        this.root.innerHTML='<div class="ce-toolbar">'+this.button('new','+ Criar grupo','','',true)+'</div>'+this.filters()+this.groups.map((g,i)=>'<section class="ce-card" data-group-name="'+this.esc(g.nome)+'" data-active="'+(g.ativo?'1':'0')+'"><div class="ce-card-head"><div><h3>'+this.esc(g.nome)+'</h3><p class="ce-meta">'+g.opcoes.filter(o=>o.ativo).length+' opções · '+Number(g.qtd_produtos||0)+' produtos vinculados · '+(g.ativo?'Ativo':'Inativo')+'</p></div>'+this.button('edit-group','Editar',i)+'</div><div class="ce-card-actions">'+this.button('copy-library','Duplicar',i)+this.button('linked','Ver produtos',i)+this.button('state',g.ativo?'Desativar':'Ativar',i)+'<details class="ce-more"><summary>Mais ações</summary>'+this.button('delete','Excluir quando seguro',i)+'</details></div></section>').join('')+'<div class="ce-empty" data-filter-empty '+(this.groups.length?'hidden':'')+'>Nenhum grupo encontrado.</div><p class="ce-status" role="status"></p>';this.applyFilter();this.emit();
    }
    renderGuide(){
        const choices=[['single','Escolher uma opção','Ponto, tipo de pão, temperatura. Obrigatório: 1 escolha.'],['multi','Escolher várias opções','Molhos, acessórios e serviços. Opcional: até 3 unidades.'],['quantities','Adicionar quantidades','Adicionais repetíveis, com limites por opção e grupo.'],['remove','Retirar ingredientes','Preferências sem acréscimo de preço.']];
        this.root.innerHTML='<div class="ce-edit"><span class="ce-eyebrow">Novo grupo</span><h3>O que o cliente vai escolher?</h3><p class="ce-intro">Escolha um ponto de partida. Todas as regras podem ser ajustadas.</p><div class="ce-templates">'+choices.map(([kind,name,hint])=>'<button type="button" class="ce-template" data-ce="template-'+kind+'"><strong>'+name+'</strong><span>'+hint+'</span></button>').join('')+'</div><details class="ce-advanced"><summary>Modelos prontos</summary><div class="ce-template-links">'+[['addons','Adicionais'],['point','Ponto da carne'],['remove','Retirar ingredientes'],['drink','Escolha sua bebida'],['cutlery','Talheres e descartáveis']].map(([kind,name])=>this.button('template-'+kind,name)).join('')+'</div></details><div class="ce-edit-actions">'+this.button('cancel','Cancelar')+'</div></div>';
    }
    renderGroup(){
        const {group:g}=this.edit,repeat=g.repeat??g.opcoes.some(o=>Number(o.max_quantidade)>1);
        this.root.innerHTML='<form class="ce-edit"><span class="ce-eyebrow">Grupo de complementos</span><h3>'+this.esc(g.id?'Editar grupo':'Novo grupo')+'</h3><div class="ce-fields"><div class="ce-field ce-wide"><label for="ceName">Nome do grupo *</label><input id="ceName" name="nome" required maxlength="100" placeholder="Ex.: Quer adicionar algo?" value="'+this.esc(g.nome)+'"></div><div class="ce-field ce-wide"><label for="ceDescription">Orientação ao cliente</label><input id="ceDescription" name="descricao" maxlength="255" placeholder="Ex.: Escolha até 3 adicionais" value="'+this.esc(g.descricao)+'"></div><label class="ce-check ce-wide"><input name="required" type="checkbox" '+(g.minimo>0?'checked':'')+'><span><strong>Escolha obrigatória</strong><small>Desmarcado: opcional. Marcado: mínimo de uma unidade.</small></span></label><div class="ce-field"><label>Mínimo de unidades</label><input name="minimo" type="number" min="0" max="99" step="1" required value="'+Number(g.minimo)+'"></div><div class="ce-field"><label>Máximo de unidades</label><input name="maximo" type="number" min="1" max="99" step="1" required value="'+Number(g.maximo)+'"></div><label class="ce-check ce-wide"><input name="repeat" type="checkbox" '+(repeat?'checked':'')+'><span>Permitir repetir opções<small>Configure o limite individual ao editar cada opção.</small></span></label></div><div class="ce-section-title">Opções do grupo <span>'+g.opcoes.length+'</span></div><div class="ce-options">'+g.opcoes.map((o,i)=>'<div class="ce-option-line"><div class="ce-option-name"><span>'+this.esc(o.nome)+'</span><small>'+(!o.ativo?'Inativa':Number(o.max_quantidade)>1?'Até '+Number(o.max_quantidade)+' un.':'Uma unidade')+'</small></div><strong>'+ProductComplementsEditor.money(o.preco_adicional)+'</strong>'+this.button('option-edit','Editar','',i)+'</div>').join('')+'</div><div class="ce-toolbar">'+this.button('option-new','+ Adicionar opção')+'</div><details class="ce-advanced"><summary>Disponibilidade do grupo</summary><label class="ce-check"><input name="ativo" type="checkbox" '+(g.ativo?'checked':'')+'><span>Grupo ativo na vitrine</span></label></details>'+(g.id&&g.qtd_produtos?'<p class="ce-shared-note">Grupo compartilhado: '+Number(g.qtd_produtos)+' produtos vinculados. Os pedidos anteriores manterão seus valores.</p>':'')+'<div data-error role="alert"></div><div class="ce-edit-actions">'+this.button('cancel','Cancelar')+'<button class="ce-btn primary" type="submit" '+(this.saving?'disabled':'')+'>'+(this.saving?'Salvando…':'Salvar grupo')+'</button></div></form>';
    }
    renderOption(){
        const o=this.edit.option;
        this.root.innerHTML='<form class="ce-edit"><span class="ce-eyebrow">'+this.esc(this.edit.parent.group.nome||'Opções do grupo')+'</span><h3>'+(this.edit.optionIndex<0?'Adicionar opção':'Editar opção')+'</h3><div class="ce-fields"><div class="ce-field ce-wide"><label for="ceOptionName">Nome *</label><input id="ceOptionName" name="nome" required maxlength="100" value="'+this.esc(o.nome)+'" placeholder="Ex.: Bacon" autofocus></div><div class="ce-field ce-wide"><label for="ceOptionPrice">Preço adicional (R$)</label><input id="ceOptionPrice" name="preco_adicional" type="number" min="0" max="99999999.99" step="0.01" inputmode="decimal" required value="'+this.esc(o.preco_adicional)+'" placeholder="0,00"><small>R$ 0,00 é válido. Este é o preço cobrado do cliente.</small></div></div><details class="ce-advanced"><summary>+ Mais detalhes</summary><div class="ce-fields"><div class="ce-field"><label>Custo (opcional)</label><input name="custo_adicional" type="number" min="0" max="99999999.99" step="0.01" value="'+this.esc(o.custo_adicional??'')+'" placeholder="Não informado"><small>Deixe vazio quando o custo for desconhecido.</small></div><div class="ce-field"><label>Máximo por produto</label><input name="max_quantidade" type="number" min="1" max="99" step="1" required value="'+Number(o.max_quantidade)+'"><small>1: sem repetição. 2 ou mais: permite quantidades.</small></div><label class="ce-check ce-wide"><input name="ativo" type="checkbox" '+(o.ativo?'checked':'')+'><span>Opção disponível</span></label></div></details><div data-error role="alert"></div><div class="ce-edit-actions">'+this.button('option-cancel','Cancelar')+'<div class="ce-save-options"><button class="ce-btn" type="submit" data-another="1">Salvar e adicionar outro</button><button class="ce-btn primary" type="submit">Concluir</button></div></div>'+(this.edit.optionIndex>=0?'<div class="ce-toolbar">'+this.button('option-up','Subir')+this.button('option-down','Descer')+this.button('option-remove','Remover opção')+'</div>':'')+'</form>';
        this.root.querySelector('[name="nome"]').focus();
    }
    renderPicker(){
        this.root.innerHTML='<div class="ce-edit"><h3>Usar grupo existente</h3><p class="ce-intro">Compartilhado: atualizações valem para os produtos vinculados. Cópia: você edita este produto de forma independente.</p>'+this.filters()+this.library.map(g=>'<label class="ce-picker-row" data-group-name="'+this.esc(g.nome)+'" data-active="'+(g.ativo?'1':'0')+'"><input type="checkbox" data-pick="'+Number(g.id)+'" '+(this.edit.selected.has(Number(g.id))?'checked':'')+' '+(!g.ativo||this.groups.some(x=>Number(x.id)===Number(g.id))?'disabled':'')+'><span><strong>'+this.esc(g.nome)+'</strong><small>'+g.opcoes.filter(o=>o.ativo).length+' opções · '+Number(g.qtd_produtos||0)+' produtos'+(!g.ativo?' · inativo':'')+'</small></span></label>').join('')+'<p data-filter-empty hidden>Nenhum grupo encontrado.</p><div data-error role="alert"></div><div class="ce-edit-actions">'+this.button('cancel','Cancelar')+'<div class="ce-save-options">'+this.button('use-copies','Criar cópias independentes')+this.button('use-selected','Adicionar selecionados','','',true)+'</div></div></div>';this.applyFilter();
    }
    renderImpact(){
        const g=this.edit.group;
        this.root.innerHTML='<div class="ce-edit"><span class="ce-eyebrow">Grupo compartilhado</span><h3>'+this.esc(g.nome)+'</h3><p>Este grupo é utilizado por <strong>'+Number(g.qtd_produtos)+' produtos</strong>.</p><p class="ce-intro">As alterações de regras, opções e preços serão aplicadas a todos os produtos vinculados. Pedidos anteriores permanecem intactos.</p><details class="ce-advanced"><summary>Ver produtos</summary>'+this.productsHTML(g)+'</details><div class="ce-edit-actions">'+this.button('cancel','Cancelar')+this.button('confirm-impact','Continuar edição','','',true)+'</div>'+(!this.libraryOnly?'<div class="ce-toolbar">'+this.button('impact-copy','Criar cópia só para este produto')+'</div>':'')+'</div>';
    }
    productsHTML(g){return '<ul class="ce-linked">'+(g.produtos||[]).map(p=>'<li><a href="complementos.php?produto_id='+Number(p.id)+'">'+this.esc(p.nome)+'</a></li>').join('')+'</ul>'+(!(g.produtos||[]).length?'<p class="ce-intro">Nenhum produto vinculado.</p>':'');}
    renderEdit(){
        const kind=this.edit.kind;if(kind==='guide')this.renderGuide();else if(kind==='group')this.renderGroup();else if(kind==='option')this.renderOption();else if(kind==='picker')this.renderPicker();else if(kind==='impact')this.renderImpact();else if(kind==='linked')this.root.innerHTML='<div class="ce-edit"><h3>Produtos vinculados</h3><p>'+this.esc(this.edit.group.nome)+'</p>'+this.productsHTML(this.edit.group)+this.button('cancel','Voltar')+'</div>';else if(kind==='preview')this.renderPreview();this.emit();
    }
    collect(){
        if(!this.edit)return;const f=this.root.querySelector('form');if(!f)return;const field=n=>f.elements.namedItem(n);
        if(this.edit.kind==='group'){Object.assign(this.edit.group,{nome:field('nome').value.trim(),descricao:field('descricao').value.trim(),minimo:Number(field('minimo').value),maximo:Number(field('maximo').value),ativo:field('ativo').checked,repeat:field('repeat').checked});}
        if(this.edit.kind==='option'){Object.assign(this.edit.option,{nome:field('nome').value.trim(),preco_adicional:field('preco_adicional').value,custo_adicional:field('custo_adicional').value||null,max_quantidade:Number(field('max_quantidade').value),ativo:field('ativo').checked});}
    }
    error(message){const target=this.root.querySelector('[data-error]');if(target)target.innerHTML='<div class="ce-error">'+this.esc(message)+'</div>';}
    formValid(){const f=this.root.querySelector('form'),bad=f.querySelector(':invalid');if(bad){let p=bad.parentElement;while(p&&p!==f){if(p.tagName==='DETAILS')p.open=true;p=p.parentElement;}}return f.reportValidity();}
    option(parent,index=-1){const repeated=parent.group.repeat??parent.group.opcoes.some(o=>Number(o.max_quantidade)>1),o=index<0?{id:0,nome:'',preco_adicional:'0.00',custo_adicional:null,max_quantidade:repeated?2:1,ativo:true,ordem:parent.group.opcoes.length}:ProductComplementsEditor.clone(parent.group.opcoes[index]);this.edit={kind:'option',parent,optionIndex:index,option:o};this.renderEdit();}
    commitOption(another=false){
        if(!this.formValid())return false;this.collect();const {option:o,parent,optionIndex}=this.edit;
        if(!o.nome){this.error('Informe o nome da opção.');return false;}try{ProductComplementsEditor.cents(o.preco_adicional);if(o.custo_adicional!==null)ProductComplementsEditor.cents(o.custo_adicional);}catch(e){this.error(e.message);return false;}
        if(parent.group.opcoes.length>=100&&optionIndex<0){this.error('O grupo atingiu 100 opções.');return false;}
        if(optionIndex<0)parent.group.opcoes.push(o);else parent.group.opcoes[optionIndex]=o;parent.group.opcoes.forEach((option,i)=>option.ordem=i);parent.changed=true;parent.group.repeat=parent.group.opcoes.some(option=>Number(option.max_quantidade)>1);
        if(another)this.option(parent);else{this.edit=parent;this.renderEdit();}return true;
    }
    async commitGroup(){
        if(this.edit?.kind!=='group'||this.saving)return false;if(!this.formValid())return false;this.collect();const edit=this.edit,g=edit.group,problem=ProductComplementsEditor.problem(g);if(problem){this.error(problem);return false;}
        g.alterado=true;
        if(this.libraryOnly){
            this.saving=true;this.renderEdit();
            try{const j=await this.request('complementos_biblioteca_salvar',{grupo:JSON.stringify(g),confirmar_compartilhado:Boolean(g.confirmar_compartilhado),versao:g.versao||''});if(edit.index<0)this.groups.push(j.grupo);else this.groups[edit.index]=j.grupo;this.library=this.groups;this.edit=null;this.saving=false;this.render();this.message('Grupo salvo.');return true;}
            catch(e){this.saving=false;this.renderEdit();this.error(e.message+(e.status===409?' Confira o impacto novamente para manter e aplicar este rascunho.':''));if(e.status===409){const el=this.root.querySelector('[data-error]');el.insertAdjacentHTML('beforeend',this.button('refresh-impact','Revisar impacto mantendo rascunho'));}return false;}
        }
        if(edit.index<0){if(this.groups.length>=30){this.error('O produto aceita até 30 grupos.');return false;}this.groups.push(g);}else this.groups[edit.index]=g;this.dirty=true;this.edit=null;this.render();return true;
    }
    message(text){const el=this.root.querySelector('.ce-status');if(el)el.textContent=text;}
    beginGroup(g,index){const shared=this.libraryOnly?g.qtd_produtos>0:g.qtd_produtos>1;this.edit={kind:shared?'impact':'group',index,group:ProductComplementsEditor.clone(g),changed:false};this.renderEdit();}
    async action(action,index,optionIndex){
        if(this.saving)return;if(action==='reload'){this.load();return;}if(!this.ready)return;
        if(action==='cancel'){if(this.edit?.changed&&!confirm('Descartar as alterações deste grupo?'))return;this.edit=null;this.render();return;}
        if(action==='new'){this.edit={kind:'guide'};this.renderEdit();return;}
        if(action.startsWith('template-')){this.edit={kind:'group',index:-1,group:this.group(action.slice(9)),changed:false};this.renderEdit();this.root.querySelector('[name="nome"]').focus();return;}
        if(action==='edit-group'){this.beginGroup(this.groups[index],index);return;}
        if(action==='refresh-impact'){
            this.collect();const j=await this.request('complementos_biblioteca'),fresh=j.grupos.find(g=>Number(g.id)===Number(this.edit.group.id));
            if(!fresh){this.error('O grupo não está mais disponível. Crie uma cópia do rascunho.');return;}
            Object.assign(this.edit.group,{versao:fresh.versao,qtd_produtos:fresh.qtd_produtos,produtos:fresh.produtos});this.edit.kind='impact';this.renderEdit();return;
        }
        if(action==='confirm-impact'){this.edit.group.confirmar_compartilhado=true;this.edit.kind='group';this.renderEdit();return;}
        if(action==='impact-copy'){this.edit.group=this.independent(this.edit.group);this.edit.kind='group';this.renderEdit();return;}
        if(action==='copy-product'){this.edit={kind:'group',index,group:this.independent(this.groups[index]),changed:false};this.renderEdit();return;}
        if(action==='linked'){this.edit={kind:'linked',group:this.groups[index]};this.renderEdit();return;}
        if(action==='picker'){this.search='';this.status='active';this.edit={kind:'picker',selected:new Set()};this.renderEdit();return;}
        if(action==='use-selected'||action==='use-copies'){
            const chosen=this.library.filter(g=>this.edit.selected.has(Number(g.id)));if(!chosen.length){this.error('Selecione pelo menos um grupo.');return;}if(this.groups.length+chosen.length>30){this.error('O produto aceita até 30 grupos.');return;}
            chosen.forEach(g=>this.groups.push(action==='use-copies'?this.independent(g):{...ProductComplementsEditor.clone(g),alterado:false}));this.dirty=true;this.edit=null;this.render();return;
        }
        if(action==='option-new'||action==='option-edit'){this.collect();this.option(this.edit,action==='option-new'?-1:optionIndex);return;}
        if(action==='option-cancel'){this.edit=this.edit.parent;this.renderEdit();return;}
        if(action==='option-remove'){if(!confirm('Remover esta opção do grupo? Os pedidos antigos serão preservados.'))return;const {parent,optionIndex:i}=this.edit;parent.group.opcoes.splice(i,1);parent.changed=true;this.edit=parent;this.renderEdit();return;}
        if(action==='option-up'||action==='option-down'){this.collect();const {parent,optionIndex:i}=this.edit,to=i+(action==='option-up'?-1:1),list=parent.group.opcoes;if(to>=0&&to<list.length){list[i]=this.edit.option;[list[i],list[to]]=[list[to],list[i]];list.forEach((o,n)=>o.ordem=n);parent.changed=true;this.edit.optionIndex=to;this.renderEdit();}return;}
        if(action==='preview'){if(!this.validate())return;this.edit={kind:'preview',choices:{},quantity:1};this.renderEdit();return;}
        if(action==='preview-close'){this.edit=null;this.render();return;}
        if(action==='preview-plus'||action==='preview-minus'||action==='preview-toggle'||action==='preview-clear'){this.previewChange(action,index,optionIndex);return;}
        if(action==='preview-product-plus'||action==='preview-product-minus'){this.edit.quantity=Math.max(1,Math.min(99,this.edit.quantity+(action.endsWith('plus')?1:-1)));this.renderPreview();return;}
        if(action==='preview-check'){await this.checkPreview();return;}
        if(this.libraryOnly&&(action==='copy-library'||action==='state'||action==='delete')){
            const g=this.groups[index];if(action==='state'&&g.qtd_produtos>0&&!confirm('Este grupo é utilizado por '+g.qtd_produtos+' produtos. '+(g.ativo?'Desativar':'Ativar')+' em todos eles?'))return;
            if(action==='delete'&&!confirm('Excluir este grupo apenas se nunca foi utilizado? Grupos com vínculos ou histórico serão preservados.'))return;
            this.saving=true;this.message('Salvando…');
            try{if(action==='copy-library'){const j=await this.request('complemento_grupo_copiar',{id:g.id,nome:g.nome.slice(0,91)+' (cópia)',token:ProductComplementsEditor.token()});this.groups.push(j.grupo);}
                else if(action==='state'){const j=await this.request('complementos_grupo_estado',{id:g.id,ativo:!g.ativo,confirmar_compartilhado:true,versao:g.versao||''});this.groups[index]=j.grupo;}
                else{await this.request('complementos_grupo_excluir',{id:g.id});this.groups.splice(index,1);}
                this.saving=false;this.library=this.groups;this.render();this.message('Alteração salva.');
            }catch(e){this.saving=false;this.message(e.message);}return;
        }
        if(action==='unlink'){if(!confirm('Remover somente o vínculo deste produto? O grupo continuará na biblioteca.'))return;this.groups.splice(index,1);}
        if(action==='up'||action==='down'){const to=index+(action==='up'?-1:1);if(to<0||to>=this.groups.length)return;[this.groups[index],this.groups[to]]=[this.groups[to],this.groups[index]];}
        this.dirty=true;this.render();
    }
    previewConfig(){return this.groups.filter(g=>g.ativo).map(g=>({...g,opcoes:g.opcoes.filter(o=>o.ativo)}));}
    previewChange(action,groupIndex,optionIndex){
        const groups=this.previewConfig(),g=groups[groupIndex],o=g.opcoes[optionIndex];if(action==='preview-clear'){this.edit.choices[groupIndex]={};this.renderPreview();return;}
        const choices=this.edit.choices[groupIndex]||{},count=Number(choices[optionIndex]||0),total=Object.values(choices).reduce((a,b)=>a+Number(b),0);
        if(Number(g.maximo)===1){this.edit.choices[groupIndex]=count?{}:{[optionIndex]:1};}
        else{let next=action==='preview-minus'?Math.max(0,count-1):action==='preview-toggle'?(count?0:1):count+1;if(next>Number(o.max_quantidade)||total-count+next>Number(g.maximo)){this.error('O limite deste grupo ou desta opção foi atingido.');return;}this.edit.choices[groupIndex]={...choices,[optionIndex]:next};}
        this.renderPreview();
    }
    renderPreview(){
        const groups=this.previewConfig();let unit=0;try{unit=ProductComplementsEditor.cents(this.root.dataset.basePrice||'0');}catch{}
        const html=groups.map((g,i)=>{
            const choices=this.edit.choices[i]||{},count=Object.values(choices).reduce((a,b)=>a+Number(b),0);let options=g.opcoes.map((o,n)=>{
                const qty=Number(choices[n]||0);unit+=ProductComplementsEditor.cents(o.preco_adicional)*qty;
                const price=Number(o.preco_adicional)>0?'+ '+ProductComplementsEditor.money(o.preco_adicional):'Sem acréscimo';
                const pick=Number(g.maximo)===1||Number(o.max_quantidade)===1;
                return '<div class="ce-preview-option">'+(pick?'<button type="button" class="ce-pick '+(qty?'selected':'')+'" data-ce="preview-toggle" data-index="'+i+'" data-option="'+n+'" role="'+(Number(g.maximo)===1?'radio':'checkbox')+'" aria-checked="'+Boolean(qty)+'"><span class="ce-pick-mark">'+(qty?'✓':'')+'</span><span>'+this.esc(o.nome)+'</span><strong>'+price+'</strong></button>':'<span>'+this.esc(o.nome)+'<small>'+price+'</small></span><div class="ce-preview-qty">'+this.button('preview-minus','−',i,n)+'<output>'+qty+'</output>'+this.button('preview-plus','+',i,n)+'</div>')+'</div>';
            }).join('');
            return '<section class="ce-preview-group"><h4>'+this.esc(g.nome)+(g.minimo>0?' *':'')+'</h4><p class="ce-meta">'+this.esc(g.descricao||this.rules(g))+' · '+count+'/'+g.maximo+'</p>'+options+(count<g.minimo?'<p class="ce-preview-error">Escolha pelo menos '+g.minimo+' unidade(s).</p>':'')+'</section>';
        }).join('');
        this.root.innerHTML='<div class="ce-preview-screen"><div class="ce-preview-top"><div><span class="ce-eyebrow">Prévia · sem criar pedido</span><h3>'+this.esc(this.root.dataset.productName||'Produto')+'</h3><p>Preço base: '+ProductComplementsEditor.money(this.root.dataset.basePrice||0)+'</p></div>'+this.button('preview-close','Fechar')+'</div>'+html+'<div data-error role="alert"></div><div class="ce-preview-footer"><div class="ce-preview-qty">'+this.button('preview-product-minus','−')+'<output>'+this.edit.quantity+'</output>'+this.button('preview-product-plus','+')+'</div><strong>Total: '+ProductComplementsEditor.money(unit*this.edit.quantity/100)+'</strong>'+this.button('preview-check','Validar escolhas','','',true)+'</div><p class="ce-intro">A prévia não adiciona ao carrinho e não altera estoque.</p></div>';
    }
    async checkPreview(){
        const config=this.previewConfig(),selected=config.map((g,i)=>({grupo_id:i+1,opcoes:Object.entries(this.edit.choices[i]||{}).filter(([,qty])=>qty>0).map(([index,qty])=>({opcao_id:Number(index)+1,quantidade:Number(qty)}))}));
        try{const j=await this.request('complementos_previa',{produto_id:String(this.productId),preco_base:String(this.root.dataset.basePrice||'0'),grupos:JSON.stringify(config),selecoes:JSON.stringify(selected)});this.root.querySelector('[data-error]').innerHTML='<div class="ce-feedback" role="status">'+this.esc('Escolhas válidas. Preço unitário: '+ProductComplementsEditor.money(Number(j.preco_centavos)/100)+'. Nenhum pedido foi criado.')+'</div>';}
        catch(e){this.error(e.message);}
    }
    hasUnsavedChanges(){return this.dirty||Boolean(this.edit?.changed||this.edit?.parent?.changed);}
    validate(){
        if(!this.ready)return false;if(this.edit)return false;const bad=this.groups.find(g=>ProductComplementsEditor.problem(g));if(bad){this.render();this.message('Corrija o grupo '+bad.nome+' antes de salvar.');return false;}return true;
    }
    summaryHTML(){return this.groups.length?this.groups.map((g,i)=>this.groupCard(g,i,true)).join(''):'<p class="ce-intro">Sem personalização.</p>';}
    async save(productId){
        if(!this.validate())throw Error('Conclua a edição e corrija as regras dos grupos antes de salvar.');if(!this.dirty)return;if(this.saving)throw Error('Aguarde o salvamento.');this.saving=true;
        try{const j=await this.request('complementos_produto_salvar',{produto_id:String(productId),grupos:JSON.stringify(this.groups),rascunho_token:this.saveToken});this.productId=Number(productId);this.groups=j.grupos;this.groups.forEach(g=>g.alterado=false);const map=new Map(this.library.map(g=>[Number(g.id),g]));this.groups.forEach(g=>map.set(Number(g.id),ProductComplementsEditor.clone(g)));this.library=Array.from(map.values());this.dirty=false;this.saveToken=ProductComplementsEditor.token();this.saving=false;this.render();}
        catch(e){
            this.saving=false;
            if(e.status===409){
                try{const j=await this.request('complementos_biblioteca');const index=this.groups.findIndex(g=>g.id&&g.alterado&&j.grupos.some(f=>f.id===g.id&&f.versao!==g.versao));
                    if(index>=0){const draft=ProductComplementsEditor.clone(this.groups[index]),fresh=j.grupos.find(f=>f.id===draft.id);Object.assign(draft,{versao:fresh.versao,qtd_produtos:fresh.qtd_produtos,produtos:fresh.produtos});this.edit={kind:'impact',index,group:draft,changed:true};this.renderEdit();}
                }catch{}
            }
            this.message(e.message);throw e;
        }
    }
}
window.ProductComplementsEditor=ProductComplementsEditor;
