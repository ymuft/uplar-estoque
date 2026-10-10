<?php
/**
 * Compatibilidade da configuração de experiência da vitrine.
 *
 * A coluna experiencia_json é opcional durante a implantação da migration.
 */

function uplar_vitrine_experience_schema_disponivel(PDO $pdo): bool {
    static $cache = [];

    $connectionId = spl_object_id($pdo);
    if (array_key_exists($connectionId, $cache)) {
        return $cache[$connectionId];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM vitrine_config LIKE 'experiencia_json'");
        $cache[$connectionId] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cache[$connectionId] = false;
    }

    return $cache[$connectionId];
}

function uplar_vitrine_experience_data($experienciaJson): array {
    if (!is_string($experienciaJson) || $experienciaJson === '') {
        return [];
    }

    try {
        $data = json_decode($experienciaJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return [];
    }

    return is_array($data) ? $data : [];
}

function uplar_vitrine_experience_mode($experienciaJson): string {
    $modo = uplar_vitrine_experience_data($experienciaJson)['modo'] ?? 'padrao';
    return in_array($modo, ['padrao', 'loja', 'cardapio'], true) ? $modo : 'padrao';
}

function uplar_vitrine_experience_json_com_modo($experienciaJson, string $modo): string {
    $data = uplar_vitrine_experience_data($experienciaJson);
    $data['modo'] = $modo;

    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** Preferences affect presentation and enabled service choices, never prices. */
function uplar_vitrine_experience_defaults(): array {
    return ['modo'=>'padrao','categorias'=>[],'mostrar_destaques'=>true,'indisponiveis'=>'ocultar',
        'entrega_habilitada'=>true,'retirada_habilitada'=>true,'pedidos_habilitados'=>true,'fonte_categorias'=>'legado'];
}

function uplar_vitrine_experience_validate(array $values): array {
    $defaults=uplar_vitrine_experience_defaults();
    foreach($values as $key=>$value){
        if(!array_key_exists($key,$defaults))throw new InvalidArgumentException('Preferência da vitrine inválida.');
        if(in_array($key,['mostrar_destaques','entrega_habilitada','retirada_habilitada','pedidos_habilitados'],true)&&!is_bool($value))throw new InvalidArgumentException('Informe uma configuração de atendimento válida.');
    }
    if(array_key_exists('modo',$values)&&!in_array($values['modo'],['padrao','loja','cardapio'],true))throw new InvalidArgumentException('Modo de experiência inválido.');
    if(array_key_exists('indisponiveis',$values)&&!in_array($values['indisponiveis'],['mostrar','ocultar'],true))throw new InvalidArgumentException('Exibição de produtos indisponíveis inválida.');
    if(array_key_exists('fonte_categorias',$values)&&!in_array($values['fonte_categorias'],['legado','canonico'],true))throw new InvalidArgumentException('Origem das categorias inválida.');
    if(array_key_exists('categorias',$values)){
        if(!is_array($values['categorias'])||!array_is_list($values['categorias'])||count($values['categorias'])>40)throw new InvalidArgumentException('Configure até 40 categorias.');
        $seenCategories=[];$seenProducts=[];$categories=[];
        foreach($values['categorias'] as $category){
            if(!is_array($category)||!is_string($category['id']??null)||!preg_match('/^[a-zA-Z0-9_-]{1,60}$/D',$category['id'])||isset($seenCategories[$category['id']]))throw new InvalidArgumentException('Identificador de categoria inválido ou repetido.');
            $name=is_string($category['nome']??null)?trim($category['nome']):'';
            if($name===''||mb_strlen($name)>80)throw new InvalidArgumentException('Informe o nome da categoria com até 80 caracteres.');
            $ids=$category['produto_ids']??[];
            if(!is_array($ids)||!array_is_list($ids))throw new InvalidArgumentException('Produtos da categoria inválidos.');
            foreach($ids as $id){
                if(!is_int($id)||$id<1||isset($seenProducts[$id]))throw new InvalidArgumentException('Cada produto deve pertencer a uma única categoria.');
                $seenProducts[$id]=true;
                if(count($seenProducts)>5000)throw new InvalidArgumentException('Limite de produtos nas categorias excedido.');
            }
            $seenCategories[$category['id']]=true;$categories[]=['id'=>$category['id'],'nome'=>$name,'produto_ids'=>$ids];
        }
        $values['categorias']=$categories;
    }
    return $values;
}

function uplar_vitrine_experience_preferences($json): array {
    $data=uplar_vitrine_experience_data($json);$defaults=uplar_vitrine_experience_defaults();
    $known=array_intersect_key($data,$defaults);
    try{$known=uplar_vitrine_experience_validate($known);}catch(InvalidArgumentException $e){$known=['modo'=>uplar_vitrine_experience_mode($json)];}
    return array_replace($defaults,$known);
}

function uplar_vitrine_experience_merge($storedJson,array $posted): array {
    $validated=uplar_vitrine_experience_validate($posted);
    $stored=uplar_vitrine_experience_data($storedJson);$merged=array_replace($stored,$validated);
    $prefs=uplar_vitrine_experience_preferences(json_encode($merged,JSON_THROW_ON_ERROR));
    if($prefs['pedidos_habilitados']&&!$prefs['entrega_habilitada']&&!$prefs['retirada_habilitada'])throw new InvalidArgumentException('Habilite entrega ou retirada para receber pedidos.');
    return $merged;
}
