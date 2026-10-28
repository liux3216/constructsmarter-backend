<?php
function vocabularyAbbreviationSurface($value): string {
    if(!is_string($value))throw new InvalidArgumentException('Enter an abbreviation.');
    $value=trim(str_replace(['’','‘','‐','‑'],["'","'",'-','-'],$value));
    if(class_exists('Normalizer'))$value=Normalizer::normalize($value,Normalizer::FORM_C);
    $valid=$value!==''&&mb_strlen($value)<=100&&preg_match('/\\p{L}/u',$value);
    if($valid)foreach(explode('-',$value) as $part)if(!preg_match("/^[\\p{L}\\p{M}\\p{N}]+(?:[.'][\\p{L}\\p{M}\\p{N}]+)*\\.?(?:'[sS])?$/u",$part)){$valid=false;break;}
    if(!$valid)throw new InvalidArgumentException('Use an abbreviation of up to 100 characters, with letters, numbers, dots, apostrophes or hyphens.');
    return $value;
}
function vocabularyAbbreviationKey(string $surface, bool $caseSensitive=true): string {
    $surface=rtrim($surface,'.');
    if(str_ends_with(mb_strtolower($surface),"'s"))$surface=substr($surface,0,-2);
    $surface=rtrim($surface,'.');
    return $caseSensitive?$surface:mb_strtolower($surface,'UTF-8');
}
function vocabularyAbbreviationParts(string $surface): array {
    return array_values(array_unique([$surface,...explode('-',$surface)]));
}
function vocabularyPossibleAbbreviation(string $surface): bool {
    if(str_ends_with(mb_strtolower($surface),"'s"))$surface=substr($surface,0,-2);
    return vocabularyIsAbbreviation($surface)
        || (preg_match('/\\p{L}.*\\p{L}/u',$surface)&&preg_match('/^[\\p{Lu}\\p{N}-]+\\.?$/u',$surface))
        || preg_match('/^\\p{Lu}[\\p{Ll}]{1,5}\\.$/u',$surface);
}
function vocabularySurfaceWord(string $surface): ?string {
    try{return vocabularyWord($surface);}catch(InvalidArgumentException $error){
        try{return vocabularyWord(rtrim($surface,'.'));}catch(InvalidArgumentException $error){return null;}
    }
}
function vocabularyAbbreviationApi($db,string $userId,array $input): array {
    $action=$input['action'];
    if($action==='abbreviations') {
        $sort=$input['sort']??'word_asc';
        $orders=['word_asc'=>'LOWER(abbreviation),abbreviation,id','date_asc'=>'created_at ASC,id ASC','date_desc'=>'created_at DESC,id DESC'];
        if(!is_string($sort)||!isset($orders[$sort]))throw new InvalidArgumentException('Invalid sort order.');
        $order=$orders[$sort];
        $q=$input['q']??'';
        if(!is_string($q)||mb_strlen($q)>100)throw new InvalidArgumentException('Search must be up to 100 characters.');
        $q=mb_strtolower(trim($q),'UTF-8');$params=[$userId,$q,$q];
        $filter='user_id=? AND (LOCATE(?,LOWER(abbreviation))>0 OR LOCATE(?,LOWER(expansion))>0)';
        $total=(int)$db->one("SELECT COUNT(*) total FROM vocabulary_abbreviations WHERE $filter",$params)['total'];
        $page=$input['page']??1;$limit=$input['limit']??20;
        if(!preg_match('/^[1-9]\d*$/',(string)$page)||!in_array((string)$limit,['1','5','10','20','50','100'],true))throw new InvalidArgumentException('Invalid page.');
        $limit=(int)$limit;$page=min((int)$page,max(1,(int)ceil($total/$limit)));$offset=($page-1)*$limit;
        $items=$db->all("SELECT id,abbreviation,expansion,case_sensitive,created_at FROM vocabulary_abbreviations WHERE $filter ORDER BY $order LIMIT $limit OFFSET $offset",$params);
        foreach($items as &$item)$item['case_sensitive']=(bool)$item['case_sensitive'];unset($item);
        return ['items'=>$items,'total'=>$total,'page'=>$page,'limit'=>$limit];
    }
    $id=empty($input['id'])?0:vocabularyId($input['id']);
    if($action==='deleteAbbreviation') {
        if(!$id)throw new InvalidArgumentException('Invalid abbreviation ID.');
        $db->begin();try{vocabularyOwned($db,'vocabulary_abbreviations',$userId,$id);$db->exec('DELETE FROM vocabulary_abbreviations WHERE id=? AND user_id=?',[$id,$userId]);$db->commit();}catch(Throwable $error){$db->rollback();throw $error;}
        return ['success'=>true];
    }
    $abbreviation=vocabularyAbbreviationSurface($input['abbreviation']??null);
    $expansion=$input['expansion']??'';$case=$input['case_sensitive']??'1';
    if(!is_string($expansion)||mb_strlen($expansion)>2000)throw new InvalidArgumentException('Expansion must be up to 2000 characters.');
    if(!in_array($case,[true,false,1,0,'1','0'],true))throw new InvalidArgumentException('Choose whether capitalization must match.');
    $case=(int)$case;$key=vocabularyAbbreviationKey($abbreviation,(bool)$case);
    $db->begin();try {
        if($id)vocabularyOwned($db,'vocabulary_abbreviations',$userId,$id);
        if($db->one('SELECT id FROM vocabulary_abbreviations WHERE user_id=? AND match_key=? AND case_sensitive=? AND id<>?',[$userId,$key,$case,$id]))throw new InvalidArgumentException('This abbreviation rule already exists. Edit its existing entry.');
        if($id)$db->exec('UPDATE vocabulary_abbreviations SET abbreviation=?,match_key=?,expansion=?,case_sensitive=? WHERE id=? AND user_id=?',[$abbreviation,$key,trim($expansion),$case,$id,$userId]);
        else{$db->exec('INSERT INTO vocabulary_abbreviations (user_id,abbreviation,match_key,expansion,case_sensitive) VALUES (?,?,?,?,?)',[$userId,$abbreviation,$key,trim($expansion),$case]);$id=(int)$db->lastInsertId();}
        $db->commit();
    }catch(Throwable $error){$db->rollback();throw $error;}
    return ['id'=>$id,'abbreviation'=>$abbreviation,'expansion'=>trim($expansion),'case_sensitive'=>(bool)$case];
}
function vocabularyFindAbbreviations($db,string $userId,$encoded,array $words,array $new,array $names=[]): array {
    $surfaces=is_string($encoded)?json_decode($encoded,true):null;
    if(!is_array($surfaces)||!array_is_list($surfaces)||count($surfaces)>10000)throw new InvalidArgumentException('Use up to 10,000 unique original word forms.');
    $groups=[];$keys=[];
    foreach($surfaces as $value){
        $surface=vocabularyAbbreviationSurface($value);$key=rtrim($surface,'.');
        // Prefer the dotted spelling if both forms occur, to flag Nov / Nov. once.
        if(!isset($groups[$key])||str_ends_with($surface,'.'))$groups[$key]=$surface;
        foreach(vocabularyAbbreviationParts($surface) as $part){$keys[vocabularyAbbreviationKey($part)]=true;$keys[vocabularyAbbreviationKey($part,false)]=true;}
    }
    $sensitive=[];$insensitive=[];
    foreach(array_chunk(array_keys($keys),500) as $chunk){
        $in=implode(',',array_fill(0,count($chunk),'?'));
        foreach($db->all("SELECT match_key,case_sensitive FROM vocabulary_abbreviations WHERE user_id=? AND match_key IN ($in)",[$userId,...$chunk]) as $row){
            if($row['case_sensitive'])$sensitive[$row['match_key']]=true;else $insensitive[$row['match_key']]=true;
        }
    }
    $ordinary=[];$possible=[];$matched=[];$newSet=array_fill_keys($new,true);$wordSet=array_fill_keys($words,true);
    foreach($groups as $groupKey=>$surface){
        $key=vocabularyAbbreviationKey($surface);
        $word=vocabularySurfaceWord($surface);
        foreach(vocabularyNameLookupWords($surface) as $nameKey)if(isset($names[$nameKey])){$matched[$groupKey]=true;continue 2;}
        foreach(vocabularyAbbreviationParts($surface) as $part){
            if(isset($sensitive[vocabularyAbbreviationKey($part)])||isset($insensitive[vocabularyAbbreviationKey($part,false)])){$matched[$groupKey]=true;continue 2;}
        }
        if(vocabularyPossibleAbbreviation($surface)){
            // Uppercase undotted forms may be acronyms even if lowercase is known.
            $ambiguousCase=!str_contains($key,'.')&&mb_strtoupper($key,'UTF-8')===$key;
            if(!$ambiguousCase&&$word!==null&&isset($wordSet[$word])&&!isset($newSet[$word]))$matched[$groupKey]=true;
            else $possible[]=$surface;
        }elseif($word!==null)$ordinary[$word]=true;else $possible[]=$surface;
    }
    $remaining=array_values(array_filter($new,fn($word)=>isset($ordinary[$word])));
    $checked=count($ordinary)+count($possible)+count($matched);
    return ['words'=>$remaining,'possible_abbreviations'=>$possible,'checked'=>$checked,'excluded'=>$checked-count($remaining)-count($possible)];
}
