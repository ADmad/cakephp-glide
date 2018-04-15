<?php
namespace ADmad\Glide\TestCase\Middleware;

use ADmad\Glide\Exception\ResponseException;
use ADmad\Glide\Exception\SignatureException;
use ADmad\Glide\Middleware\GlideMiddleware;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use League\Glide\ServerFactory;
use League\Glide\Signatures\Signature;
use Zend\Diactoros\Stream;

class GlideMiddlewareTest extends TestCase
{
    public function setUp()
    {
        $this->config = [
            'server' => [
                'source' => PLUGIN_ROOT . '/test_app/webroot/upload',
                'cache' => TMP . 'cache',
            ],
        ];

        $this->request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake-logo.png?w=100',
        ]);
        $this->response = new Response();
        $this->next = function ($req, $res) {
            return $res;
        };

        Security::setSalt('salt');

        exec('rm -rf ' . TMP . 'cache/cake-logo.png');
        clearstatcache(false, TMP . 'cache/cake-logo.png');
    }

    public function testNormalResponse()
    {
        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($this->request, $this->response, $this->next);

        $this->assertTrue(is_dir(TMP . 'cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertTrue(isset($headers['Content-Length']));
    }

    public function testServerCallable()
    {
        $config = $this->config;
        $config['server'] = function () {
            return ServerFactory::create(
                $this->config['server'] + ['base_url' => '/images']
            );
        };

        $middleware = new GlideMiddleware($config);
        $response = $middleware($this->request, $this->response, $this->next);

        $this->assertTrue(is_dir(TMP . 'cache/cake-logo.png'));
    }

    public function testOriginalPassThrough()
    {
        $fileSize = filesize(PLUGIN_ROOT . '/test_app/webroot/upload/cake-logo.png');

        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake-logo.png',
        ]);

        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($request, $this->response, $this->next);

        $this->assertTrue(is_dir(TMP . 'cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertNotSame(
            $fileSize,
            (int)$headers['Content-Length'][0],
            'Content length shouldnt be same as original filesize since glide always generates new file.'
        );

        exec('rm -rf ' . TMP . 'cache/cake-logo.png');
        clearstatcache(false, TMP . 'cache/cake-logo.png');

        $middleware = new GlideMiddleware($this->config + ['originalPassThrough' => true]);
        $response = $middleware($request, $this->response, $this->next);

        $this->assertFalse(is_dir(TMP . 'cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertSame($fileSize, (int)$headers['Content-Length'][0]);
    }

    public function testPathConfig()
    {
        $middleware = new GlideMiddleware(['path' => '/img'] + $this->config);
        $response = $middleware($this->request, $this->response, $this->next);

        $this->assertFalse(is_dir(TMP . 'cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertFalse(isset($headers['Content-Length']));
    }

    public function testSecureUrl()
    {
        $this->config['security']['secureUrls'] = true;

        $signature = new Signature(Security::getSalt());
        $sig = $signature->generateSignature('/images/cake logo.png', ['w' => 100]);

        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake%20logo.png',
            'QUERY_STRING' => 'w=100&s=' . $sig,
        ]);

        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($request, $this->response, $this->next);

        $this->assertTrue(is_dir(TMP . 'cache/cake logo.png'));
    }

    public function testCache()
    {
        $middleware = new GlideMiddleware($this->config);
        $response = $middleware($this->request, $this->response, $this->next);

        $this->assertTrue(is_dir(TMP . 'cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertTrue($response->getBody() instanceof Stream);
        $this->assertTrue(isset($headers['Last-Modified']));
        $this->assertTrue(isset($headers['Expires']));

        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake-logo.png?w=100',
            'HTTP_IF_MODIFIED_SINCE' => $headers['Last-Modified'][0],
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

    public function testSignatureException()
    {
        $this->config['security']['secureUrls'] = true;

        $signature = new Signature(Security::getSalt());
        $sig = $signature->generateSignature('/images/cake logo.png', ['w' => 100]);

        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake%20logo.png',
        ]);

        $middleware = new GlideMiddleware($this->config);

        $this->expectException(SignatureException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Signature is missing.');
        $middleware($request, $this->response, $this->next);
    }

    public function testResponseException()
    {
        $middleware = new GlideMiddleware($this->config);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/non-existent.jpg',
        ]);

        $this->expectException(ResponseException::class);
        $middleware($request, $this->response, $this->next);
    }

    public function testExceptionEventListener()
    {
        $middleware = new GlideMiddleware($this->config);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/non-existent.jpg',
        ]);

        EventManager::instance()->on(GlideMiddleware::RESPONSE_FAILURE_EVENT, function ($event) {
            return (new Response())
                ->withFile(PLUGIN_ROOT . '/test_app/webroot/upload/cake-logo.png');
        });

        $response = $middleware($request, $this->response, $this->next);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/png', $response->getHeaderLine('Content-Type'));
    }
}
