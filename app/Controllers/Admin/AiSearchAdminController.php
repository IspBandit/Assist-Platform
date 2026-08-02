<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Platform\AiSearch\Budget\AiCostSimulator;
use App\Platform\AiSearch\Budget\AiSettings;
use App\Platform\AiSearch\Budget\AIUsageService;
use App\Platform\AiSearch\Knowledge\KnowledgeGapService;
use App\Platform\AiSearch\Retention\AiRetentionService;
use App\Platform\AiSearch\Support\AiReleaseGate;
use App\Platform\AiSearch\Support\AiSearchFeature;
use App\Platform\AiSearch\Support\DatasetSearchFeature;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\AuditLog;
use App\Services\CsvExport;
use App\Services\FeatureFlag;

/**
 * Admin visibility for Assist AI cache, budget, usage, gaps and hardening.
 */
final class AiSearchAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $summary = (new AIUsageService())->adminSummary();
        $gate = (new AiReleaseGate())->evaluate();
        $sim = null;
        if ($request->input('sim') === '1') {
            $sim = AiCostSimulator::simulate(
                (string) $request->input('sim_model', ''),
                (int) $request->input('sim_searches_per_day', 100),
                (float) $request->input('sim_ai_hit_pct', 20),
                is_numeric($request->input('sim_input_tokens')) ? (int) $request->input('sim_input_tokens') : null,
                is_numeric($request->input('sim_output_tokens')) ? (int) $request->input('sim_output_tokens') : null,
            );
        }

        return $this->view('admin.ai-search.index', [
            'title' => 'Assist AI Search',
            'summary' => $summary,
            'askFlagEnabled' => AiSearchFeature::enabled(),
            'askFlagKey' => AiSearchFeature::FLAG,
            'datasetsFlagEnabled' => DatasetSearchFeature::enabled(),
            'datasetsFlagKey' => DatasetSearchFeature::FLAG,
            'facilitiesFlagEnabled' => TravellerFacilitiesFeature::enabled(),
            'facilitiesFlagKey' => TravellerFacilitiesFeature::FLAG,
            'releaseGate' => $gate,
            'retentionWindows' => AiRetentionService::windows(),
            'costSimulation' => $sim,
            'simInputs' => [
                'model' => (string) $request->input('sim_model', ''),
                'searches_per_day' => (int) $request->input('sim_searches_per_day', 100),
                'ai_hit_pct' => (float) $request->input('sim_ai_hit_pct', 20),
                'input_tokens' => (int) $request->input('sim_input_tokens', 800),
                'output_tokens' => (int) $request->input('sim_output_tokens', 500),
            ],
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('settings.manage');

        $before = AiSettings::get();
        $after = AiSettings::save([
            'ai_enabled' => $request->input('ai_enabled') === '1',
            'openai_enabled' => $request->input('openai_enabled') === '1',
            'model_allowlist' => (string) $request->input('model_allowlist', ''),
            'daily_request_cap' => $request->input('daily_request_cap'),
            'monthly_request_cap' => $request->input('monthly_request_cap'),
            'daily_budget_aud' => $request->input('daily_budget_aud'),
            'monthly_budget_aud' => $request->input('monthly_budget_aud'),
            'soft_warn_pct' => $request->input('soft_warn_pct'),
            'max_prompt_chars' => $request->input('max_prompt_chars'),
            'max_output_tokens' => $request->input('max_output_tokens'),
            'max_retries' => $request->input('max_retries'),
            'timeout_seconds' => $request->input('timeout_seconds'),
            'intent_cache_ttl_hours' => $request->input('intent_cache_ttl_hours'),
        ], auth()->id());

        AuditLog::record(
            'ai.settings_updated',
            'ai_settings',
            '1',
            json_encode([
                'ai_enabled' => $before['ai_enabled'],
                'openai_enabled' => $before['openai_enabled'],
                'daily_request_cap' => $before['daily_request_cap'],
                'monthly_request_cap' => $before['monthly_request_cap'],
                'daily_budget_aud' => $before['daily_budget_aud'],
                'monthly_budget_aud' => $before['monthly_budget_aud'],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'ai_enabled' => $after['ai_enabled'],
                'openai_enabled' => $after['openai_enabled'],
                'daily_request_cap' => $after['daily_request_cap'],
                'monthly_request_cap' => $after['monthly_request_cap'],
                'daily_budget_aud' => $after['daily_budget_aud'],
                'monthly_budget_aud' => $after['monthly_budget_aud'],
            ], JSON_THROW_ON_ERROR)
        );

        if ($request->input('assist_ai_search') !== null) {
            FeatureFlag::set(AiSearchFeature::FLAG, $request->input('assist_ai_search') === '1');
            AuditLog::record(
                'ai.ask_flag_updated',
                'feature_flags',
                AiSearchFeature::FLAG,
                null,
                $request->input('assist_ai_search') === '1' ? '1' : '0'
            );
        }

        if ($request->input('assist_ai_datasets_present') !== null) {
            FeatureFlag::set(DatasetSearchFeature::FLAG, $request->input('assist_ai_datasets') === '1');
            AuditLog::record(
                'ai.datasets_flag_updated',
                'feature_flags',
                DatasetSearchFeature::FLAG,
                null,
                $request->input('assist_ai_datasets') === '1' ? '1' : '0'
            );
        }

        if ($request->input('assist_ai_traveller_facilities_present') !== null) {
            FeatureFlag::set(TravellerFacilitiesFeature::FLAG, $request->input('assist_ai_traveller_facilities') === '1');
            AuditLog::record(
                'ai.traveller_facilities_flag_updated',
                'feature_flags',
                TravellerFacilitiesFeature::FLAG,
                null,
                $request->input('assist_ai_traveller_facilities') === '1' ? '1' : '0'
            );
        }

        return $this->redirectWith('/admin/ai-search', 'success', 'Assist AI settings saved. Paid AI stays off unless explicitly enabled with caps.');
    }

    public function gaps(Request $request): Response
    {
        $this->requireGapsAccess();
        $status = (string) $request->input('status', KnowledgeGapService::STATUS_OPEN);
        $allowed = ['all', KnowledgeGapService::STATUS_OPEN, KnowledgeGapService::STATUS_RESEARCHING, KnowledgeGapService::STATUS_RESOLVED, KnowledgeGapService::STATUS_WONT_FIX];
        if (!in_array($status, $allowed, true)) {
            $status = KnowledgeGapService::STATUS_OPEN;
        }
        $brand = current_brand();
        $rows = (new KnowledgeGapService())->listForAdmin($brand->id(), $status, 150);

        return $this->view('admin.ai-search.gaps', [
            'title' => 'Knowledge gaps',
            'rows' => $rows,
            'status' => $status,
            'brand' => $brand,
            'canManage' => can('settings.manage'),
        ]);
    }

    public function updateGap(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $id = (int) $request->input('gap_id', 0);
        $status = (string) $request->input('resolution_status', KnowledgeGapService::STATUS_OPEN);
        $job = trim((string) $request->input('assigned_research_job', ''));
        $notes = trim((string) $request->input('resolution_notes', ''));
        $ok = (new KnowledgeGapService())->updateStatus($id, $status, $job !== '' ? $job : null, $notes !== '' ? $notes : null, auth()->id());
        if ($ok) {
            AuditLog::record('ai.knowledge_gap_updated', 'knowledge_gap', (string) $id, null, $status);
            return $this->redirectWith('/admin/ai-search/gaps', 'success', 'Knowledge gap updated.');
        }
        return $this->redirectWith('/admin/ai-search/gaps', 'error', 'Could not update that knowledge gap.');
    }

    public function exportGaps(Request $request): Response
    {
        if (!can('demand.export') && !can('settings.manage')) {
            $this->abort(403, 'You do not have permission to perform this action.');
        }
        $brand = current_brand();
        $status = (string) $request->input('status', 'all');
        $format = strtolower(trim((string) $request->input('format', 'csv')));
        $svc = new KnowledgeGapService();
        $rows = $svc->listForAdmin($brand->id(), $status === '' ? 'all' : $status, 500);

        if ($format === 'json' || $format === 'search-gaps') {
            $payload = $svc->searchGapCollection($brand->id(), $status === '' ? 'all' : $status, 500);
            AuditLog::record('ai.knowledge_gaps_exported_json', 'knowledge_gap', $brand->id(), null, $status);
            return $this->json($payload);
        }

        $csvRows = [];
        foreach ($rows as $row) {
            $csvRows[] = [
                $row['id'] ?? '',
                $row['brand_key'] ?? '',
                $row['normalised_query'] ?? '',
                $row['original_query_sample'] ?? '',
                $row['intent_type'] ?? '',
                $row['result_quality'] ?? '',
                $row['search_count'] ?? 0,
                $row['zero_result_count'] ?? 0,
                $row['weak_result_count'] ?? 0,
                $row['priority_score'] ?? 0,
                $row['safety_relevant'] ?? 0,
                $row['resolution_status'] ?? '',
                $row['assigned_research_job'] ?? '',
                $row['first_seen_at'] ?? '',
                $row['last_seen_at'] ?? '',
            ];
        }

        AuditLog::record('ai.knowledge_gaps_exported', 'knowledge_gap', $brand->id(), null, $status);

        return CsvExport::download(
            'knowledge-gaps-' . $brand->id() . '-' . date('Ymd') . '.csv',
            [
                'id', 'brand_key', 'normalised_query', 'original_query_sample', 'intent_type',
                'result_quality', 'search_count', 'zero_result_count', 'weak_result_count',
                'priority_score', 'safety_relevant', 'resolution_status', 'assigned_research_job',
                'first_seen_at', 'last_seen_at',
            ],
            $csvRows
        );
    }

    private function requireGapsAccess(): void
    {
        if (!can('demand.view') && !can('settings.manage')) {
            $this->abort(403, 'You do not have permission to perform this action.');
        }
    }
}
