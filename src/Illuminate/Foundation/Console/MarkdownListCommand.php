<?php

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'markdown:list')]
class MarkdownListCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'markdown:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered markdown routes';

    /**
     * The router instance.
     *
     * @var \Illuminate\Routing\Router
     */
    protected $router;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Routing\Router  $router
     */
    public function __construct(Router $router)
    {
        parent::__construct();

        $this->router = $router;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $routes = $this->routes();

        if ($routes->isEmpty()) {
            $this->components->error("Your application doesn't have any markdown routes.");

            return self::FAILURE;
        }

        $this->table(['Method', 'URI', 'Source', 'Layout'], $routes->all());

        return self::SUCCESS;
    }

    /**
     * Get the markdown routes in displayable form.
     *
     * @return \Illuminate\Support\Collection<int, array{method: string, uri: string, source: string, layout: string}>
     */
    protected function routes()
    {
        return (new Collection($this->router->getRoutes()))
            ->map(fn ($route) => $this->routeInformation($route))
            ->filter()
            ->sortBy('uri')
            ->values();
    }

    /**
     * Get the markdown route information for the given route.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return array|null
     */
    protected function routeInformation(Route $route)
    {
        if (($layout = $route->getAction('markdown.layout')) === null) {
            return null;
        }

        return [
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'source' => $route->getAction('markdown.path'),
            'layout' => $layout,
        ];
    }
}
