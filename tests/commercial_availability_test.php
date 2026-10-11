<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/commercial_availability.php';

function ca_assert(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); }
function ca_same($expected,$actual,string $message): void { if($expected!==$actual) throw new RuntimeException($message.' esperado='.var_export($expected,true).' atual='.var_export($actual,true)); }
function ca_now(string $value): DateTimeImmutable { return new DateTimeImmutable($value,new DateTimeZone('UTC')); }

$legacy=uplar_storefront_schedule_preferences([]);
$defaultHours=uplar_storefront_schedule_defaults()['horarios'];
ca_same(uplar_commercial_availability_weekdays(),array_keys($defaultHours['entrega']),'Defaults precisam serializar dias como objeto/mapa completo');
ca_same('[]',json_encode($defaultHours['entrega']['seg']),'Dia desabilitado deve continuar como lista vazia');
$legacyState=uplar_commercial_availability_service_state($legacy,'entrega',ca_now('2026-10-12T12:00:00Z'));
ca_same(true,$legacyState['allowed'],'Dados antigos não podem ativar bloqueio de horário');
ca_same('horario_legado',$legacyState['reason'],'Comportamento legado deve ser identificável');

$schedule=uplar_commercial_availability_validate_schedule([
    'controle_horarios'=>true,'timezone'=>'America/Sao_Paulo','pausado'=>false,'mensagem_pausa'=>'',
    'horarios'=>[
        'entrega'=>['seg'=>[['inicio'=>'18:00','fim'=>'02:00']],'ter'=>[],'qua'=>[],'qui'=>[],'sex'=>[],'sab'=>[],'dom'=>[]],
        'retirada'=>['seg'=>[['inicio'=>'09:00','fim'=>'12:00'],['inicio'=>'14:00','fim'=>'18:00']],'ter'=>[],'qua'=>[],'qui'=>[],'sex'=>[],'sab'=>[],'dom'=>[]]
    ],'excecoes'=>[]
]);
ca_same(true,uplar_commercial_availability_service_state($schedule,'entrega',ca_now('2026-10-12T22:30:00Z'))['allowed'],'Intervalo noturno deve abrir');
ca_same(true,uplar_commercial_availability_service_state($schedule,'entrega',ca_now('2026-10-13T04:30:00Z'))['allowed'],'Intervalo noturno deve atravessar meia-noite');
ca_same(false,uplar_commercial_availability_service_state($schedule,'retirada',ca_now('2026-10-12T15:30:00Z'))['allowed'],'Intervalo entre turnos permanece fechado');
ca_same(true,uplar_commercial_availability_service_state($schedule,'retirada',ca_now('2026-10-12T17:30:00Z'))['allowed'],'Segundo intervalo deve abrir');
$closed=$schedule;$closed['excecoes']=[['data'=>'2026-10-12','fechado'=>true]];
ca_same('fechado_por_excecao',uplar_commercial_availability_service_state($closed,'entrega',ca_now('2026-10-12T22:30:00Z'))['reason'],'Exceção de fechamento prevalece');
$paused=$schedule;$paused['pausado']=true;$paused['mensagem_pausa']='Voltamos em breve';
$pausedState=uplar_commercial_availability_service_state($paused,'entrega',ca_now('2026-10-12T22:30:00Z'));
ca_same('pedido_pausado',$pausedState['reason'],'Pausa global precisa de motivo próprio');
ca_same('Voltamos em breve',$pausedState['message'],'Mensagem de pausa deve ser preservada');
$invalid=uplar_storefront_schedule_preferences(['atendimento'=>['controle_horarios'=>true,'timezone'=>'Fuso/Inválido']]);
$invalidState=uplar_commercial_availability_service_state($invalid,'entrega',ca_now('2026-10-12T12:00:00Z'));
ca_same(true,$invalid['configuracao_invalida'],'Configuração inválida deve ser identificada');
ca_same(false,$invalidState['allowed'],'Horário M6 inválido precisa falhar fechado');
ca_same('horario_invalido',$invalidState['reason'],'Erro de configuração deve ser explícito');

$stock=uplar_commercial_availability_evaluate_product(['quantidade'=>3],null,null,null);
ca_same(true,$stock['available'],'Produto legado com saldo está disponível');ca_same(3,$stock['stock'],'Saldo físico deve ser retornado');
ca_same('sem_estoque',uplar_commercial_availability_evaluate_product(['quantidade'=>0],null,null,null)['reason'],'Saldo zero deve ser indisponível');
ca_same('produto_pausado',uplar_commercial_availability_evaluate_product(['quantidade'=>9,'disponibilidade_manual'=>0],null,null,null)['reason'],'Pausa manual prevalece');
$demand=uplar_commercial_availability_evaluate_product(['modo_disponibilidade'=>'sob_demanda','quantidade'=>0],null,null,null);
ca_same(true,$demand['available'],'Sob demanda não usa saldo fictício');ca_same(false,$demand['stock_limited'],'Sob demanda não limita quantidade pelo estoque');
ca_same('sob_demanda',uplar_commercial_availability_snapshot_mode(['modo_disponibilidade'=>'sob_demanda']),'Snapshot Site PRO usa alias legado');
ca_same('estoque',uplar_commercial_availability_snapshot_mode([]),'Pedido antigo continua usando baixa de estoque físico');
try{uplar_commercial_availability_snapshot_mode(['disponibilidade_modo'=>'inesperado']);throw new RuntimeException('Modo de pedido inválido deveria ser rejeitado');}catch(InvalidArgumentException $e){}
$reservations=[];
ca_same(true,uplar_commercial_availability_reserve_quantity($reservations,7,'M',2,['stock_limited'=>true,'stock'=>3]),'Primeira linha deve reservar saldo');
ca_same(false,uplar_commercial_availability_reserve_quantity($reservations,7,'M',2,['stock_limited'=>true,'stock'=>3]),'Personalizações distintas devem compartilhar o mesmo saldo da variação');
ca_same(true,uplar_commercial_availability_reserve_quantity($reservations,8,null,99,['stock_limited'=>false,'stock'=>null]),'Sob demanda não deve reservar estoque físico');
ca_same(true,uplar_commercial_availability_evaluate_product(['modo_disponibilidade'=>'sob_demanda','usa_tamanhos'=>1],'G',0,0)['available'],'Variação existente sob demanda pode ter quantidade zero');
ca_same('variante_indisponivel',uplar_commercial_availability_evaluate_product(['modo_disponibilidade'=>'sob_demanda','usa_tamanhos'=>1],'GG',null,0)['reason'],'Variação ausente/inativa não pode ser comprada');
ca_same('variante_obrigatoria',uplar_commercial_availability_evaluate_product(['usa_tamanhos'=>1,'quantidade'=>3],null,null,3)['reason'],'Produto com variação exige seleção');

$merged=json_decode(uplar_storefront_schedule_merge_json('{"modo":"cardapio"}',$schedule),true,512,JSON_THROW_ON_ERROR);
ca_same('cardapio',$merged['modo'],'Mesclagem deve manter modo da vitrine');ca_same(true,$merged['atendimento']['controle_horarios'],'Mesclagem salva atendimento');
try{uplar_commercial_availability_validate_schedule(['controle_horarios'=>true,'timezone'=>'UTC','horarios'=>['entrega'=>['seg'=>[['inicio'=>'09:00','fim'=>'09:00']]]]]);throw new RuntimeException('Intervalo vazio deveria ser rejeitado');}catch(InvalidArgumentException $e){}
try{uplar_commercial_availability_validate_schedule(['version'=>2]);throw new RuntimeException('Versão desconhecida deveria ser rejeitada');}catch(InvalidArgumentException $e){}
echo "commercial_availability_test: ok\n";
