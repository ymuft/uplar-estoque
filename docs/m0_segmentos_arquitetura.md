# M0 — Adaptação comercial por segmento (auditoria e contratos)

Escopo analisado: `onboarding.php`, `onboarding_api.php`, `includes/dashboard_onboarding.php`, `includes/vitrine_experience.php`, `vitrine.php`, `vitrine_config.php`, `vitrine_api.php`, `produtos.php`, `api.php`, `config_api.php`, `pedidos.php`, `site_pro_commerce_helpers.php`, `pedido_imprimir.php`. A versão em produção ainda precisa ser comparada com a branch main.

## Inventário e decisões

1. **Perfil do negócio ≠ modo da vitrine.** O modo está em `vitrine_config.experiencia_json`, com valores `padrao`, `loja`, `cardapio` e fallback `padrao`. Segmentar em `empresa_config` sem modificar a configuração de vitrines antigas.
2. **Onboarding é existente.** `onboarding_state` mantém status `in_progress/completed/dismissed` e o guia atual tem cinco passos. Um novo assistente de segmento não deve recolocar usuários dispensados ou concluídos em onboarding obrigatório. Usar transição de estado explícita e recuperável.
3. **Categorias não são unificadas.** O cadastro usa `produtos.categoria` (texto), enquanto `experiencia_json.categorias` mantém `id/nome/produto_ids`. Há risco de divergência entre importação, wizard e vitrine. Manter a vitrine publicada estável na migração.
4. **Estoque por unidade ≠ disponibilidade para preparo.** Não inferir “sem estoque” para produtos sob demanda, nem inserir quantidades artificiais. Primeiro projetar o contrato de disponibilidade entre API pública, carrinho, checkout, confirmação, estoque e relatórios.
5. **Status comercial ≠ preparo.** Preservar `pendente/confirmado/cancelado`; eventual fluxo de cozinha deve ser independente e não registrar venda nem baixa repetidas vezes.
6. **Compatibilidade entre canais.** Vitrine e Site PRO compartilham partes do núcleo comercial. Conferir identificação de canal, sessão, cálculo e isolamento do carrinho antes de mudanças no checkout.

## Contrato dos presets (implementado nesta branch)

`includes/business_profiles.php` oferece um catálogo estático, versionado (v1), com 11 segmentos, modo sugerido, categorias sugeridas e prioridades de cadastro. É uma fonte de **sugestões**, não de permissões ou regras comerciais.

- Não altera banco, produtos, vitrines, checkout nem pedidos.
- Não assume um perfil para contas antigas ou valores desconhecidos (retorna `null`).
- Não cria registros de categorias nem publica a vitrine.
- Permite exibir uma prévia de opções para confirmação do lojista.
- Atualizações futuras de presets não devem sobrescrever escolhas personalizadas.

## Contratos a implementar em milestones seguintes

**Persistência do perfil (M1).** Proposta: `empresa_config.perfil_negocio_json JSON NULL` contendo `segmento`, `versao_preset` e histórico mínimo de aplicação. A presença de `NULL` significa não selecionado; não inferir ramo a partir de nomes dos produtos. Separar escolha, aplicação e conclusão do onboarding. Avaliar schema real antes da migration.

**Categorias (M3).** Proposta: catálogo relacional de categorias por `user_id`, ids estáveis e associação principal `produtos.categoria_id NULL`. Conciliar o texto legado e os `produto_ids` da vitrine sem sobrescrita automática. Resolver conflitos explicitamente, com modo de compatibilidade para vitrines antigas.

**Disponibilidade (M6).** Proposta: campo explícito por produto indicando controle físico ou sob demanda, além do estado comercial disponível/indisponível. Não acoplar esse campo ao segmento. Definir quando o checkout reserva/valida disponibilidade sem duplicar baixas.

**Atendimento e preparo (M7).** Horários estruturados e estados operacionais independentes dos estados comerciais. Definir regras de timezone, abertura, exceções e atualização de status com transições válidas.

Todos os ajustes de schema acima **dependem de autorização específica antes de execução em produção**. Não aplicar migracões nesta branch.

## Sequência e testes

1. Presets puros e testes (incluídos nesta branch).
2. Definição/aprovação da persistência; API autenticada, CSRF, isolamento por `user_id`, e seleção/revisão não destrutiva do perfil.
3. Integração com onboarding sem alterar a elegibilidade das contas atuais.
4. Reconciliação segura de categorias.
5. Wizard e vitrine que consumam os mesmos dados sem duplicar regras.
6. Contrato de disponibilidade + testes transacionais.
7. Operação de cozinha, impressão e Site PRO.
8. Testes ponta a ponta em staging: esportes/tamanhos; hamburgueria/complementos; negócio misto; conta legada; mudança de segmento; isolamento entre empresas.

**Rollback:** como o primeiro incremento só acrescenta código não referenciado, reverter os commits da branch o remove integralmente. Para migrations futuras, planejar migração expand/contract e preservar dados legados.

## Estado

- M0: **PARTIAL** — inventário e contratos definidos; falta conferir o runtime/DB real e os fluxos autenticados.
- M1: **PARTIAL** — catálogo de presets disponível; falta persistência, UI e aprovação de schema.
- M2–M10: **NOT STARTED**.
- Produção: **não alterada**.
