<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * Public FAQ page. Lists active FAQs grouped by category and emits FAQPage
 * structured data (JSON-LD) for rich results.
 */
final class FaqController extends Controller
{
    public function index(Request $request): Response
    {
        $faqs = $this->safe(
            fn () => Database::select('SELECT category, question, answer FROM faqs WHERE is_active = 1 ORDER BY category, sort_order, id')
        );

        $grouped = [];
        foreach ($faqs as $faq) {
            $grouped[(string) $faq['category']][] = $faq;
        }
        ksort($grouped);

        $intro = $this->safe(
            fn () => Database::selectOne("SELECT body FROM content_pages WHERE slug = 'faqs' AND is_published = 1 LIMIT 1")
        );

        return $this->view('public.faqs', [
            'title'           => 'Frequently asked questions',
            'metaDescription' => 'Answers to common questions about using VanAssist to find caravan and RV service across regional Australia.',
            'canonical'       => url('faqs'),
            'grouped'         => $grouped,
            'introBody'       => $intro['body'] ?? null,
            'jsonLd'          => $this->buildJsonLd($faqs),
        ]);
    }

    /** @param array<int,array<string,mixed>> $faqs */
    private function buildJsonLd(array $faqs): ?string
    {
        if ($faqs === []) {
            return null;
        }
        $items = [];
        foreach ($faqs as $faq) {
            $items[] = [
                '@type'          => 'Question',
                'name'           => (string) $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => strip_tags((string) $faq['answer']),
                ],
            ];
        }
        return json_encode([
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }

    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return [];
        }
    }
}
