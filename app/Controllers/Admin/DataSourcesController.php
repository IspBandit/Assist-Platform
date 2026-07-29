<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\DataSourceService;
use App\Services\NationalRouteImportService;
use Throwable;

final class DataSourcesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.view');
        $service=new DataSourceService();$brandId=current_brand()->databaseId();
        $intelligenceTask=(new \App\Services\DataIntelligenceService())->task((int)$request->input('intelligence_task'),$brandId);
        return $this->view('admin.data-sources.index',['title'=>'Data sources','connectors'=>$service->connectors(),'mappings'=>$service->mappings($brandId),'coverage'=>$service->coverage($brandId),'jobs'=>$service->jobs($brandId),'schedules'=>$service->schedules($brandId),'intelligenceTask'=>$intelligenceTask]);
    }
    public function queue(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        $brandId = current_brand()->databaseId();
        $filters = [
            'status' => (string)$request->query('status', 'pending'),
            'state' => (string)$request->query('state', ''),
            'category' => (int)$request->query('category', 0),
            'evidence' => (string)$request->query('evidence', ''),
            'duplicate' => (string)$request->query('duplicate', ''),
            'contact' => (string)$request->query('contact', ''),
            'route' => (string)$request->query('route', ''),
            'search' => (string)$request->query('q', ''),
            'page' => (int)$request->query('page', 1),
            'per_page' => 50,
        ];
        $service = new DataSourceService();
        $queue = $service->reviewQueue($brandId, $filters);
        $jobId = (int)$request->query('import_job', 0);
        return $this->view('admin.data-sources.queue', [
            'title' => 'Import review queue',
            'candidates' => $queue['rows'],
            'total' => $queue['total'],
            'page' => $queue['page'],
            'perPage' => $queue['perPage'],
            'summary' => $queue['summary'],
            'filters' => $filters,
            'categories' => $service->reviewCategories($brandId),
            'nationalImportJob' => $jobId > 0 ? (new NationalRouteImportService())->jobStatus($jobId, $brandId) : null,
            'isVanAssist' => current_brand()->id() === 'vanassist',
        ]);
    }
    public function saveConnector(Request $r): Response { $this->requirePlatformAdmin('data_sources.manage');try{(new DataSourceService())->saveConnector((int)$r->input('connector_id'),(string)$r->input('api_key'),(int)$r->input('daily_request_limit'),(float)$r->input('daily_budget_aud'),$r->input('active')==='1',(int)auth()->id());return $this->redirectWith('/admin/data-sources','success','Connector settings saved securely.');}catch(Throwable $e){return $this->redirectWith('/admin/data-sources','error',$e->getMessage());} }
    public function saveMapping(Request $r): Response { $this->requirePlatformAdmin('data_sources.manage');try{(new DataSourceService())->saveMapping((int)$r->input('connector_id'),current_brand()->databaseId(),(int)$r->input('category_id'),(string)$r->input('external_query'),$r->input('active')==='1');return $this->redirectWith('/admin/data-sources','success','Category mapping saved.');}catch(Throwable $e){return $this->redirectWith('/admin/data-sources','error',$e->getMessage());} }
    public function run(Request $r): Response { $this->requirePlatformAdmin('data_sources.run');try{$brandId=current_brand()->databaseId();$result=(new DataSourceService())->run((int)$r->input('connector_id'),$brandId,(int)$r->input('mapping_id'),(string)$r->input('location'),(int)auth()->id());$taskId=(int)$r->input('intelligence_task');if($taskId>0){(new \App\Services\DataIntelligenceService())->updateTask($taskId,$brandId,'in_progress',(int)auth()->id());}return $this->redirectWith('/admin/data-sources/review','success',$result['new'].' new candidates queued for review.');}catch(Throwable $e){return $this->redirectWith('/admin/data-sources','error',$e->getMessage());} }
    public function review(Request $r): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        $returnTo = $this->reviewReturnTo((string)$r->input('return_to', ''));
        try {
            $decision = (string)$r->input('decision');
            $id = (new DataSourceService())->review(
                (int)$r->input('candidate_id'),
                current_brand()->databaseId(),
                $decision,
                (int)$r->input('provider_id') ?: null,
                (int)auth()->id(),
                $r->input('retention_confirmed') === '1',
                (int)$r->input('category_id') ?: null,
                (string)$r->input('evidence_url', ''),
                (string)$r->input('review_notes', '')
            );
            $message = $id > 0
                ? 'Candidate processed and linked to provider #' . $id . '.'
                : match ($decision) {
                    'confirm' => 'Evidence and service confirmed. This candidate is now eligible for controlled bulk approval.',
                    'hold' => 'Candidate placed on hold.',
                    'restore' => 'Candidate returned to the pending queue.',
                    default => 'Candidate rejected.',
                };
            return $this->redirectWith($returnTo, 'success', $message);
        } catch (Throwable $e) {
            return $this->redirectWith($returnTo, 'error', $e->getMessage());
        }
    }

    public function uploadNationalRoute(Request $r): Response
    {
        $this->requirePlatformAdmin('data_sources.run');
        if (current_brand()->id() !== 'vanassist') {
            return $this->redirectWith('/admin/data-sources/review', 'error', 'Switch to the VanAssist workspace before importing this route dataset.');
        }
        try {
            $jobId = (new NationalRouteImportService())->stageUpload(
                $r->file('discovery_file') ?? [], current_brand()->databaseId(), (int)auth()->id()
            );
            return $this->redirectWith('/admin/data-sources/review?import_job=' . $jobId, 'success', 'National route file staged. Safe batch processing has started.');
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/data-sources/review', 'error', $e->getMessage());
        }
    }

    public function processNationalRoute(Request $r): Response
    {
        $this->requirePlatformAdmin('data_sources.run');
        $jobId = (int)$r->input('job_id');
        try {
            $result = (new NationalRouteImportService())->processJob($jobId, current_brand()->databaseId(), 500);
            $message = $result['done']
                ? number_format($result['total_processed']) . ' discovery rows screened. The review queue is ready.'
                : number_format($result['total_processed']) . ' rows screened so far; continuing safely.';
            return $this->redirectWith('/admin/data-sources/review?import_job=' . $jobId, 'success', $message);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/data-sources/review?import_job=' . $jobId, 'error', $e->getMessage());
        }
    }

    public function bulkReview(Request $r): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        $returnTo = $this->reviewReturnTo((string)$r->input('return_to', ''));
        try {
            $ids = $r->input('candidate_ids', []);
            $decision = (string)$r->input('bulk_decision');
            $result = (new DataSourceService())->bulkReview(
                is_array($ids) ? $ids : [], $decision, current_brand()->databaseId(), (int)auth()->id(),
                $r->input('bulk_confirmed') === '1'
            );
            $label = match ($decision) {
                'approve_eligible' => 'eligible listings approved',
                'merge_exact_duplicates' => 'exact duplicates merged',
                default => 'candidates updated',
            };
            $message = $result['processed'] . ' ' . $label . '.';
            if ($result['skipped'] > 0) {
                $message .= ' ' . $result['skipped'] . ' safely skipped because they were ineligible or had changed.';
            }
            return $this->redirectWith($returnTo, 'success', $message);
        } catch (Throwable $e) {
            return $this->redirectWith($returnTo, 'error', $e->getMessage());
        }
    }
    public function resolveExactDuplicates(Request $r): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        try {
            $result = (new DataSourceService())->resolveExactDuplicates(current_brand()->databaseId(), (int)auth()->id());
            $message = $result['processed'] . ' exact duplicates automatically linked without changing provider details.';
            if ($result['remaining'] > 0) $message .= ' ' . $result['remaining'] . ' remain for another safe batch.';
            return $this->redirectWith('/admin/data-sources/review?duplicate=yes', 'success', $message);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/data-sources/review?duplicate=yes', 'error', $e->getMessage());
        }
    }
    public function saveSchedule(Request $r): Response { $this->requirePlatformAdmin('data_sources.manage');try{(new DataSourceService())->saveSchedule((int)$r->input('connector_id'),current_brand()->databaseId(),(int)$r->input('mapping_id'),(string)$r->input('name'),(string)$r->input('location'),(string)$r->input('frequency'),$r->input('enabled')==='1',(int)auth()->id());return $this->redirectWith('/admin/data-sources','success','Import schedule saved.');}catch(Throwable $e){return $this->redirectWith('/admin/data-sources','error',$e->getMessage());} }
    private function requirePlatformAdmin(string $permission): void { $this->requirePermission($permission);if(!auth()->isSuperAdmin()&&!auth()->hasAnyRole('administrator','platform-administrator')){$this->abort(403);} }

    private function reviewReturnTo(string $value): string
    {
        return str_starts_with($value, '/admin/data-sources/review') ? $value : '/admin/data-sources/review';
    }
}
