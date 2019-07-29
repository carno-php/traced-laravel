<?php
/**
 * Provider
 * User: moyo
 * Date: Jul 23, 2019
 * Time: 16:38
 */

namespace Carno\Laravel\Tracing;

use Carno\Console\App;
use Carno\Laravel\Tracing\Middleware\WebTracing;
use Carno\Traced\Components\Platforms;
use Illuminate\Support\ServiceProvider;

class Provider extends ServiceProvider
{
    /**
     * middleware alias
     */
    const MW_KEY = 'web:tracing';

    /**
     */
    public function boot()
    {
        if (is_null($endpoint = config('devops.tracing.addr'))) {
            return;
        }

        // init webTracing

        $this->app->singleton(WebTracing::class, static function ($app) {
            return new WebTracing($app['config']->get('devops.tracking.named', 'laravel-app'));
        });

        // middleware injects

        $router = $this->app->get('router');

        $router->aliasMiddleware(self::MW_KEY, WebTracing::class);

        foreach ($router->getMiddlewareGroups() as $group => $mws) {
            $router->pushMiddlewareToGroup($group, WebTracing::class);
        }

        // platform initializing

        $platform = new Platforms();

        if ($platform->runnable()) {
            $app = new App();
            $platform->starting($app);
            $app->starting()->perform();
            $app->conf()->set('tracing.addr', $endpoint);
        }
    }
}
