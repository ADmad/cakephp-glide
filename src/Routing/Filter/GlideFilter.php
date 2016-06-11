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
     * Constructor.
     *
     * @param array $config Array of config.
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
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
        $config = &$this->_config;

        $path = urldecode($request->url);

        if (!empty($config['secureUrls'])) {
            SignatureFactory::create(Security::salt())
                ->validateRequest('/' . $path, $request->query);
        }

        $server = ServerFactory::create($config['serverConfig']);

        if (empty($config['serverConfig']['response'])) {
            $server->setResponseFactory(new CakeResponseFactory());
        }

        if (!empty($config['cache'])) {
            $timestamp = $server->getSource()->getTimestamp($server->getSourcePath($path));
            $response->modified($timestamp);

            if (!$response->checkNotModified($request)) {
                $response = $this->_getResponse($server, $path, $request, $response);
                $response->cache($timestamp, $config['cache']);
            }
        } else {
            $response = $this->_getResponse($server, $path, $request, $response);
        }

        if (!empty($config['headers'])) {
            foreach ($config['headers'] as $key => $value) {
                $response->header($key, $value);
            }
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
     * @return \Cake\Network\Response Response instance.
     */
    protected function _getResponse(Server $server, $path, Request $request, Response $response)
    {
        return $server->getImageResponse($path, $request->query);
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
