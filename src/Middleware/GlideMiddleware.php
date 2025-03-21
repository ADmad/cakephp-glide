<?php
declare(strict_types=1);

namespace ADmad\Glide\Middleware;

use ADmad\Glide\Exception\ResponseException;
use ADmad\Glide\Exception\SignatureException;
use ADmad\Glide\Response\PsrResponseFactory;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventManagerInterface;
use Cake\Http\Response;
use Cake\Utility\Security;
use Exception;
use Laminas\Diactoros\Stream;
use League\Glide\Server;
use League\Glide\ServerFactory;
use League\Glide\Signatures\SignatureFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @implements \Cake\Event\EventDispatcherInterface<\ADmad\Glide\Middleware\GlideMiddleware>
 */
class GlideMiddleware implements MiddlewareInterface, EventDispatcherInterface
{
    /**
     * @use \Cake\Event\EventDispatcherTrait<\ADmad\Glide\Middleware\GlideMiddleware>
     */
    use EventDispatcherTrait;

    use InstanceConfigTrait;

    public const RESPONSE_FAILURE_EVENT = 'Glide.response_failure';

    /**
     * Default config.
     *
     * @var array
     */
    protected array $_defaultConfig = [
        'path' => null,
        'cacheTime' => '+1 days',
        'server' => [
            'base_url' => '/images/',
            'response' => null,
        ],
        'security' => [
            'secureUrls' => false,
            'signKey' => null,
        ],
        'headers' => null,
        'allowedParams' => null,
        'originalPassThrough' => false,
    ];

    /**
     * Image path.
     *
     * @var string
     */
    protected string $_path = '';

    /**
     * Constructor.
     *
     * @param array $config Array of config.
     * @param \Cake\Event\EventManagerInterface|null $eventManager An event manager if you want to inject one.
     */
    public function __construct(array $config = [], ?EventManagerInterface $eventManager = null)
    {
        $this->setConfig($config);

        if ($eventManager) {
            $this->setEventManager($eventManager);
        }
    }

    /**
     * Return response with image data.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The request handler.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $this->_path = urldecode($uri->getPath());

        $config = $this->getConfig();

        if ($config['path'] && strpos($this->_path, $config['path']) !== 0) {
            return $handler->handle($request);
        }

        $this->_checkSignature($request);

        $server = $this->_getServer($config['server']);

        $modifiedTime = null;
        if ($config['cacheTime']) {
            $return = $this->_checkModified($request, $server);
            if ($return === null) {
                return $handler->handle($request);
            }
            if ($return instanceof ResponseInterface) {
                return $return;
            }
            $modifiedTime = $return;
        }

        $response = $this->_getResponse($request, $server);
        if ($response === null) {
            return $handler->handle($request);
        }

        if ($config['cacheTime']) {
            /** @psalm-suppress PossiblyNullArgument */
            $response = $this->_withCacheHeaders(
                $response,
                $config['cacheTime'],
                $modifiedTime,
            );
        }

        $response = $this->_withCustomHeaders($response);

        return $response;
    }

    /**
     * Get glide server instance.
     *
     * @param callable|array $config Config array or callable.
     * @return \League\Glide\Server
     */
    protected function _getServer(callable|array $config): Server
    {
        if (is_array($config)) {
            return ServerFactory::create($config);
        }

        return $config();
    }

    /**
     * Check signature token if secure URLs are enabled.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @throws \ADmad\Glide\Exception\SignatureException
     * @return void
     */
    protected function _checkSignature(ServerRequestInterface $request): void
    {
        if (!$this->getConfig('security.secureUrls')) {
            return;
        }

        $signKey = $this->getConfig('security.signKey') ?: Security::getSalt();
        try {
            SignatureFactory::create($signKey)->validateRequest(
                $this->_path,
                $request->getQueryParams(),
            );
        } catch (Exception $exception) {
            throw new SignatureException($exception->getMessage(), null, $exception);
        }
    }

    /**
     * Get file's modified time.
     *
     * After comparing with "If-Modified-Since" either return modified time or
     * response with 304 Not Modified status.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \League\Glide\Server $server Glide server.
     * @return \Psr\Http\Message\ResponseInterface|int|null
     */
    protected function _checkModified(ServerRequestInterface $request, Server $server): ResponseInterface|int|null
    {
        try {
            $modifiedTime = $server->getSource()
                ->lastModified($server->getSourcePath($this->_path));
        } catch (Exception $exception) {
            return $this->_handleException($request, $exception);
        }

        if ($this->_isNotModified($request, $modifiedTime)) {
            $response = new Response(['status' => 304]);
            $response = $this->_withCustomHeaders($response);

            return $response->withHeader('Last-Modified', (string)$modifiedTime);
        }

        return $modifiedTime;
    }

    /**
     * Get response instance which contains image to render.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \League\Glide\Server $server Glide server.
     * @return \Psr\Http\Message\ResponseInterface|null Response instance on success else null
     */
    protected function _getResponse(ServerRequestInterface $request, Server $server): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $allowedParams = $this->getConfig('allowedParams');
        if ($allowedParams) {
            $queryParams = array_intersect_key($queryParams, array_flip($allowedParams));
        }

        if (
            (empty($queryParams)
                || (count($queryParams) === 1 && isset($queryParams['s']))
            )
            && $this->getConfig('originalPassThrough')
        ) {
            try {
                $response = $this->_passThrough($request, $server);
            } catch (Exception $exception) {
                return $this->_handleException($request, $exception);
            }

            return $response;
        }

        /** @var \League\Glide\Responses\ResponseFactoryInterface|null $responseFactory */
        $responseFactory = $server->getResponseFactory();
        if ($responseFactory === null) {
            $server->setResponseFactory(new PsrResponseFactory());
        }

        try {
            $response = $server->getImageResponse($this->_path, $queryParams);
        } catch (Exception $exception) {
            return $this->_handleException($request, $exception);
        }

        return $response;
    }

    /**
     * Generate response using original image.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \League\Glide\Server $server Glide server.
     * @throws \ADmad\Glide\Exception\ResponseException
     * @return \Psr\Http\Message\ResponseInterface Response instance
     */
    protected function _passThrough(ServerRequestInterface $request, Server $server): ?ResponseInterface
    {
        $source = $server->getSource();
        $path = $server->getSourcePath($this->_path);

        $resource = $source->readStream($path);
        $stream = new Stream($resource);

        $contentType = $source->mimeType($path);
        $contentLength = $source->fileSize($path);

        /** @psalm-suppress PossiblyFalseArgument */
        return (new Response())->withBody($stream)
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Length', (string)$contentLength);
    }

    /**
     * Compare file's modfied time with "If-Modified-Since" header.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request to check.
     * @param string|int $modifiedTime Last modified time of file.
     * @return bool
     */
    protected function _isNotModified(ServerRequestInterface $request, string|int $modifiedTime): bool
    {
        $modifiedSince = $request->getHeaderLine('If-Modified-Since');
        if (!$modifiedSince) {
            return false;
        }

        return strtotime($modifiedSince) === (int)$modifiedTime;
    }

    /**
     * Return response instance with caching headers.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param string $cacheTime Cache time.
     * @param string|int $modifiedTime Modified time.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function _withCacheHeaders(
        ResponseInterface $response,
        string $cacheTime,
        string|int $modifiedTime,
    ): ResponseInterface {
        /** @var int $expire */
        $expire = strtotime($cacheTime);
        $maxAge = $expire - time();

        return $response
            ->withHeader('Cache-Control', 'public,max-age=' . $maxAge)
            ->withHeader('Date', gmdate(DATE_RFC7231, time()))
            ->withHeader('Last-Modified', gmdate(DATE_RFC7231, (int)$modifiedTime))
            ->withHeader('Expires', gmdate(DATE_RFC7231, $expire));
    }

    /**
     * Return response instance with headers specified in config.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function _withCustomHeaders(ResponseInterface $response): ResponseInterface
    {
        foreach ((array)$this->getConfig('headers') as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response;
    }

    /**
     * Handle exception.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request instance.
     * @param \Exception $exception Exception instance.
     * @throws \ADmad\Glide\Exception\ResponseException
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    protected function _handleException(ServerRequestInterface $request, Exception $exception): ?ResponseInterface
    {
        $event = $this->dispatchEvent(
            static::RESPONSE_FAILURE_EVENT,
            compact('request', 'exception'),
        );
        $result = $event->getResult();

        if ($event->isStopped()) {
            return null;
        }

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (Configure::read('debug')) {
            throw $exception;
        }

        throw new ResponseException(null, null, $exception);
    }
}
