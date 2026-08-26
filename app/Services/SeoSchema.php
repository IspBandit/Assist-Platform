<?php

declare(strict_types=1);

namespace App\Services;

use App\Platform\Brand\Brand;

/** Small, factual JSON-LD builders shared by public brand pages. */
final class SeoSchema
{
    /** @return array<int,string> */
    public static function brandWebsite(Brand $brand): array
    {
        $metadata = $brand->metadata();
        $assets = $brand->assets();
        $organisation = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $brand->name(),
            'url' => $brand->url() . '/',
            'description' => (string) ($metadata['description'] ?? $metadata['tagline'] ?? ''),
        ];
        $logo = (string) ($assets['logo'] ?? '');
        if ($logo !== '') {
            $organisation['logo'] = str_starts_with($logo, 'http')
                ? $logo
                : $brand->url() . '/' . ltrim($logo, '/');
        }

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $brand->name(),
            'url' => $brand->url() . '/',
        ];

        return array_values(array_filter([
            json_encode($organisation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
        ]));
    }

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
