(() => {
  'use strict';
  const config = window.UPLAR_CHANGELOG_ADMIN;
  const form = document.getElementById('changelogForm');
  if (!config || !form) return;
  const itemsContainer = document.getElementById('adminItems');
  const template = document.getElementById('adminItemTemplate');
  const feedback = document.getElementById('adminFeedback');
  const previewOverlay = document.getElementById('adminPreviewOverlay');
  const previewDialog = document.getElementById('adminPreviewDialog');
  const previewContent = document.getElementById('adminPreviewContent');
  const categories = {new:['Novo','add_circle'],improvement:['Melhoria','auto_awesome'],fix:['Correção','build_circle'],security:['Segurança','verified_user'],performance:['Desempenho','speed']};
  let nextIndex = 0;
  let previewUrls = [];
  let previousFocus = null;

  function showFeedback(message, type) {
    feedback.textContent = message;
    feedback.className = `admin-feedback is-visible is-${type}`;
    feedback.scrollIntoView({behavior:'smooth', block:'nearest'});
  }
  function renumber() {
    [...itemsContainer.children].forEach((item, index) => item.querySelector('.item-number').textContent = String(index + 1));
    itemsContainer.querySelectorAll('.remove-item').forEach(button => button.disabled = itemsContainer.children.length === 1);
  }
  function addItem() {
    if (itemsContainer.children.length >= 20) return showFeedback('O limite é de 20 alterações por changelog.', 'error');
    const item = template.content.firstElementChild.cloneNode(true);
    item.dataset.index = String(nextIndex++);
    item.querySelector('.remove-item').addEventListener('click', () => { item.remove(); renumber(); });
    item.querySelector('[data-field="is_featured"]').addEventListener('change', event => {
      if (!event.target.checked) return;
      itemsContainer.querySelectorAll('[data-field="is_featured"]').forEach(input => { if (input !== event.target) input.checked = false; });
    });
    itemsContainer.append(item);
    renumber();
  }
  function collectItems() {
    return [...itemsContainer.children].map(item => ({
      index: Number(item.dataset.index),
      category: item.querySelector('[data-field="category"]').value,
      title: item.querySelector('[data-field="title"]').value.trim(),
      description: item.querySelector('[data-field="description"]').value.trim(),
      link_url: item.querySelector('[data-field="link_url"]').value.trim(),
      button_text: item.querySelector('[data-field="button_text"]').value.trim(),
      is_featured: item.querySelector('[data-field="is_featured"]').checked,
      file: item.querySelector('[data-field="image"]').files[0] || null
    }));
  }
  function validate(items) {
    if (!form.reportValidity()) return 'Preencha os campos obrigatórios.';
    if (!items.length || items.some(item => !item.title || !item.description)) return 'Preencha o título e a descrição de todas as alterações.';
    for (const item of items) {
      if (!item.file) continue;
      if (!['image/jpeg','image/png','image/webp'].includes(item.file.type)) return 'Formato não suportado. Utilize JPG, PNG ou WebP.';
      if (item.file.size > 4 * 1024 * 1024) return 'Uma das imagens ultrapassa o limite de 4 MB.';
    }
    return '';
  }
  function node(tag, className, text) { const el=document.createElement(tag); if(className)el.className=className; if(text!==undefined)el.textContent=text; return el; }
  function category(item) { const data=categories[item.category]||categories.improvement; const label=node('span',`wn-category wn-category--${item.category}`,data[0]); const icon=node('span','material-symbols-outlined',data[1]); icon.setAttribute('aria-hidden','true'); label.prepend(icon); return label; }
  function previewItem(item, featured) {
    const card=node('article',featured?'wn-featured':'wn-item');
    let copy=card;
    if (!featured) { const iconBox=node('div','wn-item-icon'); iconBox.append(node('span','material-symbols-outlined',(categories[item.category]||categories.improvement)[1])); copy=node('div'); card.append(iconBox,copy); }
    if (featured && item.file) { const image=node('img','wn-featured-media'); const url=URL.createObjectURL(item.file); previewUrls.push(url); image.src=url; image.alt=''; card.append(image); copy=node('div','wn-featured-copy'); card.append(copy); }
    else if (featured) { copy=node('div','wn-featured-copy'); card.append(copy); }
    copy.append(category(item),node('h3','wn-item-title',item.title),node('p','wn-item-description',item.description));
    if (!featured && item.file) { const image=node('img','wn-item-media'); const url=URL.createObjectURL(item.file); previewUrls.push(url); image.src=url; image.alt=''; copy.append(image); }
    if (item.link_url) { const link=node('span','wn-link',item.button_text||'Conhecer funcionalidade'); link.append(node('span','material-symbols-outlined','arrow_forward')); copy.append(link); }
    return card;
  }
  function clearPreviewUrls() { previewUrls.forEach(url => URL.revokeObjectURL(url)); previewUrls=[]; }
  function openPreview() {
    const items=collectItems(), error=validate(items); if(error)return showFeedback(error,'error');
    const field = name => form.elements.namedItem(name);
    clearPreviewUrls(); previousFocus=document.activeElement;
    document.getElementById('adminPreviewTitle').textContent=field('title').value;
    document.getElementById('adminPreviewIntro').textContent=field('intro').value;
    const date=field('published_at').value ? new Date(field('published_at').value).toLocaleDateString('pt-BR',{day:'numeric',month:'long',year:'numeric'}) : '';
    document.getElementById('adminPreviewMeta').textContent=[field('version').value&&`Versão ${field('version').value}`,date].filter(Boolean).join(' · ');
    document.getElementById('adminPreviewBadge').textContent=field('importance').value==='critical'?'ATUALIZAÇÃO CRÍTICA':'NOVIDADE';
    previewDialog.classList.toggle('wn-dialog--critical',field('importance').value==='critical');
    previewContent.replaceChildren(); const featured=items.find(item=>item.is_featured);
    if(featured)previewContent.append(previewItem(featured,true)); const list=node('div','wn-items'); items.filter(item=>item!==featured).forEach(item=>list.append(previewItem(item,false))); previewContent.append(list);
    previewOverlay.hidden=false;previewOverlay.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';requestAnimationFrame(()=>{previewOverlay.classList.add('is-open');document.getElementById('closeAdminPreview').focus()});
  }
  function closePreview() { previewOverlay.classList.remove('is-open');setTimeout(()=>{previewOverlay.hidden=true;previewOverlay.setAttribute('aria-hidden','true');document.body.style.overflow='';clearPreviewUrls();previousFocus?.focus?.()},190); }

  document.getElementById('addItem').addEventListener('click', addItem);
  document.getElementById('previewChangelog').addEventListener('click', openPreview);
  document.getElementById('closeAdminPreview').addEventListener('click', closePreview);
  document.getElementById('adminPreviewDone').addEventListener('click', closePreview);
  previewOverlay.addEventListener('mousedown', event => {if(event.target===previewOverlay)closePreview()});
  document.addEventListener('keydown', event => {
    if(previewOverlay.hidden)return;
    if(event.key==='Escape'){event.preventDefault();closePreview();return}
    if(event.key!=='Tab')return;const focusable=[...previewDialog.querySelectorAll('button:not([disabled]),a[href]')];const first=focusable[0],last=focusable.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
  });

  form.addEventListener('submit', async event => {
    event.preventDefault(); const items=collectItems(), error=validate(items); if(error)return showFeedback(error,'error');
    const button=document.getElementById('saveChangelog');button.disabled=true;button.textContent='Salvando…';
    const data=new FormData(form);data.append('action','admin_create');data.append('csrf_token',config.csrfToken);
    data.append('items_json',JSON.stringify(items.map(({file,...item})=>item)));
    items.forEach(item=>{if(item.file)data.append(`item_image_${item.index}`,item.file,item.file.name)});
    try { const response=await fetch(config.endpoint,{method:'POST',body:data,credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'Não foi possível salvar a atualização.');showFeedback('Atualização salva com sucesso.','success');setTimeout(()=>location.reload(),650); }
    catch(error){showFeedback(error.message||'Não foi possível salvar a atualização.','error');button.disabled=false;button.innerHTML='<span class="material-symbols-outlined" aria-hidden="true">publish</span>Salvar atualização';}
  });

  const storyOverlay = document.getElementById('storyOverlay');
  const storyDialog = document.getElementById('storyDialog');
  const storyCanvas = document.getElementById('storyCanvas');
  const storyLoading = document.getElementById('storyLoading');
  const downloadStory = document.getElementById('downloadStory');
  const shareStory = document.getElementById('shareStory');
  const storyTitleEdit = document.getElementById('storyTitleEdit');
  const storyIntroEdit = document.getElementById('storyIntroEdit');
  const storyItemsEditor = document.getElementById('storyItemsEditor');
  const storySelectedCount = document.getElementById('storySelectedCount');
  const storyEditorHelp = document.getElementById('storyEditorHelp');
  let storyData = null;
  let storyBlob = null;
  let storyPreviousFocus = null;
  let storyTheme = 'dark';
  let storyEditItems = [];
  let storyRenderTimer = null;
  let storyRenderVersion = 0;
  let storyCurrentDraft = null;

  function roundedRect(context, x, y, width, height, radius) {
    const safeRadius = Math.min(radius, width / 2, height / 2);
    context.beginPath();
    context.moveTo(x + safeRadius, y);
    context.arcTo(x + width, y, x + width, y + height, safeRadius);
    context.arcTo(x + width, y + height, x, y + height, safeRadius);
    context.arcTo(x, y + height, x, y, safeRadius);
    context.arcTo(x, y, x + width, y, safeRadius);
    context.closePath();
  }

  function storyLines(context, value, maxWidth, maxLines) {
    const words = String(value || '').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean);
    const lines = [];
    let current = '';
    for (const word of words) {
      const candidate = current ? `${current} ${word}` : word;
      if (context.measureText(candidate).width <= maxWidth || !current) {
        current = candidate;
        continue;
      }
      lines.push(current);
      current = word;
      if (lines.length === maxLines) break;
    }
    if (lines.length < maxLines && current) lines.push(current);
    if (words.length && lines.length === maxLines) {
      const rendered = lines.join(' ').split(' ').length;
      if (rendered < words.length) {
        let last = lines[maxLines - 1];
        while (last.length > 1 && context.measureText(`${last}…`).width > maxWidth) {
          last = last.slice(0, -1).trimEnd();
        }
        lines[maxLines - 1] = `${last}…`;
      }
    }
    return lines;
  }

  function drawStoryLines(context, lines, x, y, lineHeight) {
    lines.forEach((line, index) => context.fillText(line, x, y + (index * lineHeight)));
    return y + (lines.length * lineHeight);
  }

  function storyCategoryLabel(categoryName) {
    return {
      new:'NOVO',
      improvement:'MELHORIA',
      fix:'CORREÇÃO',
      security:'SEGURANÇA',
      performance:'DESEMPENHO'
    }[categoryName] || 'MELHORIA';
  }

  function storyFileName(title) {
    const slug = String(title || 'novidades-uplar')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
      .slice(0, 70);
    return `${slug || 'novidades-uplar'}-story.png`;
  }

  function drawCloudSymbol(context, x, y, size) {
    context.fillStyle = '#3b6ff5';
    roundedRect(context, x, y, size, size, 14);
    context.fill();
    context.fillStyle = '#ffffff';
    context.beginPath();
    context.moveTo(x + (size * .25), y + (size * .68));
    context.bezierCurveTo(x + (size * .10), y + (size * .68), x + (size * .08), y + (size * .43), x + (size * .28), y + (size * .39));
    context.bezierCurveTo(x + (size * .34), y + (size * .17), x + (size * .65), y + (size * .16), x + (size * .73), y + (size * .39));
    context.bezierCurveTo(x + (size * .91), y + (size * .40), x + (size * .94), y + (size * .68), x + (size * .75), y + (size * .68));
    context.closePath();
    context.fill();
  }

  function drawStory(changelog) {
    const context = storyCanvas.getContext('2d', {alpha:false});
    const width = storyCanvas.width;
    const height = storyCanvas.height;
    const isLight = changelog.theme === 'light';
    const items = Array.isArray(changelog.items) ? changelog.items.slice(0, 3) : [];
    const colors = isLight
      ? {background:'#f3f6fb', text:'#102038', muted:'#5f6f83', grid:'rgba(65,85,112,.10)', panel:'#0e1726', panelText:'#f8fafc', panelMuted:'#aeb9c8', divider:'#2b394d', footer:'#5f6f83'}
      : {background:'#0e1726', text:'#ffffff', muted:'#aeb9c8', grid:'rgba(148,163,184,.10)', panel:'#f5f7fb', panelText:'#172033', panelMuted:'#64748b', divider:'#dce3ed', footer:'#7f8da1'};

    context.fillStyle = colors.background;
    context.fillRect(0, 0, width, height);
    context.fillStyle = '#3b6ff5';
    context.fillRect(0, 0, width, 18);
    context.fillRect(950, 96, 60, 190);
    context.strokeStyle = colors.grid;
    context.lineWidth = 1;
    for (let x = 70; x <= 1010; x += 94) {
      context.beginPath();
      context.moveTo(x, 18);
      context.lineTo(x, 820);
      context.stroke();
    }

    drawCloudSymbol(context, 70, 94, 64);
    context.fillStyle = colors.text;
    context.font = '800 31px Inter, Arial, sans-serif';
    context.fillText('UPLAR', 154, 137);
    context.fillStyle = colors.muted;
    context.font = '700 18px Inter, Arial, sans-serif';
    context.fillText('GESTÃO QUE VENDE', 154, 162);

    context.fillStyle = '#3b6ff5';
    context.font = '800 19px Inter, Arial, sans-serif';
    context.fillText('NOVIDADES NA PLATAFORMA', 70, 270);
    context.fillStyle = colors.text;
    context.font = '800 72px Inter, Arial, sans-serif';
    const titleLines = storyLines(context, changelog.title, 880, 3);
    const titleBottom = drawStoryLines(context, titleLines, 70, 360, 82);

    context.fillStyle = colors.muted;
    context.font = '500 29px Inter, Arial, sans-serif';
    const introLines = storyLines(context, changelog.intro, 875, Math.min(3, Math.max(2, 6 - titleLines.length)));
    drawStoryLines(context, introLines, 70, titleBottom + 28, 43);

    const panelX = 70;
    const panelY = 820;
    const panelWidth = 940;
    const panelHeight = 850;
    context.fillStyle = colors.panel;
    roundedRect(context, panelX, panelY, panelWidth, panelHeight, 24);
    context.fill();
    context.fillStyle = colors.panelMuted;
    context.font = '800 18px Inter, Arial, sans-serif';
    context.fillText('DETALHES DA ATUALIZAÇÃO', 112, 886);
    context.fillStyle = colors.divider;
    context.fillRect(112, 910, 856, 2);

    const rowHeight = items.length > 2 ? 225 : 270;
    items.forEach((item, index) => {
      const rowY = 970 + (index * rowHeight);
      context.fillStyle = '#3b6ff5';
      roundedRect(context, 112, rowY - 25, 52, 52, 12);
      context.fill();
      context.fillStyle = '#ffffff';
      context.font = '800 21px Inter, Arial, sans-serif';
      context.textAlign = 'center';
      context.fillText(String(index + 1).padStart(2, '0'), 138, rowY + 8);
      context.textAlign = 'left';

      context.fillStyle = item.category === 'security' ? '#12a276' : '#3b6ff5';
      context.font = '800 15px Inter, Arial, sans-serif';
      context.fillText(storyCategoryLabel(item.category), 190, rowY - 10);
      context.fillStyle = colors.panelText;
      context.font = '750 27px Inter, Arial, sans-serif';
      const itemTitleLines = storyLines(context, item.title, 740, 2);
      const itemTitleBottom = drawStoryLines(context, itemTitleLines, 190, rowY + 29, 34);
      context.fillStyle = colors.panelMuted;
      context.font = '500 21px Inter, Arial, sans-serif';
      const descriptionLines = storyLines(context, item.description, 740, items.length > 2 ? 3 : 4);
      drawStoryLines(context, descriptionLines, 190, itemTitleBottom + 8, 29);

      if (index < items.length - 1) {
        context.fillStyle = colors.divider;
        context.fillRect(190, rowY + rowHeight - 47, 778, 2);
      }
    });

    const remaining = Math.max(0, Number(changelog.total_items || 0) - items.length);
    if (remaining > 0) {
      context.fillStyle = colors.panelMuted;
      context.font = '700 18px Inter, Arial, sans-serif';
      context.fillText(`+ ${remaining} ${remaining === 1 ? 'adição' : 'adições'} no changelog completo`, 112, 1642);
    }

    context.fillStyle = colors.footer;
    context.font = '700 19px Inter, Arial, sans-serif';
    context.fillText(String(changelog.date_label || '').toUpperCase(), 70, 1780);
    context.fillStyle = colors.text;
    context.font = '800 34px Inter, Arial, sans-serif';
    context.fillText('uplar.cloud', 70, 1842);
    context.fillStyle = '#3b6ff5';
    roundedRect(context, 754, 1784, 256, 62, 12);
    context.fill();
    context.fillStyle = '#ffffff';
    context.font = '800 17px Inter, Arial, sans-serif';
    context.textAlign = 'center';
    context.fillText('CONFIRA NO UPLAR', 882, 1823);
    context.textAlign = 'left';
  }

  function storyCanvasBlob() {
    return new Promise((resolve, reject) => {
      storyCanvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Não foi possível gerar a imagem.')), 'image/png');
    });
  }

  function updateStorySelectionState() {
    const selected = storyEditItems.filter(item => item.selected).length;
    storySelectedCount.textContent = `${selected} de 3`;
    storyItemsEditor.querySelectorAll('[data-story-item-index]').forEach(card => {
      const item = storyEditItems[Number(card.dataset.storyItemIndex)];
      card.classList.toggle('is-off', !item?.selected);
    });
  }

  function renderStoryItemsEditor() {
    storyItemsEditor.replaceChildren();
    storyEditItems.forEach((item, index) => {
      const card = document.createElement('article');
      card.className = `story-item-editor${item.selected ? '' : ' is-off'}`;
      card.dataset.storyItemIndex = String(index);

      const toggle = document.createElement('label');
      toggle.className = 'story-item-toggle';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = item.selected;
      const label = document.createElement('span');
      label.textContent = `Exibir adição ${index + 1}`;
      toggle.append(checkbox, label);

      const title = document.createElement('textarea');
      title.rows = 2;
      title.maxLength = 160;
      title.value = item.title;
      title.setAttribute('aria-label', `Título da adição ${index + 1}`);

      const description = document.createElement('textarea');
      description.rows = 3;
      description.maxLength = 1000;
      description.value = item.description;
      description.setAttribute('aria-label', `Detalhes da adição ${index + 1}`);

      checkbox.addEventListener('change', () => {
        const selectedCount = storyEditItems.filter(entry => entry.selected).length;
        if (checkbox.checked && selectedCount >= 3) {
          checkbox.checked = false;
          storyEditorHelp.textContent = 'Você pode exibir no máximo três adições por Story.';
          storyEditorHelp.classList.add('is-error');
          return;
        }
        item.selected = checkbox.checked;
        storyEditorHelp.textContent = 'Escolha até três adições. Título e detalhes podem ser ajustados somente para esta imagem.';
        storyEditorHelp.classList.remove('is-error');
        updateStorySelectionState();
        scheduleStoryRender();
      });
      title.addEventListener('input', () => {
        item.title = title.value;
        scheduleStoryRender();
      });
      description.addEventListener('input', () => {
        item.description = description.value;
        scheduleStoryRender();
      });
      card.append(toggle, title, description);
      storyItemsEditor.append(card);
    });
    updateStorySelectionState();
  }

  function prepareStoryEditor(changelog) {
    storyTitleEdit.value = changelog.title || '';
    storyIntroEdit.value = changelog.intro || '';
    storyTheme = 'dark';
    document.querySelectorAll('[data-story-theme]').forEach(button => {
      const active = button.dataset.storyTheme === storyTheme;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    const items = Array.isArray(changelog.items) ? changelog.items : [];
    const preferred = [];
    const featuredIndex = items.findIndex(item => item.is_featured);
    if (featuredIndex >= 0) preferred.push(featuredIndex);
    items.forEach((item, index) => {
      if (preferred.length < 3 && !preferred.includes(index)) preferred.push(index);
    });
    storyEditItems = items.map((item, index) => ({
      category:item.category || 'improvement',
      title:item.title || '',
      description:item.description || '',
      selected:preferred.includes(index)
    }));
    storyEditorHelp.textContent = 'Escolha até três adições. Título e detalhes podem ser ajustados somente para esta imagem.';
    storyEditorHelp.classList.remove('is-error');
    renderStoryItemsEditor();
  }

  function editedStoryData() {
    return {
      title:storyTitleEdit.value.trim(),
      intro:storyIntroEdit.value.trim(),
      date_label:storyData?.date_label || '',
      theme:storyTheme,
      total_items:storyEditItems.length,
      items:storyEditItems.filter(item => item.selected).map(item => ({
        category:item.category,
        title:item.title.trim(),
        description:item.description.trim()
      }))
    };
  }

  async function renderEditedStory() {
    const version = ++storyRenderVersion;
    const draft = editedStoryData();
    storyCurrentDraft = draft;
    if (!draft.title || !draft.intro || !draft.items.length || draft.items.some(item => !item.title || !item.description)) {
      downloadStory.disabled = true;
      shareStory.hidden = true;
      storyEditorHelp.textContent = 'Preencha título, resumo e os textos das adições selecionadas.';
      storyEditorHelp.classList.add('is-error');
      return;
    }
    storyEditorHelp.textContent = 'Escolha até três adições. Título e detalhes podem ser ajustados somente para esta imagem.';
    storyEditorHelp.classList.remove('is-error');
    drawStory(draft);
    const blob = await storyCanvasBlob();
    if (version !== storyRenderVersion) return;
    storyBlob = blob;
    downloadStory.disabled = false;
    shareStory.hidden = true;
    if (typeof File === 'function' && navigator.share && navigator.canShare) {
      const file = new File([storyBlob], storyFileName(draft.title), {type:'image/png'});
      shareStory.hidden = !navigator.canShare({files:[file]});
    }
  }

  function scheduleStoryRender() {
    clearTimeout(storyRenderTimer);
    storyRenderVersion++;
    downloadStory.disabled = true;
    shareStory.hidden = true;
    storyRenderTimer = setTimeout(() => {
      renderEditedStory().catch(error => {
        storyEditorHelp.textContent = error.message || 'Não foi possível atualizar a imagem.';
        storyEditorHelp.classList.add('is-error');
      });
    }, 120);
  }

  async function openStory(changelogId, trigger) {
    storyPreviousFocus = trigger;
    storyData = null;
    storyBlob = null;
    downloadStory.disabled = true;
    shareStory.hidden = true;
    storyLoading.hidden = false;
    storyOverlay.hidden = false;
    storyOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => {
      storyOverlay.classList.add('is-open');
      document.getElementById('closeStory').focus();
    });
    try {
      const response = await fetch(`${config.endpoint}?action=admin_detail&changelog_id=${encodeURIComponent(changelogId)}`, {credentials:'same-origin'});
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível carregar esta atualização.');
      storyData = result.changelog;
      await document.fonts?.ready;
      prepareStoryEditor(storyData);
      await renderEditedStory();
    } catch (error) {
      closeStory();
      showFeedback(error.message || 'Não foi possível gerar o Story.', 'error');
    } finally {
      storyLoading.hidden = true;
    }
  }

  function closeStory() {
    clearTimeout(storyRenderTimer);
    storyOverlay.classList.remove('is-open');
    setTimeout(() => {
      storyOverlay.hidden = true;
      storyOverlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      storyPreviousFocus?.focus?.();
    }, 190);
  }

  storyTitleEdit.addEventListener('input', scheduleStoryRender);
  storyIntroEdit.addEventListener('input', scheduleStoryRender);
  document.querySelectorAll('[data-story-theme]').forEach(button => button.addEventListener('click', () => {
    storyTheme = button.dataset.storyTheme === 'light' ? 'light' : 'dark';
    document.querySelectorAll('[data-story-theme]').forEach(item => {
      const active = item === button;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    scheduleStoryRender();
  }));
  document.querySelectorAll('[data-story-id]').forEach(button => button.addEventListener('click', () => openStory(button.dataset.storyId, button)));
  document.getElementById('closeStory').addEventListener('click', closeStory);
  storyOverlay.addEventListener('mousedown', event => { if (event.target === storyOverlay) closeStory(); });
  downloadStory.addEventListener('click', async () => {
    if (!storyCurrentDraft) return;
    const blob = storyBlob || await storyCanvasBlob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = storyFileName(storyCurrentDraft.title);
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
  shareStory.addEventListener('click', async () => {
    if (!storyCurrentDraft || !storyBlob) return;
    const file = new File([storyBlob], storyFileName(storyCurrentDraft.title), {type:'image/png'});
    try {
      await navigator.share({files:[file], title:storyCurrentDraft.title});
    } catch (error) {
      if (error.name !== 'AbortError') showFeedback('Não foi possível abrir o compartilhamento.', 'error');
    }
  });
  document.addEventListener('keydown', event => {
    if (storyOverlay.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeStory();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...storyDialog.querySelectorAll('button:not([disabled]):not([hidden])')];
    const first = focusable[0];
    const last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  document.querySelectorAll('.delete-draft').forEach(button=>button.addEventListener('click',async()=>{
    const card=button.closest('[data-admin-id]');button.disabled=true;const data=new FormData();data.append('action','admin_delete_draft');data.append('changelog_id',card.dataset.adminId);data.append('csrf_token',config.csrfToken);
    try{const response=await fetch(config.endpoint,{method:'POST',body:data,credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error);card.remove();showFeedback('Rascunho excluído.','success')}catch(error){button.disabled=false;showFeedback(error.message||'Não foi possível excluir o rascunho.','error')}
  }));
  const sb=document.getElementById('sb'),ov=document.getElementById('ov');document.getElementById('ham')?.addEventListener('click',()=>{sb?.classList.add('open');ov?.classList.add('show')});ov?.addEventListener('click',()=>{sb?.classList.remove('open');ov.classList.remove('show')});document.getElementById('logoutButton')?.addEventListener('click',()=>document.getElementById('lf')?.submit());
  window.addEventListener('beforeunload',clearPreviewUrls);addItem();
})();
