<?php
namespace ADmad\Glide\Middleware;

use ADmad\Glide\Responses\PsrResponseFactory;
use Cake\Core\InstanceConfigTrait;
use Cake\Utility\Security;
use League\Glide\Server;
use League\Glide\ServerFactory;
use League\Glide\Signatures\SignatureFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;

class GlideMiddleware
{
    use InstanceConfigTrait;

    /**
     * Default config.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'when' => null,
        'scope' => null,
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
        'ignoreException' => false,
    ];

    /**
     * Image path.
     *
     * @var string
     */
    protected $_path = '';

    /**
     * Glide params for generating thumbnails.
     *
     * @var array
     */
    protected $_params = [];

    /**
     * Constructor.
     *
     * @param array $config Array of config.
     */
    public function __construct($config = [])
    {
        $this->config($config);

        if ($this->config('scope') === null) {
            $server = $this->config('server');
            if (is_array($server)) {
                $this->config('scope', $server['base_url']);
            } else {
                $this->config('scope', $server->getBaseUrl());
            }
        }
    }

    /**
     * Return response with image data.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param callable $next Callback to invoke the next middleware.
     *
     * @return \Psr\Http\Message\ResponseInterface A response
     */
    public function __invoke($request, $response, $next)
    {
        $uri = $request->getUri();
        $this->_path = urldecode($uri->getPath());
        parse_str($uri->getQuery(), $this->_params);

        $config = $this->config();

        if (strpos($this->_path, $config['scope']) !== 0) {
            return $next($request, $response);
        }

        if ($config['security']['secureUrls']) {
            $signKey = $config['security']['signKey'] ?: Security::salt();
            SignatureFactory::create($signKey)->validateRequest(
                $this->_path,
                $this->_params
            );
        }

        $server = $config['server'];
        if (is_array($server)) {
            $server = ServerFactory::create($server);
        }

        if ($config['cacheTime']) {
            try {
                $modifiedTime = $server->getSource()
                    ->getTimestamp($server->getSourcePath($this->_path));
            } catch (\Exception $e) {
                if ($config['ignoreException']) {
                    return $next($request, $response);
                }
                throw $e;
            }

            if ($this->_isNotModified($request, $modifiedTime)) {
                $response = new Response('php://memory', 304);
                $response = $this->_withCustomHeaders($response);

                return $response->withHeader('Last-Modified', (string)$modifiedTime);
            }
        }

        if ($server->getResponseFactory() === null) {
            $server->setResponseFactory(new PsrResponseFactory());
        }
        $response = $this->_getResponse($request, $response, $server);
        if ($response === null) {
            return $next($request, $response);
        }

        if ($config['cacheTime']) {
            $response = $this->_withCacheHeaders(
                $response,
                $config['cacheTime'],
                $modifiedTime
            );
        }

        $response = $this->_withCustomHeaders($response);

        return $response;
    }

    /**
     * Get response instance which contains image to render.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param \League\Glide\Server $server Glide server.
     *
     * @throws \Exception
     *
     * @return \Psr\Http\Message\ResponseInterface|null Response instance on success else null
     */
    protected function _getResponse(ServerRequestInterface $request, ResponseInterface $response, Server $server)
    {
        try {
            $response = $server->getImageResponse($this->_path, $this->_params);
        } catch (\Exception $e) {
            if ($this->config('ignoreException')) {
                return null;
            }
            throw $e;
        }

        return $response;
    }

    /**
     * Check the not modified header.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request to check.
     * @param string|int $modifiedTime Last modified time of file.
     *
     * @return bool
     */
    protected function _isNotModified($request, $modifiedTime)
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
     * @param int $modifiedTime Modified time.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function _withCacheHeaders($response, $cacheTime, $modifiedTime)
    {
        $expire = strtotime($cacheTime);
        $maxAge = $expire - time();

        return $response
            ->withHeader('Cache-Control', 'public,max-age=' . $maxAge)
            ->withHeader('Date', gmdate('D, j M Y G:i:s \G\M\T', time()))
            ->withHeader('Last-Modified', gmdate('D, j M Y G:i:s \G\M\T', $modifiedTime))
            ->withHeader('Expires', gmdate('D, j M Y G:i:s \G\M\T', $expire));
    }

    /**
     * Return response instance with headers specified in config.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function _withCustomHeaders($response)
    {
        if (!empty($this->_config['headers'])) {
            foreach ($this->_config['headers'] as $key => $value) {
                $response = $response->withHeader($key, $value);
            }
        }

        return $response;
    }
}
