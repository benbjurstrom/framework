# Markdown Routes

## Introduction

Laravel includes first-party support for serving Markdown files as routable pages. Markdown routes are useful for documentation sections, help centers, guides, and other content that benefits from file-based organization while still rendering through your application's existing Blade layouts.

Markdown routes are registered using the `Route` facade and are materialized as normal routes during application boot. This means they appear in `php artisan route:list`, work with route caching, and participate in Laravel's normal middleware pipeline.

## Registering Markdown Routes

You may register a directory of Markdown files using the `markdown` method on the `Route` facade. The first argument is the URI prefix that should be used for the mounted pages. The `path` argument should be relative to your application's `resources/markdown` directory, while the `layout` argument should be the Blade view that wraps each rendered page:

```php
use Illuminate\Support\Facades\Route;

Route::markdown('docs', path: 'docs', layout: 'layouts.docs');
```

In this example, Laravel will discover Markdown files within `resources/markdown/docs` and make them available beneath the `/docs` URI prefix.

Since `Route::markdown` returns a pending route registration instance, you may fluently assign middleware and route names:

```php
Route::markdown('docs', path: 'docs', layout: 'layouts.docs')
    ->middleware('web')
    ->name('docs');
```

If a name prefix is assigned, each discovered page will receive its own route name. For example, a `getting-started.md` page would receive the `docs.getting-started` route name.

## Markdown Route Conventions

Markdown route discovery is based entirely on the filesystem. Only `.md` files are considered routable.

Assume the following route registration:

```php
Route::markdown('docs', path: 'docs', layout: 'layouts.docs');
```

And the following files:

```text
resources/markdown/docs/index.md
resources/markdown/docs/getting-started.md
resources/markdown/docs/guides/index.md
resources/markdown/docs/guides/install.md
```

These files will produce the following routes:

```text
/docs
/docs/getting-started
/docs/guides
/docs/guides/install
```

When both an exact file and a directory index could satisfy the same URI, the exact file takes precedence.

## Rendering Markdown Pages

Each discovered Markdown file is rendered to HTML and then passed to the configured layout view. The layout receives two variables:

- `$content`, an `Illuminate\Support\HtmlString` instance containing the rendered HTML.
- `$page`, an object containing information about the current Markdown page.

The `$page` object exposes:

```php
$page->uri;   // docs/getting-started
$page->path;  // docs/getting-started.md
$page->title; // Title from frontmatter, if present
$page->meta;  // All frontmatter attributes
```

Your layout may render the page content like so:

```blade
<html>
    <head>
        <title>{{ $page->title ?? 'Documentation' }}</title>
    </head>

    <body>
        <main>{!! $content !!}</main>
    </body>
</html>
```

## Frontmatter

Markdown routes support YAML frontmatter. Frontmatter is treated as page metadata and made available via the `$page` object:

```markdown
---
title: Getting Started
description: Learn how to install the application.
author: Taylor
---

# Getting Started
```

You may access these values within your layout:

```blade
<title>{{ $page->title }}</title>
<meta name="description" content="{{ $page->meta['description'] ?? '' }}">
```

Frontmatter is data only. It may not define middleware, mutate the route, or execute application logic.

## Middleware

Middleware may be assigned to the entire mounted Markdown section:

```php
Route::markdown('docs', path: 'docs', layout: 'layouts.docs')
    ->middleware(['auth']);
```

This middleware will be applied to every discovered Markdown page within the mount.

## Inspecting Markdown Routes

Because Markdown routes are registered as normal routes, they will appear in the output of the `route:list` Artisan command.

Laravel also provides a dedicated `markdown:list` command for inspecting discovered Markdown pages:

```shell
php artisan markdown:list
```

Each row includes the HTTP method, URI, source Markdown file, and layout view for the page.

## Listing Markdown Pages

If you need to render navigation links for a mounted Markdown section, you may retrieve the discovered pages for a given Markdown folder using the `markdownPages` method on the `Route` facade:

```php
use Illuminate\Support\Facades\Route;

$pages = Route::markdownPages('docs');
```

The argument should be the mounted Markdown folder path relative to `resources/markdown`.

Each item in the returned collection contains the page's routing information and frontmatter metadata:

```php
$page->uri;   // help/getting-started
$page->url;   // /help/getting-started
$page->name;  // docs.getting-started
$page->path;  // docs/getting-started.md
$page->title; // Title from frontmatter, if present
$page->meta;  // All frontmatter attributes
```

This makes it easy to render navigation within a layout or component:

```blade
<nav>
    @foreach (Route::markdownPages('docs') as $page)
        <a href="{{ $page->url }}">
            {{ $page->title ?? $page->path }}
        </a>
    @endforeach
</nav>
```

Markdown page metadata is read from the Markdown files when the page list is requested, so the returned frontmatter remains current even when your routes are cached.

## Smoke Testing Markdown Pages

If your layout expects frontmatter that may be missing from one of your Markdown files, you may smoke test all registered Markdown pages using Laravel's HTTP testing layer:

```php
test('markdown pages render successfully', function () {
    $this->assertMarkdownRoutesRender();
});
```

This assertion will request every registered Markdown route and fail the test if any page does not render successfully. The failure message includes the route URI and source Markdown file so the broken page may be identified quickly.

By default, middleware is disabled so the assertion focuses on rendering failures. If you need to test the pages with middleware enabled, you may pass `true` to the method:

```php
test('authenticated markdown pages render successfully', function () {
    $this->actingAs($user);

    $this->assertMarkdownRoutesRender(withMiddleware: true);
});
```

## Customizing Markdown Rendering

Laravel's Markdown route renderer is container-managed and may be customized from a service provider. This allows you to add CommonMark extensions or merge additional CommonMark configuration without replacing the renderer entirely:

```php
use Illuminate\Routing\Markdown;
use Phiki\CommonMark\PhikiExtension;
use Phiki\Theme\Theme;

public function boot(): void
{
    $this->app->afterResolving(Markdown::class, function (Markdown $markdown) {
        $markdown->configure([
            'phiki' => [
                'theme' => Theme::Synthwave84,
                'with_gutter' => false,
                'with_wrapper' => false,
            ],
        ])->extend(new PhikiExtension);
    });
}
```

This approach is particularly useful when adding syntax highlighting to fenced code blocks. If you are using Phiki, make sure the `phiki/phiki` package is installed in your application before registering the extension.

The renderer includes the following CommonMark extensions by default:

- `CommonMarkCoreExtension`
- `GithubFlavoredMarkdownExtension`
- `FrontMatterExtension`

## Missing Markdown Directories

If the configured Markdown path does not exist within `resources/markdown`, Laravel will throw an exception during route registration. This helps surface misconfigured mounts early during application boot.
