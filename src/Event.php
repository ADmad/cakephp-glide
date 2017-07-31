<?php
namespace ADmad\Glide;

use Cake\Event\Event as BaseEvent;
use Psr\Http\Message\ResponseInterface;

class Event extends BaseEvent
{
    /**
     * @var boolean
     */
    protected $ignoreException = false;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * Ignore all raised exceptions
     */
    public function ignoreException()
    {
        $this->ignoreException = true;
    }

    /**
     * Checks whether the exception is ignored
     */
    public function isIgnoreException()
    {
        return $this->ignoreException;
    }

    /**
     * Sets a customize response.
     * @param ResponseInterface $response
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Gets the customize response
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }
}