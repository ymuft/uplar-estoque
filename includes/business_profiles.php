<?php
/**
 * Presets iniciais de segmentos comerciais.
 *
 * Metadados apenas: não persistem configurações, não criam categorias,
 * não mudam estoque, não concedem permissões e não publicam vitrines.
 * A aplicação de um preset exige uma ação explícita do lojista.
 */

declare(strict_types=1);

function uplar_business_profiles_catalog(): array
{
    return [
        'moda_vestuario' => [
            'nome' => 'Moda e vestuário', 'familia' => 'varejo', 'modo_sugerido' => 'loja',
            'categorias_sugeridas' => ['Camisetas', 'Calças', 'Bermudas', 'Conjuntos', 'Acessórios'],
            'prioridades_cadastro' => ['imagens', 'tamanhos', 'estoque'],
        ],
        'artigos_esportivos' => [
            'nome' => 'Artigos esportivos', 'familia' => 'varejo', 'modo_sugerido' => 'loja',
            'categorias_sugeridas' => ['Camisetas', 'Bermudas', 'Conjuntos', 'Calçados', 'Equipamentos'],
            'prioridades_cadastro' => ['imagens', 'tamanhos', 'estoque'],
        ],
        'calcados' => [
            'nome' => 'Calçados', 'familia' => 'varejo', 'modo_sugerido' => 'loja',
            'categorias_sugeridas' => ['Tênis', 'Sandálias', 'Chinelos', 'Botas', 'Acessórios'],
            'prioridades_cadastro' => ['imagens', 'tamanhos', 'estoque'],
        ],
        'eletronicos' => [
            'nome' => 'Eletrônicos', 'familia' => 'varejo', 'modo_sugerido' => 'loja',
            'categorias_sugeridas' => ['Celulares', 'Informática', 'Áudio', 'Acessórios'],
            'prioridades_cadastro' => ['codigo', 'descricao', 'imagens', 'estoque'],
        ],
        'cosmeticos_beleza' => [
            'nome' => 'Cosméticos e beleza', 'familia' => 'varejo', 'modo_sugerido' => 'loja',
            'categorias_sugeridas' => ['Cuidados com a pele', 'Cabelos', 'Maquiagem', 'Perfumes'],
            'prioridades_cadastro' => ['imagens', 'validade', 'estoque'],
        ],
        'restaurante' => [
            'nome' => 'Restaurante', 'familia' => 'alimentacao', 'modo_sugerido' => 'cardapio',
            'categorias_sugeridas' => ['Pratos principais', 'Acompanhamentos', 'Bebidas', 'Sobremesas'],
            'prioridades_cadastro' => ['categoria', 'descricao', 'complementos', 'disponibilidade'],
        ],
        'hamburgueria' => [
            'nome' => 'Hamburgueria', 'familia' => 'alimentacao', 'modo_sugerido' => 'cardapio',
            'categorias_sugeridas' => ['Hambúrgueres', 'Combos', 'Acompanhamentos', 'Bebidas', 'Sobremesas'],
            'prioridades_cadastro' => ['categoria', 'descricao', 'complementos', 'disponibilidade'],
        ],
        'cafeteria_lanchonete' => [
            'nome' => 'Cafeteria/lanchonete', 'familia' => 'alimentacao', 'modo_sugerido' => 'cardapio',
            'categorias_sugeridas' => ['Cafés', 'Bebidas', 'Lanches', 'Doces', 'Salgados'],
            'prioridades_cadastro' => ['categoria', 'descricao', 'complementos', 'disponibilidade'],
        ],
        'mercado_conveniencia' => [
            'nome' => 'Mercado/conveniência', 'familia' => 'varejo', 'modo_sugerido' => 'loja',
            'categorias_sugeridas' => ['Bebidas', 'Alimentos', 'Higiene', 'Limpeza', 'Outros'],
            'prioridades_cadastro' => ['codigo', 'categoria', 'validade', 'estoque'],
        ],
        'negocio_misto' => [
            'nome' => 'Negócio misto', 'familia' => 'misto', 'modo_sugerido' => 'padrao',
            'categorias_sugeridas' => [],
            'prioridades_cadastro' => ['imagens', 'estoque', 'complementos'],
        ],
        'outros' => [
            'nome' => 'Outros', 'familia' => 'generico', 'modo_sugerido' => 'padrao',
            'categorias_sugeridas' => [],
            'prioridades_cadastro' => ['nome', 'codigo', 'preco', 'estoque'],
        ],
    ];
}

/** Nulo para segmento não selecionado ou desconhecido; não faz inferências. */
function uplar_business_profile_get(?string $segmento): ?array
{
    if ($segmento === null || $segmento === '') {
        return null;
    }
    $catalog = uplar_business_profiles_catalog();
    if (!array_key_exists($segmento, $catalog)) {
        return null;
    }
    return ['segmento' => $segmento, 'versao_preset' => 1] + $catalog[$segmento];
}

function uplar_business_profile_is_valid(string $segmento): bool
{
    return uplar_business_profile_get($segmento) !== null;
}
