<?php
/** M3 integration, only isolated uplar_m3_ci MySQL database */
declare(strict_types=1);
require_once __DIR__.'/../includes/catalog_categories.php';
require_once __DIR__.'/../includes/vitrine_experience.php';
function ensureM3(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
$dsn=(string)getenv('UPLAR_TEST_MYSQL_DSN');
if($dsn===''||!str_contains($dsn,'dbname=uplar_m3_ci'))throw new RuntimeException('Refusing test outside uplar_m3_ci.');
$pdo=new PDO($dsn,(string)getenv('UPLAR_TEST_MYSQL_USER'),(string)getenv('UPLAR_TEST_MYSQL_PASSWORD'),[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE produtos (
 id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
 user_id INT NOT NULL,
 nome VARCHAR(150) NOT NULL,
 codigo VARCHAR(50) NOT NULL DEFAULT '',
 categoria VARCHAR(80) NULL,
 vitrine_exposto TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("INSERT INTO produtos(user_id,nome,codigo,categoria,vitrine_exposto)
VALUES (11,'X-Bacon','LAN1','Sanduíches',1),(11,'Refrigerante','BEB1','Bebidas',1),
(12,'Camisa Esportiva','CAM1','Vestuário',1),(11,'Produto oculto','OCULTO','Bebidas',0)");
ensureM3(!uplar_catalogo_schema_ready($pdo),'Schema should be missing before migration.');
$sql=file_get_contents(__DIR__.'/../sql/20261010_catalog_categories.sql');
$sql=preg_replace('/^\\s*--[^\\n]*(\\n|$)/m','',$sql);
foreach(range(1,2) as $iteration){foreach(explode(';',$sql) as $part){if(trim($part)!=='')$pdo->exec(trim($part));}}
ensureM3(uplar_catalogo_schema_ready($pdo),'Schema not ready after migration.');
$burgers=uplar_catalogo_ensure($pdo,11,'Hambúrgueres');
ensureM3($burgers===uplar_catalogo_ensure($pdo,11,'  Hambúrgueres  '),'Category creation must be idempotent.');
$drinks=uplar_catalogo_ensure($pdo,11,'Bebidas');
ensureM3($drinks===uplar_catalogo_ensure($pdo,11,'bebidas'),'Case insensitive duplicate must reuse existing row.');
$other=uplar_catalogo_ensure($pdo,12,'Vestuário');
ensureM3($other!==$burgers,'Tenant category IDs must be distinct.');
try{uplar_catalogo_assign($pdo,11,1,$other);throw new RuntimeException('Cross-tenant category accepted');}
catch(InvalidArgumentException $e){/* expected */}
try{uplar_catalogo_assign($pdo,11,3,$burgers);throw new RuntimeException('Cross-tenant product accepted');}
catch(InvalidArgumentException $e){/* expected */}
uplar_catalogo_assign($pdo,11,1,$burgers);
uplar_catalogo_assign($pdo,11,2,$drinks);
uplar_catalogo_assign($pdo,11,4,$drinks);
$public=uplar_catalogo_public_categories($pdo,11);
ensureM3(count($public)===2,'Should expose both categories.');
$map=array_column($public,'produto_ids','nome');
ensureM3($map['Bebidas']===[2],'Hidden product must not appear in category IDs.');
ensureM3($map['Hambúrgueres']===[1],'Wrong product-category membership.');
$legacy=['modo'=>'cardapio','categorias'=>[['id'=>'old','nome'=>'Antigos','produto_ids'=>[1]]],'fonte_categorias'=>'legado'];
ensureM3(!uplar_catalogo_use_public($pdo,11,$legacy),'Legacy explicitly configured categories must take precedence.');
ensureM3(uplar_catalogo_public_prefs($pdo,11,$legacy)['categorias'][0]['nome']==='Antigos','Legacy category lost.');
$canonical=$legacy;$canonical['fonte_categorias']='canonico';
ensureM3(uplar_catalogo_use_public($pdo,11,$canonical),'Explicit canonical activation not honored.');
ensureM3(uplar_catalogo_public_prefs($pdo,11,$canonical)['categorias'][0]['nome']!=='Antigos','Public mapping did not switch.');
$conflicts=uplar_catalogo_audit($pdo,11,$legacy['categorias']);
ensureM3($conflicts['conflitos']>=1,'Mismatch should be reported, not overwritten.');
ensureM3($conflicts['origem_manual']===1,'Manual association must remain readable.');
ensureM3((int)$pdo->query('SELECT categoria_id FROM produtos WHERE id=1')->fetchColumn()===$burgers,'Audit must not mutate products.');
echo "PASS — migration twice, tenant isolation, categorização, conflito, fallback legado, visibilidade.\n";
