<?php
namespace ADmad\Glide\TestCase\Routing\Filter;

use ADmad\Glide\Responses\CakeResponseFactory;
use ADmad\Glide\Routing\Filter\GlideFilter;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use League\Glide\Signatures\Signature;

class GlideFilterTest extends TestCase
{
    public function setUp()
    {
        Configure::write('Glide', [
            'serverConfig' => [
                'base_url' => '/images/',
                'source' => PLUGIN_ROOT . '/test_app/webroot/upload',
                'cache' => TMP . '/cache',
                'response' => new CakeResponseFactory,
            ],
            'secureUrls' => false,
            'headers' => [
                'Cache-Control' => 'max-age=31536000, public',
                'Expires' => true
            ]
        ]);

        $request = new Request;
        $request->url = 'images/cake-logo.png';
        $request->query = ['w' => 100];

        $response = new Response;
        $this->event = new Event(
            'Dispatcher.beforeDispatch',
            null,
            compact('request', 'response')
        );

        Security::salt('salt');
    }

    public function tearDown()
    {
        Configure::delete('Glide');
    }

    public function testBeforeDispatch()
    {
        $response = (new GlideFilter)->beforeDispatch($this->event);

        $this->assertInstanceOf('Cake\Network\Response', $response);
        $this->assertTrue(is_callable($response->body()));
        $this->assertTrue(is_dir(TMP . '/cache/cake-logo.png'));

        $headers = $response->header();
        $this->assertEquals('11994', $headers['Content-Length']);
        $this->assertEquals('max-age=31536000, public', $headers['Cache-Control']);
    }

    public function testSecureUrl()
    {
        Configure::write('Glide.secureUrls', true);

        $signature = new Signature(Security::salt());
        $sig = $signature->generateSignature('/images/cake logo.png', ['w' => 100]);

        $this->event->data['request']->url = 'images/cake%20logo.png';
        $this->event->data['request']->query = ['w' => 100, 's' => $sig];
        $response = (new GlideFilter)->beforeDispatch($this->event);

        $this->assertInstanceOf('Cake\Network\Response', $response);
    }
}
