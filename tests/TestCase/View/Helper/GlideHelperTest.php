<?php
namespace ADmad\Glide\TestCase\View\Helper;

use ADmad\Glide\View\Helper\GlideHelper;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use Cake\View\View;

class GlideHelperTest extends TestCase
{
    public function setUp()
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

        if (method_exists($this->helper->getView(), 'setRequest') && method_exists($this->helper->getView(), 'getRequest')) {
            $this->helper->getView()->setRequest($this->helper->getView()->getRequest()->withAttribute('webroot', '/subfolder/'));
        } else {
            $this->helper->request = $this->helper->request->withAttribute('webroot', '/subfolder/');
        }
        $this->helper->initialize([]);

        $result = $this->helper->url('logo.png', ['w' => 100]);
        $this->assertEquals('/subfolder/images/logo.png?w=100', $result);

        $helper = new GlideHelper($this->view, [
            'baseUrl' => '/images/',
            'secureUrls' => true,
        ]);
        $result = $helper->url('logo.png', ['w' => 100]);
        $this->assertContains('&s=', $result);
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

        if (method_exists($this->helper->getView(), 'setRequest') && method_exists($this->helper->getView(), 'getRequest')) {
            $this->helper->Html->Url->getView()->setRequest($this->helper->Html->Url->getView()->getRequest()->withAttribute('webroot', '/subfolder/'));
        } else {
            $this->helper->Html->Url->request = $this->helper->Html->Url->request->withAttribute('webroot', '/subfolder/');
        }

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
