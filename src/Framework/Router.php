<?php

/**
 * Author: Roger Creasy
 * Email:  roger@rogercreasy.com
 * Date:   Saturday, July 15, 2017 05:47
 */

namespace ChassisPHP\Framework;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ChassisPHP\Framework\Http\MiddlewareQueue;

class Router
{

    private $dispatcher;
    private $request;
    private $response;
    protected $middlewareQueue;

    public function __construct(
        ServerRequestInterface $request,
        ResponseInterface $response,
        MiddlewareQueue $middlewareQueue
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->middlewareQueue = $middlewareQueue;
    }

    public function getRouteInfo(ServerRequestInterface $request, $routeDefinitionCallback)
    {
        $params = $request->getServerParams();
        $requestURI = $params['REQUEST_URI'];
        $dispatcher = $this->dispatcher($routeDefinitionCallback);

        // if the route has a trailing /, remove it
        if (strlen($requestURI)>1) {
            $requestURI = rtrim($requestURI, "/");
        }

        $routeInfo = $dispatcher->dispatch($request->getMethod(), $requestURI);

        return $routeInfo;
    }

    private function dispatcher($routeDefinitionCallback)
    {
        $dispatcher = \FastRoute\simpleDispatcher($routeDefinitionCallback);
        return $dispatcher;
    }

    // dispatch a response
    public function dispatch($request, $routeDefinitionCallback, $container)
    {
        $routeInfo = $this->getRouteInfo($request, $routeDefinitionCallback);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return $this->response->withStatus(404);

            case Dispatcher::METHOD_NOT_ALLOWED:
                $response->setContent('405 - Method not allowed');
                $response->setStatusCode(405);
                break;
            case Dispatcher::FOUND:
                if (is_array($routeInfo[1])) {
                    $classname = $routeInfo[1][0];
                    $classname = 'App\Http\Controllers\\' . $classname;
                    $method = $routeInfo[1][1];
                    $vars = $routeInfo[2];
                    $class = $container->get($classname);
                    $classResponse = $class->$method($vars);
                    $this->middlewareQueue->addController($classResponse);
                } else {
                    $handler = $routeInfo[1];
                    $vars = $routeInfo[2];
                    $classResponse = call_user_func($handler, $vars);
                    $this->middlewareQueue->addController($classResponse);
                }
                break;
        }

        $this->addCoreMiddleware();

        // call the middlewareQueue
        $this->response = $this->middlewareQueue->callMiddleware($request, $this->response);
        return $this->response;
    }

    // add the core middleware that should be applied to all routes
    // any other middleware that should be run prior to routes/controllers
    // can be added here
    private function addCoreMiddleware()
    {
        $this->middlewareQueue->addMiddleware(
            'SessionMiddleware',
            '\ChassisPHP\Framework\Http\Middleware\\'
        );
    }
}
