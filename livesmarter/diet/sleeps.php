<?php
function dietSleepApi($db, string $userId, array $input) {
    $action=$input['action'];
    if ($action==='sleepDates') return array_column($db->all('SELECT DISTINCT `datePerformed` FROM `dietSleeps` WHERE `userId` = ? ORDER BY `datePerformed`',[$userId]),'datePerformed');
    if ($action==='sleepTrend') {
        $start=dietDate(['date'=>$input['startDate']??'']);$end=dietDate(['date'=>$input['endDate']??'']);
        if ($start>$end) throw new InvalidArgumentException('Start date must be on or before end date.');
        $rows=$db->all("SELECT datePerformed AS date, SUM(hours) AS hours, COUNT(*) AS entryCount FROM dietSleeps WHERE userId=? AND datePerformed BETWEEN ? AND ? GROUP BY datePerformed ORDER BY datePerformed",[$userId,$start,$end]);
        return ['startDate'=>$start,'endDate'=>$end,'items'=>array_map(fn($row)=>['date'=>$row['date'],'hours'=>(float)$row['hours'],'entryCount'=>(int)$row['entryCount']],$rows)];
    }
    if ($action==='sleeps') {
        $date=dietDate($input);
        $total=(int)$db->one('SELECT COUNT(*) AS total FROM dietSleeps WHERE userId=? AND datePerformed=?',[$userId,$date])['total'];
        $page=dietPage($input,$total);$limit=$page['limit'];$offset=($page['page']-1)*$limit;
        return [...$page,'items'=>$db->all("SELECT id,datePerformed,hours,`time`,notes FROM dietSleeps WHERE userId=? AND datePerformed=? ORDER BY id DESC LIMIT $limit OFFSET $offset",[$userId,$date])];
    }
    if ($action==='deleteSleep') {
        $id=dietId($input,'id');dietFind($db,'dietSleeps',$userId,$id);
        $db->exec('DELETE FROM dietSleeps WHERE id=? AND userId=?',[$id,$userId]);
        return ['success'=>true];
    }
    $date=dietDate($input);$hours=dietNumber($input,'hours');
    if ($hours>24) throw new InvalidArgumentException('Sleep hours must be from 0 to 24.');
    $time=dietText($input,'time',5,false);
    if ($time!=='' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$time)) throw new InvalidArgumentException('Enter a valid time (HH:mm).');
    $time=$time===''?null:$time;
    $notes=dietText($input,'notes',2000,false);
    if (!empty($input['id'])) {
        $id=dietId($input,'id');$existing=dietFind($db,'dietSleeps',$userId,$id);
        if (!array_key_exists('time',$input)) $time=$existing['time'];
        $db->exec('UPDATE dietSleeps SET datePerformed=?,hours=?,`time`=?,notes=? WHERE id=? AND userId=?',[$date,$hours,$time,$notes,$id,$userId]);
    } else {
        $db->exec('INSERT INTO dietSleeps (userId,datePerformed,hours,`time`,notes) VALUES (?,?,?,?,?)',[$userId,$date,$hours,$time,$notes]);
        $id=$db->lastInsertId();
    }
    return dietFind($db,'dietSleeps',$userId,(int)$id);
}

function createDietSleepSchema($db, bool $temporary = false): void {
    $prefix = $temporary ? 'CREATE TEMPORARY TABLE' : 'CREATE TABLE IF NOT EXISTS';
    $db->exec("$prefix `dietSleeps` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `userId` VARCHAR(64) NOT NULL,
        `datePerformed` DATE NOT NULL,
        `hours` DECIMAL(6,3) NOT NULL,
        `time` VARCHAR(5) NULL DEFAULT NULL,
        `notes` TEXT NOT NULL,
        `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `diet_sleep_day` (`userId`, `datePerformed`, `id`)
    ) ENGINE=InnoDB", []);
}
