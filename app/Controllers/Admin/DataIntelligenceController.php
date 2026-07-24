<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\DataIntelligenceService;
use Throwable;

final class DataIntelligenceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('data_intelligence.view');
        $brandId=current_brand()->databaseId();
        $filters=['state_id'=>(int)$request->input('state_id'),'category_id'=>(int)$request->input('category_id'),'source'=>'provider_coverage'];
        return $this->view('admin.data-intelligence.index',['title'=>'Data Intelligence','filters'=>$filters]+(new DataIntelligenceService())->dashboard($brandId,$filters));
    }
    public function createTask(Request $request): Response
    {
        $this->requirePermission('data_intelligence.manage');
        try{$service=new DataIntelligenceService();$id=$service->createTask(current_brand()->databaseId(),(int)$request->input('category_id'),(int)$request->input('town_id'),(float)$request->input('score'),(int)auth()->id());return $this->redirectWith('/admin/data-sources?intelligence_task='.$id,'success','Opportunity added to the import workflow.');}
        catch(Throwable $e){return $this->redirectWith('/admin/data-intelligence','error',$e->getMessage());}
    }
    public function updateTask(Request $request): Response
    {
        $this->requirePermission('data_intelligence.manage');
        try{(new DataIntelligenceService())->updateTask((int)$request->input('task_id'),current_brand()->databaseId(),(string)$request->input('status'),(int)auth()->id());return $this->redirectWith('/admin/data-intelligence','success','Task updated.');}
        catch(Throwable $e){return $this->redirectWith('/admin/data-intelligence','error',$e->getMessage());}
    }
}
