<?php
/**
 * Context global
 * User: moyo
 * Date: Jul 30, 2019
 * Time: 14:42
 */

namespace Carno\Laravel\Tracing\Middleware;

use Carno\Coroutine\Context;

class CTXG
{
    /**
     * @var Context
     */
    private static $session = null;

    /**
     * @return bool
     */
    public static function joined() : bool
    {
        return !! self::$session;
    }

    /**
     * @return Context
     */
    public static function session() : Context
    {
        return self::$session ?? new Context;
    }

    /**
     * @param Context $current
     * @return Context
     */
    public static function assign(Context $current) : Context
    {
        return self::$session = $current;
    }

    /**
     */
    public static function release() : void
    {
        self::$session = null;
    }
}
