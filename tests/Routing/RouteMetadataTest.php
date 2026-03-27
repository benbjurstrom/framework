<?php

namespace Illuminate\Tests\Routing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Illuminate\Routing\Router;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class RouteMetadataTest extends TestCase
{
    protected function getRouter(): Router
    {
        $container = Container::getInstance();

        $container->instance(CallableDispatcherContract::class, new CallableDispatcher($container));

        return new Router(
            m::mock(Dispatcher::class, ['dispatch' => null]),
            $container,
        );
    }

    public function test_route_metadata_is_merged_in_the_expected_order()
    {
        $router = $this->getRouter();

        $router->defaultMetadata([
            'title' => 'Laravel',
            'robots' => 'index,follow',
        ]);

        $router->metadata([
            'robots' => 'noindex,nofollow',
            'section' => 'marketing',
        ])->group(function ($router) {
            $router->get('about', function () {
                return 'about';
            })->metadata([
                'title' => 'About',
                'description' => 'About the application.',
            ]);
        });

        $route = $router->getRoutes()->getRoutes()[0];

        $this->assertSame([
            'title' => 'About',
            'robots' => 'noindex,nofollow',
            'section' => 'marketing',
            'description' => 'About the application.',
        ], $route->getDeclaredMetadata());
    }

    public function test_runtime_metadata_is_reset_when_the_route_is_rebound()
    {
        $router = $this->getRouter();

        $route = $router->get('about', function () {
            return 'about';
        })->metadata([
            'title' => 'About',
        ]);

        $route->bind(Request::create('/about', 'GET'));
        $route->mergeMetadata([
            'title' => 'Runtime Title',
        ]);

        $this->assertSame('Runtime Title', $route->getMetadata('title'));

        $route->bind(Request::create('/about', 'GET'));

        $this->assertSame('About', $route->getMetadata('title'));
    }

    public function test_router_can_return_the_current_route_metadata()
    {
        $router = $this->getRouter();

        $router->defaultMetadata([
            'title' => 'Laravel',
        ]);

        $router->get('about', function () {
            return 'about';
        });

        $router->dispatch(Request::create('/about', 'GET'));
        $router->current()->mergeMetadata([
            'title' => 'About',
        ]);

        $this->assertSame('About', $router->currentMetadata('title'));
        $this->assertSame([
            'title' => 'About',
        ], $router->currentMetadata());
    }
}
