<?php
function dietFoodPhoto(array $food): array {
    $food['profileUrl'] = empty($food['profileId']) ? '' : 'https://constructsmarterpublic.s3.us-west-1.amazonaws.com/' . rawurlencode($food['profileId']);
    return $food;
}
// Recover a missing unit only when the food still has the same serving amount.
function dietServingLabel(string $saved, ?string $current): string {
    if (preg_match('/^\d+(?:\.\d+)?$/', trim($saved))) {
        if ($current && preg_match('/^(\d+(?:\.\d+)?)\s+(.+)$/u', trim($current), $match) && preg_match('/\p{L}/u',$match[2]) && (float)$saved === (float)$match[1]) return trim($saved).' '.$match[2];
        return trim($saved).' (unit not recorded)';
    }
    return $saved;
}
function dietText($input, string $key, int $max, bool $required = true): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > $max || ($required && trim($value) === '')) throw new InvalidArgumentException("Invalid $key.");
    return trim($value);
}
function dietNumber($input, string $key, bool $positive = false): float {
    $value = $input[$key] ?? null;
    if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,3})?$/', (string)$value)) throw new InvalidArgumentException("Enter a valid $key (up to 3 decimal places).");
    $value = (float)$value;
    if ($value > 100000 || ($positive && $value <= 0)) throw new InvalidArgumentException("Invalid $key.");
    return $value;
}
function dietId($input, string $key): int {
    $id = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) throw new InvalidArgumentException("Invalid $key.");
    return $id;
}
function dietDate($input): string {
    $value = dietText($input, 'date', 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || (int)$date->format('Y') < 1000 || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Invalid date.');
    return $value;
}
function dietPage($input, int $total): array {
    $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT);
    $limit = filter_var($input['limit'] ?? 10, FILTER_VALIDATE_INT);
    if (!$page || $page < 1 || !$limit || $limit < 1 || $limit > 100) throw new InvalidArgumentException('Invalid page.');
    $pages = max(1, (int)ceil($total / $limit));
    return ['page' => min($page, $pages), 'limit' => $limit, 'total' => $total, 'totalPages' => $pages];
}
function dietFind($db, string $table, string $userId, int $id, bool $active = false): array {
    $row = $db->one("SELECT * FROM `$table` WHERE `id` = ? AND `userId` = ?" . ($active ? ' AND `deletedAt` IS NULL' : ''), [$id, $userId]);
    if (!$row) throw new OutOfBoundsException('Record not found.');
    return $row;
}
function dietTotals(array $food, float $quantity): array {
    $totals = [];
    foreach (['calories','protein','carbs','fat'] as $key) $totals[$key] = round((float)$food[$key] * $quantity, 2);
    return $totals;
}
function dietApi($db, string $userId, array $input) {
    $action = $input['action'] ?? '';
    require_once __DIR__.'/dishes.php';
    if (in_array($action,['dish','dishes','searchDishes','saveDish','deleteDish'],true)) return dietDishApi($db,$userId,$input);
    if ($action === 'entryDetails') {
        $entry=dietFind($db,'dietEntries',$userId,dietId($input,'id'));
        $kind=empty($entry['dishId'])?'food':'dish';
        $item=$kind==='dish'?dietDish($db,$userId,(int)$entry['dishId'],false):dietFoodPhoto(dietFind($db,'dietFoods',$userId,(int)$entry['foodId']));
        return ['kind'=>$kind,'item'=>$item,'editable'=>empty($item['deletedAt'])];
    }
    if ($action === 'food') return dietFoodPhoto(dietFind($db, 'dietFoods', $userId, dietId($input,'id'), true));
    if ($action === 'foods' || $action === 'search') {
        $query = dietText($input, 'q', 200, false);
        if ($action === 'search' && $query === '') return [];
        $where = '`userId` = ? AND `deletedAt` IS NULL'; $params = [$userId];
        if ($query !== '') { $where .= " AND `name` LIKE ? ESCAPE '='"; $params[] = '%' . str_replace(['=','%','_'], ['==','=%','=_'], $query) . '%'; }
        if ($action === 'search') {
            $rows = $db->all("SELECT `id`, `name`, `serving` FROM `dietFoods` WHERE $where ORDER BY `name`, `id` LIMIT 50", $params);
            return array_map(fn($row) => ['value'=>(string)$row['id'], 'label'=>$row['name'], 'meta'=>$row['serving']], $rows);
        }
        $total = (int)$db->one("SELECT COUNT(*) AS total FROM `dietFoods` WHERE $where", $params)['total'];
        $page = dietPage($input, $total); $offset = ($page['page'] - 1) * $page['limit']; $limit = $page['limit'];
        $page['items'] = $db->all("SELECT * FROM `dietFoods` WHERE $where ORDER BY `name`, `id` LIMIT $limit OFFSET $offset", $params);
        $page['items'] = array_map('dietFoodPhoto', $page['items']);
        return $page;
    }
    if ($action === 'saveFood') {
        $name = dietText($input,'name',200);
        if (array_key_exists('servingAmount',$input) || array_key_exists('servingUnit',$input)) {
            $amount=dietNumber($input,'servingAmount',true);$unit=dietText($input,'servingUnit',60);
            if (!preg_match('/\p{L}/u',$unit) || preg_match('/[\r\n]/',$unit)) throw new InvalidArgumentException('Enter a valid serving unit.');
            $serving=rtrim(rtrim(number_format($amount,3,'.',''),'0'),'.').' '.$unit;
        } else $serving = dietText($input,'serving',100);
        $macros = array_map(fn($key) => dietNumber($input,$key), ['calories','protein','carbs','fat']);
        if (!empty($input['id'])) dietFind($db,'dietFoods',$userId,dietId($input,'id'),true);
        $profile = null;
        if (array_key_exists('profileId', $input) && $input['profileId'] !== null && $input['profileId'] !== '') {
            require_once __DIR__ . '/foodProfile.php';
            $profile = validateFoodProfile($db, $userId, $input['profileId']);
        }
        if (!empty($input['id'])) {
            $id = dietId($input,'id'); dietFind($db,'dietFoods',$userId,$id,true);
            $db->exec('UPDATE `dietFoods` SET `name`=?, `serving`=?, `calories`=?, `protein`=?, `carbs`=?, `fat`=? WHERE `id`=? AND `userId`=?', [$name,$serving,...$macros,$id,$userId]);
        } else {
            $db->exec('INSERT INTO `dietFoods` (`userId`,`name`,`serving`,`calories`,`protein`,`carbs`,`fat`) VALUES (?,?,?,?,?,?,?)', [$userId,$name,$serving,...$macros]);
            $id = (int)$db->lastInsertId();
        }
        if (array_key_exists('profileId', $input)) $db->exec('UPDATE `dietFoods` SET `profileId`=? WHERE `id`=? AND `userId`=?', [$profile,$id,$userId]);
        return dietFoodPhoto(dietFind($db,'dietFoods',$userId,$id,true));
    }
    if ($action === 'deleteFood') {
        $id = dietId($input,'id'); dietFind($db,'dietFoods',$userId,$id,true);
        $db->exec('UPDATE `dietFoods` SET `deletedAt`=CURRENT_TIMESTAMP WHERE `id`=? AND `userId`=?',[$id,$userId]);
        return ['success'=>true];
    }
    if ($action === 'day') {
        $date = dietDate($input);
        $summary = $db->one('SELECT COUNT(*) AS total, COALESCE(SUM(ROUND(`calories`*`quantity`,2)),0) AS calories, COALESCE(SUM(ROUND(`protein`*`quantity`,2)),0) AS protein, COALESCE(SUM(ROUND(`carbs`*`quantity`,2)),0) AS carbs, COALESCE(SUM(ROUND(`fat`*`quantity`,2)),0) AS fat FROM `dietEntries` WHERE `userId`=? AND `datePerformed`=?',[$userId,$date]);
        $page = dietPage($input,(int)$summary['total']); $offset = ($page['page']-1)*$page['limit']; $limit=$page['limit'];
        $page['items']=$db->all("SELECT e.*, f.serving AS currentServing FROM dietEntries e LEFT JOIN dietFoods f ON f.id=e.foodId AND f.userId=e.userId WHERE e.userId=? AND e.datePerformed=? ORDER BY e.id DESC LIMIT $limit OFFSET $offset",[$userId,$date]);
        foreach ($page['items'] as &$entry) {
            $entry['totals']=dietTotals($entry,(float)$entry['quantity']);
            $entry['serving']=dietServingLabel($entry['serving'],$entry['currentServing']);
            unset($entry['currentServing']);
        }
        unset($entry);
        $page['totals']=array_map('floatval', array_intersect_key($summary,array_flip(['calories','protein','carbs','fat'])));
        return $page;
    }
    if ($action === 'saveEntry') {
        $date=dietDate($input); $dishId=!empty($input['dishId'])?dietId($input,'dishId'):null; $foodId=$dishId?0:dietId($input,'foodId'); $quantity=dietNumber($input,'quantity',true);
        $meal=dietText($input,'meal',16); $notes=dietText($input,'notes',2000,false);
        if (!in_array($meal,['Breakfast','Lunch','Dinner','Snack'],true)) throw new InvalidArgumentException('Invalid meal.');
        $entry=!empty($input['id']) ? dietFind($db,'dietEntries',$userId,dietId($input,'id')) : null;
        // Quantity/meal edits retain the original nutrition snapshot.
        $same=$entry && (int)$entry['foodId']===$foodId && (int)($entry['dishId']??0)===(int)$dishId;
        $food=$same ? $entry : ($dishId ? dietDish($db,$userId,$dishId) : dietFind($db,'dietFoods',$userId,$foodId,true));
        if($dishId && !$same) foreach($food['items'] as $item) if($item['deletedAt']) throw new InvalidArgumentException('Replace removed foods in this dish before logging it.');
        $dishItems=$same ? $entry['dishItems'] : ($dishId ? json_encode($food['items']) : null);
        foreach(['calories','protein','carbs','fat'] as $key) if((float)$food[$key]>999999999.999) throw new InvalidArgumentException('Dish nutrition exceeds the supported limit. Reduce its food quantities.');
        $values=[$foodId,$date,$meal,$quantity,$food['name'],$food['serving'],$food['calories'],$food['protein'],$food['carbs'],$food['fat'],$notes,$dishId,$dishItems];
        if ($entry) {
            $id=(int)$entry['id'];
            $db->exec('UPDATE `dietEntries` SET `foodId`=?, `datePerformed`=?, `meal`=?, `quantity`=?, `name`=?, `serving`=?, `calories`=?, `protein`=?, `carbs`=?, `fat`=?, `notes`=?, `dishId`=?, `dishItems`=? WHERE `id`=? AND `userId`=?',[...$values,$id,$userId]);
        } else {
            $db->exec('INSERT INTO `dietEntries` (`foodId`,`datePerformed`,`meal`,`quantity`,`name`,`serving`,`calories`,`protein`,`carbs`,`fat`,`notes`,`dishId`,`dishItems`,`userId`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$values,$userId]);
            $id=(int)$db->lastInsertId();
        }
        return ['id'=>$id,'success'=>true];
    }
    if ($action === 'deleteEntry') {
        $id=dietId($input,'id'); dietFind($db,'dietEntries',$userId,$id);
        $db->exec('DELETE FROM `dietEntries` WHERE `id`=? AND `userId`=?',[$id,$userId]);
        return ['success'=>true];
    }
    throw new InvalidArgumentException('Unknown diet action.');
}
