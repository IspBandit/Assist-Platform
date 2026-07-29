<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DataSourceService;
use App\Services\NationalRouteImportService;
use App\Services\ProviderImportQueueWorker;
use Throwable;

final class DataSourcesController extends Controller
{
    private const ELIGIBLE_QUEUE_RUN = 'data_source_eligible_queue_run';

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
        $runToken = (string)$request->query('eligible_run', '');
        $runState = Session::get(self::ELIGIBLE_QUEUE_RUN, []);
        $eligibleQueueRun = is_array($runState)
            && $runToken !== ''
            && hash_equals((string)($runState['token'] ?? ''), $runToken)
            && (int)($runState['brand_id'] ?? 0) === $brandId
            && (int)($runState['user_id'] ?? 0) === (int)auth()->id();
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
            'eligibleQueueRun' => $eligibleQueueRun ? $runState : null,
            'serverQueue' => $service->eligibleQueueSummary($brandId, []),
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
                'merge_exact_duplicates' => 'strong duplicates merged',
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

    public function processServerQueue(Request $r): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        if (current_brand()->id() !== 'vanassist') {
            return $this->redirectWith('/admin/data-sources/review','error','Switch to the VanAssist workspace before processing the provider import queue.');
        }
        try {
            $result = (new ProviderImportQueueWorker())->run(30.0);
            $message = number_format((int)$result['providers_published']) . ' eligible providers published and '
                . number_format((int)$result['duplicates_merged']) . ' safe duplicates merged in this server pass. '
                . number_format((int)$result['eligible_remaining']) . ' eligible records remain; '
                . number_format((int)$result['review_required']) . ' require independent evidence or another manual decision.';
            return $this->redirectWith('/admin/data-sources/review','success',$message);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/data-sources/review','error','Server queue processor stopped safely: ' . $e->getMessage());
        }
    }

    public function processEligibleQueue(Request $r): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        if (current_brand()->id() !== 'vanassist') {
            return $this->redirectWith('/admin/data-sources/review','error','Switch to the VanAssist workspace before processing the eligible queue.');
        }
        $brandId = current_brand()->databaseId();
        $token = (string)$r->input('run_token', '');
        $state = Session::get(self::ELIGIBLE_QUEUE_RUN, []);
        $continuing = is_array($state) && $token !== '' && hash_equals((string)($state['token'] ?? ''), $token) && (int)($state['brand_id'] ?? 0) === $brandId && (int)($state['user_id'] ?? 0) === (int)auth()->id();
        if (!$continuing) {
            if ($r->input('confirmed') !== '1') {
                return $this->redirectWith('/admin/data-sources/review','error','Confirm the safeguarded eligible-queue operation before starting.');
            }
            $state = [
                'token'=>bin2hex(random_bytes(16)), 'brand_id'=>$brandId, 'user_id'=>(int)auth()->id(),
                'filters'=>$this->eligibleQueueFilters($r),
                'merged'=>0, 'approved'=>0, 'failed'=>0, 'batches'=>0,
            ];
        }
        try {
            $result = (new DataSourceService())->processEligibleQueue((array)$state['filters'],$brandId,(int)auth()->id());
            $state['merged'] = (int)$state['merged'] + $result['merged'];
            $state['approved'] = (int)$state['approved'] + $result['approved'];
            $state['failed'] = (int)$state['failed'] + $result['failed'];
            $state['batches'] = (int)$state['batches'] + 1;
            $state['remaining'] = $result['remaining'];
            $state['blocked'] = $result['blocked'];
            $state['reasons'] = $result['reasons'];
            $canContinue = $result['remaining'] > 0 && $result['processed'] > 0;
            if ($canContinue) {
                Session::set(self::ELIGIBLE_QUEUE_RUN, $state);
                return $this->redirect('/admin/data-sources/review?' . http_build_query($this->eligibleQueueQuery((array)$state['filters']) + ['eligible_run'=>$state['token']]));
            }
            Session::forget(self::ELIGIBLE_QUEUE_RUN);
            $message = number_format((int)$state['merged']) . ' safe duplicates merged and ' . number_format((int)$state['approved']) . ' eligible providers published across ' . number_format((int)$state['batches']) . ' bounded batches.';
            $message .= ' ' . number_format($result['skipped']) . ' safely skipped in the final queue state; ' . number_format($result['remaining']) . ' eligible and ' . number_format($result['blocked']) . ' ineligible filtered records remain.';
            if ($result['reasons'] !== []) {
                $reasonParts = [];
                foreach (array_slice($result['reasons'],0,5,true) as $reason=>$count) $reasonParts[] = $count . ' ' . $reason;
                $message .= ' Safely skipped: ' . implode('; ', $reasonParts) . '.';
            }
            return $this->redirectWith('/admin/data-sources/review?' . http_build_query($this->eligibleQueueQuery((array)$state['filters'])),$result['remaining'] > 0 ? 'error' : 'success',$message);
        } catch (Throwable $e) {
            Session::set(self::ELIGIBLE_QUEUE_RUN, $state);
            return $this->redirectWith('/admin/data-sources/review?' . http_build_query($this->eligibleQueueQuery((array)$state['filters'])),'error','Eligible-queue processing paused safely: ' . $e->getMessage() . ' Start it again to resume remaining records.');
        }
    }
    public function resolveExactDuplicates(Request $r): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        try {
            $result = (new DataSourceService())->resolveExactDuplicates(current_brand()->databaseId(), (int)auth()->id());
            $message = $result['processed'] . ' strong 70%+ duplicates automatically linked without changing provider details.';
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

    /** @return array<string,mixed> */
    private function eligibleQueueFilters(Request $request): array
    {
        return [
            'state'=>(string)$request->input('state',''), 'category'=>(int)$request->input('category',0),
            'evidence'=>(string)$request->input('evidence',''), 'duplicate'=>(string)$request->input('duplicate',''),
            'contact'=>(string)$request->input('contact',''), 'route'=>(string)$request->input('route',''),
            'search'=>(string)$request->input('q',''),
        ];
    }

    /** @return array<string,mixed> */
    private function eligibleQueueQuery(array $filters): array
    {
        $query = ['status'=>'pending'];
        foreach (['state','category','evidence','duplicate','contact','route'] as $key) {
            if (($filters[$key] ?? '') !== '' && ($filters[$key] ?? 0) !== 0) $query[$key] = $filters[$key];
        }
        if (trim((string)($filters['search'] ?? '')) !== '') $query['q'] = $filters['search'];
        return $query;
    }
}
