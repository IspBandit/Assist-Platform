<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\DataSourceService;
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
    public function queue(Request $request): Response { $this->requirePlatformAdmin('data_sources.review');$service=new DataSourceService();return $this->view('admin.data-sources.queue',['title'=>'Import review queue','candidates'=>$service->queue(current_brand()->databaseId())]); }
    public function saveConnector(Request $r): Response { $this->requirePlatformAdmin('data_sources.manage');try{(new DataSourceService())->saveConnector((int)$r->input('connector_id'),(string)$r->input('api_key'),(int)$r->input('daily_request_limit'),(float)$r->input('daily_budget_aud'),$r->input('active')==='1',(int)auth()->id());return $this->redirectWith('/admin/data-sources','success','Connector settings saved securely.');}catch(Throwable $e){return $this->redirectWith('/admin/data-sources','error',$e->getMessage());} }
    public function saveMapping(Request $r): Response { $this->requirePlatformAdmin('data_sources.manage');try{(new DataSourceService())->saveMapping((int)$r->input('connector_id'),current_brand()->databaseId(),(int)$r->input('category_id'),(string)$r->input('external_query'),$r->input('active')==='1');return $this->redirectWith('/admin/data-sources','success','Category mapping saved.');}catch(Throwable $e){return $this->redirectWith('/admin/data-sources','error',$e->getMessage());} }
    public function run(Request $r): Response { $this->requirePlatformAdmin('data_sources.run');try{$brandId=current_brand()->databaseId();$result=(new DataSourceService())->run((int)$r->input('connector_id'),$brandId,(int)$r->input('mapping_id'),(string)$r->input('location'),(int)auth()->id());$taskId=(int)$r->input('intelligence_task');if($taskId>0){(new \App\Services\DataIntelligenceService())->updateTask($taskId,$brandId,'in_progress',(int)auth()->id());}return $this->redirectWith('/admin/data-sources/review','success',$result['new'].' new candidates queued for review.');}catch(Throwable $e){return $this->redirectWith('/admin/data-sources','error',$e->getMessage());} }
    public function review(Request $r): Response { $this->requirePlatformAdmin('data_sources.review');try{$id=(new DataSourceService())->review((int)$r->input('candidate_id'),(string)$r->input('decision'),(int)$r->input('provider_id')?:null,(int)auth()->id(),$r->input('retention_confirmed')==='1');$message=$id>0?'Candidate processed and linked to provider #'.$id.'.':'Candidate rejected.';return $this->redirectWith('/admin/data-sources/review','success',$message);}catch(Throwable $e){return $this->redirectWith('/admin/data-sources/review','error',$e->getMessage());} }
    public function saveSchedule(Request $r): Response { $this->requirePlatformAdmin('data_sources.manage');try{(new DataSourceService())->saveSchedule((int)$r->input('connector_id'),current_brand()->databaseId(),(int)$r->input('mapping_id'),(string)$r->input('name'),(string)$r->input('location'),(string)$r->input('frequency'),$r->input('enabled')==='1',(int)auth()->id());return $this->redirectWith('/admin/data-sources','success','Import schedule saved.');}catch(Throwable $e){return $this->redirectWith('/admin/data-sources','error',$e->getMessage());} }
    private function requirePlatformAdmin(string $permission): void { $this->requirePermission($permission);if(!auth()->isSuperAdmin()&&!auth()->hasAnyRole('administrator','platform-administrator')){$this->abort(403);} }
}
