<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Installation wizard routes. The Kernel only allows these to run while no
 * install lock exists; once installed they redirect away.
 */
return static function (Router $router): void {
    $router->group(['middleware' => ['headers', 'csrf']], static function (Router $router): void {
        $router->get('/install', 'Install\InstallController@welcome', 'install');
        $router->get('/install/setup', 'Install\InstallController@setupForm', 'install.setup');
        $router->post('/install', 'Install\InstallController@install');
        $router->get('/install/complete', 'Install\InstallController@complete', 'install.complete');
    });
};
