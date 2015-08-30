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
     * @return \Cake\Network\Response Response instance.
     */
    public function beforeDispatch(Event $event)
    {
        $request = $event->data['request'];
        $path = '/' . urldecode($request->url);

        if (Configure::read('Glide.secureUrls')) {
            SignatureFactory::create(Security::salt())
                ->validateRequest($path, $request->query);
        }

        $server = ServerFactory::create(Configure::read('Glide.serverConfig'));
        $response = $server->getImageResponse($path, $request->query);

        $headers = Hash::filter((array)Configure::read('Glide.headers'));
        if (!empty($headers['Expires']) && $headers['Expires'] === true) {
            $headers['Expires'] = date_create('+1 years')->format('D, d M Y H:i:s') . ' GMT';
        }
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }
}
