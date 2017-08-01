<?php
namespace ADmad\Glide\TestCase\Middleware;

use ADmad\Glide\Middleware\GlideMiddleware;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\NotFoundException;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use League\Glide\Signatures\Signature;
use Zend\Diactoros\Stream;

class GlideMiddlewareTest extends TestCase
{
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

    public function testBadRequestException()
    {
        $this->config['security']['secureUrls'] = true;

        $signature = new Signature(Security::salt());
        $sig = $signature->generateSignature('/images/cake logo.png', ['w' => 100]);

        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake%20logo.png',
        ]);

        $middleware = new GlideMiddleware($this->config);

        $this->expectException(BadRequestException::class);
        $middleware($request, $this->response, $this->next);
    }

    public function testNotFoundException()
    {
        $middleware = new GlideMiddleware($this->config);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/non-existent.jpg',
        ]);

        $this->expectException(NotFoundException::class);
        $middleware($request, $this->response, $this->next);
    }

    public function testExceptionEventListener()
    {
        $middleware = new GlideMiddleware($this->config);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/non-existent.jpg',
        ]);

        EventManager::instance()->on(GlideMiddleware::FAILURE_EVENT, function ($event) {
            return (new Response())
                ->withFile(PLUGIN_ROOT . '/test_app/webroot/upload/cake-logo.png');
        });

        $response = $middleware($request, $this->response, $this->next);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/png', $response->getHeaderLine('Content-Type'));
    }
}
