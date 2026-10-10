const assert = require('node:assert/strict');
const {test} = require('node:test');
const {groupProducts,categoryAtOffset} = require('../assets/storefront-menu.js');
test('categorias vazias não aparecem no cardápio',()=>{
  const products=[{id:1,nome:'X-Bacon'},{id:2,nome:'Suco'}];
  const categories=[{id:'a',nome:'Hambúrgueres',produto_ids:[1]},{id:'b',nome:'Sobremesas',produto_ids:[]}];
  const result=groupProducts(products,categories);
  assert.deepEqual(result.groups.map(x=>x.nome),['Hambúrgueres']);
  assert.deepEqual(result.uncategorized.map(x=>x.id),[2]);
});
test('produto destacado aparece na categoria sem duplicar o card',()=>{
  const products=[{id:1,vitrine_destaque:'featured'},{id:2},{id:3,vitrine_destaque:'promotion'}];
  const categories=[{id:'a',nome:'Entradas',produto_ids:[1,2]},{id:'b',nome:'Combos',produto_ids:[1,3]}];
  const result=groupProducts(products,categories);
  assert.deepEqual(result.groups[0].produtos.map(x=>x.id),[1,2]);
  assert.deepEqual(result.groups[1].produtos.map(x=>x.id),[3]);
  assert.equal(result.uncategorized.length,0);
  assert.equal(result.groups.flatMap(x=>x.produtos).length,3);
});
test('categoria que só possui destaque permanece navegável',()=>{
  const result=groupProducts([{id:9,vitrine_destaque:'promotion'}],[{id:'p',nome:'Promoções',produto_ids:[9]}]);
  assert.equal(result.groups[0].id,'p');
  assert.equal(result.groups[0].produtos.length,1);
});
test('seções respondem ao offset do cabeçalho sem depender de DOM',()=>{
  const sections=[{id:'hamb',top:-200},{id:'beb',top:70},{id:'sob',top:430}];
  assert.equal(categoryAtOffset(sections,110),'beb');
  assert.equal(categoryAtOffset(sections,0),'hamb');
  assert.equal(categoryAtOffset([{id:'a',top:120}],80),'todos');
});
test('catálogo vazio e categorias malformadas não interrompem renderização',()=>{
  assert.deepEqual(groupProducts([],null),{groups:[],uncategorized:[]});
  assert.deepEqual(groupProducts([{id:1}],[{id:'x',produto_ids:null}]).uncategorized.map(x=>x.id),[1]);
});
