<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use Throwable;

final class AdvertisingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $rows = Database::tableExists('advertising_campaigns') ? Database::select(
            "SELECT c.*, a.business_name, b.name AS brand_name, cr.headline, cr.status AS creative_status, "
            . "SUM(e.event_type='impression') AS impressions, SUM(e.event_type='click') AS clicks "
            . "FROM advertising_campaigns c INNER JOIN advertisers a ON a.id=c.advertiser_id INNER JOIN brands b ON b.id=c.brand_id "
            . "LEFT JOIN advertising_creatives cr ON cr.campaign_id=c.id LEFT JOIN advertising_events e ON e.campaign_id=c.id "
            . "WHERE c.deleted_at IS NULL GROUP BY c.id, cr.id ORDER BY c.id DESC LIMIT 200"
        ) : [];
        return $this->view('admin.advertising.index', ['title'=>'Advertising campaigns','rows'=>$rows,'schemaReady'=>Database::tableExists('advertising_campaigns')]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id=(int)$request->query('id',0); $campaign=null;
        if($id>0){$campaign=Database::selectOne("SELECT c.*,a.business_name,a.contact_email,cr.id AS creative_id,cr.headline,cr.body_text,cr.call_to_action,cr.status AS creative_status FROM advertising_campaigns c INNER JOIN advertisers a ON a.id=c.advertiser_id LEFT JOIN advertising_creatives cr ON cr.campaign_id=c.id WHERE c.id=? AND c.deleted_at IS NULL LIMIT 1",[$id]);if($campaign===null)$this->abort(404);}
        return $this->view('admin.advertising.form',['title'=>$id?'Edit campaign':'New campaign','campaign'=>$campaign,'brands'=>Database::select('SELECT id,name FROM brands ORDER BY id')]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id=(int)$request->input('id',0); $business=trim((string)$request->input('business_name')); $name=trim((string)$request->input('name')); $headline=trim((string)$request->input('headline')); $body=trim((string)$request->input('body_text')); $destination=trim((string)$request->input('destination_url')); $brandId=(int)$request->input('brand_id');
        if($business===''||$name===''||$headline===''||$body===''||!in_array($brandId,[1,2,3],true)||filter_var($destination,FILTER_VALIDATE_URL)===false||parse_url($destination,PHP_URL_SCHEME)!=='https')return $this->redirectWith($id?'/admin/advertising/edit?id='.$id:'/admin/advertising/new','error','Business, campaign, creative copy, brand and a valid HTTPS destination are required.');
        $pdo=Database::connection();
        try{$pdo->beginTransaction();
            if($id>0){$current=Database::selectOne('SELECT advertiser_id FROM advertising_campaigns WHERE id=?',[$id]);if($current===null)$this->abort(404);$advertiserId=(int)$current['advertiser_id'];Database::query('UPDATE advertisers SET business_name=?,contact_email=?,status=?,updated_at=NOW() WHERE id=?',[$business,trim((string)$request->input('contact_email'))?:null,'active',$advertiserId]);Database::query('UPDATE advertising_campaigns SET brand_id=?,name=?,context_key=?,status=?,starts_at=?,ends_at=?,destination_url=?,sponsorship_label=?,priority=?,updated_at=NOW() WHERE id=?',[(int)$request->input('brand_id'),trim((string)$request->input('name')),$this->context($request),$this->statusValue($request),$this->date($request,'starts_at'),$this->date($request,'ends_at'),$destination,trim((string)$request->input('sponsorship_label','Sponsored'))?:'Sponsored',(int)$request->input('priority',0),$id]);Database::query('UPDATE advertising_creatives SET headline=?,body_text=?,call_to_action=?,status=?,updated_at=NOW() WHERE campaign_id=?',[$headline,trim((string)$request->input('body_text')),trim((string)$request->input('call_to_action'))?:'Learn more','approved',$id]);}
            if($id===0){Database::query('INSERT INTO advertisers (business_name,contact_email,status,created_at) VALUES (?,?,\'active\',NOW())',[$business,trim((string)$request->input('contact_email'))?:null]);$advertiserId=(int)$pdo->lastInsertId();Database::query("INSERT INTO advertising_campaigns (advertiser_id,brand_id,name,placement,context_key,status,starts_at,ends_at,destination_url,sponsorship_label,priority,created_at) VALUES (?,?,?,'towwise_result',?,?,?,?,?,?,?,NOW())",[$advertiserId,(int)$request->input('brand_id'),trim((string)$request->input('name')),$this->context($request),$this->statusValue($request),$this->date($request,'starts_at'),$this->date($request,'ends_at'),$destination,trim((string)$request->input('sponsorship_label','Sponsored'))?:'Sponsored',(int)$request->input('priority',0)]);$id=(int)$pdo->lastInsertId();Database::query("INSERT INTO advertising_creatives (campaign_id,headline,body_text,call_to_action,status,created_at) VALUES (?,?,?,?, 'approved',NOW())",[$id,$headline,trim((string)$request->input('body_text')),trim((string)$request->input('call_to_action'))?:'Learn more']);}
            $pdo->commit();
        }catch(Throwable){if($pdo->inTransaction())$pdo->rollBack();return $this->redirectWith($id?'/admin/advertising/edit?id='.$id:'/admin/advertising/new','error','Campaign could not be saved. Check the dates and try again.');}
        AuditLog::record('advertising.campaign_saved','advertising_campaign',(string)$id);return $this->redirectWith('/admin/advertising','success','Campaign saved.');
    }

    public function status(Request $request): Response
    {
        $this->requirePermission('providers.manage');$id=(int)$request->input('id');$status=$this->statusValue($request);Database::query('UPDATE advertising_campaigns SET status=?,updated_at=NOW() WHERE id=?',[$status,$id]);AuditLog::record('advertising.status','advertising_campaign',(string)$id,null,$status);return $this->redirectWith('/admin/advertising','success','Campaign status updated.');
    }

    private function context(Request $r): string { $v=(string)$r->input('context_key','general');return in_array($v,['general','mobile_weighing','suspension','towing_equipment'],true)?$v:'general'; }
    private function statusValue(Request $r): string { $v=(string)$r->input('status','draft');return in_array($v,['draft','pending','active','paused','completed','rejected','archived'],true)?$v:'draft'; }
    private function date(Request $r,string $key): ?string { $v=trim((string)$r->input($key));return $v===''?null:str_replace('T',' ',substr($v,0,16)).':00'; }
}
