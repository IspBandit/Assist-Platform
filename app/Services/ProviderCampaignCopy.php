<?php

declare(strict_types=1);

namespace App\Services;

/** Curated launch-email starters. Admins must verify every claim before use. */
final class ProviderCampaignCopy
{
    /** @return array<string,array{label:string,subject:string,body:string}> */
    public static function styles(): array
    {
        return [
            'workshop' => [
                'label' => 'Mechanical, workshop & mobile repair',
                'subject' => 'Help local drivers find the right workshop',
                'body' => '<p>Drivers are usually searching for a workshop when something is making a noise it definitely was not making yesterday.</p><p>Please review your free provider listing so the services, location and contact details shown are accurate. Claiming it lets you correct the record and receive relevant local enquiries.</p>',
            ],
            'electrical' => [
                'label' => 'Auto electrical, batteries & diagnostics',
                'subject' => 'Make your electrical services easier to find',
                'body' => '<p>Electrical faults have a talent for appearing at the least convenient time.</p><p>Please review your free provider listing so drivers can see the electrical, battery or diagnostic work you actually perform—not an imaginative guess made by a directory.</p>',
            ],
            'tyres' => [
                'label' => 'Tyres, wheels, brakes & suspension',
                'subject' => 'Put your real vehicle services in front of local drivers',
                'body' => '<p>Tyres and brakes are not the glamorous part of a trip, but they are rather important to finishing one.</p><p>Please check your free provider listing and confirm the services you genuinely offer, your coverage area and the best way for customers to contact you.</p>',
            ],
            'rv' => [
                'label' => 'Caravan, RV & traveller services',
                'subject' => 'Help travellers find your caravan and RV services',
                'body' => '<p>A caravan problem a long way from home is nobody’s preferred sightseeing activity.</p><p>Please review your free provider listing so travellers can find the caravan, RV or mobile services you genuinely provide, with accurate contact and service-area details.</p>',
            ],
            'trailer' => [
                'label' => 'Trailer, towing, fabrication & weighing',
                'subject' => 'Help owners find the right trailer specialist',
                'body' => '<p>“She’ll be right” is not a towing calculation, an engineering opinion or a repair plan.</p><p>Please review your free provider listing so owners can identify the trailer, towing, fabrication, weighing or compliance services you actually provide.</p>',
            ],
            'fuel' => [
                'label' => 'Fuel stations & EV charging',
                'subject' => 'Check how travellers find your fuel or charging location',
                'body' => '<p>Fuel gauges remain stubbornly unimpressed by optimism.</p><p>Please review the location and facilities shown for your fuel or charging site. Add only the fuels, charging options, access details and opening hours you can confirm.</p>',
            ],
            'compliance' => [
                'label' => 'Inspection, engineering & compliance',
                'subject' => 'Make your inspection and compliance expertise clear',
                'body' => '<p>Vehicle rules are complicated enough without a directory adding creative interpretation.</p><p>Please review your free provider listing and confirm the inspections, engineering or compliance services you are qualified and available to provide.</p>',
            ],
            'stays' => [
                'label' => 'Parks, stays & traveller facilities',
                'subject' => 'Help travellers understand what your stay offers',
                'body' => '<p>After a long day on the road, “probably has a powered site” is not especially useful information.</p><p>Please review your listing and confirm location, facilities, access, contact details and booking arrangements so travellers can make an informed choice.</p>',
            ],
        ];
    }
}
