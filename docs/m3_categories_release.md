# Uplar Estoque — M3: categorias comerciais unificadas

**Estado:** PR #2 em draft, baseado no PR #1. Nada executado em produção, nem merge na main.

## Achados do código

- O wizard de `produtos.php` salva pelo `api.php` (`adicionar`/`editar`), que antes não gravava categoria.
- A importação via `produto_lote_api.php` já salvava texto em `produtos.categoria`.
- A vitrine usava exclusivamente `experiencia_json.categorias[].produto_ids`, independentemente do campo do produto.
- O modo Padrão possuía fallback legado por primeira palavra do nome.

## Contrato expand/contract

Migration preparada: `sql/20261010_catalog_categories.sql`.
- `catalogo_categorias`: `id`, `user_id`, `nome`, `ordem`, timestamps; chave única por conta e nome com collation insensível a maiúsculas/acentos.
- `produtos.categoria_id INT UNSIGNED NULL`, index composto por conta/categoria.
- Mantém `produtos.categoria` e `vitrine_config.experiencia_json.categorias` intactos até a reconciliação aprovada.
- Repetir a migration é seguro no MySQL isolado. **Não execute em produção sem autorização explícita e backup verificado.**

## Funcionalidades codificadas

1. `includes/catalog_categories.php`: contrato de leitura, validação, criação idempotente, associação com checagem de propriedade, audit e leitura pública compatível.
2. `catalogo_categorias_api.php`: API autenticada com CSRF em escrita para CRUD, ordenação, associação, auditoria, importação legada sem overwrite de vínculos canônicos, aplicação de presets e ativação controlada.
3. `api.php`: CRUD de produto grava categoria opcional na mesma transação, sem mudar endpoint ou obrigatoriedade de campos antigos.
4. `produto_lote_api.php`: importação de categorias de texto para referência canônica quando disponível, evitando duplicações.
5. `produtos.php`: seletor e criação de categoria inline, estado preservado na revisão do wizard.
6. `vitrine.php` e `vitrine_api.php`: publicam categorias canônicas quando a origem é ativada ou não existam categorias manuais prévias. Caso contrário, a organização legada permanece.
7. `vitrine_config.php` + `assets/catalog-categories-admin.*`: gestão de categorias, produtos associados, busca paginada, preview de conflitos, migração e ativação explícita.
8. `includes/business_profile_runtime.php`: categorias aprovadas no onboarding são criadas automaticamente quando o M3 está disponível, idempotentemente.

## Fluxo seguro para lojas existentes

1. **Antes de migrar:** a vitrine segue usando `experiencia_json.categorias`, se já existirem categorias configuradas.
2. **Auditoria:** identifica divergências entre texto no produto, associação manual e vínculo canônico; nenhuma mutação.
3. **Importação controlada:** cria categorias e preenche `categoria_id` para produtos sem vínculo; nunca substitui vínculo canônico existente.
4. **Ativação explícita:** atualiza apenas `experiencia_json.fonte_categorias='canonico'` e preserva as demais preferências.
5. **Rollback de publicação:** possível ao restaurar `fonte_categorias='legado'` na configuração versionada, mantendo o legado intacto. Deve ser feito por processo controlado, com autorização, e não por operação destrutiva.

## Atenções e critérios ainda pendentes

- Testar com banco *real* de staging após aplicar migration autorizada.
- Conferir migração de contas com divergências, categorias duplicadas por acentuação e catálogos extensos.
- Testar CRUD, importação por fotos e vitrine de 3 segmentos em navegador autenticado no mobile e desktop.
- Verificar ações de exclusão/transferência sob concorrência, canais Site PRO e preço/estoque como regressão.
- Não remover `produtos.categoria` ou o JSON legado nesta fase.
- Não modificar status de pedidos, checkout, estoque, pagamentos, impressão ou vitrines existentes fora do escopo de classificação.

## Validação automatizada

Workflow `.github/workflows/catalog-categories.yml`:
- PHP lint em caminhos afetados.
- `node --check` em editor de categorias.
- MySQL 8 isolado: migration 2x, duplicação, isolamento, associação, produtos ocultos, diferença entre fontes, detecção de divergência, presets de onboarding sem duplicar categorias.

Resultado da execução deve ser consultado na aba Actions do PR. `PASS` no CI não equivale a `PASS` end-to-end em produção.
