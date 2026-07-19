<?php
/** @var array<string,mixed> $promo */
/** @var string $alt */
/** @var string $class */
$class = $class ?? 'provider-promo-ad';
$alt = $alt ?? (string) ($promo['headline'] ?? $promo['business_name'] ?? 'Provider promotion');
echo \App\Services\FoundingGraphicService::responsivePicture($promo, $alt, $class);
