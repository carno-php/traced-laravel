<?php
/**
 * Provider
 * User: moyo
 * Date: Jul 23, 2019
 * Time: 16:38
 */

namespace Carno\Laravel\Tracing;

use Carno\Console\App;
use Carno\Laravel\Tracing\Middleware\SQLTracing;
use Carno\Laravel\Tracing\Middleware\WebTracing;
use Carno\Traced\Components\Platforms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class Provider extends ServiceProvider
{
    /**
     */
    public function boot()
    {
        // pre checks

        if (env('COMPOSER_BINARY')) {
            return;
        }

        if (is_null($endpoint = config('devops.tracing.addr'))) {
            return;
        }

        // platform initializing

        $platform = new Platforms();

        if ($platform->runnable()) {
            $app = new App();
            $platform->starting($app);
            $app->starting()->perform();
            $app->conf()->set('tracing.addr', $endpoint);
        }

        $named = config('devops.tracing.named', 'laravel-app');

        // web tracking

        $this->app->singleton(WebTracing::class, static function () use ($named) {
            return new WebTracing($named);
        });

        // sql tracking

        $this->app->singleton(SQLTracing::class, static function () use ($named) {
            return new SQLTracing($named);
        });

        // init components

        $this->routers();
        $this->database();
    }

    /**
     * middleware injects
     */
    private function routers()
    {
        $router = $this->app->get('router');

        $router->aliasMiddleware('web:tracing', WebTracing::class);

        foreach ($router->getMiddlewareGroups() as $group => $mws) {
            $router->pushMiddlewareToGroup($group, WebTracing::class);
        }
    }

    /**
     * database listen
     */
    private function database()
    {
        DB::listen($this->app->get(SQLTracing::class)->observer());
    }
}
