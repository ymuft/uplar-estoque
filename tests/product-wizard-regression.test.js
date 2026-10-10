const assert = require('node:assert/strict');
const {test} = require('node:test');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('produtos.php', 'utf8');

function functionSource(name) {
  const start = source.indexOf('function ' + name + '(');
  assert.notEqual(start,-1,'Missing ' + name);
  const end = source.indexOf('\n}\n', start);
  assert.ok(end > start, 'Function ending not found for ' + name);
  return source.slice(start,end+2);
}

test('abrir produto comum não apaga estoque ao calcular total dos tamanhos',()=>{
  let enabled=false;
  const elements={
    sizeTotal:{textContent:''},
    pQtd:{value:27},
    sizeYes:{classList:{contains:()=>enabled}}
  };
  const context={
    sizeDraft:[],
    $:id=>elements[id],
    Number,Math
  };
  vm.runInNewContext(functionSource('renderSizeTotal')+'\nrenderSizeTotal()',context);
  assert.equal(elements.pQtd.value,27,'Estoque de produto comum não pode ser zerado');
  assert.equal(elements.sizeTotal.textContent,'Estoque total: 0 unidades');
  enabled=true;
  context.sizeDraft=[{tamanho:'P',quantidade:3},{tamanho:'M',quantidade:7}];
  vm.runInNewContext('renderSizeTotal()',context);
  assert.equal(elements.pQtd.value,10,'Estoque com tamanhos deve ser calculado pelas variações');
});

test('SKU gerado tem formato válido e respeita prefixo do contexto',()=>{
  const sandbox={
    BUSINESS_CONTEXT:{familia:'alimentacao'},
    window:{crypto:{getRandomValues(bytes){bytes.fill(0xab);return bytes}}},
    Uint8Array,Array,Math
  };
  vm.runInNewContext(functionSource('generateProductSku')+'\nthis.code=generateProductSku()',sandbox);
  assert.match(sandbox.code,/^ALIM-[A-F0-9]{12}$/);
  sandbox.BUSINESS_CONTEXT.familia='varejo';
  vm.runInNewContext('this.code=generateProductSku()',sandbox);
  assert.match(sandbox.code,/^PROD-[A-F0-9]{12}$/);
});
