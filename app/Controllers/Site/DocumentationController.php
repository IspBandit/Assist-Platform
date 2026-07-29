<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Documentation\DocumentationCatalog;

final class DocumentationController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('documentation.index', $this->indexData($request, false, 'layouts.public', '/help'));
    }

    public function guide(Request $request): Response
    {
        $catalog = new DocumentationCatalog();
        $guide = $catalog->guide((string) $request->route('guide'), false);
        if ($guide === null) {
            $this->abort(404, 'Guide not found.');
        }
        return $this->view('documentation.guide', $this->common(false, 'layouts.public', '/help') + ['title' => $guide['title'], 'guide' => $guide]);
    }

    public function article(Request $request): Response
    {
        $catalog = new DocumentationCatalog();
        $article = $catalog->article((string) $request->route('guide'), (string) $request->route('article'), false);
        if ($article === null) {
            $this->abort(404, 'Documentation article not found.');
        }
        return $this->view('documentation.article', $this->common(false, 'layouts.public', '/help') + ['title' => $article['title'], 'article' => $article]);
    }

    public function whatsNew(Request $request): Response
    {
        $catalog = new DocumentationCatalog();
        return $this->view('documentation.whats-new', $this->common(false, 'layouts.public', '/help') + ['title' => "What's new", 'articles' => $catalog->whatsNew(false)]);
    }

    /** @return array<string,mixed> */
    private function indexData(Request $request, bool $operational, string $layout, string $base): array
    {
        $catalog = new DocumentationCatalog();
        $filters = $this->filters($request);
        return $this->common($operational, $layout, $base) + [
            'title' => 'Help and documentation',
            'filters' => $filters,
            'filterOptions' => $catalog->filterOptions($operational),
            'results' => $catalog->search($filters, $operational),
        ];
    }

    /** @return array<string,mixed> */
    private function common(bool $operational, string $layout, string $base): array
    {
        return ['documentationLayout' => $layout, 'documentationBase' => $base, 'documentationGuides' => (new DocumentationCatalog())->guides($operational)];
    }

    /** @return array<string,string> */
    private function filters(Request $request): array
    {
        $filters = [];
        foreach (['q', 'audience', 'brand', 'module', 'version'] as $key) {
            $filters[$key] = mb_substr(trim((string) $request->query($key, '')), 0, $key === 'q' ? 120 : 60);
        }
        return $filters;
    }
}
