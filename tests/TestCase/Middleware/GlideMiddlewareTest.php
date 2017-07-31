<?php
namespace ADmad\Glide\TestCase\Middleware;

use ADmad\Glide\Event;
use ADmad\Glide\Events;
use ADmad\Glide\Middleware\GlideMiddleware;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use League\Flysystem\FileNotFoundException;
use League\Glide\Signatures\Signature;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class GlideMiddlewareTest extends TestCase
{
    protected $config;

    protected $request;

    protected $response;

    protected $next;

    public function setUp()
    {
        $this->config = [
            'server' => [
                'source' => PLUGIN_ROOT . '/test_app/webroot/upload',
                'cache' => TMP . '/cache',
            ],
        ];

        $this->request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake-logo.png?w=100',
        ]);
        $this->response = new Response();
        $this->next = function ($req, $res) {
            return $res;
        };

        Security::salt('salt');
    }

    public function tearDown()
    {
        parent::tearDown();

        exec('rm -rf ' . TMP . '/cache/cake-logo.png');
        clearstatcache(false, TMP . '/cache/cake-logo.png');
    }

    public function testNormalResponse()
    {
        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($this->request, $this->response, $this->next);

        $this->assertTrue(is_dir(TMP . '/cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertTrue(isset($headers['Content-Length']));
    }

    public function testSecureUrl()
    {
        $this->config['security']['secureUrls'] = true;

        $signature = new Signature(Security::salt());
        $sig = $signature->generateSignature('/images/cake logo.png', ['w' => 100]);

        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake%20logo.png',
            'QUERY_STRING' => 'w=100&s=' . $sig,
        ]);

        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($request, $this->response, $this->next);

        $this->assertTrue(is_dir(TMP . '/cache/cake logo.png'));
    }

    public function testCache()
    {
        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($this->request, $this->response, $this->next);

        $this->assertTrue(is_dir(TMP . '/cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertTrue($response->getBody() instanceof Stream);
        $this->assertTrue(isset($headers['Last-Modified']));
        $this->assertTrue(isset($headers['Expires']));

        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake-logo.png?w=100',
            'HTTP_IF_MODIFIED_SINCE' => $headers['Last-Modified'],
        ]);

        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($request, $this->response, $this->next);

        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEquals('', $response->getBody()->getContents());
        $this->assertFalse(isset($response->getHeaders()['Expires']));
    }

    public function testHeaders()
    {
        $this->config['headers'] = [
            'X-Custom' => 'some-value',
        ];

        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($this->request, $this->response, $this->next);
        $this->assertEquals('some-value', $response->getHeaders()['X-Custom'][0]);
    }

    public function testIgnoreException()
    {
        $middleware = new GlideMiddleware($this->config);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/a-not-exists-image',
        ]);
        try {
            $response = $middleware($request, $this->response, $this->next);
            $this->fail();
        } catch (\Exception $exception) {
            $this->assertInstanceOf(FileNotFoundException::class, $exception);
        }
        $middleware->eventManager()->on(Events::EXCEPTION_RAISED, function(Event $event){
            $event->ignoreException();
        });
        $response = $middleware($request, $this->response, $this->next);
        $this->assertEquals($this->response, $response);
    }

    public function testCustomizeResponse()
    {
        $middleware = new GlideMiddleware($this->config);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/a-not-exists-image',
        ]);
        $middleware->eventManager()->on(Events::EXCEPTION_RAISED, function(Event $event) use (&$expectedResponse){
            $expectedResponse = new Response('php://memory', 404);
            $event->setResponse($expectedResponse);
        });
        $response = $middleware($request, $this->response, $this->next);
        $this->assertEquals($expectedResponse, $response);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
