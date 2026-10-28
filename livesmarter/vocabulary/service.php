<?php
require_once __DIR__.'/abbreviations.php';
require_once __DIR__.'/phrases.php';
function vocabularyNormalize(string $word): string {
    $word=trim(str_replace(['’','‘','‐','‑'],["'","'",'-','-'],$word));
    if (class_exists('Normalizer')) $word=Normalizer::normalize($word,Normalizer::FORM_C);
    $word=mb_strtolower(preg_replace('/\s+/u',' ',$word),'UTF-8');
    if(str_ends_with($word,"'s")&&vocabularyIsAbbreviation(substr($word,0,-2)))return rtrim(substr($word,0,-2),'.').".'s";
    return vocabularyIsAbbreviation($word)?rtrim($word,'.').'.':$word;
}
function vocabularyIsAbbreviation(string $word): bool {return (bool)preg_match('/^(?:\\p{L}\\.)+\\p{L}\\.?$/u',$word);}
function vocabularyWord($value): string {
    if (!is_string($value)) throw new InvalidArgumentException('Enter one word.');
    $word=vocabularyNormalize($value);
    if (mb_strlen($word,'UTF-8')>100 || (!preg_match('/^[0-9]+-\\p{L}[\\p{L}\\p{M}]*$/u',$word) && !vocabularyIsAbbreviation($word) && !(str_ends_with($word,"'s")&&vocabularyIsAbbreviation(substr($word,0,-2))) && !preg_match("/^\\p{L}[\\p{L}\\p{M}]*(?:[' -]\\p{L}[\\p{L}\\p{M}]*)*$/u",$word))) throw new InvalidArgumentException('Enter a word, phrase, or dotted abbreviation (such as U.S.) of up to 100 characters. Apostrophes, hyphens, and numeric compounds such as 10-year are allowed.');
    return $word;
}
function vocabularyNameNormalize(string $word): string {
    $word=trim(str_replace(['’','‘','‐','‑'],["'","'",'-','-'],$word));
    if(class_exists('Normalizer'))$word=Normalizer::normalize($word,Normalizer::FORM_C);
    $word=preg_replace('/\s+/u',' ',$word);
    if(preg_match("/['’][sS]$/u",$word)&&vocabularyIsAbbreviation(substr($word,0,-2)))return rtrim(substr($word,0,-2),'.').'.'.substr($word,-2);
    return vocabularyIsAbbreviation($word)?rtrim($word,'.').'.':$word;
}
function vocabularyNameWord($value): string {
    vocabularyWord($value);
    return vocabularyNameNormalize($value);
}
function vocabularyNames($encoded): array {
    $values=is_string($encoded)?json_decode($encoded,true):null;
    if(!is_array($values)||!array_is_list($values)||count($values)>5000)throw new InvalidArgumentException('Check up to 5000 unique words at a time.');
    return array_values(array_unique(array_map('vocabularyNameWord',$values)));
}
function vocabularyProperCaseName(string $value): string {
    $word=vocabularyNormalize($value);
    return implode('',array_map(function($part){
        if(vocabularyIsAbbreviation($part))return mb_strtoupper($part,'UTF-8');
        if(str_ends_with($part,"'s")&&vocabularyIsAbbreviation(substr($part,0,-2)))return mb_strtoupper(substr($part,0,-2),'UTF-8')."'s";
        $proper=preg_replace_callback("/(^|')(\p{L})/u",fn($match)=>$match[1].mb_strtoupper($match[2],'UTF-8'),$part);
        return preg_replace("/'S$/u","'s",$proper);
    },preg_split('/(\s+|-)/u',$word,-1,PREG_SPLIT_DELIM_CAPTURE)));
}
function vocabularyNameLookupWords(string $word): array {
    $word=vocabularyNameNormalize($word);
    if(!vocabularyIsAbbreviation($word)&&!preg_match("/\.'[sS]$/u",$word))$word=rtrim($word,'.');
    $keys=[];
    foreach([$word,...explode('-',$word)] as $part){
        $part=vocabularyNameNormalize($part);
        $keys[$part]=true;
        if(preg_match("/'[sS]$/u",$part))$keys[vocabularyNameNormalize(substr($part,0,-2))]=true;
    }
    return array_keys($keys);
}
function vocabularyId($value): int {
    if ((!is_int($value)&&!is_string($value)) || !preg_match('/^[1-9]\d*$/',(string)$value)) throw new InvalidArgumentException('Invalid word ID.');
    return (int)$value;
}
function vocabularyOwned($db, string $table, string $userId, int $id): array {
    $row=$db->one("SELECT * FROM $table WHERE id=? AND user_id=?",[$id,$userId]);
    if (!$row) throw new OutOfBoundsException('Word no longer available.');
    return $row;
}
function vocabularyWords($encoded): array {
    $values=is_string($encoded)?json_decode($encoded,true):null;
    if (!is_array($values)||!array_is_list($values)||count($values)>5000) throw new InvalidArgumentException('Check up to 5000 unique words at a time.');
    return array_values(array_unique(array_map('vocabularyWord',$values)));
}
// Keep every compound prefix; accept only a complete, changed final noun form.
function vocabularyWholeCompoundRoot(string $word,string $root): bool {
    $split=strrpos($word,'-');
    if($split===false||str_contains($word,' ')||str_contains($word,"'")||str_contains($root,' ')||str_contains($root,"'"))return false;
    $prefix=substr($word,0,$split+1);
    return str_starts_with($root,$prefix)&&preg_match('/^\\p{L}[\\p{L}\\p{M}]*$/u',substr($root,strlen($prefix)))&&$root!==$word;
}
function vocabularyLemmaList($encoded, string $word, bool $compoundLookup=false): array {
    $values=is_string($encoded)?json_decode($encoded,true):null;
    if (!is_array($values)||!array_is_list($values)||count($values)>4) throw new InvalidArgumentException('Use up to four lemmas per word.');
    $roots=array_values(array_diff(array_unique(array_map('vocabularyWord',$values)),[$word]));
    return str_contains($word,'-')?($compoundLookup?array_values(array_filter($roots,fn($root)=>vocabularyWholeCompoundRoot($word,$root))):[]):$roots;
}
function vocabularyIsNumberCompound(string $word): bool {
    $split=strrpos($word,'-');if($split===false)return false;
    if(!preg_match('/^\\p{L}[\\p{L}\\p{M}]*$/u',substr($word,$split+1)))return false;
    $small='zero|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen';
    $ones='one|two|three|four|five|six|seven|eight|nine';
    $underHundred="(?:$small|(?:twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety)(?:-(?:$ones))?)";
    return (bool)preg_match("/^(?:[0-9]+|$underHundred|(?:$ones)-hundred(?:-(?:and-)?$underHundred)?|hundred|thousand|million|billion)$/",substr($word,0,$split));
}
function vocabularyCandidateLemmas($encoded, array $words): array {
    $lemmas=is_string($encoded)?json_decode($encoded,true):null;
    if (!is_array($lemmas)||count($lemmas)>5000) throw new InvalidArgumentException('Invalid lemma lookup.');
    $source=array_fill_keys($words,true);
    foreach ($lemmas as $word=>$roots) {
        if (!isset($source[$word])||!is_array($roots)||!array_is_list($roots)||count($roots)>4) throw new InvalidArgumentException('Invalid lemma lookup.');
        $lemmas[$word]=array_values(array_diff(array_unique(array_map('vocabularyWord',$roots)),[$word]));
        if(str_contains($word,'-')&&!vocabularyIsNumberCompound($word)) {
            $possessiveBase=str_ends_with($word,"'s")?vocabularyNormalize(substr($word,0,-2)):null;
            $lemmas[$word]=array_values(array_filter($lemmas[$word],fn($root)=>$root===$possessiveBase||vocabularyWholeCompoundRoot($word,$root)));
        }
    }
    return $lemmas;
}
function vocabularyMeanings($encoded): array {
    $rows=is_string($encoded)?json_decode($encoded,true):null;
    if (!is_array($rows)||!array_is_list($rows)||count($rows)>50) throw new InvalidArgumentException('Add up to 50 meanings per word.');
    $parts=['noun','pronoun','verb','adjective','adverb','preposition','conjunction','interjection','determiner','suffix','other'];
    foreach ($rows as &$row) {
        if (!is_array($row)||!in_array($row['part_of_speech']??null,$parts,true)||!is_string($row['meaning']??null)||trim($row['meaning'])===''||mb_strlen($row['meaning'])>2000) throw new InvalidArgumentException('Each meaning needs a part of speech and up to 2000 characters of text.');
        if (array_key_exists('example',$row) && (!is_string($row['example']) || mb_strlen($row['example'])>2000)) throw new InvalidArgumentException('Each example must be up to 2000 characters.');
        if (array_key_exists('id',$row)) $row['id']=vocabularyId($row['id']);
        if (array_key_exists('profileId',$row) && $row['profileId']!==null && (!is_string($row['profileId'])||($row['profileId']!==''&&!preg_match('/^[a-f0-9]{32}$/',$row['profileId'])))) throw new InvalidArgumentException('Invalid meaning picture.');
        $row=['part_of_speech'=>$row['part_of_speech'],'meaning'=>trim($row['meaning']),...(array_key_exists('example',$row)?['example'=>trim($row['example'])]:[]),...(isset($row['id'])?['id'=>$row['id']]:[]),...(array_key_exists('profileId',$row)?['profileId'=>$row['profileId']]:[])];
    }
    unset($row);
    return $rows;
}
function vocabularyLockName(string $userId): string {return 'vocab:'.substr(hash('sha256',$userId),0,58);}
function vocabularyRequireExclusive($db, string $userId, string $destination, array $words): void {
    $labels=['vocabulary'=>'word list','vocabulary_blacklist'=>'blacklist','vocabulary_names'=>'name list'];
    foreach ($labels as $other=>$otherLabel) {
        // Names are independent; only Word List and Blacklist are mutually exclusive.
        if ($other===$destination || $other==='vocabulary_names' || $destination==='vocabulary_names') continue;
        foreach (array_chunk($words,500) as $chunk) {
            $in=implode(',',array_fill(0,count($chunk),'?'));
            $conflict=$db->one("SELECT word FROM $other WHERE user_id=? AND word IN ($in) LIMIT 1",[$userId,...array_map('vocabularyNormalize',$chunk)]);
            if ($conflict) throw new InvalidArgumentException('“'.$conflict['word'].'” is already in your '.$otherLabel.'. Remove it there before adding it to your '.$labels[$destination].'.');
        }
    }
}
function vocabularyApi($db, string $userId, array $input) {
    if (!in_array($input['action']??'',['addWords','addBlacklist','saveWord','saveBlacklist','deleteWord','deleteBlacklist','addNames','saveName','deleteName','moveExclusion','moveWordToBlacklist','saveAbbreviation','deleteAbbreviation'],true)) return vocabularyApiUnlocked($db,$userId,$input);
    // Serialize this user's writes across all three lists, including when the word is
    // absent from all lists. Acquire before opening the transaction to read fresh data.
    $lock=vocabularyLockName($userId);
    if ((int)($db->one('SELECT GET_LOCK(?,10) AS acquired',[$lock])['acquired']??0)!==1) throw new RuntimeException('Another vocabulary change is in progress. Please try again.');
    try {return vocabularyApiUnlocked($db,$userId,$input);}
    finally {$db->one('SELECT RELEASE_LOCK(?) AS released',[$lock]);}
}
function vocabularyApiUnlocked($db, string $userId, array $input) {
    $action=$input['action']??'';
    if($action==='matchPhrases')return vocabularyMatchPhrases($db,$userId,$input['paragraph']??null);
    if(in_array($action,['abbreviations','saveAbbreviation','deleteAbbreviation'],true))return vocabularyAbbreviationApi($db,$userId,$input);
    if ($action==='moveWordToBlacklist') {
        $id=vocabularyId($input['id']??null);
        $db->begin();
        try {
            $entry=vocabularyOwned($db,'vocabulary',$userId,$id);
            $db->exec('DELETE FROM vocabulary_meanings WHERE vocab_id=?',[$id]);
            $db->exec('DELETE FROM vocabulary_lemmas WHERE vocab_id=?',[$id]);
            $db->exec('DELETE FROM vocabulary WHERE id=? AND user_id=?',[$id,$userId]);
            $db->exec('INSERT INTO vocabulary_blacklist (user_id,word) VALUES (?,?) ON DUPLICATE KEY UPDATE id=id',[$userId,$entry['word']]);
            $db->commit();
        } catch(Throwable $error) {$db->rollback();throw $error;}
        return ['success'=>true,'word'=>$entry['word'],'destination'=>'blacklist'];
    }
    if ($action==='moveExclusion') {
        $source=$input['source']??null;
        if (!is_string($source)||!in_array($source,['blacklist','namelist'],true)) throw new InvalidArgumentException('Choose blacklist or name list as the source.');
        $id=vocabularyId($input['id']??null);
        $from=$source==='blacklist'?'vocabulary_blacklist':'vocabulary_names';
        $to=$source==='blacklist'?'vocabulary_names':'vocabulary_blacklist';
        $db->begin();
        try {
            $entry=vocabularyOwned($db,$from,$userId,$id);
            $db->exec("DELETE FROM $from WHERE id=? AND user_id=?",[$id,$userId]);
            $entry['word']=$to==='vocabulary_blacklist'?vocabularyNormalize($entry['word']):vocabularyNameNormalize($entry['word']);
            vocabularyRequireExclusive($db,$userId,$to,[$entry['word']]);
            $db->exec("INSERT INTO $to (user_id,word) VALUES (?,?) ON DUPLICATE KEY UPDATE id=id",[$userId,$entry['word']]);
            $db->commit();
        } catch(Throwable $error) {$db->rollback();throw $error;}
        return ['success'=>true,'word'=>$entry['word'],'destination'=>$source==='blacklist'?'namelist':'blacklist'];
    }
    if (in_array($action,['words','blacklist','namelist'],true)) {
        $table=['words'=>'vocabulary','blacklist'=>'vocabulary_blacklist','namelist'=>'vocabulary_names'][$action];
        $query=$input['q']??'';
        if (!is_string($query)||mb_strlen($query)>100) throw new InvalidArgumentException('Search must be up to 100 characters.');
        $query=vocabularyNormalize($query);
        $filter=$action==='namelist'?'user_id=? AND LOCATE(?,LOWER(word))>0':'user_id=? AND LOCATE(?,word)>0';$params=[$userId,$query];
        if ($action==='words' && $query!=='') {
            $roots=array_key_exists('q_lemmas',$input)?vocabularyLemmaList($input['q_lemmas'],$query,true):[];
            $keys=array_values(array_unique([$query,...$roots]));
            $in=implode(',',array_fill(0,count($keys),'?'));
            $filter="user_id=? AND (LOCATE(?,word)>0 OR word IN ($in) OR EXISTS (SELECT 1 FROM vocabulary_lemmas l WHERE l.vocab_id=vocabulary.id AND LOCATE('-',vocabulary.word)=0 AND l.lemma IN ($in)))";
            $params=[$userId,$query,...$keys,...$keys];
        }
        if ($action==='blacklist' && $query!=='') {
            $roots=array_key_exists('q_lemmas',$input)?vocabularyLemmaList($input['q_lemmas'],$query,true):[];
            $keys=array_values(array_unique([$query,...$roots]));
            $in=implode(',',array_fill(0,count($keys),'?'));
            $filter="user_id=? AND (LOCATE(?,word)>0 OR word IN ($in))";
            $params=[$userId,$query,...$keys];
        }
        $total=(int)$db->one("SELECT COUNT(*) total FROM $table WHERE $filter",$params)['total'];
        $sort=$input['sort']??'word_asc';
        $orders=['word_asc'=>'word ASC,id ASC','date_asc'=>'created_at ASC,id ASC','date_desc'=>'created_at DESC,id DESC'];
        if (!is_string($sort)||!isset($orders[$sort])) throw new InvalidArgumentException('Invalid sort order.');
        $order=$action==='namelist'&&$sort==='word_asc'?'LOWER(word),word,id':$orders[$sort];
        $page=$input['page']??1;$limit=$input['limit']??($action==='words'?10:20);
        if (!preg_match('/^[1-9]\d*$/',(string)$page)||!in_array((string)$limit,['1','5','10','20','50','100'],true)) throw new InvalidArgumentException('Invalid page.');
        $page=min((int)$page,max(1,(int)ceil($total/(int)$limit)));$limit=(int)$limit;$offset=($page-1)*$limit;
        $items=$db->all("SELECT * FROM $table WHERE $filter ORDER BY $order LIMIT $limit OFFSET $offset",$params);
        if ($action==='words' && $items) {
            $ids=array_column($items,'id');$placeholders=implode(',',array_fill(0,count($ids),'?'));
            $meanings=$db->all("SELECT id,vocab_id,part_of_speech,meaning,COALESCE(example,'') AS example,profileId FROM vocabulary_meanings WHERE vocab_id IN ($placeholders) ORDER BY id",$ids);
            foreach ($meanings as &$meaning) $meaning['profileUrl']=empty($meaning['profileId'])?'':'https://constructsmarterpublic.s3.us-west-1.amazonaws.com/'.rawurlencode($meaning['profileId']);
            unset($meaning);
            $roots=$db->all("SELECT vocab_id,lemma FROM vocabulary_lemmas WHERE vocab_id IN ($placeholders) ORDER BY id",$ids);
            foreach ($items as &$item) {
                $item['meanings']=array_values(array_filter($meanings,fn($meaning)=>(string)$meaning['vocab_id']===(string)$item['id']));
                $item['lemmas']=array_values(array_column(array_filter($roots,fn($root)=>(string)$root['vocab_id']===(string)$item['id']),'lemma'));
            }
            unset($item);
        }
        return ['items'=>$items,'page'=>$page,'limit'=>$limit,'total'=>$total];
    }
    if ($action==='find') {
        $words=vocabularyWords($input['words']??null);$known=[];$lemmas=[];
        if (array_key_exists('lemmas',$input)) $lemmas=vocabularyCandidateLemmas($input['lemmas'],$words);
        $keys=$words;
        foreach ($lemmas as $roots) $keys=array_merge($keys,$roots);
        $keys=array_values(array_unique($keys));
        foreach (array_chunk($keys,500) as $chunk) {
            $in=implode(',',array_fill(0,count($chunk),'?'));
            $matches=$db->all("SELECT word FROM vocabulary WHERE user_id=? AND word IN ($in) UNION SELECT word FROM vocabulary_blacklist WHERE user_id=? AND word IN ($in) UNION SELECT l.lemma AS word FROM vocabulary_lemmas l JOIN vocabulary v ON v.id=l.vocab_id WHERE v.user_id=? AND LOCATE('-',v.word)=0 AND l.lemma IN ($in)",[$userId,...$chunk,$userId,...$chunk,$userId,...$chunk]);
            foreach ($matches as $match) $known[$match['word']]=true;
        }
        $names=[];
        // Names match exact tokens or hyphen components and their possessive bases, never lemmas.
        $nameKeys=[];
        $rawNames=array_key_exists('surfaces',$input)?json_decode(is_string($input['surfaces'])?$input['surfaces']:'',true):json_decode($input['words'],true);
        if(!is_array($rawNames)||!array_is_list($rawNames)||count($rawNames)>10000)throw new InvalidArgumentException('Invalid original word forms.');
        foreach($rawNames as $surface){
            if(!is_string($surface))throw new InvalidArgumentException('Invalid original word form.');
            if(array_key_exists('surfaces',$input))$surface=vocabularyAbbreviationSurface($surface);
            foreach(vocabularyNameLookupWords($surface) as $key)$nameKeys[$key]=true;
        }
        $nameForms=[];foreach($rawNames as $surface)$nameForms[vocabularyNormalize($surface)][]=$surface;
        foreach (array_chunk(array_keys($nameKeys),500) as $chunk) {
            $in=implode(',',array_fill(0,count($chunk),'?'));
            foreach ($db->all("SELECT word FROM vocabulary_names WHERE user_id=? AND word IN ($in)",[$userId,...$chunk]) as $match) $names[$match['word']]=true;
        }
        $new=array_values(array_filter($words,function($word) use ($known,$lemmas,$names,$nameForms,$input) {
            if(!array_key_exists('surfaces',$input)){
                $allNamed=true;
                foreach($nameForms[$word]??[$word] as $surface){$matched=false;foreach(vocabularyNameLookupWords($surface) as $key)if(isset($names[$key])){$matched=true;break;}if(!$matched){$allNamed=false;break;}}
                if($allNamed)return false;
            }
            foreach ([$word,...($lemmas[$word]??[])] as $key) if (isset($known[$key])) return false;
            return true;
        }));
        if(array_key_exists('surfaces',$input))return vocabularyFindAbbreviations($db,$userId,$input['surfaces'],$words,$new,$names);
        return ['words'=>$new,'checked'=>count($words),'excluded'=>count($words)-count($new)];
    }
    if (in_array($action,['addWords','addBlacklist','addNames'],true)) {
        $words=$action==='addNames'?vocabularyNames($input['words']??null):vocabularyWords($input['words']??null);
        if (!$words) throw new InvalidArgumentException('Select at least one word.');
        $table=['addWords'=>'vocabulary','addBlacklist'=>'vocabulary_blacklist','addNames'=>'vocabulary_names'][$action];
        $roots=$action==='addWords'&&array_key_exists('lemmas',$input)?vocabularyCandidateLemmas($input['lemmas'],$words):[];
        $db->begin();
        try {
            vocabularyRequireExclusive($db,$userId,$table,$words);
            foreach (array_chunk($words,200) as $chunk) {
                $placeholders=implode(',',array_fill(0,count($chunk),'(?,?)'));$params=[];
                foreach ($chunk as $word) {$params[]=$userId;$params[]=$word;}
                $db->exec("INSERT INTO $table (user_id,word) VALUES $placeholders ON DUPLICATE KEY UPDATE id=id",$params);
            }
            if ($roots) {
                foreach (array_chunk($words,200) as $chunk) {
                    $in=implode(',',array_fill(0,count($chunk),'?'));
                    $entries=$db->all("SELECT id,word FROM vocabulary WHERE user_id=? AND word IN ($in)",[$userId,...$chunk]);
                    foreach ($entries as $entry) {
                        $values=str_contains($entry['word'],'-')?[]:($roots[$entry['word']]??[]);if(!$values)continue;
                        $existing=array_column($db->all('SELECT lemma FROM vocabulary_lemmas WHERE vocab_id=?',[$entry['id']]),'lemma');
                        if(count(array_unique([...$existing,...$values]))>4) throw new InvalidArgumentException('Use up to four lemmas per word.');
                        foreach ($values as $root) $db->exec('INSERT INTO vocabulary_lemmas (vocab_id,lemma) VALUES (?,?) ON DUPLICATE KEY UPDATE id=id',[$entry['id'],$root]);
                    }
                }
            }
            $db->commit();
        } catch(Throwable $error) {$db->rollback();throw $error;}
        return ['success'=>true];
    }
    if (in_array($action,['deleteWord','deleteBlacklist','deleteName'],true)) {
        $id=vocabularyId($input['id']??null);$table=['deleteWord'=>'vocabulary','deleteBlacklist'=>'vocabulary_blacklist','deleteName'=>'vocabulary_names'][$action];
        $db->begin();
        try {
            vocabularyOwned($db,$table,$userId,$id);
            if ($action==='deleteWord') {
                $db->exec('DELETE FROM vocabulary_meanings WHERE vocab_id=?',[$id]);
                $db->exec('DELETE FROM vocabulary_lemmas WHERE vocab_id=?',[$id]);
            }
            $db->exec("DELETE FROM $table WHERE id=? AND user_id=?",[$id,$userId]);
            $db->commit();
        } catch(Throwable $error) {$db->rollback();throw $error;}
        return ['success'=>true];
    }
    if (in_array($action,['saveWord','saveBlacklist','saveName'],true)) {
        $word=$action==='saveName'?vocabularyNameWord($input['word']??null):vocabularyWord($input['word']??null);
        $id=empty($input['id'])?0:vocabularyId($input['id']);
        $table=['saveWord'=>'vocabulary','saveBlacklist'=>'vocabulary_blacklist','saveName'=>'vocabulary_names'][$action];
        $meanings=$action==='saveWord'?vocabularyMeanings($input['meanings']??'[]'):[];
        $blacklistId=$action==='saveWord'&&isset($input['blacklist_id'])?vocabularyId($input['blacklist_id']):0;
        if ($blacklistId && $id) throw new InvalidArgumentException('Create a new word when moving from Blacklist.');
        $roots=$action==='saveWord'&&array_key_exists('lemmas',$input)?vocabularyLemmaList($input['lemmas'],$word):null;
        $db->begin();
        try {
            if ($blacklistId) {
                vocabularyOwned($db,'vocabulary_blacklist',$userId,$blacklistId);
                $db->exec('DELETE FROM vocabulary_blacklist WHERE id=? AND user_id=?',[$blacklistId,$userId]);
            }
            if ($id) $owned=vocabularyOwned($db,$table,$userId,$id);
            vocabularyRequireExclusive($db,$userId,$table,[$word]);
            $duplicate=$db->one("SELECT id FROM $table WHERE user_id=? AND word=? AND id<>?",[$userId,$word,$id]);
            if ($duplicate) throw new InvalidArgumentException('This word already exists. Edit its existing entry.');
            if ($id) $db->exec("UPDATE $table SET word=? WHERE id=? AND user_id=?",[$word,$id,$userId]);
            else {$db->exec("INSERT INTO $table (user_id,word) VALUES (?,?)",[$userId,$word]);$id=(int)$db->lastInsertId();}
            if ($action==='saveWord') {
                if ($roots!==null) {
                    $db->exec('DELETE FROM vocabulary_lemmas WHERE vocab_id=?',[$id]);
                    foreach ($roots as $root) $db->exec('INSERT INTO vocabulary_lemmas (vocab_id,lemma) VALUES (?,?)',[$id,$root]);
                } else {
                    // A legacy client's rename must not retain roots from a different word.
                    if (isset($owned) && $owned['word']!==$word) $db->exec('DELETE FROM vocabulary_lemmas WHERE vocab_id=?',[$id]);
                }
                // Preserve examples from older clients that omit the new field.
                $existing=$db->all('SELECT id,part_of_speech,meaning,example,profileId FROM vocabulary_meanings WHERE vocab_id=?',[$id]);
                foreach ($meanings as &$meaning) {
                    $matches=array_values(array_filter($existing,fn($row)=>isset($meaning['id'])?(int)$row['id']===$meaning['id']:($row['part_of_speech']===$meaning['part_of_speech'] && $row['meaning']===$meaning['meaning'])));
                    if (isset($meaning['id'])&&!$matches) throw new OutOfBoundsException('Meaning no longer available for this word.');
                    $previous=$matches[0]??null;
                    $profile=$previous['profileId']??null;
                    if (array_key_exists('profileId',$meaning)) {
                        $requested=$meaning['profileId']??'';
                        if ($requested==='') $profile=null;
                        elseif ($requested!==$profile) {
                            require_once __DIR__.'/meaningPhoto.php';
                            $profile=validateVocabularyPhoto($db,$userId,$requested);
                        }
                    }
                    $meaning['profileId']=$profile;
                    if (!array_key_exists('example',$meaning)) {
                        $meaning['example']=$previous['example']??'';
                    }
                }
                unset($meaning);
                $db->exec('DELETE FROM vocabulary_meanings WHERE vocab_id=?',[$id]);
                foreach ($meanings as $meaning) $db->exec('INSERT INTO vocabulary_meanings (vocab_id,part_of_speech,meaning,example,profileId) VALUES (?,?,?,?,?)',[$id,$meaning['part_of_speech'],$meaning['meaning'],$meaning['example'],$meaning['profileId']]);
            }
            $db->commit();return ['id'=>$id,'word'=>$word];
        } catch(Throwable $error) {$db->rollback();throw $error;}
    }
    throw new InvalidArgumentException('Unknown vocabulary action.');
}
