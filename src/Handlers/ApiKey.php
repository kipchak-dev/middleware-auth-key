<?php


namespace Kipchak\Middleware\Auth\Key\Handlers;

use Kipchak\Core\Components\Http;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Kipchak\Driver\Config\Config;

class ApiKey
{
    public $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

    }

    /**
     * Key Authentication middleware invokable class
     *
     * @param ServerRequestInterface $request PSR-7 request
     * @param RequestHandlerInterface $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): Response
    {
        $authConfig = Config::get('kipchak.auth.key');


        $response = new Response();

        if (
            (isset($authConfig['ignore_options']) && $request->getMethod() === 'OPTIONS' && $authConfig['ignore_options']) ||
            (isset($authConfig['ignore_paths']) && in_array($request->getUri()->getPath(), $authConfig['ignore_paths']))
        ) {
            $response = $handler->handle($request);

            return $response;
        }

        $headerToCheck = $authConfig['header'];
        $queryParamToCheck = $authConfig['query_param'];

        $key = isset($request->getHeader($headerToCheck)[0]) ?
            $request->getHeader($headerToCheck)[0] :
            Http\Request::getQueryParam($request, $queryParamToCheck );

        if (!empty($key) && isset($authConfig['authorised_keys'][$key])) {
            // Key matched!
            $response = $handler->handle($request);

            return $response;
        }

        return Http\Response::json($response,
            'Missing or invalid key',
            401
        );

    }
}