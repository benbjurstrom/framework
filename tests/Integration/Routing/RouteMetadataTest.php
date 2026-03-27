<?php

namespace Illuminate\Tests\Integration\Routing;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Tests\Integration\Routing\Fixtures\RouteMetadataController;
use Illuminate\Tests\Integration\Routing\Fixtures\RouteMetadataPost;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class RouteMetadataTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['router']->defaultMetadata([
            'title' => 'Laravel',
            'robots' => 'index,follow',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('route_metadata_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt');
            $table->timestamps();
        });

        $this->beforeApplicationDestroyed(function () {
            Schema::dropIfExists('route_metadata_posts');
        });
    }

    public function test_route_metadata_is_resolved_during_request_handling(): void
    {
        Route::metadata([
            'robots' => 'noindex,nofollow',
            'section' => 'marketing',
        ])->group(function () {
            Route::get('/blog/{post:slug}', [RouteMetadataController::class, 'show'])
                ->name('posts.show')
                ->middleware(SubstituteBindings::class)
                ->metadata([
                    'title' => 'Blog',
                    'description' => 'Posts',
                ]);
        });

        $post = RouteMetadataPost::create([
            'title' => 'My Great Post',
            'slug' => 'my-great-post',
            'excerpt' => 'A great post excerpt',
        ]);

        $this->get("/blog/{$post->slug}")
            ->assertOk()
            ->assertJson([
                'title' => 'My Great Post',
                'description' => 'A great post excerpt',
                'robots' => 'noindex,nofollow',
                'section' => 'blog',
            ])
            ->assertRouteMetadata([
                'title' => 'My Great Post',
                'robots' => 'noindex,nofollow',
                'section' => 'blog',
                'description' => 'A great post excerpt',
            ]);
    }

    public function test_method_level_metadata_attribute_overrides_class_metadata(): void
    {
        Route::get('/blog/{post:slug}/edit', [RouteMetadataController::class, 'edit'])
            ->name('posts.edit')
            ->middleware(SubstituteBindings::class);

        $post = RouteMetadataPost::create([
            'title' => 'My Great Post',
            'slug' => 'my-great-post',
            'excerpt' => 'A great post excerpt',
        ]);

        $this->get("/blog/{$post->slug}/edit")
            ->assertOk()
            ->assertJson([
                'title' => 'Edit Post',
                'robots' => 'noindex,nofollow',
                'section' => 'blog',
            ])
            ->assertRouteMetadata([
                'title' => 'Edit Post',
                'robots' => 'noindex,nofollow',
                'section' => 'blog',
            ]);
    }

    public function test_cached_routes_preserve_declared_and_runtime_metadata(): void
    {
        $this->defineCacheRoutes(sprintf(<<<'PHP'
<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use %s;
use %s;

Route::metadata([
    'robots' => 'noindex,nofollow',
])->group(function () {
    Route::get('/cached-blog/{post:slug}', [%s::class, 'show'])
        ->name('cached.posts.show')
        ->middleware(SubstituteBindings::class)
        ->metadata([
            'title' => 'Blog',
            'description' => 'Posts',
        ]);
});
PHP, RouteMetadataPost::class, RouteMetadataController::class, RouteMetadataController::class));

        $post = RouteMetadataPost::create([
            'title' => 'Cached Post',
            'slug' => 'cached-post',
            'excerpt' => 'Cached post excerpt',
        ]);

        $this->get("/cached-blog/{$post->slug}")
            ->assertOk()
            ->assertRouteMetadata([
                'title' => 'Cached Post',
                'robots' => 'noindex,nofollow',
                'section' => 'blog',
                'description' => 'Cached post excerpt',
            ]);
    }

    public function test_route_show_command_displays_declared_and_resolved_metadata(): void
    {
        Route::metadata([
            'robots' => 'noindex,nofollow',
        ])->group(function () {
            Route::get('/blog/{post:slug}', [RouteMetadataController::class, 'show'])
                ->name('posts.show')
                ->middleware(SubstituteBindings::class)
                ->metadata([
                    'title' => 'Blog',
                    'description' => 'Posts',
                ]);
        });

        $post = RouteMetadataPost::create([
            'title' => 'My Great Post',
            'slug' => 'my-great-post',
            'excerpt' => 'A great post excerpt',
        ]);

        $this->artisan('route:show', [
            'name' => 'posts.show',
            'params' => ["post={$post->slug}"],
        ])
            ->expectsOutputToContain('Declared Metadata')
            ->expectsOutputToContain('Resolved Metadata')
            ->expectsOutputToContain('My Great Post')
            ->expectsOutputToContain('<- changed')
            ->assertExitCode(0);
    }

    public function test_route_show_command_warns_when_route_parameters_are_not_supplied(): void
    {
        Route::get('/blog/{post:slug}', [RouteMetadataController::class, 'show'])
            ->name('posts.show')
            ->middleware(SubstituteBindings::class)
            ->metadata([
                'title' => 'Blog',
            ]);

        $this->artisan('route:show', [
            'name' => 'posts.show',
        ])
            ->expectsOutputToContain('Supply route parameters to see resolved metadata.')
            ->assertExitCode(0);
    }

    public function test_route_show_command_reports_missing_route_parameters(): void
    {
        Route::get('/blog/{post}/{comment}', function () {
            return 'ok';
        })->name('comments.show');

        $this->artisan('route:show', [
            'name' => 'comments.show',
            'params' => ['post=1'],
        ])
            ->expectsOutputToContain('Missing route parameters: comment.')
            ->assertExitCode(1);
    }

    public function test_route_show_command_fails_gracefully_when_a_model_cannot_be_resolved(): void
    {
        Route::get('/blog/{post:slug}', [RouteMetadataController::class, 'show'])
            ->name('posts.show')
            ->middleware(SubstituteBindings::class)
            ->metadata([
                'title' => 'Blog',
            ]);

        $this->artisan('route:show', [
            'name' => 'posts.show',
            'params' => ['post=missing-post'],
        ])
            ->expectsOutputToContain('Unable to resolve the supplied route parameters.')
            ->assertExitCode(1);
    }
}
