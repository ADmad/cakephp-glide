<?php
declare(strict_types=1);

namespace ADmad\Glide\TestCase\Middleware;

use ADmad\Glide\Exception\ResponseException;
use ADmad\Glide\Exception\SignatureException;
use ADmad\Glide\Middleware\GlideMiddleware;
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Glide\ServerFactory;
use League\Glide\Signatures\Signature;
use TestApp\Http\TestRequestHandler;
use Zend\Diactoros\Stream;

class GlideMiddlewareTest extends TestCase
{
    public function setUp(): void
    {
        $this->config = [
            'server' => [
                'source' => PLUGIN_ROOT . '/test_app/webroot/upload',
                'cache' => TMP . 'cache',
            ],
        ];

        $this->request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/images/cake-logo.png'],
            ['w' => '100']
        );
        $this->handler = new TestRequestHandler();

        Security::setSalt('salt');

        exec('rm -rf ' . TMP . 'cache/cake-logo.png');
        clearstatcache(false, TMP . 'cache/cake-logo.png');
    }

    public function testNormalResponse()
    {
        $middleware = new GlideMiddleware($this->config);
        $response = $middleware->process($this->request, $this->handler);

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
        $response = $middleware->process($this->request, $this->handler);

        $this->assertTrue(is_dir(TMP . 'cache/cake-logo.png'));
    }

    public function testAllowedParams()
    {
        $this->config['allowedParams'] = ['w'];
        $middleware = new GlideMiddleware($this->config);
        $middleware->process($this->request, $this->handler);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/images/cake-logo.png'],
            ['w' => '100', 'foo' => 'bar']
        );

        $middleware = new GlideMiddleware($this->config);
        $middleware->process($request, $this->handler);

        $files = glob(TMP . 'cache/cake-logo.png/*');
        $this->assertSame(1, count($files));
    }

    public function testOriginalPassThrough()
    {
        $fileSize = filesize(PLUGIN_ROOT . '/test_app/webroot/upload/cake-logo.png');

        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/cake-logo.png',
        ]);

        $middleware = new GlideMiddleware($this->config);
        $response = $middleware->process($request, $this->handler);

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
        $response = $middleware->process($request, $this->handler);

        $this->assertFalse(is_dir(TMP . 'cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertSame($fileSize, (int)$headers['Content-Length'][0]);
    }

    public function testPathConfig()
    {
        $middleware = new GlideMiddleware(['path' => '/img'] + $this->config);
        $response = $middleware->process($this->request, $this->handler);

        $this->assertFalse(is_dir(TMP . 'cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertFalse(isset($headers['Content-Length']));
    }

    public function testSecureUrl()
    {
        $this->config['security']['secureUrls'] = true;

        $signature = new Signature(Security::getSalt());
        $sig = $signature->generateSignature('/images/cake logo.png', ['w' => 100]);

        $request = ServerRequestFactory::fromGlobals(
            ['REQUEST_URI' => '/images/cake%20logo.png'],
            ['w' => 100, 's' => $sig]
        );

        $middleware = new GlideMiddleware($this->config);
        $middleware->process($request, $this->handler);

        $this->assertTrue(is_dir(TMP . 'cache/cake logo.png'));
    }

    public function testCache()
    {
        $middleware = new GlideMiddleware($this->config);
        $response = $middleware->process($this->request, $this->handler);

        $this->assertTrue(is_dir(TMP . 'cache/cake-logo.png'));

        $headers = $response->getHeaders();
        $this->assertTrue($response->getBody() instanceof Stream);
        $this->assertTrue(isset($headers['Last-Modified']));
        $this->assertTrue(isset($headers['Expires']));

        $request = ServerRequestFactory::fromGlobals(
            [
                'REQUEST_URI' => '/images/cake-logo.png',
                'HTTP_IF_MODIFIED_SINCE' => $headers['Last-Modified'][0],
            ],
            ['w' => '100']
        );

        $middleware = new GlideMiddleware($this->config);
        $response = $middleware->process($request, $this->handler);

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
        $response = $middleware->process($this->request, $this->handler);
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
        $middleware->process($request, $this->handler);
    }

    public function test3rdPartyException()
    {
        $middleware = new GlideMiddleware($this->config);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/non-existent.jpg',
        ]);

        $this->expectException(UnableToRetrieveMetadata::class);
        $middleware->process($request, $this->handler);
    }

    public function testResponseException()
    {
        $middleware = new GlideMiddleware($this->config);
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/images/non-existent.jpg',
        ]);

        Configure::write('debug', false);
        $this->expectException(ResponseException::class);
        $middleware->process($request, $this->handler);
        Configure::write('debug', true);
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

        $response = $middleware->process($request, $this->handler);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/png', $response->getHeaderLine('Content-Type'));
    }
}
