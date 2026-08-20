<?php

declare(strict_types=1);

namespace App\Services;

final class OrganisationCampaignCopy
{
    /** @return array<string,array{label:string,subject:string,body:string}> */
    public static function styles(): array
    {
        return [
            'club_member_resource' => [
                'label' => 'Club or federation — free member resource',
                'subject' => 'A free Australian travel-help resource for your members to review',
                'body' => '<p>Hello,</p><p>I am writing to the published club contact because VanAssist may be useful to people travelling with caravans, motorhomes and RVs.</p><p>VanAssist is free for travellers and helps people find nearby caravan and vehicle services, fuel, EV charging and caravan-suitable places to stay across Australia. It also shows whether a listing is claimed or verified and explains that public-source information should be checked before relying on it.</p><p>Would your committee be willing to review <a href="https://vanassist.com.au/">vanassist.com.au</a>? If you consider it genuinely useful, you are welcome to share it with members in whatever way suits the club. We are not asking for your member list or implying an endorsement.</p><p>If it is not relevant, simply reply or use the unsubscribe link and we will not contact this address again.</p><p>Regards,<br>Glen Condren<br>VanAssist</p>',
            ],
            'industry_data_collaboration' => [
                'label' => 'Peak body or network — data and member collaboration',
                'subject' => 'VanAssist service-directory and traveller-resource collaboration',
                'body' => '<p>Hello,</p><p>I am contacting your published industry role because VanAssist is building a free, national location-first directory for caravan and RV travellers.</p><p>The platform helps travellers find relevant repairers, mobile technicians, parts, fuel, charging and caravan-suitable stays. We are also working to improve listing accuracy, source transparency and provider claim workflows.</p><p>I would value a short discussion about whether your organisation can help us direct listing-accuracy questions to the right channel, and whether the finished traveller resource may be appropriate for your members or audience. We will not request or import a member mailing list.</p><p>You can review the live service at <a href="https://vanassist.com.au/">vanassist.com.au</a>. If this is not within your role, a pointer to the correct partnership or member-communications contact would be appreciated; otherwise we will not follow up.</p><p>Regards,<br>Glen Condren<br>VanAssist</p>',
            ],
            'fleet_dealer_owner_support' => [
                'label' => 'Manufacturer, dealer or rental fleet — owner support',
                'subject' => 'A free location-based support resource for caravan and RV travellers',
                'body' => '<p>Hello,</p><p>I am writing to your published partnership or network contact because VanAssist may complement the support your owners or renters already receive.</p><p>VanAssist is a free Australian web service for finding relevant caravan/RV repairers, mobile help, fuel, charging and suitable stays near the traveller or along a route. It is designed for phones and does not require users to install an app.</p><p>I would welcome the chance to discuss a simple resource or data collaboration—particularly keeping dealer, service and support locations accurate. This is not a request for customer data and we will not represent your organisation as a partner without agreement.</p><p>The live service is at <a href="https://vanassist.com.au/">vanassist.com.au</a>. If this is outside your role, please point me to the appropriate team or opt out and I will close the contact.</p><p>Regards,<br>Glen Condren<br>VanAssist</p>',
            ],
            'editorial_story' => [
                'label' => 'Publication or media — earned story pitch',
                'subject' => 'Story lead: a free location-first tool for Australian caravan travellers',
                'body' => '<p>Hello,</p><p>I am sending this to your published editorial contact as a possible reader-service story, not asking for access to your subscriber list.</p><p>VanAssist is a new free Australian platform that helps caravan, motorhome and RV travellers find nearby repairs, mobile help, fuel, charging and caravan-suitable places to stay. It is location-first, works in a phone browser and makes claimed, verified and public-source listing status visible.</p><p>The useful story is also the difficult bit: building a genuinely accurate national service directory and giving travellers a simple way to report gaps or corrections. You can review it at <a href="https://vanassist.com.au/">vanassist.com.au</a>.</p><p>If it interests your editor, I can provide background, screenshots and answer questions. If not, no heroic unsubscribe quest is required—the link below works, and we will leave the inbox alone.</p><p>Regards,<br>Glen Condren<br>VanAssist</p>',
            ],
            'tourism_visitor_resource' => [
                'label' => 'Tourism or park organisation — visitor resource',
                'subject' => 'A free road-travel support resource for regional visitors',
                'body' => '<p>Hello,</p><p>I am contacting your published tourism or industry role because VanAssist may help caravan and RV visitors travel more confidently through regional Australia.</p><p>The free mobile-friendly website helps travellers find nearby repair and mobile services, fuel, charging and caravan-suitable stays. It can also expose service or accommodation gaps that matter on well-used touring routes.</p><p>Would your team be willing to review <a href="https://vanassist.com.au/">vanassist.com.au</a> and advise whether it belongs in your visitor or industry resources? We are not asking for visitor data and will not imply endorsement or partnership without agreement.</p><p>If this is outside your role, a pointer to the correct industry contact would help. Otherwise reply or unsubscribe and we will not follow up.</p><p>Regards,<br>Glen Condren<br>VanAssist</p>',
            ],
        ];
    }
}
