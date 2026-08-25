<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\VanAssistGrowthService;
use Throwable;

final class GrowthTrustController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('demand.view');
        if (current_brand()->id() !== 'vanassist') $this->abort(404);
        return $this->view('admin.growth-trust.index', [
            'title' => 'VanAssist growth and trust',
            'report' => (new VanAssistGrowthService())->dashboard(current_brand()->databaseId()),
        ]);
    }

    public function publishTown(Request $request): Response
    {
        $this->requirePermission('seo.manage');
        if (current_brand()->id() !== 'vanassist') $this->abort(404);
        try {
            (new VanAssistGrowthService())->publishTown(current_brand()->databaseId(), (int) $request->input('town_id'));
            return $this->redirectWith('/admin/growth-trust', 'success', 'The reviewed town page is now indexable and included in the sitemap.');
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/growth-trust', 'error', $e->getMessage());
        }
    }
}
