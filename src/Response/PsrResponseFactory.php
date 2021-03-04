<?php
declare(strict_types=1);

namespace ADmad\Glide\Response;

use ADmad\Glide\Exception\ResponseException;
use Cake\Http\Response;
use Laminas\Diactoros\Stream;
use League\Flysystem\FilesystemOperator;
use League\Glide\Responses\ResponseFactoryInterface;

class PsrResponseFactory implements ResponseFactoryInterface
{
    /**
     * Create response.
     *
     * @param \League\Flysystem\FilesystemOperator $cache Cache file system.
     * @param string $path Cached file path.
     * @return \Psr\Http\Message\ResponseInterface Response object.
     */
    public function create(FilesystemOperator $cache, $path)
    {
        $resource = $cache->readStream($path);
        if ($resource === false) {
            throw new ResponseException();
        }
        $stream = new Stream($resource);

        $contentType = $cache->mimeType($path);
        $contentLength = $cache->fileSize($path);

        return (new Response())->withBody($stream)
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Length', (string)$contentLength);
    }
}
