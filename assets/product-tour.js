(function(){
    const config=window.UPLAR_TOUR_CONFIG||{};
    let tour=null,openedSidebar=false,seenSent=false;

    const steps=[
        {element:'.sb-link[href="estoque.php"]',popover:{title:'Dashboard',description:'Acompanhe o resumo da operação: vendas, pedidos, produtos, clientes e os principais indicadores.',side:'right',align:'start'}},
        {element:'.sb-link[href="novidades.php"]',popover:{title:'Novidades',description:'Veja as melhorias e recursos novos que chegam à Uplar.',side:'right',align:'start'}},
        {element:'.sb-link[href="relatorio.php"]',popover:{title:'Relatórios',description:'Consulte relatórios para entender o desempenho e tomar decisões com mais clareza.',side:'right',align:'start'}},
        {element:'.sb-link[href="produtos.php"]',popover:{title:'Produtos',description:'Cadastre produtos, organize preços, estoque, validade e categorias.',side:'right',align:'start'}},
        {element:'.sb-link[href="vendedores.php"]',popover:{title:'Vendedores',description:'Cadastre vendedores e acompanhe a participação de cada um nas vendas.',side:'right',align:'start'}},
        {element:'.sb-link[href="cliente.php"]',popover:{title:'Clientes',description:'Mantenha seus clientes organizados e consulte o histórico de relacionamento.',side:'right',align:'start'}},
        {element:'.sb-link[href="financeiro.php"]',popover:{title:'Financeiro',description:'Controle receitas, despesas, fluxo de caixa, comissões e resultados.',side:'right',align:'start'}},
        {element:'.sb-link[href="vitrine_config.php"]',popover:{title:'Minha Vitrine',description:'Personalize sua loja, escolha os produtos expostos e copie o link para compartilhar.',side:'right',align:'start'}},
        {element:'.sb-link[href="vitrine_metricas.php"]',popover:{title:'Métricas da Vitrine',description:'Acompanhe visitas, produtos visualizados e o interesse dos clientes na sua loja.',side:'right',align:'start'}},
        {element:'.sb-link[href="pedidos.php"]',popover:{title:'Pedidos',description:'Os pedidos feitos pela sua vitrine aparecem aqui para confirmação e acompanhamento.',side:'right',align:'start'}},
        {element:'.sb-link[href="config.php"]',popover:{title:'Configurações',description:'Revise os dados da conta, WhatsApp, empresa e assinatura.',side:'right',align:'start'}},
        {element:'#logoutButton',popover:{title:'Sair',description:'Use este botão para encerrar seu acesso com segurança.',side:'right',align:'start'}},
    ];

    const markSeen=()=>{
        if(seenSent)return;
        seenSent=true;
        const body=new FormData();body.append('action','tour_seen');body.append('csrf_token',config.csrfToken||'');
        fetch('onboarding_api.php',{method:'POST',body,credentials:'same-origin'}).catch(()=>{});
        const invite=document.querySelector('.onboarding-tour-invite');
        if(invite){
            invite.querySelector('strong').textContent='Rever a plataforma?';
            invite.querySelector('[data-onboarding-tour]').textContent='Repetir tour';
        }
    };

    const openMobileSidebar=()=>{
        if(window.innerWidth>860)return;
        const sidebar=document.getElementById('sb'),overlay=document.getElementById('ov');
        if(sidebar&&!sidebar.classList.contains('open')){
            sidebar.classList.add('open');overlay?.classList.add('on');openedSidebar=true;
        }
    };

    const closeTour=()=>{tour?.destroy();};

    const start=()=>{
        if(!window.driver?.js?.driver){
            console.error('Driver.js não foi carregado.');
            return;
        }
        window.closeSidebarProfile?.();
        closeTour();
        openedSidebar=false;seenSent=false;openMobileSidebar();
        const available=steps.filter(step=>document.querySelector(step.element));
        if(!available.length)return;
        tour=window.driver.js.driver({
            animate:true,
            allowClose:true,
            overlayColor:'#0f1c2e',
            overlayOpacity:.68,
            stagePadding:5,
            stageRadius:9,
            popoverClass:'uplar-tour-popover',
            showProgress:true,
            progressText:'{{current}} de {{total}}',
            nextBtnText:'Próximo',
            prevBtnText:'Voltar',
            doneBtnText:'Concluir',
            steps:available,
            onPopoverRender:(popover)=>{
                const skip=document.createElement('button');
                skip.type='button';skip.className='uplar-tour-skip';skip.textContent='Pular tour';
                skip.addEventListener('click',closeTour);
                popover.footerButtons.prepend(skip);
            },
            onDestroyed:()=>{
                markSeen();
                if(openedSidebar){document.getElementById('sb')?.classList.remove('open');document.getElementById('ov')?.classList.remove('on');openedSidebar=false;}
                tour=null;
            },
        });
        tour.drive();
    };

    window.UplarTour={start,close:closeTour};
    document.getElementById('sidebarTourButton')?.addEventListener('click',start);
    if(config.autoStart)setTimeout(start,450);
})();
