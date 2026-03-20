<?php

namespace Illuminate\Routing;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MarkdownRouteRegistrar
{
    /**
     * Create a new markdown route registrar instance.
     *
     * @param  \Illuminate\Routing\Router  $router
     */
    public function __construct(protected Router $router)
    {
    }

    /**
     * Register markdown files under the given URI prefix.
     *
     * @param  string  $uri
     * @param  string  $path
     * @param  string  $layout
     * @param  array  $attributes
     * @return \Illuminate\Routing\PendingMarkdownRoute
     */
    public function register($uri, $path, $layout, array $attributes = [])
    {
        $routes = [];

        foreach ($this->discover($path) as $page) {
            $route = $this->router->match(['GET', 'HEAD'], $this->routeUri($uri, $page['uri']), array_merge($attributes, [
                'uses' => MarkdownController::class,
            ]));

            $route->setAction(array_merge($route->getAction(), [
                'markdown' => [
                    'name' => $page['name'],
                    'mount' => trim(str_replace('\\', '/', $path), '/'),
                    'path' => $this->sourcePath($path, $page['path']),
                    'layout' => $layout,
                    'uri' => ltrim($route->uri(), '/') ?: '/',
                ],
            ]));

            $routes[] = $route;
        }

        return new PendingMarkdownRoute($this->router, $routes);
    }

    /**
     * Discover the markdown files for the given path.
     *
     * @param  string  $path
     * @return array<int, array{name: string, path: string, precedence: int, uri: string}>
     */
    protected function discover($path)
    {
        $directory = $this->directory($path);
        $pages = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            $relativePath = str_replace('\\', '/', ltrim(Str::after($file->getPathname(), $directory), DIRECTORY_SEPARATOR));
            $uri = $this->pageUri($relativePath);
            $precedence = $this->isIndexFile($relativePath) ? 1 : 2;

            if (! isset($pages[$uri]) || $precedence > $pages[$uri]['precedence']) {
                $pages[$uri] = [
                    'uri' => $uri,
                    'name' => $this->pageName($relativePath),
                    'path' => $relativePath,
                    'precedence' => $precedence,
                ];
            }
        }

        ksort($pages);

        return array_values($pages);
    }

    /**
     * Resolve the mounted markdown directory.
     *
     * @param  string  $path
     * @return string
     */
    protected function directory($path)
    {
        if ($this->pathIsInvalid($path)) {
            throw MarkdownException::invalidPath($path);
        }

        $directory = rtrim($this->markdownDirectory(), DIRECTORY_SEPARATOR).($path !== '' ? DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, trim($path, '/')) : '');

        if (! is_dir($directory)) {
            throw MarkdownException::missingDirectory($path);
        }

        $markdownDirectory = realpath($this->markdownDirectory());
        $directory = realpath($directory);

        if ($directory === false ||
            ($markdownDirectory !== false &&
             ! str_starts_with($directory, $markdownDirectory.DIRECTORY_SEPARATOR) &&
             $directory !== $markdownDirectory)) {
            throw MarkdownException::invalidPath($path);
        }

        return $directory;
    }

    /**
     * Determine if the given path is invalid.
     *
     * @param  string  $path
     * @return bool
     */
    protected function pathIsInvalid($path)
    {
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path)
            || in_array('..', array_filter(explode('/', $path)), true);
    }

    /**
     * Resolve the markdown base directory.
     *
     * @return string
     */
    protected function markdownDirectory()
    {
        return Container::getInstance()->resourcePath('markdown');
    }

    /**
     * Resolve the route URI for the given markdown page.
     *
     * @param  string  $mount
     * @param  string  $uri
     * @return string
     */
    protected function routeUri($mount, $uri)
    {
        return trim(trim($mount, '/').'/'.trim($uri, '/'), '/') ?: '/';
    }

    /**
     * Resolve the source path for the given markdown page.
     *
     * @param  string  $path
     * @param  string  $relativePath
     * @return string
     */
    protected function sourcePath($path, $relativePath)
    {
        return trim(trim(str_replace('\\', '/', $path), '/').'/'.trim($relativePath, '/'), '/');
    }

    /**
     * Resolve the relative URI for the given markdown file.
     *
     * @param  string  $path
     * @return string
     */
    protected function pageUri($path)
    {
        $segments = explode('/', Str::replaceLast('.md', '', $path));

        if (Arr::last($segments) === 'index') {
            array_pop($segments);
        }

        return implode('/', $segments);
    }

    /**
     * Resolve the route name suffix for the given markdown file.
     *
     * @param  string  $path
     * @return string
     */
    protected function pageName($path)
    {
        return str_replace('/', '.', Str::replaceLast('.md', '', $path));
    }

    /**
     * Determine if the given markdown file is an index file.
     *
     * @param  string  $path
     * @return bool
     */
    protected function isIndexFile($path)
    {
        return basename($path) === 'index.md';
    }
}
