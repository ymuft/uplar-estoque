<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/business_profile_runtime.php';

function checkRuntime(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
checkRuntime(uplar_business_profile_decode(null) === [], 'Nulo não deve inferir perfil.');
checkRuntime(uplar_business_profile_decode('{') === [], 'JSON inválido deve resultar vazio.');
checkRuntime(uplar_business_profile_decode('{"segmento":"hack","status_configuracao":"concluida"}') === [], 'Segmento desconhecido deve ser rejeitado.');
checkRuntime(uplar_business_profile_decode('{"segmento":"hamburgueria","status_configuracao":"concluida"}')['segmento'] === 'hamburgueria', 'Perfil válido deve ser lido.');
checkRuntime(uplar_business_profile_categories([' Bebidas  ','Lanches']) === ['Bebidas','Lanches'], 'Trim de categorias falhou.');
checkRuntime(uplar_business_profile_categories([]) === [], 'Lista vazia válida.');
foreach ([['Bebidas','bebidas'],[''],[str_repeat('x',81)]] as $invalid) {
    try { uplar_business_profile_categories($invalid); throw new RuntimeException('Deveria rejeitar categorias inválidas'); }
    catch (InvalidArgumentException $e) { /* esperado */ }
}
checkRuntime(uplar_vitrine_experience_mode(null) === 'padrao','Vitrine antiga deve manter Padrão.');
checkRuntime(uplar_business_profile_get('restaurante')['modo_sugerido'] === 'cardapio','Restaurante sugere Cardápio.');
echo "PASS — perfil nulo, validação, categorias, compatibilidade da vitrine.\n";
