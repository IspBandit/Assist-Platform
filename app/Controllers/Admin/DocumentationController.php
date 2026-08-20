<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Documentation\DocumentationCatalog;

final class DocumentationController extends Controller
{
    public function index(Request $request): Response
    {
        $catalog = new DocumentationCatalog();
        $filters = $this->filters($request);
        return $this->view('documentation.index', $this->common() + [
            'title' => 'Platform documentation',
            'filters' => $filters,
            'filterOptions' => $catalog->filterOptions(true),
            'results' => $catalog->search($filters, true),
        ]);
    }

    public function guide(Request $request): Response
    {
        $guide = (new DocumentationCatalog())->guide((string) $request->route('guide'), true);
        if ($guide === null) {
            $this->abort(404, 'Guide not found.');
        }
        return $this->view('documentation.guide', $this->common() + ['title' => $guide['title'], 'guide' => $guide]);
    }

    public function article(Request $request): Response
    {
        $article = (new DocumentationCatalog())->article((string) $request->route('guide'), (string) $request->route('article'), true);
        if ($article === null) {
            $this->abort(404, 'Documentation article not found.');
        }
        return $this->view('documentation.article', $this->common() + ['title' => $article['title'], 'article' => $article]);
    }

    public function whatsNew(Request $request): Response
    {
        return $this->view('documentation.whats-new', $this->common() + ['title' => "What's new", 'articles' => (new DocumentationCatalog())->whatsNew(true)]);
    }

    /** @return array<string,mixed> */
    private function common(): array
    {
        return ['documentationLayout' => 'layouts.admin', 'documentationBase' => '/admin/help', 'documentationGuides' => (new DocumentationCatalog())->guides(true)];
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
