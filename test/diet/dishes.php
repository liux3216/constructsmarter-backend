<?php
function dietDish($db, string $userId, int $id, bool $active = true): array {
    $dish = dietFoodPhoto(dietFind($db, 'dietDishes', $userId, $id, $active));
    $dish['items'] = $db->all('SELECT i.*, f.name, f.serving, f.calories, f.protein, f.carbs, f.fat, f.deletedAt FROM dietDishFoods i JOIN dietFoods f ON f.id=i.foodId AND f.userId=? WHERE i.dishId=? ORDER BY i.id', [$userId,$id]);
    $dish['serving'] = '1 dish';
    foreach (['calories','protein','carbs','fat'] as $key) {
        $dish[$key] = round(array_sum(array_map(fn($item)=>(float)$item[$key]*(float)$item['quantity'], $dish['items'])),3);
    }
    return $dish;
}
function dietDishApi($db, string $userId, array $input) {
    $action=$input['action'];
    if ($action==='dish') return dietDish($db,$userId,dietId($input,'id'));
    if ($action==='dishes' || $action==='searchDishes') {
        $q=dietText($input,'q',200,false);
        if ($action==='searchDishes' && $q==='') return [];
        $like='%'.str_replace(['=','%','_'],['==','=%','=_'],$q).'%';
        $where="userId=? AND deletedAt IS NULL AND name LIKE ? ESCAPE '='";
        $args=[$userId,$like];
        if ($action==='searchDishes') return array_map(fn($row)=>['value'=>(string)$row['id'],'label'=>$row['name']],$db->all("SELECT id,name FROM dietDishes WHERE $where ORDER BY name,id LIMIT 50",$args));
        $page=dietPage($input,(int)$db->one("SELECT COUNT(*) total FROM dietDishes WHERE $where",$args)['total']);
        $offset=($page['page']-1)*$page['limit'];$limit=$page['limit'];
        $page['items']=array_map('dietFoodPhoto',$db->all("SELECT * FROM dietDishes WHERE $where ORDER BY name,id LIMIT $limit OFFSET $offset",$args));
        return $page;
    }
    if ($action==='deleteDish') {
        $id=dietId($input,'id');dietFind($db,'dietDishes',$userId,$id,true);
        $db->exec('UPDATE dietDishes SET deletedAt=CURRENT_TIMESTAMP WHERE id=? AND userId=?',[$id,$userId]);
        return ['success'=>true];
    }
    $name=dietText($input,'name',200);
    $old=!empty($input['id'])?dietDish($db,$userId,dietId($input,'id')):null;
    $items=json_decode(dietText($input,'items',50000),true);
    if (!is_array($items) || count($items)<1 || count($items)>100 || !array_is_list($items)) throw new InvalidArgumentException('Add between 1 and 100 foods.');
    $seen=[];$validated=[];
    foreach ($items as $item) {
        if (!is_array($item)) throw new InvalidArgumentException('Invalid dish food.');
        $foodId=dietId($item,'foodId');$quantity=dietNumber($item,'quantity',true);
        if (isset($seen[$foodId])) throw new InvalidArgumentException('Each food should appear once. Adjust its servings instead.');
        $seen[$foodId]=true;
        $existing=$old && in_array($foodId,array_column($old['items'],'foodId'));
        dietFind($db,'dietFoods',$userId,$foodId,!$existing);
        $validated[]=[$foodId,$quantity];
    }
    $profile=$old['profileId']??null;
    if(array_key_exists('profileId',$input)) {
        $profile=$input['profileId'];
        if($profile===null || $profile==='') $profile=null;
        else {require_once __DIR__.'/foodProfile.php';$profile=validateFoodProfile($db,$userId,$profile);}
    }
    $db->begin();
    try {
        if($old) {$id=$old['id'];$db->exec('UPDATE dietDishes SET name=?,profileId=? WHERE id=? AND userId=?',[$name,$profile,$id,$userId]);}
        else {$db->exec('INSERT INTO dietDishes (userId,name,profileId) VALUES (?,?,?)',[$userId,$name,$profile]);$id=(int)$db->lastInsertId();}
        $db->exec('DELETE FROM dietDishFoods WHERE dishId=?',[$id]);
        foreach($validated as [$foodId,$quantity]) $db->exec('INSERT INTO dietDishFoods (dishId,foodId,quantity) VALUES (?,?,?)',[$id,$foodId,$quantity]);
        $db->commit();
    } catch(Throwable $e) {$db->rollback();throw $e;}
    return dietDish($db,$userId,(int)$id);
}
