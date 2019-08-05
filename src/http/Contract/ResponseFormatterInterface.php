<?php declare(strict_types=1);

namespace Scar\http\Contract;

use Psr\Http\Message\ResponseInterface;
use Scar\http\Response;

/**
 * Class ResponseFormatterInterface
 *
 * @since 2.0
 */
interface ResponseFormatterInterface
{
    /**
     * @param Response $response
     *
     * @return Response|ResponseInterface
     */
    public function format(Response $response): Response;
}
