<?php
namespace ADmad\Glide\Routing\Filter;

use ADmad\Glide\Responses\CakeResponseFactory;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Routing\DispatcherFilter;
use Cake\Utility\Security;
use League\Glide\ServerFactory;
use League\Glide\Signatures\SignatureFactory;

class GlideFilter extends DispatcherFilter
{
    /**
     * Callback for Routing.beforeDispatch event.
     *
     * @param \Cake\Event\Event $event The event instance.
     *
     * @return \Cake\Network\Response Response instance.
     */
    public function beforeDispatch(Event $event)
    {
        $request = $event->data['request'];
        $response = $event->data['response'];
        $config = Configure::read('Glide');

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
                $response = $server->getImageResponse($path, $request->query);
                $response->cache($timestamp, $config['cache']);
            }
        } else {
            $response = $server->getImageResponse($path, $request->query);
        }

        if (!empty($config['headers'])) {
            foreach ($config['headers'] as $key => $value) {
                $response->header($key, $value);
            }
        }

        $event->stopPropagation();

        return $response;
    }
}
