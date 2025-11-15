<?php

namespace Stellify\Laravel\Parser;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteParser
{
    /**
     * Parse all Laravel routes and return data for database insertion
     *
     * @return array
     */
    public function parseRoutes(): array
    {
        $routes = [];
        
        foreach (Route::getRoutes() as $route) {
            $routes[] = $this->parseRoute($route);
        }

        return $routes;
    }

    /**
     * Parse a single route
     */
    private function parseRoute($route): array
    {
        $action = $route->getAction();
        $controller = $action['controller'] ?? null;
        $controllerClass = null;
        $controllerMethod = null;

        // Parse controller@method format
        if ($controller && is_string($controller)) {
            if (str_contains($controller, '@')) {
                [$controllerClass, $controllerMethod] = explode('@', $controller);
            } else {
                // Invokable controller
                $controllerClass = $controller;
                $controllerMethod = '__invoke';
            }
        }

        // Get middleware
        $middleware = $route->middleware();
        $middlewareGroup = 'web'; // default
        if (in_array('api', $middleware)) {
            $middlewareGroup = 'api';
        }

        // Check for auth middleware
        $requiresAuth = in_array('auth', $middleware) || in_array('auth:sanctum', $middleware);
        $emailVerify = in_array('verified', $middleware);

        return [
            'uuid' => Str::uuid()->toString(),
            'user_id' => null,
            'project_id' => null,
            'name' => $route->getName(),
            'path' => $route->uri(),
            'controller' => $controllerClass,
            'controller_method' => $controllerMethod,
            'middleware_group' => $middlewareGroup,
            'redirect_url' => '',
            'status_code' => '',
            'type' => in_array('api', $middleware) ? 'api' : 'web',
            'method' => implode('|', $route->methods()),
            'public' => !$requiresAuth,
            'ssr' => false,
            'email_verify' => $emailVerify,
            'subview' => false,
            'data' => json_encode([
                'middleware' => $middleware,
                'domain' => $route->getDomain(),
                'where' => $route->wheres,
                'defaults' => $route->defaults,
            ])
        ];
    }
}
