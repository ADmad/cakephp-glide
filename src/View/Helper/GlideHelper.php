<?php
namespace ADmad\Glide\View\Helper;

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
     * Default config for this helper.
     *
     * Valid keys:
     * - `baseUrl`: Base URL. Default '/images/'.
     * - `secureUrls`: Whether to generate secure URLs. Default `false`.
     * - `signKey`: Signing key to use when generating secure URLs. If empty
     *   value of `Security::salt()` will be used. Default `null`.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'baseUrl' => '/images/',
        'secureUrls' => false,
        'signKey' => null,
    ];

    /**
     * URL builder.
     *
     * @var \League\Glide\Urls\UrlBuilder|null
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
        return $this->Html->image(
            $this->url($path, $params + ['_base' => false]),
            $options
        );
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
     * @param \League\Urls\UrlBuilder|null $urlBuilder URL builder instance to
     *   set or null to get instance.
     *
     * @return \League\Urls\UrlBuilder URL builder instance.
     */
    public function urlBuilder($urlBuilder = null)
    {
        if ($urlBuilder !== null) {
            return $this->_urlBuilder = $urlBuilder;
        }

        if (!isset($this->_urlBuilder)) {
            $config = $this->_config;

            $this->_urlBuilder = UrlBuilderFactory::create(
                $config['baseUrl'],
                $config['secureUrls'] ? ($config['signKey'] ?: Security::salt()) : null
            );
        }

        return $this->_urlBuilder;
    }
}
