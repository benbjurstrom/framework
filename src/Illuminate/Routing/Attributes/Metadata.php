<?php

namespace Illuminate\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Metadata
{
    /**
     * Create a new metadata attribute instance.
     *
     * @param  array  $metadata
     */
    public function __construct(
        public array $metadata,
    ) {
    }
}
