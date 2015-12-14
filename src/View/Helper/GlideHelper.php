<?php
namespace ADmad\Glide\View\Helper;

use Cake\Core\Configure;
use Cake\Utility\Security;
use Cake\View\Helper;
use League\Glide\Urls\UrlBuilderFactory;

class GlideHelper extends Helper
{
    /**
     * Helpers used by this helper.
     *
     * @var array
     */
    public $helpers = ['Html'];

    /**
     * URL builder.
     *
     * @var \League\Glide\Urls\UrlBuilder
     */
    protected $_urlBuilder;

    /**
     * Creates a formatted IMG element.
     *
     * @param string $path Image path.
     * @param array $params Image manipulation parameters.
     * @param array $options Array of HTML attributes for image tag.
     *
     * @return string Complete <img> tag.
     *
     * @see http://glide.thephpleague.com/1.0/api/quick-reference/
     */
    public function image($path, array $params = [], array $options = [])
    {
        return $this->Html->image($this->url($path, $params + ['_base' => false]), $options);
    }

    /**
     * URL with query string based on resizing params.
     *
     * @param string $path Image path.
     * @param array $params Image manipulation parameters.
     *
     * @return string Image URL.
     *
     * @see http://glide.thephpleague.com/1.0/api/quick-reference/
     */
    public function url($path, array $params = [])
    {
        $base = true;
        if (isset($params['_base'])) {
            $base = $params['_base'];
            unset($params['_base']);
        }
        $url = $this->urlBuilder()->getUrl($path, $params);
        if ($base && strpos($url, 'http') !== 0) {
            $url = $this->request->webroot . ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Get URL builder instance.
     *
     * @return \League\Urls\UrlBuilder URL builder instance.
     */
    public function urlBuilder()
    {
        if (!isset($this->_urlBuilder)) {
            $this->_urlBuilder = UrlBuilderFactory::create(
                Configure::read('Glide.serverConfig.base_url'),
                Configure::read('Glide.secureUrls') ? Security::salt() : null
            );
        }

        return $this->_urlBuilder;
    }
}
