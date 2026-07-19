<?php

declare(strict_types=1);

/**
 * VanAssist front controller. The only PHP file that should be web-accessible
 * as an entry point. The cPanel subdomain document root must point here:
 *   /home/CPANEL_USERNAME/vanassist/public
 */

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

require BASE_PATH . '/bootstrap/autoload.php';

(new App\Core\Kernel())->run();
