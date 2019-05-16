<?php
declare(strict_types=1);
namespace ADmad\Glide\TestCase\View\Helper;

use ADmad\Glide\View\Helper\GlideHelper;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use Cake\View\View;

class GlideHelperTest extends TestCase
{
    public function setUp(): void
    {
        $this->request = new ServerRequest();
        $this->request = $this->request->withAttribute('webroot', '/');
        $this->view = new View($this->request);
        $this->helper = new GlideHelper($this->view, ['baseUrl' => '/images/']);

        Security::setSalt('salt');
    }

    public function testUrl()
    {
        $result = $this->helper->url('logo.png', ['w' => 100]);
        $this->assertEquals('/images/logo.png?w=100', $result);

        $this->helper->getView()->setRequest(
            $this->helper->getView()->getRequest()->withAttribute('webroot', '/subfolder/')
        );
        $result = $this->helper->url('logo.png', ['w' => 100]);
        $this->assertEquals('/subfolder/images/logo.png?w=100', $result);

        $helper = new GlideHelper($this->view, [
            'baseUrl' => '/images/',
            'secureUrls' => true,
        ]);
        $result = $helper->url('logo.png', ['w' => 100]);
        $this->assertStringContainsString('&s=', $result);
    }

    public function testImage()
    {
        $result = $this->helper->image('logo.png', ['w' => 100], ['width' => 100]);
        $this->assertHtml([
            'img' => [
                'src' => '/images/logo.png?w=100',
                'width' => 100,
                'alt' => '',
            ],
        ], $result);

        $this->helper->Html->Url->getView()->setRequest(
            $this->helper->Html->Url->getView()->getRequest()
                ->withAttribute('webroot', '/subfolder/')
        );
        $result = $this->helper->image('logo.png', ['w' => 100], ['width' => 100]);
        $this->assertHtml([
            'img' => [
                'src' => '/subfolder/images/logo.png?w=100',
                'width' => 100,
                'alt' => '',
            ],
        ], $result);
    }
}
