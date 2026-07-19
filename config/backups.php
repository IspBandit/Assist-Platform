<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'retention' => [
        'daily'   => (int) Env::get('BACKUP_RETENTION_DAILY', 7),
        'weekly'  => (int) Env::get('BACKUP_RETENTION_WEEKLY', 4),
        'monthly' => (int) Env::get('BACKUP_RETENTION_MONTHLY', 3),
    ],
    'path' => 'storage/backups',
];
