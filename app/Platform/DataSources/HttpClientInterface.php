<?php
declare(strict_types=1);

namespace App\Platform\DataSources;

interface HttpClientInterface
{
    /** @param array<string,string> $headers @return array{status:int,body:string} */
    public function postJson(string $url, array $headers, array $payload): array;
}

