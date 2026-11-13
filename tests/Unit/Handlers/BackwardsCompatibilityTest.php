<?php

declare(strict_types=1);

namespace Kipchak\Middleware\Auth\Key\Tests\Unit\Handlers;

use DI\Container;
use Kipchak\Driver\Config\Config;
use Kipchak\Middleware\Auth\Key\Handlers\ApiKey;
use Kipchak\Middleware\Auth\Key\Tests\Fixtures\V141\ApiKey as ApiKey141;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * A project that upgrades without touching its config sees exactly what 1.41 gave it. The config is a verbatim copy of
 * 1.41's sample.config.php (tests/Fixtures/V141/sample.config.php) with one authorised key added, as a project adds
 * its own. Each request goes through the 1.41 handler (tests/Fixtures/V141/ApiKey.php, verbatim but for its
 * namespace) and the current one, and the outcomes must be identical.
 */
final class BackwardsCompatibilityTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $config = require __DIR__ . '/../../Fixtures/V141/sample.config.php';
        $config['authorised_keys']['k-123'] = 'client-one';
        $config['authorised_keys']['12345'] = 'numeric-client'; // PHP stores this key as the integer 12345
        $config['authorised_keys']['k-null'] = null;            // isset() is false for a null value: refused in 1.41

        $this->container = new Container();
        $this->container->set('drivers.config', ['kipchak.auth.key' => $config]);
        (new \ReflectionProperty(Config::class, 'container'))->setValue(null, $this->container);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    public static function requests(): array
    {
        return [
            'valid key in the header' => ['GET', '/v1/things', ['X-Api-Key' => 'k-123']],
            'valid key in the query' => ['GET', '/v1/things?apikey=k-123', []],
            'wrong key in the header' => ['GET', '/v1/things', ['X-Api-Key' => 'k-999']],
            'wrong key in the query' => ['GET', '/v1/things?apikey=k-999', []],
            'missing key' => ['GET', '/v1/things', []],
            'ignored path' => ['GET', '/status', []],
            'OPTIONS without a key' => ['OPTIONS', '/v1/things', []],
            'header wins over the query' => ['GET', '/v1/things?apikey=k-123', ['X-Api-Key' => 'k-999']],
            'empty header, valid query' => ['GET', '/v1/things?apikey=k-123', ['X-Api-Key' => '']],
            'numeric key' => ['GET', '/v1/things', ['X-Api-Key' => '12345']],
            'key mapped to null' => ['GET', '/v1/things', ['X-Api-Key' => 'k-null']],
            'key in another case' => ['GET', '/v1/things', ['X-Api-Key' => 'K-123']],
            'key with a trailing space' => ['GET', '/v1/things?apikey=k-123%20', []],
            'POST with a valid key' => ['POST', '/v1/things', ['X-Api-Key' => 'k-123']],
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, reached: bool}
     */
    private function outcome(callable $handler, string $method, string $uri, array $headers): array
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $next = new class implements RequestHandlerInterface {
            public bool $reached = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->reached = true;

                return (new ResponseFactory())->createResponse(200);
            }
        };
        $response = $handler($request, $next);

        return ['status' => $response->getStatusCode(), 'body' => (string) $response->getBody(), 'reached' => $next->reached];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('requests')]
    public function testTheOutcomeIsTheSameAsIn141(string $method, string $uri, array $headers): void
    {
        $then = $this->outcome(new ApiKey141($this->container), $method, $uri, $headers);
        $now = $this->outcome(new ApiKey($this->container), $method, $uri, $headers);

        $this->assertSame($then, $now);
    }

    public function testTheExpectedOutcomesOf141(): void
    {
        // Pins what "the same" means, so that a change to both handlers at once would still show.
        $old = new ApiKey141($this->container);
        $this->assertTrue($this->outcome($old, 'GET', '/v1/things', ['X-Api-Key' => 'k-123'])['reached']);
        $this->assertTrue($this->outcome($old, 'GET', '/v1/things?apikey=k-123', [])['reached']);
        $this->assertTrue($this->outcome($old, 'GET', '/status', [])['reached']);
        $this->assertTrue($this->outcome($old, 'OPTIONS', '/v1/things', [])['reached']);
        $refused = $this->outcome($old, 'GET', '/v1/things', ['X-Api-Key' => 'k-999']);
        $this->assertSame(401, $refused['status']);
        $this->assertFalse($refused['reached']);
        $this->assertSame(['code' => 401, 'status' => 'UNAUTHENTICATED', 'data' => 'Missing or invalid key'], json_decode($refused['body'], true));
        $this->assertSame(401, $this->outcome($old, 'GET', '/v1/things', [])['status']);
    }
}
