<?php
namespace ADmad\Glide\TestCase\Routing\Filter;

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
            ],
        ]);

        $request = new Request();
        $request->url = 'images/cake-logo.png';
        $request->query = ['w' => 100];

        $response = new Response();
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
        $response = (new GlideFilter())->beforeDispatch($this->event);

        $this->assertInstanceOf('Cake\Network\Response', $response);
        $this->assertTrue(is_callable($response->body()));
        $this->assertTrue(is_dir(TMP . '/cache/cake-logo.png'));

        $headers = $response->header();
        $this->assertTrue(isset($headers['Content-Length']));
    }

    public function testSecureUrl()
    {
        Configure::write('Glide.secureUrls', true);

        $signature = new Signature(Security::salt());
        $sig = $signature->generateSignature('/images/cake logo.png', ['w' => 100]);

        $this->event->data['request']->url = 'images/cake%20logo.png';
        $this->event->data['request']->query = ['w' => 100, 's' => $sig];
        $response = (new GlideFilter())->beforeDispatch($this->event);

        $this->assertInstanceOf('Cake\Network\Response', $response);
    }

    public function testCache()
    {
        Configure::write('Glide.cache', '+1 days');

        $response = (new GlideFilter())->beforeDispatch($this->event);
        $this->assertTrue(is_dir(TMP . '/cache/cake-logo.png'));

        $headers = $response->header();
        $this->assertTrue(is_callable($response->body()));
        $this->assertTrue(isset($headers['Last-Modified']));
        $this->assertTrue(isset($headers['Expires']));

        $this->event->data['request']->env('HTTP_IF_MODIFIED_SINCE', $headers['Last-Modified']);
        $response = (new GlideFilter())->beforeDispatch($this->event);
        $this->assertFalse(is_callable($response->body()));
        $this->assertFalse(isset($response->header()['Expires']));
    }

    public function testHeaders()
    {
        Configure::write('Glide.headers', [
            'X-Custom' => 'some-value',
        ]);

        $response = (new GlideFilter())->beforeDispatch($this->event);
        $this->assertEquals('some-value', $response->header()['X-Custom']);
    }
}
