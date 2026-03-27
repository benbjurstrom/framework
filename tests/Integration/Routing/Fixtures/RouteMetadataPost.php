<?php

namespace Illuminate\Tests\Integration\Routing\Fixtures;

use Illuminate\Database\Eloquent\Model;

class RouteMetadataPost extends Model
{
    protected $table = 'route_metadata_posts';

    protected $guarded = [];

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
