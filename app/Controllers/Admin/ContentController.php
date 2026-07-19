<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditLog;

/**
 * Admin CMS: editable pages (with per-page SEO), homepage content blocks and
 * FAQs. Page bodies and block bodies are trusted admin HTML.
 */
final class ContentController extends Controller
{
    // ---- Pages -------------------------------------------------------------

    public function pages(Request $request): Response
    {
        $this->requirePermission('content.manage');
        return $this->view('admin.content.pages', [
            'title' => 'Pages',
            'pages' => Database::select('SELECT id, page_key, title, slug, is_published, is_system, noindex FROM content_pages ORDER BY title'),
        ]);
    }

    public function pageForm(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $id = (int) $request->input('id');
        $page = $id > 0 ? Database::selectOne('SELECT * FROM content_pages WHERE id = ?', [$id]) : null;
        if ($id > 0 && $page === null) {
            $this->abort(404, 'Page not found.');
        }
        return $this->view('admin.content.page-form', [
            'title'  => $page ? 'Edit page' : 'New page',
            'page'   => $page,
            'errors' => Session::errors(),
        ]);
    }

    public function savePage(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $id = (int) $request->input('id');
        $existing = $id > 0 ? Database::selectOne('SELECT * FROM content_pages WHERE id = ?', [$id]) : null;
        if ($id > 0 && $existing === null) {
            $this->abort(404);
        }

        $title = trim((string) $request->input('title'));
        $slug = str_slug((string) ($request->input('slug') ?: $title));
        $schema = trim((string) $request->input('schema_json'));

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }
        if ($slug === '') {
            $errors['slug'] = 'A valid slug is required.';
        }
        if ($schema !== '' && json_decode($schema) === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors['schema_json'] = 'Structured data must be valid JSON (or left blank).';
        }
        $clash = Database::selectOne('SELECT id FROM content_pages WHERE slug = ? AND id <> ?', [$slug, $id]);
        if ($clash !== null) {
            $errors['slug'] = 'Another page already uses that slug.';
        }
        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput($request->all());
            return $this->redirect($id > 0 ? '/admin/content/pages/edit?id=' . $id : '/admin/content/pages/new');
        }

        $fields = [
            'title'           => $title,
            'slug'            => $slug,
            'body'            => (string) $request->input('body'),
            'is_published'    => $request->input('is_published') ? 1 : 0,
            'seo_title'       => trim((string) $request->input('seo_title')) ?: null,
            'seo_description' => trim((string) $request->input('seo_description')) ?: null,
            'canonical_url'   => trim((string) $request->input('canonical_url')) ?: null,
            'noindex'         => $request->input('noindex') ? 1 : 0,
            'og_title'        => trim((string) $request->input('og_title')) ?: null,
            'og_description'  => trim((string) $request->input('og_description')) ?: null,
            'og_image'        => trim((string) $request->input('og_image')) ?: null,
            'schema_json'     => $schema ?: null,
            'updated_by'      => current_user()['id'] ?? null,
        ];

        if ($existing === null) {
            $fields['page_key'] = $this->uniqueKey($slug);
            $fields['is_system'] = 0;
            $cols = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $newId = Database::insert("INSERT INTO content_pages ($cols, created_at, updated_at) VALUES ($placeholders, NOW(), NOW())", array_values($fields));
            AuditLog::record('content.page_create', 'content_page', (string) $newId, null, $slug);
            return $this->redirectWith('/admin/content/pages/edit?id=' . $newId, 'success', 'Page created.');
        }

        $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;
        Database::query("UPDATE content_pages SET $set, updated_at = NOW() WHERE id = ?", $params);
        AuditLog::record('content.page_update', 'content_page', (string) $id, null, $slug);
        return $this->redirectWith('/admin/content/pages/edit?id=' . $id, 'success', 'Page saved.');
    }

    public function deletePage(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $page = Database::selectOne('SELECT * FROM content_pages WHERE id = ?', [(int) $request->input('id')]);
        if ($page === null) {
            $this->abort(404);
        }
        if ((int) $page['is_system'] === 1) {
            return $this->redirectWith('/admin/content', 'error', 'System pages cannot be deleted.');
        }
        Database::query('DELETE FROM content_pages WHERE id = ?', [(int) $page['id']]);
        AuditLog::record('content.page_delete', 'content_page', (string) $page['id'], (string) $page['slug']);
        return $this->redirectWith('/admin/content', 'success', 'Page deleted.');
    }

    // ---- Homepage blocks ---------------------------------------------------

    public function blocks(Request $request): Response
    {
        $this->requirePermission('content.manage');
        return $this->view('admin.content.blocks', [
            'title'  => 'Homepage blocks',
            'blocks' => Database::select("SELECT * FROM content_blocks WHERE block_group = 'homepage' ORDER BY sort_order, id"),
        ]);
    }

    public function blockForm(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $id = (int) $request->input('id');
        $block = $id > 0 ? Database::selectOne('SELECT * FROM content_blocks WHERE id = ?', [$id]) : null;
        if ($id > 0 && $block === null) {
            $this->abort(404);
        }
        return $this->view('admin.content.block-form', [
            'title' => $block ? 'Edit block' : 'New block',
            'block' => $block,
        ]);
    }

    public function saveBlock(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $id = (int) $request->input('id');
        $existing = $id > 0 ? Database::selectOne('SELECT id FROM content_blocks WHERE id = ?', [$id]) : null;

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return $this->redirectWith($id > 0 ? '/admin/content/blocks/edit?id=' . $id : '/admin/content/blocks/new', 'error', 'A title is required.');
        }

        $fields = [
            'title'        => $title,
            'subtitle'     => trim((string) $request->input('subtitle')) ?: null,
            'body'         => trim((string) $request->input('body')) ?: null,
            'button_label' => trim((string) $request->input('button_label')) ?: null,
            'button_url'   => trim((string) $request->input('button_url')) ?: null,
            'sort_order'   => (int) $request->input('sort_order'),
            'is_active'    => $request->input('is_active') ? 1 : 0,
        ];

        if ($existing === null) {
            $fields['block_group'] = 'homepage';
            $fields['block_key'] = trim((string) $request->input('block_key')) ?: ('block_' . substr(bin2hex(random_bytes(4)), 0, 8));
            $cols = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $newId = Database::insert("INSERT INTO content_blocks ($cols, created_at, updated_at) VALUES ($placeholders, NOW(), NOW())", array_values($fields));
            AuditLog::record('content.block_create', 'content_block', (string) $newId);
            return $this->redirectWith('/admin/content/blocks', 'success', 'Block created.');
        }

        $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;
        Database::query("UPDATE content_blocks SET $set, updated_at = NOW() WHERE id = ?", $params);
        AuditLog::record('content.block_update', 'content_block', (string) $id);
        return $this->redirectWith('/admin/content/blocks', 'success', 'Block saved.');
    }

    public function deleteBlock(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $id = (int) $request->input('id');
        Database::query("DELETE FROM content_blocks WHERE id = ? AND block_group = 'homepage'", [$id]);
        AuditLog::record('content.block_delete', 'content_block', (string) $id);
        return $this->redirectWith('/admin/content/blocks', 'success', 'Block deleted.');
    }

    // ---- FAQs --------------------------------------------------------------

    public function faqs(Request $request): Response
    {
        $this->requirePermission('content.manage');
        return $this->view('admin.content.faqs', [
            'title' => 'FAQs',
            'faqs'  => Database::select('SELECT * FROM faqs ORDER BY category, sort_order, id'),
        ]);
    }

    public function faqForm(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $id = (int) $request->input('id');
        $faq = $id > 0 ? Database::selectOne('SELECT * FROM faqs WHERE id = ?', [$id]) : null;
        if ($id > 0 && $faq === null) {
            $this->abort(404);
        }
        return $this->view('admin.content.faq-form', [
            'title' => $faq ? 'Edit FAQ' : 'New FAQ',
            'faq'   => $faq,
        ]);
    }

    public function saveFaq(Request $request): Response
    {
        $this->requirePermission('content.manage');
        $id = (int) $request->input('id');
        $existing = $id > 0 ? Database::selectOne('SELECT id FROM faqs WHERE id = ?', [$id]) : null;

        $question = trim((string) $request->input('question'));
        $answer = trim((string) $request->input('answer'));
        if ($question === '' || $answer === '') {
            return $this->redirectWith($id > 0 ? '/admin/content/faqs/edit?id=' . $id : '/admin/content/faqs/new', 'error', 'Question and answer are required.');
        }

        $fields = [
            'category'   => trim((string) $request->input('category')) ?: 'general',
            'question'   => $question,
            'answer'     => $answer,
            'sort_order' => (int) $request->input('sort_order'),
            'is_active'  => $request->input('is_active') ? 1 : 0,
        ];

        if ($existing === null) {
            $cols = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            Database::insert("INSERT INTO faqs ($cols, created_at, updated_at) VALUES ($placeholders, NOW(), NOW())", array_values($fields));
            return $this->redirectWith('/admin/content/faqs', 'success', 'FAQ created.');
        }

        $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;
        Database::query("UPDATE faqs SET $set, updated_at = NOW() WHERE id = ?", $params);
        return $this->redirectWith('/admin/content/faqs', 'success', 'FAQ saved.');
    }

    public function deleteFaq(Request $request): Response
    {
        $this->requirePermission('content.manage');
        Database::query('DELETE FROM faqs WHERE id = ?', [(int) $request->input('id')]);
        return $this->redirectWith('/admin/content/faqs', 'success', 'FAQ deleted.');
    }

    private function uniqueKey(string $slug): string
    {
        $base = preg_replace('/[^a-z0-9_]+/', '_', strtolower($slug)) ?: 'page';
        $key = $base;
        $n = 1;
        while ((int) Database::scalar('SELECT COUNT(*) FROM content_pages WHERE page_key = ?', [$key]) > 0) {
            $key = $base . '_' . (++$n);
        }
        return $key;
    }
}
