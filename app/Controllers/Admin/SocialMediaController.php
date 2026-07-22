<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\FileStorage;
use App\Services\SocialMediaAssetService;
use Throwable;

final class SocialMediaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $brand = current_brand();
        return $this->view('admin.social-media.index', [
            'title' => 'Social studio',
            'schemaReady' => SocialMediaAssetService::schemaReady(),
            'assets' => SocialMediaAssetService::listForBrand($brand->databaseId()),
            'formats' => SocialMediaAssetService::formats(),
            'intentions' => SocialMediaAssetService::intentions(),
            'brand' => $brand,
        ]);
    }

    public function generate(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $brand = current_brand();
        if (!SocialMediaAssetService::schemaReady()) {
            return $this->redirectWith('/admin/social-media', 'error', 'Run the latest database migration first.');
        }
        $format = trim((string) $request->input('format_key'));
        $intention = trim((string) $request->input('intention'));
        try {
            SocialMediaAssetService::generate($brand->id(), $brand->databaseId(), $format, $intention, (int) (current_user()['id'] ?? 0) ?: null);
            AuditLog::record('social_asset.generated', 'brand', (string) $brand->databaseId(), null, $format . ':' . $intention);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/social-media', 'error', $e->getMessage());
        }
        return $this->redirectWith('/admin/social-media', 'success', 'Premium social graphic and post copy generated for review.');
    }

    public function status(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $brand = current_brand();
        $id = (int) $request->input('id');
        if (SocialMediaAssetService::find($id, $brand->databaseId()) === null) { $this->abort(404); }
        $status = trim((string) $request->input('status'));
        try {
            SocialMediaAssetService::setStatus($id, $brand->databaseId(), $status, (int) (current_user()['id'] ?? 0) ?: null);
            AuditLog::record('social_asset.' . $status, 'social_media_asset', (string) $id);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/social-media', 'error', $e->getMessage());
        }
        return $this->redirectWith('/admin/social-media', 'success', 'Social graphic review status updated.');
    }

    public function preview(Request $request): Response
    {
        return $this->assetResponse($request, true);
    }

    public function download(Request $request): Response
    {
        return $this->assetResponse($request, false);
    }

    private function assetResponse(Request $request, bool $inline): Response
    {
        $this->requirePermission('content.manage');
        $asset = SocialMediaAssetService::find((int) $request->input('id'), current_brand()->databaseId());
        if ($asset === null) { $this->abort(404); }
        $name = current_brand()->id() . '-' . $asset['format_key'] . '-' . $asset['intention'] . '.png';
        return FileStorage::serve('social_media_assets', (string) $asset['image_path'], $name, 'image/png', $inline);
    }
}
