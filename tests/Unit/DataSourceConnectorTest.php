<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\DataSources\Connectors\GooglePlacesConnector;
use App\Platform\DataSources\ConnectorRegistry;
use App\Platform\DataSources\DuplicateMatcher;
use App\Platform\DataSources\HttpClientInterface;
use PHPUnit\Framework\TestCase;

final class DataSourceConnectorTest extends TestCase
{
    public function testGoogleConnectorNormalisesResultsBehindGenericContract(): void
    {
        $http=new class implements HttpClientInterface {
            public array $headers=[]; public array $payload=[];
            public function postJson(string $url,array $headers,array $payload): array{$this->headers=$headers;$this->payload=$payload;return['status'=>200,'body'=>json_encode(['places'=>[['id'=>'place-1','displayName'=>['text'=>'Smith Auto'],'formattedAddress'=>'1 Main St, Roma QLD','location'=>['latitude'=>-26.5,'longitude'=>148.7],'nationalPhoneNumber'=>'07 0000 0000','websiteUri'=>'https://smith.example','types'=>['car_repair']]]])];}
        };
        $connector=new GooglePlacesConnector($http);
        $result=$connector->search(['query'=>'mechanic','location'=>'Roma QLD'],['api_key'=>'secret'],[]);
        self::assertSame('google_places',$connector->key());
        self::assertSame('place-1',$result[0]['external_id']);
        self::assertSame('Smith Auto',$result[0]['business_name']);
        self::assertStringContainsString('mechanic in Roma QLD, Australia',$http->payload['textQuery']);
        self::assertStringContainsString('places.id',$http->headers['X-Goog-FieldMask']);
    }

    public function testRegistryIsExtensibleWithoutCoreConditionals(): void
    {
        $connector=new GooglePlacesConnector(new class implements HttpClientInterface{public function postJson(string $url,array $headers,array $payload):array{return['status'=>200,'body'=>'{"places":[]}'];}});
        $registry=new ConnectorRegistry();$registry->register($connector);
        self::assertSame($connector,$registry->get('google_places'));
        self::assertSame('google_places',(new ConnectorRegistry())->resolve('google_places',GooglePlacesConnector::class)->key());
    }

    public function testDuplicateMatcherUsesIndependentSignals(): void
    {
        $match=(new DuplicateMatcher())->score(['business_name'=>'Smith Auto Repairs','phone'=>'07 1234 5678','website'=>'https://smith.example'],['business_name'=>'Smith Auto Repairs','phone'=>'0712345678','website'=>'https://smith.example/about']);
        self::assertSame(100,$match['score']);
        self::assertContains('same phone',$match['reasons']);
        self::assertContains('same website',$match['reasons']);
    }
}
