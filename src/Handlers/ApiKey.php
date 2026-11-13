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
    /**
     * The request attribute that carries the name of the client whose key matched: the value the key maps to in
     * authorised_keys. Null when that value is not a non-empty string. Requests on ignored paths, and OPTIONS requests
     * let through by ignore_options, do not carry it.
     *
     * Read it with $request->getAttribute(ApiKey::ATTRIBUTE_CLIENT).
     */
    public const ATTRIBUTE_CLIENT = 'kipchak.auth.key.client';

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
        // Optional, default true as before 1.43. False accepts the key from the header only.
        $acceptQueryParam = $authConfig['accept_query_param'] ?? true;

        $key = isset($request->getHeader($headerToCheck)[0]) ?
            $request->getHeader($headerToCheck)[0] :
            ($acceptQueryParam ? Http\Request::getQueryParam($request, $queryParamToCheck) : null);

        if (is_string($key) && !empty($key)) {
            [$matched, $client] = self::match($key, $authConfig['authorised_keys'] ?? []);
            if ($matched) {
                // Key matched!
                $response = $handler->handle($request->withAttribute(self::ATTRIBUTE_CLIENT, $client));

                return $response;
            }
        }

        return Http\Response::error($response,
            'Missing or invalid key',
            401
        );

    }

    /**
     * Compares the presented key with every authorised key in constant time: each is hashed with SHA-256 and compared
     * with hash_equals(), and the loop never stops early, so the time taken does not depend on which key matched or on
     * the keys' lengths. A key whose client value is null does not match, as isset() did not match it before 1.43.
     *
     * @param array<array-key, mixed> $authorisedKeys
     * @return array{0: bool, 1: ?string} whether a key matched, and its client name
     */
    private static function match(string $key, array $authorisedKeys): array
    {
        $presented = hash('sha256', $key, true);
        $matched = false;
        $client = null;
        foreach ($authorisedKeys as $authorisedKey => $name) {
            $equal = hash_equals(hash('sha256', (string) $authorisedKey, true), $presented);
            if ($equal && !$matched && $name !== null) {
                $matched = true;
                $client = is_string($name) && $name !== '' ? $name : null;
            }
        }

        return [$matched, $client];
    }
}
