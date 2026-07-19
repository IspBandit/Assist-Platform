<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ServiceCategory;
use App\Services\AuditLog;

/**
 * Admin management of nestable service categories with SEO content.
 */
final class CategoriesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('categories.manage');

        return $this->view('admin.categories.index', [
            'title'      => 'Service categories',
            'categories' => ServiceCategory::listing(),
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('categories.manage');
        $id = (int) $request->input('id');
        $category = $id ? ServiceCategory::find($id) : null;
        if ($id && $category === null) {
            $this->abort(404);
        }

        return $this->view('admin.categories.form', [
            'title'    => $category ? 'Edit category' : 'New category',
            'category' => $category,
            'parents'  => ServiceCategory::parentOptions($id ?: null),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('categories.manage');
        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWith('/admin/categories', 'error', 'Category name is required.');
        }

        // A category cannot be its own parent; only top-level categories may be parents.
        $parentId = (int) $request->input('parent_id') ?: null;
        if ($parentId === $id) {
            $parentId = null;
        }

        $slug = $this->uniqueSlug($request->input('slug') ?: $name, $id);

        $data = [
            'parent_id'          => $parentId,
            'name'               => $name,
            'slug'               => $slug,
            'icon'               => trim((string) $request->input('icon')) ?: null,
            'short_description'  => trim((string) $request->input('short_description')) ?: null,
            'public_description' => trim((string) $request->input('public_description')) ?: null,
            'customer_guidance'  => trim((string) $request->input('customer_guidance')) ?: null,
            'typical_issues'     => trim((string) $request->input('typical_issues')) ?: null,
            'sort_order'         => (int) $request->input('sort_order', 0),
            'is_active'          => $request->input('is_active') ? 1 : 0,
            'seo_title'          => trim((string) $request->input('seo_title')) ?: null,
            'seo_description'    => trim((string) $request->input('seo_description')) ?: null,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            ServiceCategory::update($id, $data);
            AuditLog::record('category.updated', 'service_category', (string) $id, null, $name);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = ServiceCategory::create($data);
            AuditLog::record('category.created', 'service_category', (string) $id, null, $name);
        }

        return $this->redirectWith('/admin/categories', 'success', 'Category saved.');
    }

    public function toggle(Request $request): Response
    {
        $this->requirePermission('categories.manage');
        $id = (int) $request->input('id');
        $category = ServiceCategory::find($id);
        if ($category === null) {
            $this->abort(404);
        }
        $new = $category['is_active'] ? 0 : 1;
        ServiceCategory::update($id, ['is_active' => $new, 'updated_at' => date('Y-m-d H:i:s')]);
        AuditLog::record('category.toggled', 'service_category', (string) $id, (string) $category['is_active'], (string) $new);

        return $this->redirectWith('/admin/categories', 'success', 'Category visibility updated.');
    }

    private function uniqueSlug(string $source, int $excludeId): string
    {
        $base = str_slug($source) ?: 'category';
        $slug = $base;
        $n = 1;
        while (true) {
            $sql = 'SELECT COUNT(*) FROM service_categories WHERE slug = ?';
            $params = [$slug];
            if ($excludeId > 0) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeId;
            }
            if ((int) Database::scalar($sql, $params) === 0) {
                return $slug;
            }
            $slug = $base . '-' . (++$n);
        }
    }
}
