<?php

declare(strict_types=1);

namespace App\Services;

/** Curated launch-email starters. Admins must verify every claim before use. */
final class ProviderCampaignCopy
{
    private const IMAGE_BASE = 'https://vanassist.com.au/assets/img/email-campaigns/provider-';

    /** @return array<string,array{label:string,subject:string,body:string}> */
    public static function styles(): array
    {
        return [
            'workshop' => self::style(
                'Mechanical, workshop & mobile repair',
                'Help local drivers find the right workshop',
                'workshop',
                'Drivers are usually searching for a workshop when something is making a noise it definitely was not making yesterday.'
            ),
            'electrical' => self::style(
                'Auto electrical, batteries & diagnostics',
                'Make your electrical services easier to find',
                'electrical',
                'Electrical faults have a talent for appearing at the least convenient time.'
            ),
            'tyres' => self::style(
                'Tyres, wheels, brakes & suspension',
                'Put your real vehicle services in front of local drivers',
                'tyres',
                'Tyres and brakes are not the glamorous part of a trip, but they are rather important to finishing one.'
            ),
            'rv' => self::style(
                'Caravan, RV & traveller services',
                'Help travellers find your caravan and RV services',
                'rv',
                'A caravan problem a long way from home is nobody’s preferred sightseeing activity.'
            ),
            'trailer' => self::style(
                'Trailer, towing, fabrication & weighing',
                'Help owners find the right trailer specialist',
                'trailer',
                '“She’ll be right” is not a towing calculation, an engineering opinion or a repair plan.'
            ),
            'fuel' => self::style(
                'Fuel stations & EV charging',
                'Check how travellers find your fuel or charging location',
                'fuel',
                'Fuel gauges remain stubbornly unimpressed by optimism.'
            ),
            'compliance' => self::style(
                'Inspection, engineering & compliance',
                'Make your inspection and compliance expertise clear',
                'compliance',
                'Vehicle rules are complicated enough without a directory adding creative interpretation.'
            ),
            'stays' => self::style(
                'Parks, stays & traveller facilities',
                'Help travellers understand what your stay offers',
                'stays',
                'After a long day on the road, “probably has a powered site” is not especially useful information.'
            ),
        ];
    }

    /** @return array{label:string,subject:string,body:string} */
    public static function forCategory(string $name, string $slug): array
    {
        $family = self::familyForCategory($slug);
        $style = self::styles()[$family];
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $style['subject'] = 'A quick accuracy check for ' . $name . ' on VanAssist';
        $style['body'] = str_replace(
            [
                'Please review your provider listing so the services, location and contact details shown are accurate.',
                'Confirm the services your business genuinely provides.',
            ],
            [
                'Our public directory currently lists your business for <strong>' . $safeName . '</strong>. We would rather ask you than let a directory make an enthusiastic guess about what you do.',
                'Please confirm that <strong>' . $safeName . '</strong> genuinely belongs on your listing.',
            ],
            $style['body']
        );
        return $style;
    }

    public static function familyForCategory(string $slug): string
    {
        $slug = strtolower($slug);
        $families = [
            'fuel' => ['fuel', 'charging', 'lpg'],
            'stays' => ['park', 'camp', 'accommodation', 'rest-area', 'parking', 'grocery', 'pet-friendly', 'storage', 'washing'],
            'compliance' => ['roadworthy', 'inspection', 'engineering', 'compliance', 'certification'],
            'tyres' => ['tyre', 'wheel', 'brake', 'suspension'],
            'electrical' => ['electrical', 'battery', 'diagnostic'],
            'trailer' => ['trailer', 'towing', 'weigh', 'fabricat', 'weld'],
            'rv' => ['caravan', 'rv-', '-rv', 'refrigeration', 'plumbing', 'solar', 'potable-water', 'dump-point', 'gas'],
        ];
        foreach ($families as $family => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($slug, $needle)) {
                    return $family;
                }
            }
        }
        return 'workshop';
    }

    /** @return array{label:string,subject:string,body:string} */
    private static function style(string $label, string $subject, string $image, string $opener): array
    {
        $body = '<img src="' . self::IMAGE_BASE . $image . '.webp" alt="" width="600" height="200" style="display:block;width:100%;height:auto;border:0;border-radius:12px">'
            . '<p>Hi,</p><p>' . $opener . '</p>'
            . '<p>{{category_intro}}</p>'
            . '<p>VanAssist helps Australian caravan and road travellers find relevant nearby services. Your basic listing is free to review and claim.</p>'
            . '<p><strong>A useful listing needs the facts right:</strong></p>'
            . '<ul><li>{{category_check}}</li><li>Check the business name, location, phone, email and website.</li><li>Correct service areas, traveller access details and opening hours where relevant.</li><li>Remove anything your business does not actually provide.</li></ul>'
            . '<p><a href="https://vanassist.com.au/for-providers">Review your VanAssist provider information</a></p>'
            . '<p>We are not promising a miraculous queue of leads by Tuesday. The aim is simpler and more useful: when a traveller needs the work you genuinely do, your accurate details should be easy to find.</p>'
            . '<p>Regards,<br>The VanAssist team</p>';
        return [
            'label' => $label,
            'subject' => $subject,
            'body' => str_replace(
                ['{{category_intro}}', '{{category_check}}'],
                [
                    'Please review your provider listing so the services, location and contact details shown are accurate.',
                    'Confirm the services your business genuinely provides.',
                ],
                $body
            ),
        ];
    }
}
