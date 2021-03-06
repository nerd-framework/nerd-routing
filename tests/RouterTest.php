<?php

namespace tests;

use Nerd\Framework\Http\Request\RequestContract;
use Nerd\Framework\Routing\Route\RouteContract;
use Nerd\Framework\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private function makeRequest($method, $path)
    {
        $request = $this->getMockBuilder(RequestContract::class)
                        ->setMethods([])
                        ->getMock();
        $request->method('getMethod')->willReturn($method);
        $request->method('getPath')->willReturn($path);
        return $request;
    }

    private function getRouter()
    {
        return new Router();
    }

    private function getRouterWithTestRoutes()
    {
        $router = $this->getRouter();

        $router->get('/', function () {
            return 'test-get';
        });
        $router->post('test/post', function () {
            return 'test-post';
        });
        $router->put('test/put', function () {
            return 'test-put';
        });
        $router->delete('test/delete', function () {
            return 'test-delete';
        });
        $router->any('test/any', function () {
            return 'test-any';
        });

        $router->get('foo', function () {
            return 'foo';
        });
        $router->get('bar/', function () {
            return 'bar';
        });

        return $router;
    }

    public function testInstance()
    {
        $router = $this->getRouter();

        $this->assertInstanceOf(Router::class, $router);
    }

    public function testRouteHandling()
    {
        $router = $this->getRouterWithTestRoutes();

        $this->assertEquals('test-get', $router->handle($this->makeRequest('GET', '/')));
        $this->assertEquals('test-post', $router->handle($this->makeRequest('POST', 'test/post')));
        $this->assertEquals('test-put', $router->handle($this->makeRequest('PUT', 'test/put')));
        $this->assertEquals('test-delete', $router->handle($this->makeRequest('DELETE', 'test/delete')));

        $this->assertEquals('test-any', $router->handle($this->makeRequest('GET', 'test/any')));
        $this->assertEquals('test-any', $router->handle($this->makeRequest('POST', 'test/any')));
        $this->assertEquals('test-any', $router->handle($this->makeRequest('PUT', 'test/any')));
        $this->assertEquals('test-any', $router->handle($this->makeRequest('DELETE', 'test/any')));
    }

    public function testRouteSlashSuffix()
    {
        $router = $this->getRouterWithTestRoutes();

        $this->assertEquals('foo', $router->handle($this->makeRequest('GET', 'foo')));
        $this->assertEquals('bar', $router->handle($this->makeRequest('GET', 'bar/')));
    }

    /**
     * @expectedException \Nerd\Framework\Routing\RouteNotFoundException
     */
    public function testDocumentNotFound()
    {
        $router = $this->getRouterWithTestRoutes();

        $router->handle($this->makeRequest('GET', '404'));
    }

    /**
     * @expectedException \Nerd\Framework\Routing\RouteNotFoundException
     */
    public function testRouteSlashDoNotCompletion1()
    {
        $router = $this->getRouterWithTestRoutes();

        $router->handle($this->makeRequest('GET', 'foo/'));
    }

    /**
     * @expectedException \Nerd\Framework\Routing\RouteNotFoundException
     */
    public function testRouteSlashDoNotCompletion2()
    {
        $router = $this->getRouterWithTestRoutes();

        $router->handle($this->makeRequest('GET', 'bar'));
    }

    public function testRouteParams()
    {
        $router = $this->getRouter();

        $router->get('hello/:name', function ($name) {
            return "Hello, $name";
        });

        $this->assertEquals('Hello, Sam', $router->handle($this->makeRequest('GET', 'hello/Sam')));
        $this->assertEquals('Hello, Bill', $router->handle($this->makeRequest('GET', 'hello/Bill')));
    }

    public function testMiddlewareFunction()
    {
        $router = $this->getRouter();

        $mw = function (RouteContract $route, RequestContract $request, callable $next) {
            return 'foo' . $next($route, $request);
        };

        $router->get('/', $mw, function () {
            return 'bar';
        });

        $this->assertEquals('foobar', $router->handle($this->makeRequest('GET', '/')));
    }

    public function testMiddlewareRegExpParam()
    {
        $router = $this->getRouter();

        $mw = function (RouteContract $route, RequestContract $request, callable $next) {
            $parameters = $route->parameters($request);
            if ($parameters['param'] == 'admin') {
                return 'bar';
            }
            return $next($route, $request);
        };

        $router->get('profile/:param', $mw, function () {
            return 'foo';
        });

        $this->assertEquals('foo', $router->handle($this->makeRequest('GET', 'profile/john-smith')));
        $this->assertEquals('bar', $router->handle($this->makeRequest('GET', 'profile/admin')));
    }

    public function testMiddlewareCascade()
    {
        $router = $this->getRouter();

        $mw1 = function ($route, $request, $next) {
            return 'a.' . $next($route, $request);
        };

        $mw2 = function ($route, $request, $next) {
            return 'b.' . $next($route, $request);
        };

        $router->get('/', $mw1, $mw2, function () {
            return 'foo';
        });

        $this->assertEquals('a.b.foo', $router->handle($this->makeRequest('GET', '/')));
    }

    /**
     * @expectedException \Nerd\Framework\Routing\RouteNotFoundException
     */
    public function testHardRoutePattern()
    {
        $router = $this->getRouter();

        $router->get('~^foo-(\w+)-bar/(\w+)/abc/(\d+)$~', function ($a, $b, $c) {
            return "$a-$b-$c";
        });

        $this->assertEquals('baz-buzz-15', $router->handle($this->makeRequest('GET', 'foo-baz-bar/buzz/abc/15')));

        $router->handle($this->makeRequest('GET', 'foo-baz-bar/buzz/abc/dd'));
    }

    public function testGlobalRouteHandler()
    {
        $router = $this->getRouter();

        Router::setGlobalRouteHandler(function (RequestContract $request, RouteContract $route) {
            return 'global-' . call_user_func($route->getAction());
        });

        $router->get('/', function () {
            return 'home';
        });

        $this->assertEquals('global-home', $router->handle($this->makeRequest('GET', '/')));

        Router::setGlobalRouteHandler(null);
    }

    public function testGlobalMiddlewareHandler()
    {
        $router = $this->getRouter();

        Router::setGlobalMiddlewareHandler(function ($middleware, $route, $request, $next) {
            return 'global-' . $middleware($route, $request, $next);
        });

        $mw = function ($route, $request, $next) {
            return 'middleware-' . $next($route, $request);
        };

        $router->get('/', $mw, function () {
            return 'home';
        });

        $this->assertEquals('global-middleware-home', $router->handle($this->makeRequest('GET', '/')));

        Router::setGlobalMiddlewareHandler(null);
    }

    public function testDoubleDotsRouteParam()
    {
        $router = new Router;
        $router->get('some/::param/other', function ($param) {
            return $param;
        });

        $this->assertEquals('foo', $router->handle($this->makeRequest('GET', 'some/foo/other')));
        $this->assertEquals('foo/bar', $router->handle($this->makeRequest('GET', 'some/foo/bar/other')));
    }
}
