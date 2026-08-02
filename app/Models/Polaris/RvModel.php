<?php

declare(strict_types=1);

namespace App\Models\Polaris;

use App\Models\Model;

final class RvModel extends Model
{
    protected static string $table = 'polaris_rv_models';
    protected static bool $softDeletes = true;
}
