<?php

declare(strict_types=1);

namespace App\Models\Polaris;

use App\Models\Model;

final class Manufacturer extends Model
{
    protected static string $table = 'polaris_manufacturers';
    protected static bool $softDeletes = true;
}
