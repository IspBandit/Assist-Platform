<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Platform\AiSearch\Knowledge\KnowledgeGapService;
use App\Platform\AiSearch\Support\AiSearchFeature;

/**
 * Ask VanAssist outcome redirects — knowledge-gap click-through attribution.
 * Only relative, same-site paths are allowed (open-redirect safe).
 */
final class AssistOutcomeController extends Controller
{
    public function click(Request $request): Response
    {
        if (current_brand()->id() !== 'vanassist' || !AiSearchFeature::enabled()) {
            $this->abort(404, 'Page not found.');
        }

        $gapId = (int) $request->route('gapId');
        if ($gapId > 0) {
            (new KnowledgeGapService())->recordClickThrough($gapId);
        }

        $to = trim((string) $request->input('to', ''));
        $path = $this->safeRelativePath($to);
        if ($path === null) {
            return $this->redirect('ask');
        }

        return $this->redirect($path);
    }

    private function safeRelativePath(string $to): ?string
    {
        $to = ltrim($to, '/');
        if ($to === '' || str_contains($to, '..') || str_contains($to, '://') || str_starts_with($to, '//')) {
            return null;
        }
        if (!preg_match('#^(providers|business|caravan-parks|stays|find|ask)/[A-Za-z0-9._~/-]*$#', $to)
            && !preg_match('#^(providers|business|caravan-parks|stays|find|ask)$#', $to)) {
            return null;
        }
        return $to;
    }
}
