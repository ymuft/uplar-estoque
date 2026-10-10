/* Uplar: deterministic, reusable menu grouping logic.
 * The same product appears once in the menu, even if legacy links overlap.
 * Featured/promotional products stay in their category with visible badges.
 */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  root.UplarStorefrontMenu = api;
})(typeof globalThis !== 'undefined' ? globalThis : window, function () {
  'use strict';

  function groupProducts(products, categories) {
    const source = Array.isArray(products) ? products : [];
    const list = Array.isArray(categories) ? categories : [];
    const claimed = new Set();
    const groups = [];
    for (const category of list) {
      if (!category || !Array.isArray(category.produto_ids)) continue;
      const allowed = new Set(category.produto_ids.map(Number).filter(Number.isFinite));
      const belonging = [];
      for (const product of source) {
        const id = Number(product?.id);
        if (allowed.has(id) && !claimed.has(id)) {
          claimed.add(id);
          belonging.push(product);
        }
      }
      if (belonging.length) {
        groups.push({ id: category.id, nome: category.nome, produtos: belonging });
      }
    }
    const uncategorized = source.filter(product => !claimed.has(Number(product?.id)));
    return { groups, uncategorized };
  }

  function categoryAtOffset(sections, offset) {
    // Receives sorted positions [{id, top}], usually from getBoundingClientRect().
    // The nearest section that has reached the sticky navigation becomes active.
    let active = 'todos';
    for (const section of (Array.isArray(sections) ? sections : [])) {
      if (!Number.isFinite(section.top)) continue;
      if (section.top <= offset) active = String(section.id);
    }
    return active;
  }

  return Object.freeze({groupProducts, categoryAtOffset});
});
