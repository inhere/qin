<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2018/9/27
 * Time: 下午10:27
 */

namespace Qin\Http;

use Inhere\Route\ORouter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qin\Component\ObjectPool;
use SwoKit\Http\Server\Util\Psr7Http;

/**
 * Class App
 * @package Qin\Http
 */
class App implements RequestHandlerInterface
{
    const CTXAllowedMethodsKey = '_CTXAllowedMethods';

    /**
     * @var array|\SplStack
     */
    private $chain = [];

    /**
     * @var array|\SplStack
     * ['route path' => [... middleware]]
     */
    private $routeChain = [];

    /**
     * @var string
     */
    private $workDir;

    /**
     * @var ObjectPool
     */
    protected $ctxPool;

    public function __construct(string $workDir)
    {
        \Qin::$app = $this;

        // init properties
        $this->workDir = $workDir;
        $this->ctxPool = new ObjectPool();
        $this->ctxPool->setCreator(function () {
            return new Context();
        });
    }

    /**
     * @param MiddlewareInterface|\Closure ...$middleware
     */
    public function use(...$middleware)
    {
        foreach ($middleware as $item) {
            $this->chain[] = $item;
        }
    }

    /**
     * Handle the request and return a response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ORouter $router */
        $router = \Qin::get('httpRouter');
        $uriPath = $request->getUri()->getPath();
        $result = $router->match($uriPath, $request->getMethod());

        /** @var Context $ctx */
        $ctx = $this->ctxPool->get();
        $ctx->init($request, Psr7Http::createResponse());

        switch ($result[0]) {
            case ORouter::FOUND:
                $route = $result[2];
                $handler = $route['handler'];
                $ctx->params = $route['matches'];
                break;
            case ORouter::NOT_FOUND:
                break;
            case ORouter::METHOD_NOT_ALLOWED:

                break;
        }

        // get res object.
        $res = $ctx->res;

        // release
        $ctx->reset();
        $this->ctxPool->put($ctx);

        return $res;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function internal404Handler(ServerRequestInterface $request): ResponseInterface
    {
        $res = Psr7Http::createResponse();

        return $res->withStatus(404);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function internal405Handler(ServerRequestInterface $request): ResponseInterface
    {
        $res = Psr7Http::createResponse();

        if ($request->getMethod() === 'OPTIONS') {
            return $res->withStatus(200);
        }

        $methods = (array)$request->getAttribute(self::CTXAllowedMethodsKey);
        return $res->withStatus(405)->withHeader('Allow', \implode(',', $methods));
    }

    /**
     * @return string
     */
    public function getWorkDir(): string
    {
        return $this->workDir;
    }
}