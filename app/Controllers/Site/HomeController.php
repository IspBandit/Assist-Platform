<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Settings;
use App\Services\SeoSchema;
use Throwable;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        if (current_brand()->id() === 'towsmart') {
            return (new TowSmartController())->home($request);
        }
        if (current_brand()->id() === 'trailerwise') {
            return (new TrailerWiseController())->home($request);
        }

        $categories = $this->safe(fn () => \App\Models\ServiceCategory::activeAll());
        $categoryGroups = \App\Models\ServiceCategory::groupedForVanAssist($categories);

        return $this->view('public.home', [
            'title'         => 'Caravan help, wherever you travel',
            'canonical'     => url('/'),
            'categories'        => $categories,
            'categoryGroups'    => $categoryGroups,
            'freeMessage'   => Settings::get('free_launch_message', ''),
            'jsonLd'        => SeoSchema::brandWebsite(current_brand()),
        ]);
    }

    private function safe(callable $fn): array
    {
        try {
            return $fn();
        } catch (Throwable) {
            return [];
        }
    }
}
