<?php

namespace Illuminate\Routing;

use Illuminate\Support\HtmlString;

class RenderedMarkdown
{
    /**
     * Create a new rendered markdown instance.
     *
     * @param  \Illuminate\Support\HtmlString  $content
     * @param  array  $meta
     */
    public function __construct(
        public HtmlString $content,
        public array $meta = [],
    ) {
    }
}
