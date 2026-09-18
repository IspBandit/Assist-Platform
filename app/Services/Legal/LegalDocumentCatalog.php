<?php

declare(strict_types=1);

namespace App\Services\Legal;

use RuntimeException;

/**
 * Catalog of Assist Platform legal / sale-readiness documents.
 * Source markdown lives under docs/legal/drafts/ (COM-005).
 */
final class LegalDocumentCatalog
{
    private string $draftsPath;

    public function __construct(?string $draftsPath = null)
    {
        $this->draftsPath = $draftsPath ?? base_path('docs/legal/drafts');
    }

    /**
     * @return list<array{
     *   id:string,
     *   title:string,
     *   filename:string,
     *   category:string,
     *   audience:string,
     *   summary:string,
     *   source:string,
     *   effective_label:string
     * }>
     */
    public function all(): array
    {
        $docs = [
            [
                'id' => 'terms-of-use',
                'title' => 'Terms of Use',
                'filename' => 'Assist-Platform-Terms-of-Use.pdf',
                'category' => 'Public site',
                'audience' => 'Customers, providers, visitors',
                'summary' => 'Multi-brand platform terms covering VanAssist, TowSmart and TrailerWise.',
                'source' => '01-terms-of-use.md',
                'effective_label' => 'Effective 18 September 2026',
            ],
            [
                'id' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'filename' => 'Assist-Platform-Privacy-Policy.pdf',
                'category' => 'Public site',
                'audience' => 'Customers, providers, visitors',
                'summary' => 'Personal information collection, use, disclosure, retention and complaints.',
                'source' => '02-privacy-policy.md',
                'effective_label' => 'Effective 18 September 2026',
            ],
            [
                'id' => 'provider-terms',
                'title' => 'Provider Terms',
                'filename' => 'Assist-Platform-Provider-Terms.pdf',
                'category' => 'Public site',
                'audience' => 'Listed and claiming providers',
                'summary' => 'Additional terms for listings, claims, customer data and fees.',
                'source' => '03-provider-terms.md',
                'effective_label' => 'Effective 18 September 2026',
            ],
            [
                'id' => 'dpa',
                'title' => 'Data Processing Addendum (DPA)',
                'filename' => 'Assist-Platform-DPA.pdf',
                'category' => 'Contract / data room',
                'audience' => 'B2B partners, vendors, buyers',
                'summary' => 'Processor terms for instructed processing, subprocessors and security.',
                'source' => '04-dpa-data-processing-addendum.md',
                'effective_label' => 'Published for use · formal review scheduled',
            ],
            [
                'id' => 'opco',
                'title' => 'Operating Entity and Brand Licence (OpCo)',
                'filename' => 'Assist-Platform-OpCo-Brand-Licence.pdf',
                'category' => 'Corporate / data room',
                'audience' => 'Founder, advisers, buyers',
                'summary' => 'Current sole-trader operating picture and recommended OpCo structure.',
                'source' => '05-opco-operating-entity-and-brand-licence.md',
                'effective_label' => 'Published for use · formal review scheduled',
            ],
            [
                'id' => 'ip-assignment',
                'title' => 'IP Ownership and Assignment Schedule',
                'filename' => 'Assist-Platform-IP-Ownership-Assignment.pdf',
                'category' => 'Corporate / data room',
                'audience' => 'Founder, advisers, buyers',
                'summary' => 'Ownership statement, assignment deed template and third-party carve-outs.',
                'source' => '06-ip-ownership-and-assignment-schedule.md',
                'effective_label' => 'Published for use · formal review scheduled',
            ],
        ];

        return array_values(array_filter(
            $docs,
            fn (array $doc): bool => is_file($this->draftsPath . DIRECTORY_SEPARATOR . $doc['source'])
        ));
    }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $doc) {
            if ($doc['id'] === $id) {
                return $doc;
            }
        }
        return null;
    }

    public function markdownFor(string $id): string
    {
        $doc = $this->find($id);
        if ($doc === null) {
            throw new RuntimeException('Unknown legal document: ' . $id);
        }
        $path = $this->draftsPath . DIRECTORY_SEPARATOR . $doc['source'];
        if (!is_file($path)) {
            throw new RuntimeException('Legal source missing: ' . $doc['source']);
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read legal source: ' . $doc['source']);
        }
        return $this->stripInternalNotes($raw);
    }

    private function stripInternalNotes(string $markdown): string
    {
        // Drop unpublished drafting notes from generated PDFs.
        $markdown = preg_replace(
            '/\n##\s*Draft honesty notes.*$/is',
            "\n",
            $markdown
        ) ?? $markdown;
        $markdown = preg_replace(
            '/\*\*Draft — not legal advice\.\*\*[^\n]*/i',
            '**Status:** Effective 18 September 2026. Published for platform use; formal solicitor review may refine wording later. Not legal advice.',
            $markdown
        ) ?? $markdown;
        return trim($markdown) . "\n";
    }
}
