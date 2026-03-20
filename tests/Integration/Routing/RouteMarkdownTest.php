<?php

namespace Illuminate\Tests\Integration\Routing;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\Markdown;
use Illuminate\Routing\MarkdownException;
use Illuminate\Support\Facades\Route;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\AssertionFailedError;

#[WithConfig('filesystems.disks.local.serve', false)]
class RouteMarkdownTest extends TestCase
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;

        $this->files->deleteDirectory(resource_path('markdown/docs'));
        $this->files->delete(resource_path('views/layouts/markdown-docs.blade.php'));
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory(resource_path('markdown/docs'));
        $this->files->delete(resource_path('views/layouts/markdown-docs.blade.php'));

        parent::tearDown();
    }

    public function testItCanRenderMarkdownRoutes()
    {
        $this->layout();

        $this->markdown('docs/index.md', <<<'MARKDOWN'
---
title: Documentation
description: Welcome to the docs.
---

# Home
MARKDOWN);

        $this->markdown('docs/getting-started.md', <<<'MARKDOWN'
---
title: Getting Started
description: Start here.
---

# Getting Started
MARKDOWN);

        $this->markdown('docs/guides/index.md', <<<'MARKDOWN'
---
title: Guides
description: Browse the guides.
---

# Guides
MARKDOWN);

        $this->markdown('docs/guides/install.md', <<<'MARKDOWN'
---
title: Installation
description: Install the application.
---

# Install
MARKDOWN);

        Route::markdown('docs', path: 'docs', layout: 'layouts.markdown-docs')
            ->middleware('web')
            ->name('docs');

        $this->get('/docs')
            ->assertOk()
            ->assertSee('Documentation')
            ->assertSee('Welcome to the docs.')
            ->assertSee('docs/index.md')
            ->assertSee('<h1>Home</h1>', false);

        $this->get('/docs/getting-started')
            ->assertOk()
            ->assertSee('Getting Started')
            ->assertSee('docs/getting-started.md')
            ->assertSee('<h1>Getting Started</h1>', false);

        $this->get('/docs/guides')
            ->assertOk()
            ->assertSee('Guides')
            ->assertSee('docs/guides/index.md')
            ->assertSee('<h1>Guides</h1>', false);

        $this->get('/docs/guides/install')
            ->assertOk()
            ->assertSee('Installation')
            ->assertSee('docs/guides/install.md')
            ->assertSee('<h1>Install</h1>', false);

        $this->assertSame('/docs', route('docs.index', [], false));
        $this->assertSame('/docs/getting-started', route('docs.getting-started', [], false));
        $this->assertSame('/docs/guides', route('docs.guides.index', [], false));
        $this->assertSame('/docs/guides/install', route('docs.guides.install', [], false));
        $this->assertSame(['web'], $this->app['router']->getRoutes()->getByName('docs.index')->middleware());
    }

    public function testExactFilesTakePrecedenceOverDirectoryIndexes()
    {
        $this->layout();

        $this->markdown('docs/foo.md', '# File');
        $this->markdown('docs/foo/index.md', '# Index');

        Route::markdown('docs', path: 'docs', layout: 'layouts.markdown-docs');

        $this->get('/docs/foo')
            ->assertOk()
            ->assertSee('<h1>File</h1>', false)
            ->assertDontSee('<h1>Index</h1>', false);
    }

    public function testMarkdownRoutesCanBeListedByTheConsole()
    {
        $this->layout();

        $this->markdown('docs/index.md', '# Home');
        $this->markdown('docs/guides/install.md', '# Install');

        Route::markdown('docs', path: 'docs', layout: 'layouts.markdown-docs')
            ->name('docs');

        $this->artisan('markdown:list')
            ->expectsOutputToContain('docs/index.md')
            ->expectsOutputToContain('docs/guides/install.md')
            ->expectsOutputToContain('layouts.markdown-docs')
            ->assertExitCode(0);

        $this->artisan('route:list', ['--json' => true])
            ->expectsOutputToContain('"path":"docs\/index.md"')
            ->expectsOutputToContain('"path":"docs\/guides\/install.md"')
            ->assertExitCode(0);
    }

    public function testItThrowsAnExceptionWhenTheMarkdownDirectoryDoesNotExist()
    {
        $this->expectException(MarkdownException::class);
        $this->expectExceptionMessage('Markdown path [missing] does not exist within [resources/markdown].');

        Route::markdown('docs', path: 'missing', layout: 'layouts.markdown-docs');
    }

    public function testMarkdownRoutesCanBeResolvedFromTheCompiledRouteCollection()
    {
        $this->layout();

        $this->markdown('docs/index.md', '# Home');
        $this->markdown('docs/guides/install.md', '# Install');

        Route::markdown('docs', path: 'docs', layout: 'layouts.markdown-docs')
            ->name('docs');

        $this->app['router']->setCompiledRoutes($this->app['router']->getRoutes()->compile());

        $this->get('/docs')
            ->assertOk()
            ->assertSee('<h1>Home</h1>', false);

        $this->get('/docs/guides/install')
            ->assertOk()
            ->assertSee('<h1>Install</h1>', false);
    }

    public function testMarkdownPagesMayBeRetrievedForLinkGeneration()
    {
        $this->layout();

        $this->markdown('docs/index.md', <<<'MARKDOWN'
---
title: Documentation
description: Welcome to the docs.
---

# Home
MARKDOWN);

        $this->markdown('docs/install.md', <<<'MARKDOWN'
---
title: Install
description: Install the application.
---

# Install
MARKDOWN);

        Route::markdown('help', path: 'docs', layout: 'layouts.markdown-docs')
            ->name('docs');

        $pages = Route::markdownPages('docs');

        $this->assertCount(2, $pages);
        $this->assertSame('help', $pages[0]->uri);
        $this->assertSame('/help', $pages[0]->url);
        $this->assertSame('docs.index', $pages[0]->name);
        $this->assertSame('docs/index.md', $pages[0]->path);
        $this->assertSame('Documentation', $pages[0]->title);
        $this->assertSame('Welcome to the docs.', $pages[0]->meta['description']);

        $this->assertSame('help/install', $pages[1]->uri);
        $this->assertSame('/help/install', $pages[1]->url);
        $this->assertSame('docs.install', $pages[1]->name);
        $this->assertSame('Install', $pages[1]->title);

        $this->markdown('docs/install.md', <<<'MARKDOWN'
---
title: Updated Install
---

# Install
MARKDOWN);

        $this->assertSame('Updated Install', Route::markdownPages('docs')[1]->title);
    }

    public function testMarkdownRenderingMayBeCustomizedThroughTheContainer()
    {
        $this->layout();

        $this->app->afterResolving(Markdown::class, function (Markdown $markdown) {
            $markdown->configure([
                'default_attributes' => [
                    Heading::class => [
                        'class' => 'docs-heading',
                    ],
                ],
            ])->extend(new DefaultAttributesExtension);
        });

        $this->markdown('docs/index.md', '# Home');

        Route::markdown('docs', path: 'docs', layout: 'layouts.markdown-docs');

        $this->get('/docs')
            ->assertOk()
            ->assertSee('<h1 class="docs-heading">Home</h1>', false);
    }

    public function testMarkdownRoutesMayBeSmokeTested()
    {
        $this->layout();

        $this->markdown('docs/index.md', '# Home');
        $this->markdown('docs/install.md', '# Install');

        $this->app['router']->aliasMiddleware('block-markdown', BlockMarkdown::class);

        Route::markdown('docs', path: 'docs', layout: 'layouts.markdown-docs')
            ->middleware('block-markdown');

        $this->assertMarkdownRoutesRender();
    }

    public function testMarkdownRouteSmokeTestIncludesTheFailingSourcePath()
    {
        $this->layout(<<<'BLADE'
<html>
    <body>
        <div>{{ $page->path === 'docs/missing.md' ? $page->meta['date']->format('Y-m-d') : 'ok' }}</div>
        <main>{!! $content !!}</main>
    </body>
</html>
BLADE);

        $this->markdown('docs/index.md', <<<'MARKDOWN'
---
date: 2026-03-20
---

# Home
MARKDOWN);

        $this->markdown('docs/missing.md', '# Missing');

        Route::markdown('docs', path: 'docs', layout: 'layouts.markdown-docs');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Markdown route [docs/missing] from [docs/missing.md] failed to render successfully.');

        $this->assertMarkdownRoutesRender();
    }

    protected function markdown($path, $contents)
    {
        $path = resource_path('markdown/'.$path);

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
    }

    protected function layout($contents = null)
    {
        $path = resource_path('views/layouts/markdown-docs.blade.php');

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents ?? <<<'BLADE'
<html>
    <head>
        <title>{{ $page->title ?? 'Docs' }}</title>
    </head>
    <body>
        <div>{{ $page->uri }}</div>
        <div>{{ $page->path }}</div>
        <div>{{ $page->description }}</div>
        <main>{!! $content !!}</main>
    </body>
</html>
BLADE);
    }
}

class BlockMarkdown
{
    public function handle(Request $request, $next)
    {
        abort(418);
    }
}
