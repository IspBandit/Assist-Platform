<?php

declare(strict_types=1);

namespace App\Models\Polaris;

use App\Models\Model;

final class RvVariant extends Model
{
    protected static string $table = 'polaris_rv_variants';
    protected static bool $softDeletes = true;
}
