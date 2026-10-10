<?php
function dietWeightApi($db, string $userId, array $input) {
    $action=$input['action'];
    if ($action==='weightDates') return array_column($db->all('SELECT DISTINCT `datePerformed` FROM `dietWeights` WHERE `userId` = ? ORDER BY `datePerformed`',[$userId]),'datePerformed');
    if ($action==='weightTrend') {
        $start=dietDate(['date'=>$input['startDate']??'']);$end=dietDate(['date'=>$input['endDate']??'']);
        if ($start>$end) throw new InvalidArgumentException('Start date must be on or before end date.');
        $rows=$db->all("SELECT datePerformed AS date, AVG(CASE WHEN unit='lb' THEN weight*0.45359237 ELSE weight END) AS weightKg, COUNT(*) AS entryCount FROM dietWeights WHERE userId=? AND datePerformed BETWEEN ? AND ? GROUP BY datePerformed ORDER BY datePerformed",[$userId,$start,$end]);
        return ['startDate'=>$start,'endDate'=>$end,'items'=>array_map(fn($row)=>['date'=>$row['date'],'weightKg'=>(float)$row['weightKg'],'entryCount'=>(int)$row['entryCount']],$rows)];
    }
    if ($action==='weights') {
        $date=dietDate($input);
        $total=(int)$db->one('SELECT COUNT(*) AS total FROM dietWeights WHERE userId=? AND datePerformed=?',[$userId,$date])['total'];
        $page=dietPage($input,$total);$limit=$page['limit'];$offset=($page['page']-1)*$limit;
        return [...$page,'items'=>$db->all("SELECT id,datePerformed,weight,unit,`time`,notes FROM dietWeights WHERE userId=? AND datePerformed=? ORDER BY id DESC LIMIT $limit OFFSET $offset",[$userId,$date])];
    }
    if ($action==='deleteWeight') {
        $id=dietId($input,'id');dietFind($db,'dietWeights',$userId,$id);
        $db->exec('DELETE FROM dietWeights WHERE id=? AND userId=?',[$id,$userId]);
        return ['success'=>true];
    }
    $date=dietDate($input);$weight=dietNumber($input,'weight',true);
    if ($weight>2000) throw new InvalidArgumentException('Weight must be at most 2000.');
    $unit=dietText($input,'unit',2);
    if (!in_array($unit,['kg','lb'],true)) throw new InvalidArgumentException('Select kg or lb.');
    $time=dietText($input,'time',5,false);
    if ($time!=='' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$time)) throw new InvalidArgumentException('Enter a valid time (HH:mm).');
    $time=$time===''?null:$time;
    $notes=dietText($input,'notes',2000,false);
    if (!empty($input['id'])) {
        $id=dietId($input,'id');$existing=dietFind($db,'dietWeights',$userId,$id);
        if (!array_key_exists('time',$input)) $time=$existing['time'];
        $db->exec('UPDATE dietWeights SET datePerformed=?,weight=?,unit=?,`time`=?,notes=? WHERE id=? AND userId=?',[$date,$weight,$unit,$time,$notes,$id,$userId]);
    } else {
        $db->exec('INSERT INTO dietWeights (userId,datePerformed,weight,unit,`time`,notes) VALUES (?,?,?,?,?,?)',[$userId,$date,$weight,$unit,$time,$notes]);
        $id=$db->lastInsertId();
    }
    return dietFind($db,'dietWeights',$userId,(int)$id);
}
