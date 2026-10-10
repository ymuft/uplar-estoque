(() => {
  'use strict';

  const config = window.UPLAR_CHANGELOG || {};
  const overlay = document.getElementById('whatsNewOverlay');
  if (!overlay || !config.endpoint) return;

  const dialog = document.getElementById('whatsNewDialog');
  const closeButton = document.getElementById('whatsNewClose');
  const understandButton = document.getElementById('whatsNewUnderstand');
  const allLink = document.getElementById('whatsNewAll');
  const content = document.getElementById('whatsNewContent');
  const categoryMap = {
    new: ['Novo', 'add_circle'],
    improvement: ['Melhoria', 'auto_awesome'],
    fix: ['Correção', 'build_circle'],
    security: ['Segurança', 'verified_user'],
    performance: ['Desempenho', 'speed']
  };
  let current = null;
  let previousFocus = null;
  let opened = false;
  let userStartedAction = false;
  let unreadCount = 0;

  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };

  const safeMediaPath = (path) => /^imagens\/changelog\/[a-f0-9]{32}\.webp$/.test(path || '') ? path : '';

  function category(item) {
    const data = categoryMap[item.category] || categoryMap.improvement;
    const label = element('span', `wn-category wn-category--${item.category || 'improvement'}`);
    const icon = element('span', 'material-symbols-outlined', data[1]);
    icon.setAttribute('aria-hidden', 'true');
    label.append(icon, document.createTextNode(data[0]));
    return label;
  }

  function itemLink(item) {
    if (!item.link_url) return null;
    const link = element('a', 'wn-link');
    link.href = item.link_url;
    link.append(document.createTextNode(item.button_text || 'Conhecer funcionalidade'));
    const icon = element('span', 'material-symbols-outlined', 'arrow_forward');
    icon.setAttribute('aria-hidden', 'true');
    link.append(icon);
    if (/^https:\/\//i.test(item.link_url)) {
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
    }
    return link;
  }

  function renderFeatured(item) {
    const card = element('article', 'wn-featured');
    const media = safeMediaPath(item.image_path);
    if (media) {
      const image = element('img', 'wn-featured-media');
      image.src = media;
      image.alt = '';
      image.loading = 'eager';
      card.append(image);
    }
    const copy = element('div', 'wn-featured-copy');
    copy.append(category(item), element('h3', 'wn-item-title', item.title), element('p', 'wn-item-description', item.description));
    const link = itemLink(item);
    if (link) copy.append(link);
    card.append(copy);
    return card;
  }

  function renderItem(item) {
    const card = element('article', 'wn-item');
    const iconBox = element('div', 'wn-item-icon');
    const icon = element('span', 'material-symbols-outlined', (categoryMap[item.category] || categoryMap.improvement)[1]);
    icon.setAttribute('aria-hidden', 'true');
    iconBox.append(icon);
    const copy = element('div');
    copy.append(category(item), element('h3', 'wn-item-title', item.title), element('p', 'wn-item-description', item.description));
    const media = safeMediaPath(item.image_path);
    if (media) {
      const image = element('img', 'wn-item-media');
      image.src = media;
      image.alt = '';
      image.loading = 'lazy';
      copy.append(image);
    }
    const link = itemLink(item);
    if (link) copy.append(link);
    card.append(iconBox, copy);
    return card;
  }

  function updateBadge(count) {
    unreadCount = Math.max(0, Number(count) || 0);
    const badge = document.getElementById('changelogUnreadBadge');
    if (!badge) return;
    badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
    badge.hidden = unreadCount < 1;
    badge.setAttribute('aria-label', `${unreadCount} ${unreadCount === 1 ? 'novidade não lida' : 'novidades não lidas'}`);
  }

  function fillModal(changelog) {
    current = changelog;
    document.getElementById('whatsNewTitle').textContent = changelog.title || 'Novidades na Uplar';
    const metadata = [];
    if (changelog.version) metadata.push(`Versão ${changelog.version}`);
    if (changelog.date_label) metadata.push(changelog.date_label);
    document.getElementById('whatsNewMeta').textContent = metadata.join(' · ');
    document.getElementById('whatsNewIntro').textContent = changelog.intro || '';
    document.getElementById('whatsNewBadge').textContent = changelog.importance === 'critical' ? 'ATUALIZAÇÃO CRÍTICA' : 'NOVIDADE';
    dialog.classList.toggle('wn-dialog--critical', changelog.importance === 'critical');
    closeButton.hidden = changelog.importance === 'critical';
    allLink.hidden = changelog.importance === 'critical';
    content.replaceChildren();
    const items = Array.isArray(changelog.items) ? changelog.items : [];
    const featured = items.find(item => item.is_featured);
    if (featured) content.append(renderFeatured(featured));
    const list = element('div', 'wn-items');
    items.filter(item => item !== featured).forEach(item => list.append(renderItem(item)));
    if (list.children.length) content.append(list);
    if (!items.length) content.append(element('p', 'wn-empty', 'Os detalhes desta atualização estão disponíveis na central de novidades.'));
  }

  function focusable() {
    return [...dialog.querySelectorAll('a[href], button:not([disabled]):not([hidden]), [tabindex]:not([tabindex="-1"])')]
      .filter(node => !node.hasAttribute('hidden'));
  }

  function openModal(changelog) {
    fillModal(changelog);
    previousFocus = document.activeElement;
    overlay.hidden = false;
    overlay.setAttribute('aria-hidden', 'false');
    document.body.dataset.changelogOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => {
      overlay.classList.add('is-open');
      opened = true;
      (closeButton.hidden ? understandButton : closeButton).focus();
    });
  }

  async function markViewed(source = 'modal') {
    if (!current) return true;
    const data = new FormData();
    data.append('action', 'mark_viewed');
    data.append('changelog_id', String(current.id));
    data.append('source', source);
    data.append('csrf_token', config.csrfToken || '');
    try {
      const response = await fetch(config.endpoint, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true });
      return response.ok;
    } catch (_) {
      return false;
    }
  }

  function hideModal() {
    opened = false;
    overlay.classList.remove('is-open');
    window.setTimeout(() => {
      overlay.hidden = true;
      overlay.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = document.body.dataset.changelogOverflow || '';
      delete document.body.dataset.changelogOverflow;
      previousFocus?.focus?.();
      current = null;
    }, 190);
  }

  async function closeModal(source = 'modal') {
    if (!opened || current?.importance === 'critical') return;
    const persistence = markViewed(source);
    hideModal();
    if (await persistence) updateBadge(unreadCount - 1);
  }

  async function confirmViewed() {
    if (!opened) return;
    understandButton.disabled = true;
    understandButton.textContent = 'Salvando…';
    const success = await markViewed('modal');
    understandButton.disabled = false;
    understandButton.textContent = success ? 'Entendi' : 'Tentar novamente';
    if (!success) return;
    updateBadge(unreadCount - 1);
    hideModal();
  }

  closeButton.addEventListener('click', () => closeModal());
  understandButton.addEventListener('click', confirmViewed);
  allLink.addEventListener('click', event => {
    event.preventDefault();
    markViewed('center').finally(() => { window.location.href = allLink.href; });
  });
  overlay.addEventListener('mousedown', event => {
    if (event.target === overlay) closeModal();
  });
  document.addEventListener('keydown', event => {
    if (!opened) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }
    if (event.key !== 'Tab') return;
    const nodes = focusable();
    if (!nodes.length) return;
    const first = nodes[0];
    const last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault(); first.focus();
    }
  });

  ['pointerdown', 'keydown'].forEach(type => document.addEventListener(type, () => {
    if (!opened) userStartedAction = true;
  }, { once: true, capture: true }));

  const statusRequest = fetch(`${config.endpoint}?action=status`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then(response => response.ok ? response.json() : null)
    .catch(() => null);

  const showWhenReady = async () => {
    const result = await statusRequest;
    if (!result?.ok) return;
    updateBadge(Number(result.unread_count) || 0);
    if (!result.modal || userStartedAction) return;
    requestAnimationFrame(() => requestAnimationFrame(() => openModal(result.modal)));
  };
  if (document.readyState === 'complete') showWhenReady();
  else window.addEventListener('load', showWhenReady, { once: true });
})();
