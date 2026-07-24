<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Platform\DataIntelligence\OpportunityScorer;
use App\Platform\DataIntelligence\SourceRegistry;
use App\Platform\DataIntelligence\Sources\ProviderCoverageSource;
use RuntimeException;

final class DataIntelligenceService
{
    private SourceRegistry $sources;
    public function __construct(?SourceRegistry $sources = null)
    {
        $this->sources = $sources ?? new SourceRegistry();
        if ($sources === null) { $this->sources->register(new ProviderCoverageSource()); }
    }

    /** @return array<string,mixed> */
    public function dashboard(int $brandId, array $filters = []): array
    {
        $rows = $this->sources->get((string) ($filters['source'] ?? 'provider_coverage'))->coverage($brandId, $filters);
        $opportunities = [];
        foreach ($rows as $row) {
            $row['demand'] = (int) ($row['zero_results'] ?? 0);
            $row['score'] = OpportunityScorer::score($row);
            $row['priority'] = OpportunityScorer::priority((float) $row['score']);
            $row['providers_per_10000'] = (int) $row['population'] > 0
                ? round(((int) $row['providers'] / (int) $row['population']) * 10000, 2) : null;
            $row['verification_rate'] = (int) $row['providers'] > 0
                ? round(((int) $row['verified'] / (int) $row['providers']) * 100, 1) : null;
            $opportunities[] = $row;
        }
        usort($opportunities, static fn(array $a,array $b): int => $b['score'] <=> $a['score']);

        $totals = Database::selectOne('SELECT COUNT(DISTINCT l.id) providers,COUNT(DISTINCT CASE WHEN l.is_verified=1 THEN l.id END) verified FROM provider_brand_listings l WHERE l.brand_id=? AND l.status=\'active\' AND l.search_visible=1 AND l.deleted_at IS NULL', [$brandId]) ?? ['providers'=>0,'verified'=>0];
        $quality = Database::selectOne('SELECT COUNT(*) total,SUM(review_status=\'pending\') pending,SUM(review_status=\'approved\') approved,SUM(review_status=\'merged\') merged,SUM(review_status=\'rejected\') rejected,SUM(duplicate_provider_id IS NOT NULL) possible_duplicates FROM data_source_import_candidates WHERE brand_id=?', [$brandId]) ?? [];
        $tasks = Database::select('SELECT i.*,c.name category,t.name town,s.abbreviation state FROM data_intelligence_tasks i LEFT JOIN brand_provider_categories c ON c.id=i.category_id LEFT JOIN towns t ON t.id=i.town_id LEFT JOIN states s ON s.id=i.state_id WHERE i.brand_id=? AND i.status IN (\'open\',\'in_progress\') ORDER BY FIELD(i.priority,\'critical\',\'high\',\'medium\',\'low\'),i.opportunity_score DESC LIMIT 100', [$brandId]);
        $states = Database::select('SELECT id,name,abbreviation FROM states WHERE is_active=1 ORDER BY name');
        $categories = Database::select('SELECT id,name FROM brand_provider_categories WHERE brand_id=? AND is_active=1 ORDER BY sort_order,name', [$brandId]);

        return ['opportunities'=>$opportunities,'summary'=>[
            'providers'=>(int)($totals['providers']??0),'verified'=>(int)($totals['verified']??0),
            'verification_rate'=>(int)($totals['providers']??0)>0?round(((int)$totals['verified']/(int)$totals['providers'])*100,1):0,
            'critical'=>count(array_filter($opportunities,static fn($r)=>$r['priority']==='critical')),
            'population_coverage'=>count(array_filter($opportunities,static fn($r)=>(int)$r['population']>0)),
        ],'quality'=>$quality,'tasks'=>$tasks,'states'=>$states,'categories'=>$categories];
    }

    public function createTask(int $brandId,int $categoryId,int $townId,float $score,int $userId): int
    {
        $row=Database::selectOne('SELECT t.id,t.name,t.state_id,t.region_id,c.name category FROM towns t JOIN brand_provider_categories c ON c.id=? AND c.brand_id=? WHERE t.id=?',[$categoryId,$brandId,$townId]);
        if($row===null){throw new RuntimeException('The selected opportunity is no longer available.');}
        $priority=OpportunityScorer::priority($score);
        $title='Fill '.$row['category'].' coverage in '.$row['name'];
        $id=Database::insert('INSERT INTO data_intelligence_tasks (brand_id,category_id,state_id,region_id,town_id,task_type,title,rationale,opportunity_score,priority,status,source_key,context_json,created_by,created_at) VALUES (?,?,?,?,?,\'coverage_import\',?,?,?, ?,\'open\',\'provider_coverage\',?, ?,NOW())',[$brandId,$categoryId,$row['state_id'],$row['region_id'],$townId,$title,'Provider supply is low relative to available demand and population signals.',$score,$priority,json_encode(['location'=>$row['name']],JSON_THROW_ON_ERROR),$userId]);
        AuditLog::record('data_intelligence.task_created','data_intelligence_task',(string)$id,null,json_encode(['score'=>$score,'priority'=>$priority]));
        return $id;
    }

    public function task(int $id,int $brandId): ?array { return Database::selectOne('SELECT i.*,t.name town,c.name category,m.id mapping_id,m.connector_id FROM data_intelligence_tasks i LEFT JOIN towns t ON t.id=i.town_id LEFT JOIN brand_provider_categories c ON c.id=i.category_id LEFT JOIN data_source_category_mappings m ON m.brand_id=i.brand_id AND m.category_id=i.category_id AND m.is_active=1 WHERE i.id=? AND i.brand_id=?',[$id,$brandId]); }
    public function updateTask(int $id,int $brandId,string $status,int $userId): void
    {
        if(!in_array($status,['open','in_progress','completed','dismissed'],true)){throw new RuntimeException('Invalid task status.');}
        Database::query('UPDATE data_intelligence_tasks SET status=?,assigned_to=COALESCE(assigned_to,?),completed_at=IF(?=\'completed\',NOW(),NULL),updated_at=NOW() WHERE id=? AND brand_id=?',[$status,$userId,$status,$id,$brandId]);
        AuditLog::record('data_intelligence.task_updated','data_intelligence_task',(string)$id,null,$status);
    }
}
