<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\FileStorage;
use App\Services\FacebookPagePublisher;
use App\Core\Database;
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
            'templates' => SocialMediaAssetService::templates(),
            'brand' => $brand,
            'facebookConnected' => FacebookPagePublisher::configured($brand->id()),
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
        $template = trim((string) $request->input('template_key', 'editorial'));
        $campaign = trim((string) $request->input('campaign_name', ''));
        try {
            SocialMediaAssetService::generate($brand->id(), $brand->databaseId(), $format, $intention, (int) (current_user()['id'] ?? 0) ?: null, $template, $campaign !== '' ? $campaign : null);
            AuditLog::record('social_asset.generated', 'brand', (string) $brand->databaseId(), null, $format . ':' . $intention . ':' . $template);
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

    public function delete(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $brand = current_brand();
        $id = (int) $request->input('id');
        if (SocialMediaAssetService::find($id, $brand->databaseId()) === null) { $this->abort(404); }

        try {
            $asset = SocialMediaAssetService::delete($id, $brand->databaseId());
            AuditLog::record(
                'social_asset.deleted',
                'social_media_asset',
                (string) $id,
                json_encode([
                    'brand_id' => $brand->databaseId(),
                    'platform' => $asset['platform'] ?? null,
                    'format_key' => $asset['format_key'] ?? null,
                    'status' => $asset['status'] ?? null,
                    'facebook_post_id' => $asset['facebook_post_id'] ?? null,
                    'image_path' => $asset['image_path'] ?? null,
                ], JSON_UNESCAPED_SLASHES) ?: null,
                null
            );
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/social-media', 'error', $e->getMessage());
        }

        $notice = empty($asset['facebook_post_id'])
            ? 'Social asset and stored image deleted.'
            : 'Social asset and stored image deleted. The existing Facebook post was not removed.';
        return $this->redirectWith('/admin/social-media', 'success', $notice);
    }

    public function download(Request $request): Response
    {
        return $this->assetResponse($request, false);
    }

    public function publishFacebook(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $brand = current_brand();
        $id = (int) $request->input('id');
        $asset = SocialMediaAssetService::find($id, $brand->databaseId());
        if ($asset === null) { $this->abort(404); }
        try {
            $result = FacebookPagePublisher::publish($brand->id(), $asset);
            Database::query('UPDATE social_media_assets SET facebook_post_id=?,facebook_publish_error=NULL,facebook_published_at=NOW(),facebook_published_by=?,updated_at=NOW() WHERE id=? AND brand_id=?', [
                $result['post_id'], current_user()['id'] ?? null, $id, $brand->databaseId(),
            ]);
            AuditLog::record('social_asset.facebook_published', 'social_media_asset', (string) $id, null, $result['post_id']);
            return $this->redirectWith('/admin/social-media', 'success', 'Published to the ' . $brand->name() . ' Facebook Page.');
        } catch (Throwable $e) {
            Database::query('UPDATE social_media_assets SET facebook_publish_error=?,updated_at=NOW() WHERE id=? AND brand_id=?', [mb_substr($e->getMessage(), 0, 500), $id, $brand->databaseId()]);
            return $this->redirectWith('/admin/social-media', 'error', $e->getMessage());
        }
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
