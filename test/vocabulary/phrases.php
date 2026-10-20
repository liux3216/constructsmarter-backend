<?php
function vocabularyMatchPhrases($db,string $userId,$paragraph): array {
    if(!is_string($paragraph)||mb_strlen($paragraph)>100000)throw new InvalidArgumentException('Use a paragraph of up to 100,000 characters.');
    $rows=$db->all("SELECT word,0 AS case_sensitive FROM vocabulary WHERE user_id=? AND (LOCATE(' ',word)>0 OR LOCATE('-',word)>0) UNION SELECT word,0 AS case_sensitive FROM vocabulary_blacklist WHERE user_id=? AND (LOCATE(' ',word)>0 OR LOCATE('-',word)>0) UNION SELECT word,1 AS case_sensitive FROM vocabulary_names WHERE user_id=? AND (LOCATE(' ',word)>0 OR LOCATE('-',word)>0) ORDER BY word",[$userId,$userId,$userId]);
    $tries=[[],[]];
    foreach($rows as $row){
        $policy=(int)$row['case_sensitive'];
        $parts=preg_split('/[\s‐‑-]+/u',$policy?vocabularyNameNormalize($row['word']):vocabularyNormalize($row['word']),-1,PREG_SPLIT_NO_EMPTY);
        if(count($parts)<2)continue;
        $node=&$tries[$policy];
        foreach($parts as $part){if(!isset($node['next'][$part]))$node['next'][$part]=[];$node=&$node['next'][$part];}
        if(!isset($node['word']))$node['word']=$row['word'];
        unset($node);
        // Saved word/blacklist phrases also recognize their fully joined spelling.
        // Name List keeps its existing exact-case spacing/hyphen rules.
        if(!$policy){
            $joined=implode('',$parts);
            if(!isset($tries[0]['next'][$joined]))$tries[0]['next'][$joined]=[];
            if(!isset($tries[0]['next'][$joined]['word']))$tries[0]['next'][$joined]['word']=$row['word'];
        }
    }
    if(!$tries[0]&&!$tries[1])return ['paragraph'=>$paragraph,'matches'=>[]];
    if(preg_match_all("/(?:\\p{L}\\.)+\\p{L}\\.?(?![\\p{L}\\p{M}\\p{N}])|[\\p{L}\\p{M}\\p{N}]+(?:['’‘][\\p{L}\\p{M}\\p{N}]+)*/u",$paragraph,$raw,PREG_OFFSET_CAPTURE)===false)throw new InvalidArgumentException('Use valid paragraph text.');
    $tokens=[];foreach($raw[0] as [$text,$offset])$tokens[]=['word'=>vocabularyNormalize($text),'name'=>vocabularyNameNormalize($text),'start'=>$offset,'end'=>$offset+strlen($text)];
    $ranges=[];$matched=[];$count=count($tokens);
    for($i=0;$i<$count;$i++){
        if($i>0){$gap=substr($paragraph,$tokens[$i-1]['end'],$tokens[$i]['start']-$tokens[$i-1]['end']);if(in_array($gap,['-','‐','‑'],true))continue;}
        $best=null;
        foreach($tries as $policy=>$trie){
        $node=$trie;
        for($j=$i;$j<$count;$j++){
            if($j>$i){$gap=substr($paragraph,$tokens[$j-1]['end'],$tokens[$j]['start']-$tokens[$j-1]['end']);if(!preg_match('/^[\s‐‑-]+$/u',$gap)||preg_match('/\R\s*\R/u',$gap))break;}
            $word=$tokens[$j][$policy?'name':'word'];if(!isset($node['next'][$word]))break;$node=$node['next'][$word];
            $continues=false;
            if($j+1<$count){$gap=substr($paragraph,$tokens[$j]['end'],$tokens[$j+1]['start']-$tokens[$j]['end']);$continues=in_array($gap,['-','‐','‑'],true);}
            if(isset($node['word'])&&!$continues&&(!$best||$j>$best['last']))$best=['word'=>$node['word'],'last'=>$j,'start'=>$tokens[$i]['start'],'end'=>$tokens[$j]['end']];
        }
        }
        if($best){$ranges[]=$best;$matched[$best['word']]=($matched[$best['word']]??0)+1;$i=$best['last'];}
    }
    // Mask only matching occurrences; independent occurrences of components survive.
    for($i=count($ranges)-1;$i>=0;$i--){$range=$ranges[$i];$paragraph=substr_replace($paragraph,str_repeat(' ',$range['end']-$range['start']),$range['start'],$range['end']-$range['start']);}
    $matches=[];foreach($matched as $word=>$occurrences)$matches[]=['word'=>$word,'count'=>$occurrences];
    return ['paragraph'=>$paragraph,'matches'=>$matches];
}
