-- Preço de custo por produto e snapshot do custo no momento da venda.
ALTER TABLE produtos
    ADD COLUMN preco_custo DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER preco;

ALTER TABLE vendas
    ADD COLUMN custo_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER preco_unitario;
