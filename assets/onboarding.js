(function(){
    const card=document.getElementById('onboardingCard');
    if(!card)return;
    const csrf=card.dataset.csrf||'';
    const post=async action=>{
        const body=new FormData();body.append('action',action);body.append('csrf_token',csrf);
        const response=await fetch('onboarding_api.php',{method:'POST',body,credentials:'same-origin'});
        const data=await response.json().catch(()=>({ok:false}));
        if(!response.ok||!data.ok)throw new Error(data.error||'Não foi possível atualizar o guia.');
        return data;
    };
    const refreshProgress=()=>{
        const steps=[...card.querySelectorAll('.onboarding-step')],done=steps.filter(step=>step.classList.contains('done')).length;
        const percent=Math.round((done/steps.length)*100),copy=card.querySelector('.onboarding-progress-copy'),bar=card.querySelector('.onboarding-progress span'),progress=card.querySelector('.onboarding-progress');
        if(copy){copy.querySelector('strong').textContent=`${done} de ${steps.length} etapas concluídas`;copy.querySelector('span').textContent=`${percent}%`}
        if(bar){bar.style.setProperty('--progress',`${percent}%`);bar.style.width=`${percent}%`}
        progress?.setAttribute('aria-valuenow',String(percent));
    };
    const completeStep=key=>{
        const step=card.querySelector(`[data-step="${key}"]`);if(!step||step.classList.contains('done'))return;
        step.classList.add('done');const icon=step.querySelector('.onboarding-check');if(icon)icon.textContent='check';step.querySelector('.onboarding-step-action')?.remove();refreshProgress();
    };
    const collapse=document.getElementById('onboardingCollapse');
    collapse?.addEventListener('click',()=>{
        const collapsed=card.classList.toggle('is-collapsed');collapse.setAttribute('aria-expanded',collapsed?'false':'true');collapse.title=collapsed?'Expandir primeiros passos':'Recolher primeiros passos';collapse.querySelector('.material-symbols-outlined').textContent=collapsed?'expand_more':'expand_less';post(collapsed?'collapse':'expand').catch(()=>{});
    });
    document.getElementById('onboardingDismiss')?.addEventListener('click',()=>{
        card.style.transition='opacity .2s,transform .2s';card.style.opacity='0';card.style.transform='translateY(-5px)';post('dismiss').then(()=>setTimeout(()=>card.remove(),210)).catch(()=>{card.style.opacity='1';card.style.transform='' });
    });
    card.querySelectorAll('[data-onboarding-add-product]').forEach(button=>button.addEventListener('click',()=>{
        const add=document.getElementById('btnNovo');if(add){add.click()}else{location.href='produtos.php'}
    }));
    card.querySelectorAll('[data-onboarding-tour]').forEach(button=>button.addEventListener('click',()=>window.UplarTour?.start()));
    card.querySelectorAll('[data-onboarding-share]').forEach(button=>button.addEventListener('click',async()=>{
        const url=new URL(button.dataset.shareUrl||'/',location.origin).href;
        try{
            if(navigator.share){await navigator.share({title:'Minha loja',text:'Conheça minha loja:',url})}
            else if(navigator.clipboard){await navigator.clipboard.writeText(url);button.textContent='Link copiado!'}
            else{window.prompt('Copie o link da sua loja:',url);return}
            await post('share');completeStep('share');
        }catch(error){if(error?.name!=='AbortError')button.textContent='Tentar novamente'}
    }));
    window.UplarOnboarding={productAdded:()=>completeStep('products')};
    if(card.classList.contains('is-complete'))setTimeout(()=>post('celebration_seen').catch(()=>{}),1400);
})();
