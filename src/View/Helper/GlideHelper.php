<?php
namespace ADmad\Glide\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;
use Cake\Utility\Security;
use League\Glide\Urls\UrlBuilderFactory;

class GlideHelper extends Helper
{

    /**
     * Helpers used by this helper.
     *
     * @var array
     */
    public $helpers = ['Html', 'Url'];

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
     * @param array $options Array of HTML attributes and options.
     *   See `$options` argument of `Cake\View\HtmlHelper::image()`.
     * @return string Complet <img> tag.
     */
    public function image($path, array $params = [], array $options = [])
    {
        return $this->Html->image($this->url($path, $params), $options);
    }

    /**
     * URL with query string based on resizing params.
     *
     * @param strin $path Image path.
     * @param array $params Image manipulation parameters.
     * @return string Image URL.
     */
    public function url($path, array $params = [])
    {
        $url = $this->Url->build($path);
        $url = $this->urlBuilder()->getUrl($url, $params);

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
                $this->request->webroot,
                Configure::read('Glide.secureUrls') ? Security::salt() : null
            );
        }

        return $this->_urlBuilder;
    }
}
