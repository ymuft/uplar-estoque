<?php
/**
 * Regras centrais de disponibilidade comercial.
 * Este helper apenas avalia preferências e produtos; não altera estoque nem pedidos.
 */
declare(strict_types=1);

const UPLAR_COMMERCIAL_AVAILABILITY_VERSION = 1;

function uplar_commercial_availability_schema_ready(PDO $pdo): bool
{
    static $cache = [];
    $key = spl_object_id($pdo);
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $mode = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'modo_disponibilidade'");
        $manual = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'disponibilidade_manual'");
        return $cache[$key] = (bool)$mode->fetch(PDO::FETCH_ASSOC) && (bool)$manual->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function uplar_commercial_availability_product_select(PDO $pdo, string $alias): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $alias)) throw new InvalidArgumentException('Alias de produto inválido.');
    if (!uplar_commercial_availability_schema_ready($pdo)) return ", 'estoque' AS disponibilidade_modo, 1 AS disponibilidade_manual";
    return ", COALESCE({$alias}.modo_disponibilidade, 'estoque') AS disponibilidade_modo, COALESCE({$alias}.disponibilidade_manual, 1) AS disponibilidade_manual";
}

function uplar_commercial_availability_bool($value, bool $default): bool
{
    if (is_bool($value)) return $value;
    if (is_int($value) || is_float($value)) return (int)$value !== 0;
    if (is_string($value)) {
        $value = strtolower(trim($value));
        if (in_array($value, ['1','true','sim','on'], true)) return true;
        if (in_array($value, ['0','false','nao','não','off'], true)) return false;
    }
    return $default;
}

function uplar_commercial_availability_normalize_product(array $row): array
{
    $mode = $row['disponibilidade_modo'] ?? $row['modo_disponibilidade'] ?? 'estoque';
    $row['disponibilidade_modo'] = $mode === 'sob_demanda' ? 'sob_demanda' : 'estoque';
    $row['disponibilidade_manual'] = uplar_commercial_availability_bool($row['disponibilidade_manual'] ?? $row['manual_disponivel'] ?? true, true);
    $stock = $row['quantidade'] ?? $row['stock'] ?? 0;
    $row['estoque_disponivel'] = max(0, is_numeric($stock) ? (int)$stock : 0);
    $row['usa_tamanhos'] = uplar_commercial_availability_bool($row['usa_tamanhos'] ?? $row['usa_variantes'] ?? false, false);
    return $row;
}

/** Resolve mode keys across current and historical order snapshots. */
function uplar_commercial_availability_snapshot_mode(array $snapshot): string
{
    $mode = $snapshot['disponibilidade_modo'] ?? $snapshot['modo_disponibilidade'] ?? 'estoque';
    if (!is_string($mode) || !in_array($mode, ['estoque', 'sob_demanda'], true)) {
        throw new InvalidArgumentException('Modo de disponibilidade inválido no pedido.');
    }
    return $mode;
}

/** Aggregate same product/variant across differently personalized order lines. */
function uplar_commercial_availability_reserve_quantity(array &$reserved, int $productId, ?string $variant, int $quantity, array $availability): bool
{
    if ($productId < 1 || $quantity < 1) return false;
    if (empty($availability['stock_limited'])) return true;
    $stock = max(0, (int)($availability['stock'] ?? 0));
    $key = $productId . ':' . ($variant ?? '');
    $next = ($reserved[$key] ?? 0) + $quantity;
    if ($next > $stock) return false;
    $reserved[$key] = $next;
    return true;
}

function uplar_commercial_availability_evaluate_product(array $product, ?string $variant, ?int $variantStock, ?int $productStock): array
{
    $product = uplar_commercial_availability_normalize_product($product);
    $mode = $product['disponibilidade_modo'];
    $hasVariants = (bool)$product['usa_tamanhos'];
    $base = ['mode'=>$mode, 'stock_limited'=>$mode === 'estoque', 'stock'=>null];
    if (!$product['disponibilidade_manual']) return array_replace($base, ['available'=>false, 'reason'=>'produto_pausado']);
    if ($hasVariants && ($variant === null || trim($variant) === '')) return array_replace($base, ['available'=>false, 'reason'=>'variante_obrigatoria']);
    if ($hasVariants && $variantStock === null) return array_replace($base, ['available'=>false, 'reason'=>'variante_indisponivel']);
    if (!$hasVariants && $variant !== null && trim($variant) !== '') return array_replace($base, ['available'=>false, 'reason'=>'variante_nao_suportada']);
    if ($mode === 'sob_demanda') return array_replace($base, ['available'=>true, 'reason'=>'disponivel']);
    $stock = $hasVariants ? max(0, (int)$variantStock) : max(0, $productStock ?? (int)$product['estoque_disponivel']);
    if ($stock < 1) return array_replace($base, ['available'=>false, 'reason'=>'sem_estoque', 'stock'=>$stock]);
    return array_replace($base, ['available'=>true, 'reason'=>'disponivel', 'stock'=>$stock]);
}

function uplar_storefront_schedule_defaults(): array
{
    $week = array_fill_keys(uplar_commercial_availability_weekdays(), []);
    return ['version'=>UPLAR_COMMERCIAL_AVAILABILITY_VERSION,'controle_horarios'=>false,'timezone'=>'America/Sao_Paulo','pausado'=>false,'mensagem_pausa'=>'','horarios'=>['entrega'=>$week,'retirada'=>$week],'excecoes'=>[]];
}

function uplar_commercial_availability_weekdays(): array { return ['seg','ter','qua','qui','sex','sab','dom']; }

function uplar_commercial_availability_weekday(DateTimeImmutable $date): string
{
    return uplar_commercial_availability_weekdays()[(int)$date->format('N') - 1];
}

function uplar_commercial_availability_time_to_minutes($value, string $field, bool $isEnd = false): int
{
    if (!is_string($value) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value)) throw new InvalidArgumentException("{$field} deve usar o formato HH:MM.");
    return (int)substr($value,0,2) * 60 + (int)substr($value,3,2);
}

function uplar_commercial_availability_normalize_intervals($value, string $field): array
{
    if (!is_array($value) || !array_is_list($value) || count($value) > 12) throw new InvalidArgumentException("{$field} deve ter até 12 intervalos.");
    $result = [];
    foreach ($value as $index=>$interval) {
        if (!is_array($interval) || array_diff(array_keys($interval), ['inicio','fim'])) throw new InvalidArgumentException("{$field}[{$index}] é inválido.");
        $start = uplar_commercial_availability_time_to_minutes($interval['inicio'] ?? null, "{$field}[{$index}].inicio");
        $end = uplar_commercial_availability_time_to_minutes($interval['fim'] ?? null, "{$field}[{$index}].fim", true);
        if ($start === $end) throw new InvalidArgumentException("{$field}[{$index}] não pode ter início e fim iguais.");
        $result[] = ['inicio'=>sprintf('%02d:%02d',intdiv($start,60),$start%60),'fim'=>sprintf('%02d:%02d',intdiv($end,60),$end%60)];
    }
    usort($result, static fn($a,$b)=>strcmp($a['inicio'],$b['inicio']));
    return $result;
}

function uplar_commercial_availability_normalize_hours($value, string $field): array
{
    if (!is_array($value)) throw new InvalidArgumentException("{$field} é inválido.");
    foreach ($value as $day=>$_) if (!is_string($day) || !in_array($day,uplar_commercial_availability_weekdays(),true)) throw new InvalidArgumentException("{$field} contém um dia inválido.");
    $result=[];
    foreach (uplar_commercial_availability_weekdays() as $day) $result[$day]=uplar_commercial_availability_normalize_intervals($value[$day]??[],"{$field}.{$day}");
    return $result;
}

function uplar_commercial_availability_validate_schedule(array $value): array
{
    $allowed=['version','controle_horarios','timezone','pausado','mensagem_pausa','horarios','excecoes'];
    foreach($value as $key=>$_) if(!in_array($key,$allowed,true)) throw new InvalidArgumentException('Preferência de atendimento inválida.');
    if(isset($value['version'])&&(!is_int($value['version'])||$value['version']!==UPLAR_COMMERCIAL_AVAILABILITY_VERSION)) throw new InvalidArgumentException('Versão de atendimento inválida.');
    $defaults=uplar_storefront_schedule_defaults();
    $control=$value['controle_horarios']??$defaults['controle_horarios'];$paused=$value['pausado']??$defaults['pausado'];
    if(!is_bool($control)||!is_bool($paused)) throw new InvalidArgumentException('Controle de atendimento inválido.');
    $timezone=$value['timezone']??$defaults['timezone'];
    if(!is_string($timezone)||strlen($timezone)>80) throw new InvalidArgumentException('Fuso horário inválido.');
    try{new DateTimeZone($timezone);}catch(Throwable $e){throw new InvalidArgumentException('Fuso horário inválido.');}
    $message=$value['mensagem_pausa']??'';
    if(!is_string($message)) throw new InvalidArgumentException('Mensagem de pausa inválida.');
    $message=trim(preg_replace('/\s+/u',' ',$message)??'');
    if(mb_strlen($message)>160) throw new InvalidArgumentException('A mensagem de pausa deve ter até 160 caracteres.');
    $hoursInput=$value['horarios']??[];
    if(!is_array($hoursInput)||array_diff(array_keys($hoursInput),['entrega','retirada'])) throw new InvalidArgumentException('Horários inválidos.');
    $hours=[];foreach(['entrega','retirada'] as $service)$hours[$service]=uplar_commercial_availability_normalize_hours($hoursInput[$service]??[],"horarios.{$service}");
    $exceptions=$value['excecoes']??[];
    if(!is_array($exceptions)||!array_is_list($exceptions)||count($exceptions)>366) throw new InvalidArgumentException('Exceções de atendimento inválidas.');
    $normalized=[];
    foreach($exceptions as $index=>$exception){
        if(!is_array($exception)||array_diff(array_keys($exception),['data','fechado','entrega','retirada'])) throw new InvalidArgumentException("Exceção {$index} inválida.");
        $date=$exception['data']??null;$closed=$exception['fechado']??null;
        if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!is_bool($closed)) throw new InvalidArgumentException("Exceção {$index} inválida.");
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('UTC'));
        if(!$parsed||$parsed->format('Y-m-d')!==$date||isset($normalized[$date])) throw new InvalidArgumentException('Data de exceção inválida ou repetida.');
        $item=['data'=>$date,'fechado'=>$closed];
        if(!$closed) foreach(['entrega','retirada'] as $service) if(array_key_exists($service,$exception))$item[$service]=uplar_commercial_availability_normalize_intervals($exception[$service],"excecoes.{$index}.{$service}");
        $normalized[$date]=$item;
    }
    ksort($normalized);
    return ['version'=>UPLAR_COMMERCIAL_AVAILABILITY_VERSION,'controle_horarios'=>$control,'timezone'=>$timezone,'pausado'=>$paused,'mensagem_pausa'=>$message,'horarios'=>$hours,'excecoes'=>array_values($normalized)];
}

function uplar_storefront_schedule_preferences($experience): array
{
    if(is_string($experience)){try{$experience=json_decode($experience,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){$experience=[];}}
    if(!is_array($experience)) return uplar_storefront_schedule_defaults();
    $has=array_key_exists('atendimento',$experience)||array_key_exists('disponibilidade',$experience);
    if(!$has)return uplar_storefront_schedule_defaults();
    $schedule=$experience['atendimento']??$experience['disponibilidade'];
    try{if(!is_array($schedule))throw new InvalidArgumentException('Horários inválidos.');return uplar_commercial_availability_validate_schedule($schedule);}
    catch(Throwable $e){$invalid=uplar_storefront_schedule_defaults();$invalid['controle_horarios']=true;$invalid['configuracao_invalida']=true;return $invalid;}
}

function uplar_storefront_schedule_merge_json($experienceJson,array $schedule): string
{
    if(is_string($experienceJson)){try{$experience=json_decode($experienceJson,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){$experience=[];}}
    else $experience=is_array($experienceJson)?$experienceJson:[];
    if(!is_array($experience))$experience=[];
    $experience['atendimento']=uplar_commercial_availability_validate_schedule($schedule);
    return json_encode($experience,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}

function uplar_commercial_availability_exception(array $schedule,string $date): ?array
{
    foreach($schedule['excecoes'] as $exception)if($exception['data']===$date)return $exception;
    return null;
}

function uplar_commercial_availability_intervals_for_date(array $schedule,string $service,DateTimeImmutable $date): array
{
    $exception=uplar_commercial_availability_exception($schedule,$date->format('Y-m-d'));
    if($exception!==null){if($exception['fechado'])return [];if(array_key_exists($service,$exception))return $exception[$service];}
    return $schedule['horarios'][$service][uplar_commercial_availability_weekday($date)]??[];
}

function uplar_commercial_availability_in_interval(int $minute,array $interval): bool
{
    $start=uplar_commercial_availability_time_to_minutes($interval['inicio'],'inicio');$end=uplar_commercial_availability_time_to_minutes($interval['fim'],'fim',true);
    return $end>$start?$minute>=$start&&$minute<$end:$minute>=$start||$minute<$end;
}

function uplar_commercial_availability_next_open(array $schedule,string $service,DateTimeImmutable $now): ?string
{
    for($offset=0;$offset<=8;$offset++){
        $date=$offset===0?$now:$now->modify("+{$offset} days")->setTime(0,0);
        if(($exception=uplar_commercial_availability_exception($schedule,$date->format('Y-m-d')))!==null&&$exception['fechado'])continue;
        foreach(uplar_commercial_availability_intervals_for_date($schedule,$service,$date) as $interval){[$hour,$minute]=array_map('intval',explode(':',$interval['inicio']));$candidate=$date->setTime($hour,$minute);if($candidate>$now)return $candidate->format(DateTimeInterface::ATOM);}
    }
    return null;
}

function uplar_commercial_availability_service_state(array $preferences,string $type,DateTimeImmutable $now): array
{
    if(!in_array($type,['entrega','retirada'],true))throw new InvalidArgumentException('Tipo de atendimento inválido.');
    if(!empty($preferences['configuracao_invalida']))$schedule=array_replace(uplar_storefront_schedule_defaults(),$preferences);
    elseif(array_key_exists('atendimento',$preferences)||array_key_exists('disponibilidade',$preferences))$schedule=uplar_storefront_schedule_preferences($preferences);
    else $schedule=uplar_commercial_availability_validate_schedule($preferences);
    $local=$now->setTimezone(new DateTimeZone($schedule['timezone']));$base=['timezone'=>$schedule['timezone'],'next_open'=>null];
    if($schedule['pausado'])return array_replace($base,['allowed'=>false,'reason'=>'pedido_pausado','message'=>$schedule['mensagem_pausa']?:'Pedidos pausados temporariamente.']);
    if(!$schedule['controle_horarios'])return array_replace($base,['allowed'=>true,'reason'=>'horario_legado','message'=>'']);
    if(!empty($schedule['configuracao_invalida']))return array_replace($base,['allowed'=>false,'reason'=>'horario_invalido','message'=>'Os horários de atendimento precisam ser revisados pela loja.']);
    $date=$local->format('Y-m-d');$exception=uplar_commercial_availability_exception($schedule,$date);
    if($exception!==null&&$exception['fechado'])return array_replace($base,['allowed'=>false,'reason'=>'fechado_por_excecao','message'=>'Serviço indisponível nesta data.','next_open'=>uplar_commercial_availability_next_open($schedule,$type,$local)]);
    $minute=(int)$local->format('H')*60+(int)$local->format('i');
    foreach(uplar_commercial_availability_intervals_for_date($schedule,$type,$local) as $interval){$start=uplar_commercial_availability_time_to_minutes($interval['inicio'],'inicio');$end=uplar_commercial_availability_time_to_minutes($interval['fim'],'fim',true);if($end>$start&&$minute>=$start&&$minute<$end)return array_replace($base,['allowed'=>true,'reason'=>'aberto','message'=>'']);if($end<$start&&$minute>=$start)return array_replace($base,['allowed'=>true,'reason'=>'aberto','message'=>'']);}
    $yesterday=$local->modify('-1 day');
    foreach(uplar_commercial_availability_intervals_for_date($schedule,$type,$yesterday) as $interval){$start=uplar_commercial_availability_time_to_minutes($interval['inicio'],'inicio');$end=uplar_commercial_availability_time_to_minutes($interval['fim'],'fim',true);if($end<$start&&$minute<$end)return array_replace($base,['allowed'=>true,'reason'=>'aberto','message'=>'']);}
    return array_replace($base,['allowed'=>false,'reason'=>'fora_do_horario','message'=>'Serviço indisponível neste horário.','next_open'=>uplar_commercial_availability_next_open($schedule,$type,$local)]);
}
