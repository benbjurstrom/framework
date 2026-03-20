<?php

namespace Illuminate\Routing;

use Closure;
use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class Markdown
{
    /**
     * The CommonMark configuration values.
     *
     * @var array
     */
    protected $config = [
        'allow_unsafe_links' => false,
    ];

    /**
     * The registered CommonMark extensions and environment customizers.
     *
     * @var array<int, callable|object|string>
     */
    protected $extensions = [];

    /**
     * Parse the given markdown into HTML and metadata.
     *
     * @param  string  $markdown
     * @return \Illuminate\Routing\RenderedMarkdown
     */
    public function parse($markdown)
    {
        $parsed = $this->frontMatterDocument($markdown);

        return new RenderedMarkdown(
            new HtmlString($this->converter()->convert($parsed->getContent())->getContent()),
            (array) ($parsed->getFrontMatter() ?? []),
        );
    }

    /**
     * Render the given markdown into HTML.
     *
     * @param  string  $markdown
     * @return \Illuminate\Support\HtmlString
     */
    public function render($markdown)
    {
        return $this->parse($markdown)->content;
    }

    /**
     * Extract the frontmatter from the given markdown.
     *
     * @param  string  $markdown
     * @return array
     */
    public function frontMatter($markdown)
    {
        return (array) ($this->frontMatterDocument($markdown)->getFrontMatter() ?? []);
    }

    /**
     * Merge the given CommonMark configuration.
     *
     * @param  array  $config
     * @return $this
     */
    public function configure(array $config)
    {
        $this->config = array_replace_recursive($this->config, $config);

        return $this;
    }

    /**
     * Register a CommonMark extension or environment customizer.
     *
     * @param  callable|object|string  $extension
     * @return $this
     */
    public function extend($extension)
    {
        $this->extensions[] = $extension;

        return $this;
    }

    /**
     * Get a markdown converter instance.
     *
     * @return \League\CommonMark\MarkdownConverter
     */
    protected function converter()
    {
        $environment = new Environment($this->config);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension);

        foreach ($this->extensions as $extension) {
            if ($extension instanceof Closure || is_callable($extension)) {
                $extension($environment);

                continue;
            }

            $environment->addExtension(is_string($extension) ? new $extension : $extension);
        }

        return new MarkdownConverter($environment);
    }

    /**
     * Parse the frontmatter document for the given markdown.
     *
     * @param  string  $markdown
     * @return mixed
     */
    protected function frontMatterDocument($markdown)
    {
        $frontMatter = new FrontMatterExtension;

        return $frontMatter->getFrontMatterParser()->parse($markdown);
    }
}
