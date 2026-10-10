# Entrega M1–M2 — Perfil comercial e primeiro acesso

## O que foi desenvolvido nesta branch

- `includes/business_profile_runtime.php`: leitura compatível com schema ausente, persistência transacional, estados `nao_selecionado / em_andamento / concluida / dispensada`, verificação de segmentos existentes, proteção contra sobrescrita de perfil concluído por ações de rascunho/dispensa.
- `business_profile_api.php`: operações `draft / complete / dismiss` via POST autenticado com CSRF e escopo por usuário da sessão.
- `perfil_negocio.php` + `assets/business-setup.js` + `assets/business-setup.css`: assistente para selecionar ramo, editar categorias sugeridas, selecionar atendimento, optar por um modo de vitrine e concluir/retomar. O próprio backend decide os presets.
- `onboarding.php` / `includes/dashboard_onboarding.php`: instruções contextualizadas e lembrete somente para rascunhos.
- `estoque.php`: redirecionamento de primeiro acesso protegido por feature flag, schema disponível e data de lançamento. Sem ativação por padrão.
- `config.php` e `vitrine_config.php`: acesso para revisar perfil, visualização de sugestões de modo e atendimento antes de salvar.
- `produtos.php`: exemplos de preenchimento contextual e variável `BUSINESS_CONTEXT` somente para experiência administrativa. Não altera validação/estoque.

## Migration preparada — NÃO executada em produção

`sql/20261010_business_profile.sql` — idempotente:
`empresa_config.perfil_negocio_json JSON NULL`.

O valor `NULL` em contas legadas permanece intacto; não executar backfill por inferência.

**Processo de rollout:** comparar código e schema com staging, confirmar backup, aplicar migration primeiro em staging, validar fluxos e só então solicitar autorização explícita para o banco de produção. O helper não cria schema automaticamente.

## Primeiro acesso com ativação controlada

Por padrão, o redirecionamento automático está **desativado**. Somente habilitar após migration e testes autenticados:

```env
UPLAR_BUSINESS_ONBOARDING_AUTOSTART=1
UPLAR_BUSINESS_ONBOARDING_LAUNCH_AT=2026-10-11 00:00:00
```

A data é **exemplo**, não configurar automaticamente. Resolver marco temporal com o fuso de `users.created_at` no servidor. A detecção considera exclusivamente contas criadas depois do lançamento explícito, schema disponível, perfil ainda não selecionado e conta não administrativa.

Usuários antigos acessam voluntariamente em `config.php` → **Perfil do negócio** ou na aba Experiência da Vitrine.

## Categorias e atendimento

- Categorias escolhidas ficam em `categorias_pendentes`. **Não** são associadas automaticamente a `produtos.categoria` nem duplicadas em `experiencia_json.categorias`.
- Modo visual explícito pode atualizar `vitrine_config.experiencia_json.modo` se existir uma configuração da vitrine. Sem registro da vitrine, a sugestão permanece em `modo_pendente` e só aparece pré-selecionada no editor.
- Atendimento confirmado em segmento de alimentação permanece como sugestão para editor sem configuração publicada; não habilita pedidos/pagamentos automaticamente.

## Critérios para considerar PASS

- [ ] Aplicar migration com autorização em staging e confirmar ausência de alteração em contas antigas.
- [ ] Testar autenticação e CSRF em browser, inclusive tentativa de alterar outra loja.
- [ ] Testar primeiro acesso com feature flag e sem ela.
- [ ] Testar rascunho + refresh, dispensar, completar, editar e alterar ramo.
- [ ] Validar vitrine não publicada e vitrine legada após alterações.
- [ ] Conferir mobile/desktop e comportamentos de teclado.
- [ ] Validar operação de `config.php` e `vitrine_config.php` com dados reais de teste.
- [ ] Checar fluxo de produtos, carrinho, pedidos e Site PRO como regressão.

## Testes automatizados

`.github/workflows/business-profiles.yml` executa lint PHP, checagem JS, testes de presets, contratos puros e integração em banco MySQL isolado (migration 2x, persistência, contas antigas e modo da vitrine). A execução e seus resultados devem ser conferidos na aba Actions.

## Próximas dependências

- M3 da arquitetura geral: categoria única integrada entre cadastro e vitrine, com reconciliação preservando vínculos legados.
- M4/M5 ampliados: layout adaptável do cadastro e das vitrines além dos exemplos/contexto; não implementados nesta rodada.
- M6/M7: produtos sob demanda, horários e estados de preparo. **Não iniciados**.
- Implementação atual permanece no PR draft; não fazer merge automático.
