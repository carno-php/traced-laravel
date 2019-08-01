<?php
/**
 * Web Tracing
 * User: moyo
 * Date: Jul 23, 2019
 * Time: 16:52
 */

namespace Carno\Laravel\Tracing\Middleware;

use Carno\Coroutine\Context;
use Carno\HTTP\Standard\Uri;
use Carno\Net\Address;
use Carno\Tracing\Contracts\Vars\EXT;
use Carno\Tracing\Contracts\Vars\FMT;
use Carno\Tracing\Contracts\Vars\TAG;
use Carno\Tracing\Standard\Endpoint;
use Carno\Tracing\Utils\SpansCreator;
use Carno\Tracing\Utils\SpansExporter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Closure;
use Throwable;

class WebTracing
{
    use SpansCreator;
    use SpansExporter;

    /**
     * @var string
     */
    private $app = null;

    /**
     * WebTracing constructor.
     * @param string $app
     */
    public function __construct(string $app)
    {
        $this->app = $app;
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws Throwable
     */
    public function handle($request, Closure $next)
    {
        $ctx = CTXG::assign($this->create($request));

        try {
            if (($response = $next($request))->exception) {
                return $this->failure($ctx, $response->exception, $response);
            } else {
                return $this->success($ctx, $response);
            }
        } catch (Throwable $e) {
            $this->failure($ctx, $e);
            throw $e;
        } finally {
            CTXG::release();
        }
    }

    /**
     * @param Request $r
     * @return Context
     */
    private function create(Request $r) : Context
    {
        $ctx = new Context();

        $this->newSpan(
            $ctx,
            $r->getPathInfo(),
            [
                TAG::SPAN_KIND => TAG::SPAN_KIND_RPC_SERVER,
                TAG::HTTP_URL => $r->getUri(),
                TAG::HTTP_METHOD => $r->getMethod(),
                TAG::USER_AGENT => $r->headers->get('User-Agent'),
                EXT::LOCAL_ENDPOINT => new Endpoint($this->app, new Address($r->server->get('SERVER_ADDR'), $r->getPort())),
                EXT::REMOTE_ENDPOINT => new Endpoint($this->app, new Address($r->getClientIp(), $r->server->get('REMOTE_PORT'))),
            ],
            [],
            FMT::HTTP_HEADERS,
            $this->request2psr($r)
        );

        return $ctx;
    }

    /**
     * @param Context $c
     * @param Response $r
     * @return Response
     */
    private function success(Context $c, Response $r) : Response
    {
        $this->closeSpan($c, [TAG::HTTP_STATUS_CODE => $r->getStatusCode()]);
        $this->spanToHResponse($c, $rr = new \Carno\HTTP\Standard\Response());
        $this->psr2response($rr, $r);
        return $r;
    }

    /**
     * @param Context $c
     * @param Throwable $e
     * @param Response $r
     * @return Response
     */
    private function failure(Context $c, Throwable $e, Response $r = null) : ?Response
    {
        $this->errorSpan($c, $e, [TAG::HTTP_STATUS_CODE => $r ? $r->getStatusCode() : 500]);
        $r && $this->spanToHResponse($c, $rr = new \Carno\HTTP\Standard\Response());
        ($rr ?? null) && $this->psr2response($rr, $r);
        return $r;
    }

    /**
     * @param Request $r
     * @return RequestInterface
     */
    private function request2psr(Request $r) : RequestInterface
    {
        return new \Carno\HTTP\Standard\Request(
            $r->getMethod(),
            new Uri($r->getScheme(), $r->getHost(), $r->getPort(), $r->getPathInfo(), $r->getQueryString()),
            (array) $r->headers->all()
        );
    }

    /**
     * @param ResponseInterface $p
     * @param Response $r
     */
    private function psr2response(ResponseInterface $p, Response $r) : void
    {
        foreach ($p->getHeaders() as $name => $value) {
            $r->headers->set($name, $value);
        }
    }
}
