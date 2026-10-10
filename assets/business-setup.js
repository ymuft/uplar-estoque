(function(){
    'use strict';
    const settings=window.UPLAR_BUSINESS_SETUP||{};
    if(!settings.profile?.ready)return;
    const catalog=settings.catalog||{},profile=settings.profile||{};
    const byId=id=>document.getElementById(id);
    let segment=profile.segmento&&catalog[profile.segmento]?profile.segmento:null;
    let editedCategories=Array.isArray(profile.categorias_pendentes)?profile.categorias_pendentes.slice():[];
    let categoriesDefined=profile.categorias_definidas===true;
    const choiceRadios=[...document.querySelectorAll('[name="segmento"]')];
    const notify=(message)=>{
        const error=byId('bs-error');
        error.textContent=message;error.hidden=false;
    };
    const showStep=step=>{
        for(let i=1;i<=3;i++){byId('bs-step-'+i).hidden=i!==step;}
        document.querySelectorAll('[data-progress]').forEach(el=>{
            const n=Number(el.dataset.progress);
            el.classList.toggle('active',n===step);
            el.classList.toggle('done',n<step);
        });
        byId('bs-error').hidden=true;
        window.scrollTo({top:0,behavior:'instant'});
    };
    const isFood=()=>catalog[segment]?.familia==='alimentacao';
    const categoriesFromUI=()=>[...byId('bs-category-list').querySelectorAll('input')].map(el=>el.value.trim()).filter(Boolean);
    const addCategory=name=>{
        const row=document.createElement('div');row.className='bs-cat-row';
        const input=document.createElement('input');input.type='text';input.maxLength=80;input.value=name||'';input.setAttribute('aria-label','Nome da categoria');
        const remove=document.createElement('button');remove.type='button';remove.textContent='Remover';remove.setAttribute('aria-label','Remover categoria');
        remove.addEventListener('click',()=>row.remove());row.append(input,remove);byId('bs-category-list').append(row);
    };
    const showConfig=()=>{
        if(!segment||!catalog[segment])return;
        const item=catalog[segment];
        byId('bs-profile-heading').textContent='Sua '+item.nome.toLowerCase()+' na Uplar';
        byId('bs-mode-label').textContent={cardapio:'Cardápio',loja:'Loja / e-commerce',padrao:'Padrão'}[item.modo_sugerido]||'Padrão';
        byId('bs-category-list').replaceChildren();
        const initial=categoriesDefined?editedCategories:item.categorias_sugeridas||[];
        initial.forEach(addCategory);
        byId('bs-service-block').hidden=!isFood();
        const pending=profile.preferencias_atendimento_pendentes||{};
        byId('bs-delivery').checked=pending.entrega===true;
        byId('bs-pickup').checked=pending.retirada===true;
        byId('bs-apply-mode').checked=profile.status_configuracao!=='concluida';
        showStep(2);
    };
    const post=async(action,values)=>{
        const payload=new FormData();
        payload.append('csrf_token',settings.csrf||'');
        payload.append('action',action);
        payload.append('segmento',segment||'');
        payload.append('categorias',JSON.stringify(values.categorias||[]));
        payload.append('categorias_definidas',values.categorias_definidas?'1':'0');
        payload.append('aplicar_modo',values.aplicar_modo?'1':'0');
        payload.append('entrega',values.entrega?'1':'0');
        payload.append('retirada',values.retirada?'1':'0');
        const response=await fetch('business_profile_api.php',{method:'POST',credentials:'same-origin',body:payload});
        const json=await response.json().catch(()=>({ok:false,error:'Resposta inválida do servidor.'}));
        if(!response.ok||!json.ok)throw new Error(json.error||'Não foi possível salvar.');
        return json;
    };
    const values=()=>({categorias:categoriesFromUI(),categorias_definidas:true,aplicar_modo:byId('bs-apply-mode').checked,
        entrega:isFood()&&byId('bs-delivery').checked,retirada:isFood()&&byId('bs-pickup').checked});
    const busy=(el,active)=>{if(el){el.disabled=active;el.setAttribute('aria-busy',active?'true':'false')}};
    choiceRadios.forEach(radio=>radio.addEventListener('change',()=>{
        if(segment!==radio.value){editedCategories=[];categoriesDefined=false;}
        segment=radio.value;byId('bs-next').disabled=false;
    }));
    if(segment){const sel=choiceRadios.find(el=>el.value===segment);if(sel){sel.checked=true;byId('bs-next').disabled=false;}}
    byId('bs-next').addEventListener('click',async()=>{
        const button=byId('bs-next');busy(button,true);
        try{
            if(profile.status_configuracao!=='concluida'){
                await post('draft',{categorias:editedCategories,categorias_definidas:categoriesDefined,aplicar_modo:false});
            }
            showConfig();
        }
        catch(e){alert(e.message);}
        finally{busy(button,false);}
    });
    byId('bs-back').addEventListener('click',()=>{editedCategories=categoriesFromUI();categoriesDefined=true;showStep(1)});
    byId('bs-add-category').addEventListener('click',()=>addCategory(''));
    if(profile.status_configuracao==='concluida')byId('bs-save-later').textContent='Sair sem aplicar';
    byId('bs-save-later').addEventListener('click',async()=>{
        const button=byId('bs-save-later');busy(button,true);
        try{
            if(profile.status_configuracao!=='concluida') await post('draft',values());
            location.href='estoque.php';
        }
        catch(e){notify(e.message);busy(button,false);}
    });
    byId('bs-confirm').addEventListener('click',async()=>{
        const button=byId('bs-confirm');busy(button,true);
        try{
            await post('complete',values());
            const tasks=isFood()
                ? ['Cadastre seu primeiro produto.','Configure grupos de adicionais.','Organize as categorias no catálogo.','Informe atendimento e horários.','Revise sua vitrine antes de publicar.']
                : ['Cadastre seus produtos.','Organize variações e estoque.','Defina as categorias.','Personalize a vitrine.','Revise antes de publicar.'];
            const root=byId('bs-done-tasks');root.replaceChildren();
            tasks.forEach(label=>{const p=document.createElement('p');p.textContent=label;root.append(p)});
            byId('bs-done-subtitle').textContent='Seu perfil '+(catalog[segment]?.nome||'')+' foi salvo.';
            showStep(3);
        }catch(e){notify(e.message);}
        finally{busy(button,false);}
    });
    if(profile.status_configuracao==='concluida')byId('bs-skip').textContent='Cancelar alterações';
    byId('bs-skip').addEventListener('click',async()=>{
        const button=byId('bs-skip');busy(button,true);
        try{
            if(profile.status_configuracao!=='concluida') await post('dismiss',{categorias:[],aplicar_modo:false});
            location.href='estoque.php';
        }
        catch(e){alert(e.message);busy(button,false);}
    });
    if(profile.status_configuracao==='em_andamento'&&segment)showConfig();
    else showStep(1);
})();
