# M6 — Disponibilidade comercial

## Contrato

- Produtos antigos e novos começam em `estoque` e disponíveis; a coluna nova tem defaults compatíveis.
- `sob_demanda` representa disponibilidade após o pedido. Não cria saldo fictício e não deduz estoque físico ao confirmar.
- Uma escolha de tamanho continua exigindo uma variação ativa cadastrada; no modo sob demanda, saldo zero nessa variação não a torna indisponível.
- `disponibilidade_manual = 0` pausa a venda do produto sem alterar saldo.
- Horários estruturados e pausa geral vivem em `vitrine_config.experiencia_json.atendimento`. `NULL` e configurações antigas mantêm o comportamento anterior; configuração M6 inválida bloqueia o fechamento.
- A vitrine WhatsApp valida e grava o snapshot antes de limpar o carrinho, na mesma transação. Linhas repetidas do mesmo produto/variação compartilham a validação de saldo; apenas snapshots `estoque` têm baixa física na confirmação.
- Site PRO valida disponibilidade no servidor, agrega as linhas iguais antes de emitir o link de pagamento e mantém o snapshot. Pausa manual posterior não invalida pedido já pago.

## Rollout

1. Fazer backup do banco de staging e verificar o mecanismo/engine das tabelas que participam do pedido, venda e estoque.
2. Aplicar `sql/20261010_commercial_availability.sql` em staging. O arquivo é idempotente e só adiciona duas colunas em `produtos`.
3. Verificar colunas, defaults (`estoque`, `1`) e que os registros preexistentes continuam com esses valores.
4. Publicar o código em staging e validar criar/editar produto, tamanhos físicos, item sob demanda, pausa por produto, horário normal/fechado, checkout Vitrine/Site PRO e confirmação concorrente.
5. Produção requer autorização de implantação e migration própria. Não há migração automática no carregamento das páginas.

## Recuperação

Reverter o código mantém a compatibilidade: colunas novas permanecem sem uso e defaults preservam o fluxo antigo. Não remover as colunas após começarem a ser usadas. Os `DROP COLUMN` comentados na migration só são seguros antes de existir dado M6 e após backup revisado.

## Limitações a validar

O ambiente disponível não fornece sessão autenticada de staging, MySQL nem sandbox de pagamentos. Testes de navegador, engine transacional, checkout real e concorrência ficam `NOT TESTABLE` localmente. O sistema não mantém reserva durável de estoque entre a criação de um checkout Site PRO e o callback do gateway; dois pedidos concorrentes ainda podem disputar as mesmas unidades. A confirmação impede saldo negativo e preserva a transação, mas um pagamento já capturado pode exigir reconciliação operacional se o saldo tiver acabado. Resolver essa corrida exige uma política e estrutura de reservas/cancelamento, fora desta migration mínima.
