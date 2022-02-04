<?php

namespace eLife\CiviContacts\Exception;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class CiviCrmResponseError extends Exception
{
    private $response;

    public function __construct(string $message, ResponseInterface $response, Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->response = $response;
    }

    final public function getResponse() : ResponseInterface
    {
        return $this->response;
    }
}
