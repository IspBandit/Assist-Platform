<?php

declare(strict_types=1);

namespace App\Services;

/** Small, factual JSON-LD builders shared by public brand pages. */
final class SeoSchema
{
    /** @param array<int,array{name:string,url:string}> $items */
    public static function breadcrumbs(array $items): string
    {
        $elements=[];
        foreach($items as $position=>$item){
            $name=trim($item['name']); $url=trim($item['url']);
            if($name===''||$url==='')continue;
            $elements[]=['@type'=>'ListItem','position'=>count($elements)+1,'name'=>$name,'item'=>$url];
        }
        if(count($elements)<2)return '';
        return json_encode(['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$elements],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'';
    }
}
