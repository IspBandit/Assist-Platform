<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\Legal\LegalDocumentCatalog;
use App\Services\Legal\LegalPdfRenderer;
use Throwable;

/**
 * COM-005 — Legal and sale-readiness PDF pack for administrators.
 */
final class LegalDocumentsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('settings.manage');

        $catalog = new LegalDocumentCatalog();
        return $this->view('admin.legal-documents.index', [
            'title' => 'Legal documents',
            'documents' => $catalog->all(),
        ]);
    }

    public function download(Request $request): Response
    {
        $this->requirePermission('settings.manage');

        $id = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $request->query('id', ''))) ?? '';
        $catalog = new LegalDocumentCatalog();
        $doc = $catalog->find($id);
        if ($doc === null) {
            $this->abort(404, 'Legal document not found.');
        }

        try {
            $binary = (new LegalPdfRenderer($catalog))->render($id);
        } catch (Throwable $e) {
            $this->abort(500, 'Unable to generate PDF: ' . $e->getMessage());
        }

        AuditLog::record(
            'legal_document.download',
            'legal_document',
            $id,
            null,
            json_encode([
                'filename' => $doc['filename'],
                'title' => $doc['title'],
            ], JSON_UNESCAPED_SLASHES) ?: null
        );

        $filename = (string) $doc['filename'];
        return (new Response($binary, 200))
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($binary))
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
