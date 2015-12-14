<?php

namespace ADmad\Glide\TestCase\View\Helper;

use ADmad\Glide\View\Helper\GlideHelper;
use Cake\Core\Configure;
use Cake\Network\Request;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use Cake\View\View;

class GlideHelperTest extends TestCase
{
    public function setUp()
    {
        Configure::write('Glide', [
            'serverConfig' => [
                'base_url' => '/images/',
            ],
        ]);

        $this->request = new Request();
        $this->request->webroot = '/';
        $this->view = new View($this->request);
        $this->helper = new GlideHelper($this->view);

        Security::salt('salt');
    }

    public function tearDown()
    {
        Configure::delete('Glide');
    }

    public function testUrl()
    {
        $result = $this->helper->url('logo.png', ['w' => 100]);
        $this->assertEquals('/images/logo.png?w=100', $result);

        $this->helper->request->webroot = '/subfolder/';
        $result = $this->helper->url('logo.png', ['w' => 100]);
        $this->assertEquals('/subfolder/images/logo.png?w=100', $result);

        Configure::write('Glide.secureUrls', true);
        $helper = new GlideHelper($this->view);
        $result = $helper->url('logo.png', ['w' => 100]);
        $this->assertContains('&s=', $result);
    }

    public function testImage()
    {
        $result = $this->helper->image('logo.png', ['w' => 100], ['width' => 100]);
        $this->assertHtml([
            'img' => [
                'src'   => '/images/logo.png?w=100',
                'width' => 100,
                'alt'   => '',
            ],
        ], $result);

        $this->helper->request->webroot = '/subfolder/';
        $result = $this->helper->image('logo.png', ['w' => 100], ['width' => 100]);
        $this->assertHtml([
            'img' => [
                'src'   => '/subfolder/images/logo.png?w=100',
                'width' => 100,
                'alt'   => '',
            ],
        ], $result);
    }
}
