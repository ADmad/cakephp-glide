<?php
namespace ADmad\Glide\Routing\Filter;

use ADmad\Glide\Responses\CakeResponseFactory;
use Cake\Event\Event;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Routing\DispatcherFilter;
use Cake\Utility\Security;
use League\Glide\Server;
use League\Glide\ServerFactory;
use League\Glide\Signatures\SignatureFactory;

class GlideFilter extends DispatcherFilter
{
    /**
     * Default priority for all methods in this filter.
     *
     * This filter should run before core's `AssetFilter` and `RoutingFilter`.
     *
     * @var int
     */
    protected $_priority = 8;

    /**
     * Default config.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'when' => null,
        'for' => null,
        'priority' => null,
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
        'ignoreException' => false
    ];

    /**
     * Constructor.
     *
     * @param array $config Array of config.
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        if ($this->config('for') === null) {
            $server = $this->config('server');
            if (is_array($server)) {
                $this->config('for', $server['base_url']);
            } else {
                $this->config('for', $server->getBaseUrl());
            }
        }
    }

    /**
     * Callback for Dispatcher.beforeDispatch event.
     *
     * @param \Cake\Event\Event $event The event instance.
     *
     * @return \Cake\Network\Response Response instance.
     */
    public function beforeDispatch(Event $event)
    {
        $request = $event->data['request'];
        $response = $event->data['response'];
        $config = $this->config();

        $path = urldecode($request->url);

        if ($config['security']['secureUrls']) {
            $signKey = $config['security']['signKey'] ?: Security::salt();
            SignatureFactory::create($signKey)->validateRequest(
                '/' . $path,
                $request->query
            );
        }

        $server = $config['server'];
        if (is_array($server)) {
            $server = ServerFactory::create($server);
        }

        if ($server->getResponseFactory() === null) {
            $server->setResponseFactory(new CakeResponseFactory());
        }

        $getResponse = true;
        if ($config['cacheTime']) {
            $timestamp = $server->getSource()->getTimestamp($server->getSourcePath($path));
            $response->modified($timestamp);

            if ($response->checkNotModified($request)) {
                $getResponse = false;
            }
        }

        if ($getResponse) {
            $response = $this->_getResponse($server, $path, $request, $response);
            if ($response === null) {
                return null;
            }

            if ($config['cacheTime']) {
                $response->cache($timestamp, $config['cacheTime']);
            }
        }

        if (!empty($config['headers'])) {
            $response->header($config['headers']);
        }

        return $response;
    }

    /**
     * Get response instance which contains image to render.
     *
     * @param \League\Glide\Server $server Glide server.
     * @param string $path Image path.
     * @param \Cake\Network\Request $request Request instance.
     * @param \Cake\Network\Response $response Response instance.
     *
     * @return \Cake\Network\Response|null Response instance on success else null
     *
     * @throws \Exception
     */
    protected function _getResponse(Server $server, $path, Request $request, Response $response)
    {
        try {
            $response = $server->getImageResponse($path, $request->query);
        } catch (\Exception $e) {
            if ($this->config('ignoreException')) {
                return null;
            }
            throw $e;
        }

        return $response;
    }

    /**
     * Returns the list of events this filter listens to.
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'Dispatcher.beforeDispatch' => [
                'callable' => 'handle',
                'priority' => $this->_config['priority'],
            ],
        ];
    }
}
