<?php

declare(strict_types=1);

namespace Kipchak\Middleware\Auth\Key\Tests\Unit\Handlers;

use DI\Container;
use Kipchak\Core\Components\Http\Response as HttpResponse;
use Kipchak\Driver\Config\Config;
use Kipchak\Middleware\Auth\Key\Handlers\ApiKey;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * The handler as an API uses it: keys in config/kipchak.auth.key.php, read from a header or a query parameter.
 */
final class ApiKeyTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->set('drivers.config', ['kipchak.auth.key' => [
            'ignore_options' => true,
            'ignore_paths' => ['/status'],
            'authorised_keys' => ['k-123' => 'Reporting'],
            'header' => 'X-Api-Key',
            'query_param' => 'apikey',
        ]]);
        (new \ReflectionProperty(Config::class, 'container'))->setValue(null, $this->container);
    }

    private function call(string $uri = '/v1/things', ?string $key = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri);
        if ($key !== null) {
            $request = $request->withHeader('X-Api-Key', $key);
        }

        return (new ApiKey($this->container))($request, new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(200);
            }
        });
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private static function envelope(ResponseInterface $response): array
    {
        $body = json_decode((string) $response->getBody(), true);

        return [$body['code'], $body['status'], $body['data']];
    }

    public function testAnAuthorisedKeyIsLetThrough(): void
    {
        $this->assertSame(200, $this->call(key: 'k-123')->getStatusCode());
        $this->assertSame(200, $this->call('/v1/things?apikey=k-123')->getStatusCode());
        $this->assertSame(200, $this->call('/status')->getStatusCode(), 'An ignored path needs no key');
    }

    public function testAMissingOrUnknownKeyIsRefusedWithTheStatusMiddlewareErrorUses(): void
    {
        $this->assertSame([401, 'UNAUTHENTICATED', 'Missing or invalid key'], self::envelope($this->call()));
        $this->assertSame([401, 'UNAUTHENTICATED', 'Missing or invalid key'], self::envelope($this->call(key: 'k-999')));
    }

    public function testControlJsonWritesTheOldLabel(): void
    {
        // What 1.40 and earlier wrote for the same refusal, through Response::json().
        $this->assertSame('UNAUTHORIZED', self::envelope(HttpResponse::json(new Response(), 'Missing or invalid key', 401))[1]);
    }
}
