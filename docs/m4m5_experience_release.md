# M4/M5 — Cadastro adaptativo e Vitrine Loja/Cardápio

**Branch:** `feature/m4-m5-adaptativa` | **PR:** #3, baseado no PR #2 | **Produção:** não alterada.

## Escopo implementado nesta branch

### M4 — Cadastro
- O wizard existente foi reduzido para etapas de informação, preço/estoque, personalização condicional e revisão. Foto e validade foram incorporadas como seções opcionais. Os controles existentes continuam disponíveis.
- Labels, exemplos e ordem dos campos se adaptam às prioridades dos perfis comerciais sem restringir funcionalidades.
- Para novos produtos de alimentação, o SKU pode ser gerado quando não informado; a API mantém a validação de unicidade.
- Novo atalho **Salvar produto simples** após preenchimento das informações essenciais, sem forçar a passagem pela revisão. Produto complexo continua no fluxo guiado.
- **Correções de integridade de estoque:** não alterar `pQtd` ao abrir produto sem tamanhos; total de tamanhos é derivado apenas das variações; botões do agregado ficam inativos quando tamanhos estão ativos; conversão de estoque existente para tamanhos é encaminhada à tela que exige distribuição explícita. Não redistribuir silenciosamente unidades à primeira variação.
- O editor de complementos atual foi preservado (templates, grupos compartilhados, limites, regras, cópias), com validação no backend. A reformulação integral desse editor ainda não foi realizada.
- O cadastro canônico de categorias do M3 foi preservado, com carregamento, criação inline e feedback de erro sem limpar os campos.

### M5 — Loja e Cardápio
- Preservadas a experiência Loja em grade e a experiência Padrão para compatibilidade.
- Cards horizontais para Cardápio, descrição e imagem lateral, cabeçalho compacto, banner ausente recolhido.
- **Categorias no Cardápio agora funcionam como navegação por seções**, com scroll, categoria ativa, controles acessíveis e busca que não exige uma categoria prévia.
- `assets/storefront-menu.js` fornece agrupamento determinístico: categorias vazias não renderizam, produtos com associação legada múltipla são mostrados uma vez; itens destacados/promocionais permanecem dentro de sua categoria, com selo e sem cards duplicados.
- Carrinho continua compartilhado, mas os rótulos exibidos se adaptam a "Meu pedido" em Cardápio e "Meu carrinho" em Loja, com contador e valor vindos da API.
- Corrigido bubbling de Enter/Espaço em cards com botões internos para preservar controles acessíveis.

## Testes automatizados

CI: `.github/workflows/m4m5-ui.yml`
- `php -l produtos.php vitrine.php`.
- Checagem da sintaxe JS inline extraída das views PHP.
- `node --check` em módulos JS relevantes.
- `node --test tests/storefront-menu.test.js tests/product-wizard-regression.test.js`: agrupamento de cardápio, destaques, categoria vazia, categoria ativa, preservação de quantidade em produtos sem tamanhos e formato de SKU assistido.
- Workflows herdados M1/M3 também são executados pelas alterações pertinentes.

**Aprovação do CI não substitui testes no navegador autenticado ou checkout completo.**

## Dependências e pendências bloqueadas por ambiente

1. Confirmar PRs #1/#2 e migrations M1/M3 em **staging**, em ordem. Não executar em produção sem autorização expressa.
2. Testar visualmente o wizard em desktop/mobile: criar, editar, duplicar, foto, validade, tamanhos, voltar etapas e abandonar alterações.
3. Verificar produto simples, produto com tamanho, produto com complementos e produto misto na Vitrine, Carrinho, Checkout, Pedido e Impressão.
4. Conferir scroll de categorias no Safari iOS e Android, safe-area, foco, teclado virtual e sticky header.
5. Conferir modo Padrão de contas legadas e Site PRO sem alteração do núcleo comercial.
6. Avaliar performance de 30/150/500/1000 produtos com medição real; não introduzir paginação às cegas.
7. M6 posterior: suporte a produtos preparados sob demanda (não usar estoque fictício), horários estruturados e fluxos operacionais do restaurante.
8. M4.4: refinamento adicional da administração de complementos pode ser feito com testes visuais específicos; regras comerciais atuais preservadas.

## Matriz mínima para aceite

| Cenário | Critério |
| --- | --- |
| Produto simples | SKU gerado para alimentação, custo/preço/estoque salvos sem passar por etapas irrelevantes |
| Camiseta P/M/G | Estoque por tamanho, total correto, sem realocação silenciosa, checkout e baixa |
| X-Bacon | Categoria, adicionais obrigatórios e opcionais, preço validado, editar no carrinho, imprimir |
| Negócio misto | Produtos simples, tamanhos e complementos coexistem sem duplicar lógica |
| Conta legada | Vitrine Padrão, categorias, destaques, pedidos preservados |
| Catálogo grande | Busca, categorias, foco e renderização sem jank detectável nas medições |
| Dispositivos | Desktop Firefox/Chrome, Safari iPhone, Chrome Android, rolagem e foco |

**Status:** código desenvolvido em PR draft, aguardando validação E2E no staging e rollout autorizado. Nenhum dado comercial real foi modificado.
