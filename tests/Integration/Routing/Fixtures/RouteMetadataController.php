<?php

namespace Illuminate\Tests\Integration\Routing\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Metadata;
use Illuminate\Support\Facades\Route;

#[Metadata(['section' => 'blog'])]
class RouteMetadataController
{
    public function show(Request $request, RouteMetadataPost $post)
    {
        $request->route()->mergeMetadata([
            'title' => $post->title,
            'description' => $post->excerpt,
        ]);

        return Route::currentMetadata();
    }

    #[Metadata(['title' => 'Edit Post', 'robots' => 'noindex,nofollow'])]
    public function edit()
    {
        return Route::currentMetadata();
    }
}
