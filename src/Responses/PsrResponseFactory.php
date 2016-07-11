<?php
namespace ADmad\Glide\Responses;

use League\Flysystem\FilesystemInterface;
use League\Glide\Filesystem\FilesystemException;
use League\Glide\Responses\ResponseFactoryInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class PsrResponseFactory implements ResponseFactoryInterface
{
    /**
     * Create response.
     *
     * @param \League\Flysystem\FilesystemInterface $cache Cache file system.
     * @param string $path Cached file path.
     *
     * @return \Psr\Http\Message\ResponseInterface Response object.
     */
    public function create(FilesystemInterface $cache, $path)
    {
        $stream = new Stream($cache->readStream($path));

        $contentType = $cache->getMimetype($path);
        $contentLength = (string)$cache->getSize($path);

        if ($contentType === false) {
            throw new FilesystemException('Unable to determine the image content type.');
        }

        if ($contentLength === false) {
            throw new FilesystemException('Unable to determine the image content length.');
        }

        return (new Response())->withBody($stream)
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Length', $contentLength);
    }
}
