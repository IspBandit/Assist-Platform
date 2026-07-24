<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class SocialMediaAssetService
{
    /** @var array<string,array{platform:string,label:string,width:int,height:int}> */
    private const FORMATS = [
        'instagram-square' => ['platform' => 'instagram', 'label' => 'Instagram post', 'width' => 1080, 'height' => 1080],
        'instagram-story' => ['platform' => 'instagram', 'label' => 'Instagram story', 'width' => 1080, 'height' => 1920],
        'instagram-profile' => ['platform' => 'instagram', 'label' => 'Instagram profile', 'width' => 1080, 'height' => 1080],
        'facebook-feed' => ['platform' => 'facebook', 'label' => 'Facebook post', 'width' => 1200, 'height' => 630],
        'facebook-cover' => ['platform' => 'facebook', 'label' => 'Facebook cover', 'width' => 1640, 'height' => 624],
        'facebook-profile' => ['platform' => 'facebook', 'label' => 'Facebook profile', 'width' => 1080, 'height' => 1080],
    ];

    /** @var array<string,string> */
    private const INTENTIONS = [
        'launch' => 'Brand launch',
        'provider-recruitment' => 'Provider recruitment',
        'service-discovery' => 'Customer service discovery',
        'education-safety' => 'Education and safety',
        'community' => 'Community engagement',
    ];

    /** @var array<string,string> */
    private const TEMPLATES = [
        'editorial' => 'Premium editorial',
        'field-guide' => 'Field guide / educational',
        'provider-spotlight' => 'Provider spotlight',
        'launch-impact' => 'Launch impact',
    ];

    public static function schemaReady(): bool
    {
        return Database::tableExists('social_media_assets');
    }

    /** @return array<string,array{platform:string,label:string,width:int,height:int}> */
    public static function formats(): array { return self::FORMATS; }

    /** @return array<string,string> */
    public static function intentions(): array { return self::INTENTIONS; }

    /** @return array<string,string> */
    public static function templates(): array { return self::TEMPLATES; }

    /** @return list<array<string,mixed>> */
    public static function listForBrand(int $brandId): array
    {
        if (!self::schemaReady()) { return []; }
        return Database::select(
            'SELECT * FROM social_media_assets WHERE brand_id = ? ORDER BY created_at DESC, id DESC',
            [$brandId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id, int $brandId): ?array
    {
        if (!self::schemaReady()) { return null; }
        return Database::selectOne('SELECT * FROM social_media_assets WHERE id = ? AND brand_id = ?', [$id, $brandId]) ?: null;
    }

    /** @return array{headline:string,caption:string} */
    public static function copyFor(string $brand, string $intention): array
    {
        $copy = [
            'vanassist' => [
                'launch' => ['Find caravan help near you', "VanAssist connects Australian travellers with relevant caravan and RV service providers. Explore local help and travel with greater confidence.\n\n#VanAssist #CaravanAustralia #RVTravel"],
                'provider-recruitment' => ['Put your caravan business on the map', "Do you repair, inspect or support caravans and RVs? Join VanAssist and help travellers find your business when they need it.\n\n#CaravanBusiness #MobileRepairs #VanAssist"],
                'service-discovery' => ['The right caravan help, closer', "Search caravan and RV repairers, auto electricians, tyre services, inspectors and more across Australia with VanAssist.\n\n#CaravanRepairs #RoadTripAustralia #VanAssist"],
                'education-safety' => ['Prepare well. Travel confidently.', "A safer trip starts before departure. Check your caravan, tyres, bearings, brakes, electrical systems and servicing needs before you travel.\n\n#CaravanSafety #TravelPrepared #VanAssist"],
                'community' => ['Helping Australia keep moving', "Where are you travelling next? Share your destination and the service tip every caravan owner should know.\n\n#CaravanCommunity #SeeAustralia #VanAssist"],
            ],
            'towsmart' => [
                'launch' => ['Tow smarter. Tow safer.', "TowSmart brings towing calculations, clear explanations and specialist services together for Australian drivers. Know your numbers before you tow.\n\n#TowSmart #TowingSafety #CaravanAustralia"],
                'provider-recruitment' => ['Help Australians tow with confidence', "Weighing specialists, towing trainers, towbar installers, brake experts and auto electricians: bring your expertise to TowSmart.\n\n#TowingIndustry #TowSmart #AustralianBusiness"],
                'service-discovery' => ['Find towing specialists near you', "Discover relevant weighing, training, hitch, brake, suspension, electrical and tyre services through TowSmart.\n\n#TowSafe #TowingSetup #TowSmart"],
                'education-safety' => ['Know your limits before you tow', "ATM, GTM, GVM and GCM all matter. TowSmart helps explain the numbers, but always verify your vehicle and trailer limits with their manufacturers.\n\n#TowingWeights #TowSmart #RoadSafety"],
                'community' => ['Better towing starts with better knowledge', "What is the one towing lesson you wish you had learned earlier? Share it with the TowSmart community.\n\n#TowSmartCommunity #TowSafe #TowingAustralia"],
            ],
            'trailerwise' => [
                'launch' => ['Every trailer. The right service.', "TrailerWise helps Australians find trailer repairers, inspections, tyres, bearings, brakes, auto electrical, fabrication, parts and mobile services.\n\n#TrailerWise #TrailerRepairs #AustralianTrailers"],
                'provider-recruitment' => ['Put your trailer expertise in front of Australia', "Repairers, inspectors, tyre shops, auto electricians, fabricators, parts suppliers and manufacturers: join the TrailerWise service network.\n\n#TrailerBusiness #TrailerWise #AustralianBusiness"],
                'service-discovery' => ['Find the right trailer specialist', "From bearings and brakes to roadworthy inspections, wiring and fabrication, TrailerWise makes relevant trailer services easier to find.\n\n#TrailerService #TrailerRepair #TrailerWise"],
                'education-safety' => ['Service it before you tow it', "Check tyres, wheel bearings, brakes, lights, coupling and safety chains before every trip. Use qualified providers for inspections and repairs.\n\n#TrailerSafety #Roadworthy #TrailerWise"],
                'community' => ['Built for every kind of trailer', "Boat, horse, box, plant or commercial: what type of trailer do you rely on? Tell the TrailerWise community.\n\n#TrailerOwners #TrailerWise #MadeForAustralia"],
            ],
            'localtorque' => [
                'launch' => ['Local automotive expertise, easier to find', "LocalTorque connects Australians with workshops, mobile mechanics and automotive specialists nationwide.\n\n#LocalTorque #AutomotiveAustralia #LocalBusiness"],
                'provider-recruitment' => ['Put your workshop on Australia’s automotive map', "Mechanics, auto electricians, tyre shops, fabricators and specialists: claim your LocalTorque listing and help local customers find you.\n\n#LocalTorque #WorkshopAustralia #AutomotiveBusiness"],
                'service-discovery' => ['Find the right automotive specialist nearby', "Search workshops, mobile mechanics and specialist automotive businesses by service, location and availability.\n\n#LocalMechanic #LocalTorque #AutomotiveServices"],
                'education-safety' => ['Good maintenance starts with the right specialist', "Use qualified automotive businesses, verify the work required and keep clear service records for your vehicle.\n\n#VehicleMaintenance #LocalTorque #RoadSafety"],
                'community' => ['Built around local automotive knowledge', "Which local workshop has earned your trust? Share the specialist skill that keeps your vehicle moving.\n\n#LocalTorqueCommunity #SupportLocal #AutomotiveAustralia"],
            ],
        ];
        if (!isset($copy[$brand][$intention])) { throw new RuntimeException('Unknown social campaign intention.'); }
        $selected = $copy[$brand][$intention];
        return ['headline' => $selected[0], 'caption' => $selected[1]];
    }

    /** @return array<string,mixed> */
    public static function generate(string $brandKey, int $brandId, string $formatKey, string $intention, ?int $userId, string $templateKey = 'editorial', ?string $campaignName = null): array
    {
        $format = self::FORMATS[$formatKey] ?? null;
        if ($format === null || !isset(self::INTENTIONS[$intention]) || !isset(self::TEMPLATES[$templateKey])) { throw new RuntimeException('Choose a valid platform format, purpose and template.'); }
        if (!extension_loaded('gd')) { throw new RuntimeException('The image-generation extension is not installed.'); }
        $brand = self::brandStyle($brandKey);
        $copy = self::copyFor($brandKey, $intention);
        $source = base_path('public/assets/img/' . $brandKey . '-hero-' . ($format['height'] > $format['width'] ? 'mobile' : 'desktop') . '.webp');
        $background = is_file($source) ? imagecreatefromwebp($source) : false;
        if ($background === false) { throw new RuntimeException('The premium brand artwork is unavailable.'); }

        $canvas = imagecreatetruecolor($format['width'], $format['height']);
        if ($canvas === false) { imagedestroy($background); throw new RuntimeException('Unable to create artwork.'); }
        self::coverImage($canvas, $background, $format['width'], $format['height']);
        imagedestroy($background);

        $overlayAlpha = $templateKey === 'launch-impact' ? 48 : 35;
        $overlay = imagecolorallocatealpha($canvas, 4, 12, 24, $overlayAlpha);
        imagefilledrectangle($canvas, 0, 0, $format['width'], $format['height'], $overlay);
        $panel = imagecolorallocatealpha($canvas, ...array_merge(self::hexRgb($brand['dark']), [18]));
        $isPortrait = $format['height'] > $format['width'];
        $isWide = ($format['width'] / $format['height']) > 1.4;
        $panelTop = (int) ($format['height'] * match ($templateKey) {
            'field-guide' => ($isPortrait ? .42 : .22),
            'provider-spotlight' => ($isPortrait ? .55 : .38),
            'launch-impact' => ($isPortrait ? .38 : .18),
            default => ($isPortrait ? .48 : ($isWide ? .27 : .34)),
        });
        imagefilledrectangle($canvas, 0, $panelTop, $format['width'], $format['height'], $panel);
        $accent = imagecolorallocate($canvas, ...self::hexRgb($brand['accent']));
        imagefilledrectangle($canvas, 0, $panelTop, max(14, (int) ($format['width'] * .012)), $format['height'], $accent);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $muted = imagecolorallocate($canvas, 224, 231, 240);
        $bold = self::font(true);
        $regular = self::font(false);
        $pad = (int) ($format['width'] * ($isWide ? .06 : .075));
        $brandSize = max(28, (int) ($format['width'] * .035));
        $headlineSize = max(42, (int) ($format['width'] * ($isPortrait ? .065 : ($isWide ? .038 : .05))));
        $markPath = base_path('public/assets/brands/' . $brandKey . '/mark.svg');
        $kicker = strtoupper($brand['name'] . ' · ' . self::TEMPLATES[$templateKey]);
        imagettftext($canvas, $brandSize, 0, $pad, $panelTop + $pad, $accent, $bold, $kicker);
        self::drawWrapped($canvas, $copy['headline'], $headlineSize, $pad, $panelTop + $pad + $brandSize + 34, $format['width'] - ($pad * 2), $white, $bold, 1.12);
        if ($templateKey === 'field-guide') {
            $ruleY = $panelTop + (int) ($pad * .45);
            imagefilledrectangle($canvas, $pad, $ruleY, $format['width'] - $pad, $ruleY + max(3, (int) ($format['height'] * .005)), $accent);
        }
        if ($templateKey === 'provider-spotlight') {
            $badge = imagecolorallocatealpha($canvas, 255, 255, 255, 18);
            imagefilledellipse($canvas, $format['width'] - $pad - 38, $panelTop + $pad - 8, 76, 76, $badge);
            imagettftext($canvas, max(18, (int) ($brandSize * .65)), 0, $format['width'] - $pad - 58, $panelTop + $pad, $brand['dark'] === '#073f43' ? $accent : $white, $bold, 'PRO');
        }
        $domainY = $format['height'] - $pad;
        imagettftext($canvas, max(24, (int) ($format['width'] * .026)), 0, $pad, $domainY, $muted, $regular, $brand['domain']);

        $dir = base_path((string) config('uploads.paths.social_media_assets'));
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) { throw new RuntimeException('Unable to create social artwork storage.'); }
        $filename = $brandKey . '-' . $formatKey . '-' . $intention . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.png';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!imagepng($canvas, $path, 8)) { imagedestroy($canvas); throw new RuntimeException('Unable to save artwork.'); }
        imagedestroy($canvas);
        @chmod($path, 0640);

        $id = Database::insert(
            'INSERT INTO social_media_assets (brand_id, platform, format_key, intention, template_key, campaign_name, headline, caption, image_path, width, height, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$brandId, $format['platform'], $formatKey, $intention, $templateKey, $campaignName !== null ? mb_substr(trim($campaignName), 0, 120) : null, $copy['headline'], $copy['caption'], $filename, $format['width'], $format['height'], 'draft', $userId]
        );
        return self::find($id, $brandId) ?? [];
    }

    public static function setStatus(int $id, int $brandId, string $status, ?int $userId): void
    {
        if (!in_array($status, ['draft', 'approved', 'archived'], true)) { throw new RuntimeException('Invalid review status.'); }
        Database::query(
            'UPDATE social_media_assets SET status = ?, approved_by = ?, approved_at = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?',
            [$status, $status === 'approved' ? $userId : null, $status === 'approved' ? date('Y-m-d H:i:s') : null, $id, $brandId]
        );
    }

    /** @return array{name:string,domain:string,dark:string,accent:string} */
    private static function brandStyle(string $key): array
    {
        return match ($key) {
            'vanassist' => ['name' => 'VanAssist', 'domain' => 'vanassist.com.au', 'dark' => '#073f43', 'accent' => '#f5a623'],
            'towsmart' => ['name' => 'TowSmart', 'domain' => 'towsmart.com.au', 'dark' => '#10275c', 'accent' => '#f5a623'],
            'trailerwise' => ['name' => 'TrailerWise', 'domain' => 'trailerwise.com.au', 'dark' => '#35135f', 'accent' => '#ff7a1a'],
            'localtorque' => ['name' => 'LocalTorque', 'domain' => 'localtorque.com.au', 'dark' => '#0f3b4c', 'accent' => '#e56b2f'],
            default => throw new RuntimeException('Unknown brand.'),
        };
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function font(bool $bold): string
    {
        $paths = $bold
            ? ['/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/ttf-dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 'C:/Windows/Fonts/arialbd.ttf']
            : ['/usr/share/fonts/dejavu/DejaVuSans.ttf', '/usr/share/fonts/ttf-dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 'C:/Windows/Fonts/arial.ttf'];
        foreach ($paths as $path) { if (is_file($path)) { return $path; } }
        throw new RuntimeException('A supported TrueType font is unavailable.');
    }

    /** @param resource|\GdImage $canvas @param resource|\GdImage $source */
    private static function coverImage($canvas, $source, int $width, int $height): void
    {
        $sw = imagesx($source); $sh = imagesy($source);
        $scale = max($width / $sw, $height / $sh);
        $rw = (int) ceil($width / $scale); $rh = (int) ceil($height / $scale);
        $sx = max(0, (int) (($sw - $rw) / 2)); $sy = max(0, (int) (($sh - $rh) / 2));
        imagecopyresampled($canvas, $source, 0, 0, $sx, $sy, $width, $height, $rw, $rh);
    }

    /** @param resource|\GdImage $canvas */
    private static function drawWrapped($canvas, string $text, int $size, int $x, int $y, int $maxWidth, int $colour, string $font, float $spacing): void
    {
        $lines = []; $line = '';
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $candidate = trim($line . ' ' . $word);
            $box = imagettfbbox($size, 0, $font, $candidate);
            if ($line !== '' && $box !== false && ($box[2] - $box[0]) > $maxWidth) { $lines[] = $line; $line = $word; } else { $line = $candidate; }
        }
        if ($line !== '') { $lines[] = $line; }
        foreach ($lines as $i => $value) { imagettftext($canvas, $size, 0, $x, (int) ($y + ($i * $size * $spacing)), $colour, $font, $value); }
    }
}
