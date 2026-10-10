<?php
/**
 * Integration test — isolated MySQL database, NEVER production.
 * Run with UPLAR_TEST_MYSQL_DSN, UPLAR_TEST_MYSQL_USER, UPLAR_TEST_MYSQL_PASSWORD.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/business_profile_runtime.php';
function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$dsn = getenv('UPLAR_TEST_MYSQL_DSN');
if (!$dsn || !str_contains($dsn, 'uplar_ci')) throw new RuntimeException('Refusing integration tests outside uplar_ci.');
$pdo = new PDO($dsn, getenv('UPLAR_TEST_MYSQL_USER') ?: 'root', getenv('UPLAR_TEST_MYSQL_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT PRIMARY KEY, created_at DATETIME NOT NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS empresa_config (user_id INT NOT NULL UNIQUE, nome_fantasia VARCHAR(120) NOT NULL DEFAULT '')");
$pdo->exec("CREATE TABLE IF NOT EXISTS vitrine_config (user_id INT NOT NULL PRIMARY KEY, experiencia_json JSON NULL)");
$pdo->exec("INSERT INTO users (id,created_at) VALUES (101,'2026-11-02 10:00:00'),(102,'2020-01-01 00:00:00')");
$pdo->exec("INSERT INTO vitrine_config (user_id,experiencia_json) VALUES (102,'{\"modo\":\"loja\",\"mostrar_destaques\":false}')");

$migration = preg_replace('/^\\s*--[^\\n]*(\\n|$)/m', '', (string)file_get_contents(__DIR__ . '/../sql/20261010_business_profile.sql'));
for ($attempt = 0; $attempt < 2; $attempt++) {
    foreach (explode(';', $migration) as $statement) {
        if (trim($statement) !== '') $pdo->exec($statement);
    }
}
verify(uplar_business_profile_schema_available($pdo), 'Migration missing column');
verify(uplar_business_profile_read($pdo, 101)['status_configuracao'] === 'nao_selecionado', 'Legacy account should remain unset');
$missing = $pdo->query('SELECT COUNT(*) FROM vitrine_config WHERE user_id = 101')->fetchColumn();
verify((int)$missing === 0, 'No vitrine_config row should be created');

putenv('UPLAR_BUSINESS_ONBOARDING_AUTOSTART=1');
putenv('UPLAR_BUSINESS_ONBOARDING_LAUNCH_AT=2026-10-10 00:00:00');
verify(uplar_business_profile_should_autostart($pdo, 101, false), 'New user should be eligible');
verify(!uplar_business_profile_should_autostart($pdo, 102, false), 'Old user should be excluded');
verify(!uplar_business_profile_should_autostart($pdo, 101, true), 'Admin should be excluded');

uplar_business_profile_store($pdo, 101, [
    'segmento' => 'hamburgueria', 'status_configuracao'=>'em_andamento',
    'categorias_pendentes'=>['Combos'], 'categorias_definidas'=>true,
]);
verify(uplar_business_profile_read($pdo, 101)['categorias_pendentes'] === ['Combos'], 'Draft should persist');
verify(!uplar_business_profile_should_autostart($pdo, 101, false), 'Draft should not autostart again');
$confirmed = uplar_business_profile_store($pdo, 101, [
    'segmento' => 'hamburgueria', 'status_configuracao'=>'concluida',
    'categorias_pendentes'=>[], 'categorias_definidas'=>true,
    'aplicar_modo'=>true, 'entrega'=>true, 'retirada'=>false,
]);
verify($confirmed['modo_pendente'] === 'cardapio', 'Without vitrine row, mode must stay pending');
verify($confirmed['categorias_pendentes'] === [], 'Intentionally empty categories must be retained');
verify((int)$pdo->query('SELECT COUNT(*) FROM vitrine_config WHERE user_id = 101')->fetchColumn() === 0, 'A profile must not publish a storefront');

try {
    uplar_business_profile_store($pdo, 101, ['segmento'=>'hamburgueria','status_configuracao'=>'dispensada']);
    throw new RuntimeException('Completed profile must not be discarded');
} catch (InvalidArgumentException $e) { /* expected */ }

$confirmed = uplar_business_profile_store($pdo, 102, [
    'segmento'=>'artigos_esportivos','status_configuracao'=>'concluida',
    'categorias_pendentes'=>['Equipamentos'],'categorias_definidas'=>true,'aplicar_modo'=>false,
]);
verify($confirmed['segmento'] === 'artigos_esportivos', 'Second business profile should persist');
$cfg = $pdo->query('SELECT experiencia_json FROM vitrine_config WHERE user_id = 102')->fetchColumn();
verify(uplar_vitrine_experience_mode($cfg) === 'loja', 'Existing vitrine must remain unchanged');
verify(uplar_vitrine_experience_data($cfg)['mostrar_destaques'] === false, 'Unrelated preferences must be preserved');

uplar_business_profile_store($pdo, 102, [
    'segmento'=>'restaurante','status_configuracao'=>'concluida',
    'categorias_pendentes'=>['Pratos'],'categorias_definidas'=>true,'aplicar_modo'=>true,
]);
$cfg = $pdo->query('SELECT experiencia_json FROM vitrine_config WHERE user_id = 102')->fetchColumn();
verify(uplar_vitrine_experience_mode($cfg) === 'cardapio', 'Explicit confirmation should change mode');
verify(uplar_vitrine_experience_data($cfg)['mostrar_destaques'] === false, 'Other preferences survive mode change');
verify(uplar_business_profile_read($pdo, 101)['segmento'] === 'hamburgueria', 'Stores must stay isolated');

echo "PASS — migration twice, drafts, atomic saves, old accounts, pending modes, isolation.\n";
