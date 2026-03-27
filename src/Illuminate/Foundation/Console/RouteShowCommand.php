<?php

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request as RequestFacade;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

#[AsCommand(name: 'route:show')]
class RouteShowCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'route:show {name} {params?*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the details for a named route';

    /**
     * Create a new route show command instance.
     *
     * @param  \Illuminate\Routing\Router  $router
     */
    public function __construct(protected Router $router)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->router->getRoutes()->refreshNameLookups();

        if (! $route = $this->router->getRoutes()->getByName($this->argument('name'))) {
            $this->components->error("Route [{$this->argument('name')}] was not found.");

            return self::FAILURE;
        }

        if (! is_array($parameters = $this->parseParameters((array) $this->argument('params')))) {
            return self::FAILURE;
        }

        $this->displayRoute($route);

        $declaredMetadata = $route->getDeclaredMetadata();

        if ($parameters === []) {
            $this->displayMetadata('Metadata', $declaredMetadata);

            if ($this->routeParameters($route) !== []) {
                $this->components->warn('Supply route parameters to see resolved metadata.');
            }

            return self::SUCCESS;
        }

        if ($missing = array_values(array_diff($this->requiredRouteParameters($route), array_keys($parameters)))) {
            $this->displayMetadata('Metadata', $declaredMetadata);
            $this->components->error('Missing route parameters: '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        try {
            $request = Request::create(
                $this->laravel['url']->route($route->getName(), $parameters),
                $this->routeMethod($route),
            );

            $this->laravel->instance('request', $request);

            RequestFacade::clearResolvedInstance();

            $resolvedRoute = $this->router->getRoutes()->match($request);

            $this->router->substituteBindings($resolvedRoute);
            $this->router->substituteImplicitBindings($resolvedRoute);

            $this->router->dispatch($request);
        } catch (RouteNotFoundException $e) {
            $this->displayMetadata('Metadata', $declaredMetadata);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (ModelNotFoundException|BackedEnumCaseNotFoundException $e) {
            $this->displayMetadata('Metadata', $declaredMetadata);
            $this->components->error('Unable to resolve the supplied route parameters.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->displayMetadata('Metadata', $declaredMetadata);
            $this->components->error($e->getMessage() !== '' ? $e->getMessage() : 'Unable to resolve route metadata.');

            return self::FAILURE;
        }

        $resolvedMetadata = ($this->router->current() ?? $route)->getMetadata();

        if ($declaredMetadata === $resolvedMetadata) {
            $this->displayMetadata('Metadata', $declaredMetadata);

            return self::SUCCESS;
        }

        $this->displayMetadata('Declared Metadata', $declaredMetadata);
        $this->displayMetadata('Resolved Metadata', $resolvedMetadata, $declaredMetadata);

        return self::SUCCESS;
    }

    /**
     * Display the route details.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return void
     */
    protected function displayRoute(Route $route)
    {
        $this->components->twoColumnDetail('Name', $route->getName());
        $this->components->twoColumnDetail('URI', $this->resolveUri($route));
        $this->components->twoColumnDetail('Methods', implode(', ', $route->methods()));
        $this->components->twoColumnDetail('Action', ltrim($route->getActionName(), '\\'));
    }

    /**
     * Display a metadata section.
     *
     * @param  string  $title
     * @param  array  $metadata
     * @param  array  $comparison
     * @return void
     */
    protected function displayMetadata($title, array $metadata, array $comparison = [])
    {
        $this->components->twoColumnDetail('<fg=green;options=bold>'.$title.'</>');

        if ($metadata === []) {
            $this->components->twoColumnDetail('  <fg=gray>None</>', '');

            return;
        }

        foreach ($this->metadataKeys($metadata, $comparison) as $key) {
            $value = $this->formatMetadataValue($metadata[$key] ?? null);

            if ($comparison !== [] && Arr::get($comparison, $key) !== Arr::get($metadata, $key)) {
                $value .= ' <fg=yellow><- changed</>';
            }

            $this->components->twoColumnDetail('  '.$key, $value);
        }
    }

    /**
     * Parse the route parameters.
     *
     * @param  array  $parameters
     * @return array|false
     */
    protected function parseParameters(array $parameters)
    {
        $parsed = [];

        foreach ($parameters as $parameter) {
            if (! str_contains($parameter, '=')) {
                $this->components->error("Invalid route parameter [{$parameter}]. Route parameters must be in key=value form.");

                return false;
            }

            [$key, $value] = explode('=', $parameter, 2);

            $parsed[$key] = $value;
        }

        return $parsed;
    }

    /**
     * Get the route method that should be used for dispatching.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return string
     */
    protected function routeMethod(Route $route)
    {
        return Arr::first(array_diff($route->methods(), ['HEAD'])) ?? 'GET';
    }

    /**
     * Get the route's parameters.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return array
     */
    protected function routeParameters(Route $route)
    {
        preg_match_all('/\{(\w+)(\?)?\}/', $route->getDomain().'/'.$route->uri(), $matches);

        return $matches[1] ?? [];
    }

    /**
     * Get the route's required parameters.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return array
     */
    protected function requiredRouteParameters(Route $route)
    {
        preg_match_all('/\{(\w+)(\?)?\}/', $route->getDomain().'/'.$route->uri(), $matches, PREG_SET_ORDER);

        return collect($matches)
            ->reject(fn ($match) => ($match[2] ?? null) === '?')
            ->pluck(1)
            ->all();
    }

    /**
     * Get the metadata keys in display order.
     *
     * @param  array  $metadata
     * @param  array  $comparison
     * @return array
     */
    protected function metadataKeys(array $metadata, array $comparison = [])
    {
        return array_values(array_unique([
            ...array_keys($comparison),
            ...array_keys($metadata),
        ]));
    }

    /**
     * Format a metadata value for display.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function formatMetadataValue($value)
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ?: var_export($value, true);
    }

    /**
     * Get the URI for the given route, including any binding fields.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return string
     */
    protected function resolveUri(Route $route)
    {
        $uri = $route->uri();

        foreach ($route->bindingFields() as $parameter => $field) {
            $uri = str_replace("{{$parameter}}", "{{$parameter}:{$field}}", $uri);
        }

        return $uri;
    }
}
