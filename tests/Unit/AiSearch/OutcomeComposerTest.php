<?php
declare(strict_types=1);
namespace Tests\Unit\AiSearch;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Dto\SearchResponse;
use App\Platform\AiSearch\Outcome\OutcomeComposer;
use PHPUnit\Framework\TestCase;
final class OutcomeComposerTest extends TestCase
{
    public function testItExplainsFactsWithoutChangingResults(): void
    {
        $provider = ['id'=>7,'service_model'=>'mobile','is_verified'=>1,'distance_km'=>18.2,'distance_metric'=>'road','drive_time_seconds'=>1320,'assist_provenance_label'=>'VanAssist listing'];
        $response = $this->response([$provider]);
        $outcome = (new OutcomeComposer())->compose($response);
        self::assertSame('Auto electrician', $outcome['understood']['need']);
        self::assertSame('Gympie, QLD', $outcome['understood']['location']);
        self::assertSame('Road distance', $outcome['distance']['label']);
        self::assertStringContainsString('mobile service', implode(' ', $outcome['result_reasons']['provider-7']));
        self::assertStringContainsString('18 km by road', implode(' ', $outcome['result_reasons']['provider-7']));
        self::assertSame([$provider], $response->providers);
    }
    public function testItLabelsFallbackDistanceHonestly(): void
    {
        $outcome = (new OutcomeComposer())->compose($this->response([['id'=>8,'is_inferred'=>1,'is_unclaimed'=>1,'distance_km'=>9.4]]));
        self::assertSame('Straight-line estimate', $outcome['distance']['label']);
        self::assertStringContainsString('confirm the service', $outcome['result_reasons']['provider-8'][0]);
    }
    public function testItProvidesANoResultAction(): void
    {
        $outcome = (new OutcomeComposer())->compose($this->response([]));
        self::assertSame('No suitable local result yet', $outcome['next_action']['heading']);
    }
    /** @param list<array<string,mixed>> $providers */
    private function response(array $providers): SearchResponse
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['auto_electrician'], [], [], 'Gympie', false, 50, 'normal', ['providers'], .95, false, null);
        return new SearchResponse($intent, $providers, [], ['name'=>'Gympie','state_abbr'=>'QLD'], -26.19, 152.67, '', [], 10, true);
    }
}
