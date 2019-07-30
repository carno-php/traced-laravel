<?php
/**
 * SQL Tracing
 * User: moyo
 * Date: Jul 30, 2019
 * Time: 11:37
 */

namespace Carno\Laravel\Tracing\Middleware;

use Carno\Net\Address;
use Carno\Tracing\Contracts\Vars\EXT;
use Carno\Tracing\Contracts\Vars\TAG;
use Carno\Tracing\Standard\Endpoint;
use Carno\Tracing\Utils\SpansCreator;
use Illuminate\Database\Events\QueryExecuted;
use Closure;

class SQLTracing
{
    use SpansCreator;

    /**
     * @var string
     */
    private $app = null;

    /**
     * SQLTracing constructor.
     * @param string $app
     */
    public function __construct(string $app)
    {
        $this->app = $app;
    }

    /**
     * @return Closure
     */
    public function observer() : Closure
    {
        return function (QueryExecuted $executed) {
            if (!CTXG::joined()) {
                return;
            }

            $type = $executed->connection->getConfig('driver');
            $host = $executed->connection->getConfig('host');
            $port = $executed->connection->getConfig('port');
            $user = $executed->connection->getConfig('username');
            $name = $executed->connection->getConfig('database');

            $now = $this->microseconds();
            $pre = $now - (int)($executed->time * 1000);

            $this->newSpan(
                $ctx = clone CTXG::session(),
                sprintf('sql.execute::%s', $executed->connectionName),
                [
                    TAG::SPAN_KIND => TAG::SPAN_KIND_RPC_CLIENT,
                    TAG::DATABASE_TYPE => $type,
                    TAG::DATABASE_INSTANCE => sprintf('%s:%d', $host, $port),
                    TAG::DATABASE_USER => sprintf('%s@%s', $user, $name),
                    TAG::DATABASE_STATEMENT => $executed->sql,
                    EXT::REMOTE_ENDPOINT => new Endpoint($this->app, new Address($host, $port)),
                ],
                [],
                null,
                null,
                null,
                null,
                $pre
            );

            $this->closeSpan($ctx, [], $now);
        };
    }
}
