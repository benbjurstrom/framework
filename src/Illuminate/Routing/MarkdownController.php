<?php

namespace Illuminate\Routing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MarkdownController extends Controller
{
    /**
     * The response factory implementation.
     *
     * @var \Illuminate\Contracts\Routing\ResponseFactory
     */
    protected $response;

    /**
     * The markdown renderer.
     *
     * @var \Illuminate\Routing\Markdown
     */
    protected $markdown;

    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Contracts\Routing\ResponseFactory  $response
     * @param  \Illuminate\Routing\Markdown  $markdown
     */
    public function __construct(ResponseFactory $response, Markdown $markdown)
    {
        $this->response = $response;
        $this->markdown = $markdown;
    }

    /**
     * Invoke the controller.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $route = $request->route();
        $markdown = $route->getAction('markdown');
        $path = $this->markdownPath($markdown['path']);

        if (! is_file($path)) {
            throw new NotFoundHttpException;
        }

        $document = $this->markdown->parse((string) file_get_contents($path));

        return $this->response->view($markdown['layout'], [
            'content' => $document->content,
            'page' => new MarkdownPage($markdown['uri'], $markdown['path'], $document->meta),
        ]);
    }

    /**
     * Resolve the full path for the given markdown file.
     *
     * @param  string  $path
     * @return string
     */
    protected function markdownPath($path)
    {
        $container = Container::getInstance();

        return rtrim($container->resourcePath('markdown'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
