<?php
namespace ADmad\Glide\Routing\Filter;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Routing\DispatcherFilter;
use Cake\Utility\Hash;
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

        $path = urldecode($request->url);

        if (Configure::read('Glide.secureUrls')) {
            SignatureFactory::create(Security::salt())
                ->validateRequest('/' . $path, $request->query);
        }

        $server = ServerFactory::create(Configure::read('Glide.serverConfig'));

        $cache = Configure::read('Glide.cache');
        if ($cache) {
            $timestamp = $server->getSource()->getTimestamp($server->getSourcePath($path));
            $response->modified($timestamp);

            if (!$response->checkNotModified($request)) {
                $response = $server->getImageResponse($path, $request->query);
            }

            $response->cache($timestamp, $cache);
        } else {
            $response = $server->getImageResponse($path, $request->query);
        }

        $headers = Hash::filter((array)Configure::read('Glide.headers'));
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }
}
