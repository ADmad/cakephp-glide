<?php
namespace ADmad\Glide\Responses;

use Cake\Network\Response;
use League\Flysystem\FilesystemInterface;
use League\Glide\Responses\ResponseFactoryInterface;

class CakeResponseFactory implements ResponseFactoryInterface
{
    /**
     * Create the response.
     *
     * @param \League\Flysystem\FilesystemInterface $cache The cache file system.
     * @param string $path The cached file path.
     *
     * @return \Cake\Network\Response The response object.
     */
    public function create(FilesystemInterface $cache, $path)
    {
        $stream = $cache->readStream($path);

        $contentType = $cache->getMimetype($path);
        $contentLength = (string)$cache->getSize($path);

        $response = new Response();
        $response->type($contentType);
        $response->header('Content-Length', $contentLength);
        $response->body(function () use ($stream) {
            rewind($stream);
            fpassthru($stream);
            fclose($stream);
        });

        return $response;
    }
}
