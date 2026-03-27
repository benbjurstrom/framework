# Route Metadata

## Introduction

Route metadata allows you to associate arbitrary key / value data with your application's routes. This data may be declared when routes are registered, resolved from controller attributes, and overridden at runtime while a request is being handled.

Metadata is framework agnostic. Laravel does not assume how your metadata should be rendered or consumed. Instead, the framework provides a consistent API for declaring, reading, and mutating metadata throughout the request lifecycle.

## Defining Default Metadata

You may define metadata that should be applied to every route using the `Route::defaultMetadata` method. Typically, this method should be called from your application's service provider:

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::defaultMetadata([
            'title' => config('app.name'),
            'robots' => 'index,follow',
        ]);
    }
}
```

These values provide the base layer for all route metadata. More specific metadata declarations will override these values.

## Route Metadata

You may attach metadata directly to a route using the `metadata` method:

```php
use App\Http\Controllers\AboutController;
use Illuminate\Support\Facades\Route;

Route::get('/about', AboutController::class)
    ->name('about')
    ->metadata([
        'title' => 'About',
        'description' => 'Learn more about our company.',
    ]);
```

Since metadata is stored as a simple associative array, you may use any keys that are meaningful for your application:

```php
[
    'title' => 'Pricing',
    'description' => 'Compare plans and features.',
    'canonical' => 'https://example.com/pricing',
    'robots' => 'index,follow',
]
```

## Route Groups

The `metadata` method may also be used when defining a route group. Group metadata will be inherited by all routes within the group:

```php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::metadata(['robots' => 'noindex,nofollow'])->group(function () {
    Route::get('/admin', AdminController::class)
        ->name('admin.dashboard');

    Route::get('/admin/users', [AdminUserController::class, 'index'])
        ->name('admin.users.index')
        ->metadata(['title' => 'Users']);
});
```

If you define nested groups, their metadata will be merged from outermost to innermost. Route-level metadata will always take precedence over group metadata.

## Controller Metadata Attributes

Laravel also supports declaring route metadata using PHP attributes on your controllers. To get started, apply the `Illuminate\Routing\Attributes\Metadata` attribute to a controller class or method:

```php
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Metadata;

#[Metadata(['title' => 'Blog', 'robots' => 'index,follow'])]
class BlogController
{
    public function __invoke(): Response
    {
        //
    }
}
```

Class-level metadata acts as the default metadata for every route method on the controller. You may define metadata on an individual method to override the class-level values for that route:

```php
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Metadata;

#[Metadata(['section' => 'blog', 'robots' => 'index,follow'])]
class PostController
{
    public function show(Request $request, Post $post): Response
    {
        //
    }

    #[Metadata(['title' => 'Edit Post', 'robots' => 'noindex,nofollow'])]
    public function edit(Request $request, Post $post): Response
    {
        //
    }
}
```

## Runtime Metadata

Sometimes metadata depends on data that is not available until the route is being handled. In these situations, you may call the `mergeMetadata` method on the current route:

```php
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PostController
{
    public function show(Request $request, Post $post): Response
    {
        $request->route()->mergeMetadata([
            'title' => $post->title,
            'description' => $post->excerpt,
        ]);

        return response()->noContent();
    }
}
```

Of course, middleware may also merge metadata:

```php
use Closure;
use Illuminate\Http\Request;

class SetSection
{
    public function handle(Request $request, Closure $next)
    {
        $request->route()?->mergeMetadata([
            'section' => 'blog',
        ]);

        return $next($request);
    }
}
```

The `mergeMetadata` method is additive. Metadata declared on the route will remain intact unless you explicitly override individual keys.

## Reading Metadata

Once a route has been resolved, you may access its metadata directly from the route instance:

```php
$metadata = $request->route()->getMetadata();

$title = $request->route()->getMetadata('title');
```

If you prefer, you may also access the current route's metadata using the `Route` facade:

```php
use Illuminate\Support\Facades\Route;

$metadata = Route::currentMetadata();

$title = Route::currentMetadata('title');
```

`This is especially convenient in Blade views:`

```blade
<title>{{ Route::currentMetadata('title') }}</title>
<meta name="description" content="{{ Route::currentMetadata('description') }}">
<meta name="robots" content="{{ Route::currentMetadata('robots') }}">
```

If you are using Inertia, you may share the current route's metadata with every response from a middleware or service provider:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Inertia::share('metadata', function (Request $request) {
    return $request->route()?->getMetadata() ?? [];
});
```

Once the metadata has been shared, it may be accessed from your frontend components like any other shared Inertia prop.

## Metadata Resolution Order

Metadata is merged in the following order:

1. Application defaults defined via `Route::defaultMetadata`
2. Route group metadata
3. Route-level metadata
4. Controller class metadata attributes
5. Controller method metadata attributes
6. Runtime metadata merged via `mergeMetadata`

Later values always override earlier values.

## Inspecting Route Metadata

Laravel includes a `route:show` Artisan command for inspecting a named route's metadata:

```shell
php artisan route:show posts.show
```

When route parameters are not supplied, the command displays the route's declared metadata:

```shell
php artisan route:show posts.show
```

If the route requires parameters, you may provide them as `key=value` pairs to inspect the route's resolved metadata:

```shell
php artisan route:show posts.show post=hello-world
```

When parameters are supplied, Laravel will resolve the route, perform route model binding, run the route middleware stack, and execute the route so that runtime metadata may be inspected. If the resolved metadata differs from the declared metadata, the command will display both values and highlight the changed keys.

## Testing Metadata

Laravel's HTTP test response object includes an `assertRouteMetadata` method. You may use this assertion to verify a single metadata key:

```php
$this->get('/blog/hello-world')
    ->assertRouteMetadata('title', 'My Great Post');
```

You may also assert the full metadata payload:

```php
$this->get('/blog/hello-world')
    ->assertRouteMetadata([
        'title' => 'My Great Post',
        'robots' => 'index,follow',
        'section' => 'blog',
    ]);
```

## Route Caching

Route metadata is fully compatible with route caching. Metadata declared with the `metadata` method is stored with the route definition, while controller metadata attributes are resolved during route caching and written into the cached route data.

Since default metadata is usually defined in a service provider, it should continue to be registered during your application's normal boot process:

```shell
php artisan route:cache
```

Runtime metadata is never stored in the route cache. It only exists for the lifetime of the current request.
