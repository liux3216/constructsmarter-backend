<?php
function todoDate($value): string {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) throw new InvalidArgumentException('Select a valid date.');
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if (!$date || (int)$date->format('Y')<1000 || $date->format('Y-m-d')!==$value) throw new InvalidArgumentException('Select a valid date.');
    return $value;
}
function todoId($input): int {
    $id=filter_var($input['id']??null,FILTER_VALIDATE_INT);
    if (!$id || $id<1) throw new InvalidArgumentException('Invalid task.');return $id;
}
function todoFind($db,string $owner,int $id): array {
    $row=$db->one('SELECT * FROM todoItems WHERE id=? AND userId=?',[$id,$owner]);
    if (!$row) throw new OutOfBoundsException('Task not found.');
    $row['completed']=(bool)$row['completed'];return $row;
}
function todoText($input,string $key,int $max,bool $required=false): string {
    $value=$input[$key]??'';
    if (!is_string($value) || strlen($value)>$max || ($required&&trim($value)==='')) throw new InvalidArgumentException("Invalid $key.");return trim($value);
}
function todoOccurs(array $task,string $date): bool {
    if(($task['taskType']??'dated')==='undated')return false;
    $start=$task['datePerformed'];if($date<$start)return false;
    $unit=$task['recurrence']??'none';if($unit==='none')return $date===$start;
    $target=new DateTimeImmutable($date);$anchor=new DateTimeImmutable($start);
    if(!empty($task['weekdaysOnly'])&&(int)$target->format('N')>5)return false;
    $interval=(int)($task['recurrenceInterval']??1);
    if($unit==='day')return (int)$anchor->diff($target)->format('%a')%$interval===0;
    $years=(int)$target->format('Y')-(int)$anchor->format('Y');
    $months=$years*12+(int)$target->format('n')-(int)$anchor->format('n');
    if($unit==='month'&&$months%$interval!==0)return false;
    if($unit==='year'&&($years%$interval!==0||$target->format('m')!==$anchor->format('m')))return false;
    return (int)$target->format('j')===min((int)$anchor->format('j'),(int)$target->format('t'));
}
function todoDayItems($db,string $owner,?string $date): array {
    $items=$date===null?$db->all("SELECT * FROM todoItems WHERE userId=? AND taskType='undated'",[$owner]):$db->all("SELECT * FROM todoItems WHERE userId=? AND taskType='dated' AND (datePerformed=? OR (recurrence<>'none' AND datePerformed<=?))",[$owner,$date,$date]);
    $status=$date===null?[]:$db->all('SELECT o.taskId,o.completed FROM todoOccurrences o JOIN todoItems t ON t.id=o.taskId WHERE t.userId=? AND o.datePerformed=?',[$owner,$date]);
    $status=array_column($status,'completed','taskId');$result=[];
    foreach($items as $task){if($date!==null&&!todoOccurs($task,$date))continue;$task['startDate']=$task['datePerformed'];$task['datePerformed']=$date;$task['completed']=$task['recurrence']==='none'?(bool)$task['completed']: (isset($status[$task['id']])?(bool)$status[$task['id']]:(bool)($task['completed']&&$task['startDate']===$date));$result[]=$task;}
    usort($result,function($a,$b)use($date){$deadline=$date===null?(((int)($a['deadline']===null)<=>(int)($b['deadline']===null))?:strcmp($a['deadline']??'',$b['deadline']??'')):0;return ((int)$a['completed']<=>(int)$b['completed'])?:$deadline?:((int)($a['time']===null)<=>(int)($b['time']===null))?:strcmp($a['time']??'',$b['time']??'')?:((int)$b['id']<=>(int)$a['id']);});
    $saved=$date===null?$db->one('SELECT taskIds FROM todoUndatedOrders WHERE userId=?',[$owner]):$db->one('SELECT taskIds FROM todoDayOrders WHERE userId=? AND datePerformed=?',[$owner,$date]);
    $ids=$saved?json_decode($saved['taskIds'],true):[];
    if(is_array($ids)&&$ids){$rank=array_flip(array_map('strval',$ids));$base=array_flip(array_map(fn($task)=>(string)$task['id'],$result));usort($result,fn($a,$b)=>((int)$a['completed']<=>(int)$b['completed'])?:(($rank[(string)$a['id']]??count($rank)+$base[(string)$a['id']])<=>($rank[(string)$b['id']]??count($rank)+$base[(string)$b['id']])));}
    return $result;
}
function todoLockOrder($db,string $owner,?string $date): void {
    if($date===null)$db->exec("INSERT INTO todoUndatedOrders (userId,taskIds) VALUES (?,'[]') ON DUPLICATE KEY UPDATE taskIds=taskIds",[$owner]);
    else $db->exec("INSERT INTO todoDayOrders (userId,datePerformed,taskIds) VALUES (?,? ,'[]') ON DUPLICATE KEY UPDATE taskIds=taskIds",[$owner,$date]);
}
function todoSaveOrder($db,string $owner,?string $date,array $ids): void {
    if($date===null)$db->exec('UPDATE todoUndatedOrders SET taskIds=? WHERE userId=?',[json_encode($ids),$owner]);
    else $db->exec('UPDATE todoDayOrders SET taskIds=? WHERE userId=? AND datePerformed=?',[json_encode($ids),$owner,$date]);
}
function todoApi($db,string $owner,array $input) {
    $action=$input['action']??'';
    if($action==='dates') {
        if(!isset($input['startDate'])&&!isset($input['endDate']))return array_column($db->all("SELECT DISTINCT datePerformed FROM todoItems WHERE userId=? AND taskType='dated' ORDER BY datePerformed",[$owner]),'datePerformed');
        $start=todoDate($input['startDate']??null);$end=todoDate($input['endDate']??null);
        $from=new DateTimeImmutable($start);$to=new DateTimeImmutable($end);
        if($start>$end||(int)$from->diff($to)->format('%a')>366)throw new InvalidArgumentException('Select a calendar range of at most 367 days.');
        $tasks=$db->all("SELECT * FROM todoItems WHERE userId=? AND taskType='dated' AND datePerformed<=? AND (recurrence<>'none' OR datePerformed>=?)",[$owner,$end,$start]);
        $dates=[];for($day=$from;$day<=$to;$day=$day->modify('+1 day')){foreach($tasks as $task){if(todoOccurs($task,$day->format('Y-m-d'))){$dates[]=$day->format('Y-m-d');break;}}}return $dates;
    }
    if($action==='day'||$action==='undated') {
        $date=$action==='undated'?null:todoDate($input['date']??null);
        $result=todoDayItems($db,$owner,$date);
        $page=filter_var($input['page']??1,FILTER_VALIDATE_INT);$limit=filter_var($input['limit']??10,FILTER_VALIDATE_INT);
        if(!$page||$page<1||!$limit||$limit<1||$limit>100)throw new InvalidArgumentException('Invalid page.');
        $total=count($result);$completed=count(array_filter($result,fn($task)=>$task['completed']));$pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);
        return ['items'=>array_slice($result,($page-1)*$limit,$limit),'page'=>$page,'limit'=>$limit,'total'=>$total,'totalPages'=>$pages,'completed'=>$completed];
    }
    if($action==='move') {
        $id=todoId($input);$task=todoFind($db,$owner,$id);$date=$task['taskType']==='undated'?null:todoDate($input['date']??null);$direction=$input['direction']??'';
        if(!in_array($direction,['up','down'],true))throw new InvalidArgumentException('Select up or down.');
        $db->begin();
        try {
            todoLockOrder($db,$owner,$date);
            $items=todoDayItems($db,$owner,$date);$ids=array_map(fn($task)=>(int)$task['id'],$items);$index=array_search($id,$ids,true);
            if($index===false)throw new OutOfBoundsException('Task is not scheduled on this date.');
            $next=$index+($direction==='up'?-1:1);
            if($next>=0&&$next<count($ids)&&$items[$index]['completed']===$items[$next]['completed'])[$ids[$index],$ids[$next]]=[$ids[$next],$ids[$index]];
            todoSaveOrder($db,$owner,$date,$ids);
            $db->commit();return ['success'=>true];
        }catch(Throwable $e){$db->rollback();throw $e;}
    }
    if($action==='delete'||$action==='complete') {
        $id=todoId($input);$task=todoFind($db,$owner,$id);
        if($action==='delete'){$db->exec('DELETE FROM todoOccurrences WHERE taskId=?',[$id]);$db->exec('DELETE FROM todoItems WHERE id=? AND userId=?',[$id,$owner]);}
        else {
            if(!in_array($input['completed']??null,[0,1,'0','1'],true))throw new InvalidArgumentException('Invalid completion status.');
            $date=$task['taskType']==='undated'?null:($task['recurrence']==='none'?$task['datePerformed']:todoDate($input['date']??null));
            if($date!==null&&!todoOccurs($task,$date))throw new InvalidArgumentException('Task is not scheduled on this date.');
            $db->begin();
            try {
                todoLockOrder($db,$owner,$date);
                $before=todoDayItems($db,$owner,$date);$previous=false;foreach($before as $item)if((int)$item['id']===$id)$previous=$item['completed'];
                if($task['recurrence']==='none')$db->exec('UPDATE todoItems SET completed=? WHERE id=? AND userId=?',[(int)$input['completed'],$id,$owner]);
                else $db->exec('INSERT INTO todoOccurrences (taskId,datePerformed,completed) VALUES (?,?,?) ON DUPLICATE KEY UPDATE completed=VALUES(completed)',[$id,$date,(int)$input['completed']]);
                if((int)$input['completed']===1&&!$previous){
                    $ids=array_map(fn($item)=>(int)$item['id'],array_filter($before,fn($item)=>(int)$item['id']!==$id));$ids[]=$id;
                    todoSaveOrder($db,$owner,$date,$ids);
                }
                $db->commit();
            }catch(Throwable $e){$db->rollback();throw $e;}
        }return ['success'=>true];
    }
    if($action!=='save')throw new InvalidArgumentException('Unknown action.');
    $existing=!empty($input['id'])?todoFind($db,$owner,todoId($input)):null;
    $type=$input['taskType']??($existing['taskType']??'dated');
    if(!in_array($type,['dated','undated'],true))throw new InvalidArgumentException('Select a valid task type.');
    $date=$type==='undated'?null:todoDate($input['date']??null);
    $deadline=$type==='undated'?($input['deadline']??($existing['deadline']??'')):'';
    $deadline=$deadline===''||$deadline===null?null:todoDate($deadline);
    $title=todoText($input,'title',200,true);$notes=todoText($input,'notes',2000);$time=todoText($input,'time',5);
    if($time!==''&&!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$time))throw new InvalidArgumentException('Enter a valid time (HH:mm).');$time=$time===''?null:$time;
    if($type==='undated'&&$deadline===null)$time=null;
    $recurrence=$input['recurrence']??($existing['recurrence']??'none');
    $interval=filter_var($input['recurrenceInterval']??($existing['recurrenceInterval']??1),FILTER_VALIDATE_INT);
    $weekdays=$input['weekdaysOnly']??($existing['weekdaysOnly']??0);
    if(!in_array($recurrence,['none','day','month','year'],true)||!$interval||$interval<1||$interval>1000||!in_array($weekdays,[0,1,'0','1'],true))throw new InvalidArgumentException('Invalid repeat schedule.');
    if($type==='undated'&&$recurrence!=='none')throw new InvalidArgumentException('Undated tasks cannot repeat.');
    if($recurrence==='none'){$interval=1;$weekdays=0;}
    if($existing){$id=(int)$existing['id'];$db->exec('UPDATE todoItems SET datePerformed=?,title=?,`time`=?,notes=?,recurrence=?,recurrenceInterval=?,weekdaysOnly=?,taskType=?,deadline=? WHERE id=? AND userId=?',[$date,$title,$time,$notes,$recurrence,$interval,(int)$weekdays,$type,$deadline,$id,$owner]);}
    else{$db->exec('INSERT INTO todoItems (userId,datePerformed,title,`time`,notes,recurrence,recurrenceInterval,weekdaysOnly,taskType,deadline) VALUES (?,?,?,?,?,?,?,?,?,?)',[$owner,$date,$title,$time,$notes,$recurrence,$interval,(int)$weekdays,$type,$deadline]);$id=(int)$db->lastInsertId();}
    return todoFind($db,$owner,$id);
}
