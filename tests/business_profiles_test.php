<?php
/** Execute com: php tests/business_profiles_test.php */
declare(strict_types=1);
require_once __DIR__ . '/../includes/business_profiles.php';

function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

$catalog = uplar_business_profiles_catalog();
check(count($catalog) === 11, 'Esperados 11 perfis comerciais.');
check(uplar_business_profile_get(null) === null, 'Perfil não escolhido não deve ser inferido.');
check(uplar_business_profile_get('desconhecido') === null, 'Segmento desconhecido não deve virar outro automaticamente.');
check(!uplar_business_profile_is_valid(''), 'Não aceitar chave vazia.');

foreach ($catalog as $segmento => $profile) {
    check(uplar_business_profile_is_valid($segmento), 'Perfil inválido: ' . $segmento);
    check(in_array($profile['modo_sugerido'], ['padrao', 'loja', 'cardapio'], true), 'Modo inválido: ' . $segmento);
    check(count($profile['categorias_sugeridas']) === count(array_unique($profile['categorias_sugeridas'])), 'Categorias duplicadas: ' . $segmento);
    check(count($profile['prioridades_cadastro']) > 0, 'Sem campos sugeridos: ' . $segmento);
    check(uplar_business_profile_get($segmento)['versao_preset'] === 1, 'Versão inválida.');
}
check(uplar_business_profile_get('hamburgueria')['modo_sugerido'] === 'cardapio', 'Hamburgueria deve sugerir Cardápio.');
check(uplar_business_profile_get('artigos_esportivos')['modo_sugerido'] === 'loja', 'Esportes deve sugerir Loja.');
check(uplar_business_profile_get('negocio_misto')['modo_sugerido'] === 'padrao', 'Negócio misto deve sugerir Padrão.');

echo "PASS — 11 perfis; chaves, sugestões e defaults não destrutivos.\n";
