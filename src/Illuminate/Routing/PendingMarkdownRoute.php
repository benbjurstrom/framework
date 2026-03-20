<?php

namespace Illuminate\Routing;

use BackedEnum;
use Illuminate\Support\Str;

class PendingMarkdownRoute
{
    /**
     * Create a new pending markdown route registration.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @param  array<int, \Illuminate\Routing\Route>  $routes
     */
    public function __construct(
        protected Router $router,
        protected array $routes,
    ) {
    }

    /**
     * Add middleware to the markdown routes.
     *
     * @param  mixed  $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        $middleware = is_array($middleware) ? $middleware : func_get_args();

        foreach ($this->routes as $route) {
            $route->middleware($middleware);
        }

        return $this;
    }

    /**
     * Remove middleware from the markdown routes.
     *
     * @param  array|string  $middleware
     * @return $this
     */
    public function withoutMiddleware($middleware)
    {
        foreach ($this->routes as $route) {
            $route->withoutMiddleware($middleware);
        }

        return $this;
    }

    /**
     * Set the route name prefix for the markdown routes.
     *
     * @param  \BackedEnum|string  $name
     * @return $this
     */
    public function name($name)
    {
        if ($name instanceof BackedEnum) {
            $name = $name->value;
        }

        $name = $name === '' ? '' : Str::finish($name, '.');

        foreach ($this->routes as $route) {
            $route->name($name.$route->getAction('markdown.name'));
        }

        $this->router->getRoutes()->refreshNameLookups();

        return $this;
    }
}
