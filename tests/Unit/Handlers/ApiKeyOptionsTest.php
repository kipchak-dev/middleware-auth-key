<?php

declare(strict_types=1);

namespace Kipchak\Middleware\Auth\Key\Tests\Unit\Handlers;

use DI\Container;
use Kipchak\Driver\Config\Config;
use Kipchak\Middleware\Auth\Key\Handlers\ApiKey;
use Kipchak\Middleware\Auth\Key\Tests\Fixtures\V141\ApiKey as ApiKey141;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * 1.43: the client name on the request, accept_query_param, and keys compared in constant time.
 */
final class ApiKeyOptionsTest extends TestCase
{
    private Container $container;
    /** @var array<string, mixed>|null the attributes the next handler saw */
    private ?array $seen = null;

    /**
     * @param array<string, mixed> $overrides
     */
    private function configure(array $overrides = []): void
    {
        $this->container = new Container();
        $this->container->set('drivers.config', ['kipchak.auth.key' => array_merge([
            'ignore_options' => true,
            'ignore_paths' => ['/status'],
            'authorised_keys' => [
                'k-123' => 'Reporting',
                'a-much-longer-key-for-the-billing-service-0123456789' => 'Billing',
                'k-empty' => '',
                'k-int' => 7,
            ],
            'header' => 'X-Api-Key',
            'query_param' => 'apikey',
        ], $overrides)]);
        (new \ReflectionProperty(Config::class, 'container'))->setValue(null, $this->container);
    }

    protected function setUp(): void
    {
        $this->configure();
    }

    private function call(string $uri = '/v1/things', ?string $key = null, ?callable $handler = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', $uri);
        if ($key !== null) {
            $request = $request->withHeader('X-Api-Key', $key);
        }
        $this->seen = null;
        $seen = &$this->seen;
        $next = new class ($seen) implements RequestHandlerInterface {
            public function __construct(private ?array &$seen)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seen = $request->getAttributes();

                return (new ResponseFactory())->createResponse(200);
            }
        };

        return ($handler ?? new ApiKey($this->container))($request, $next);
    }

    public function testTheAttributeNameIsStable(): void
    {
        $this->assertSame('kipchak.auth.key.client', ApiKey::ATTRIBUTE_CLIENT);
    }

    public function testAMatchedKeyPutsItsClientNameOnTheRequest(): void
    {
        $this->assertSame(200, $this->call(key: 'k-123')->getStatusCode());
        $this->assertSame('Reporting', $this->seen[ApiKey::ATTRIBUTE_CLIENT]);

        $this->assertSame(200, $this->call('/v1/things?apikey=a-much-longer-key-for-the-billing-service-0123456789')->getStatusCode());
        $this->assertSame('Billing', $this->seen[ApiKey::ATTRIBUTE_CLIENT]);
    }

    public function testControl141DoesNotSetTheAttribute(): void
    {
        $this->assertSame(200, $this->call(key: 'k-123', handler: new ApiKey141($this->container))->getStatusCode());
        $this->assertArrayNotHasKey(ApiKey::ATTRIBUTE_CLIENT, $this->seen);
    }

    public function testAnEmptyOrNonStringClientNameGivesNull(): void
    {
        $this->assertSame(200, $this->call(key: 'k-empty')->getStatusCode());
        $this->assertArrayHasKey(ApiKey::ATTRIBUTE_CLIENT, $this->seen);
        $this->assertNull($this->seen[ApiKey::ATTRIBUTE_CLIENT]);

        $this->assertSame(200, $this->call(key: 'k-int')->getStatusCode());
        $this->assertNull($this->seen[ApiKey::ATTRIBUTE_CLIENT]);
    }

    public function testAnIgnoredPathCarriesNoClient(): void
    {
        $this->assertSame(200, $this->call('/status')->getStatusCode());
        $this->assertArrayNotHasKey(ApiKey::ATTRIBUTE_CLIENT, $this->seen);
    }

    public function testHeaderOnlyRefusesAKeyInTheQueryAndAcceptsItInTheHeader(): void
    {
        $this->configure(['accept_query_param' => false]);

        $this->assertSame(401, $this->call('/v1/things?apikey=k-123')->getStatusCode());
        $this->assertNull($this->seen, 'The request did not reach the route');
        $this->assertSame(200, $this->call(key: 'k-123')->getStatusCode());
        $this->assertSame('Reporting', $this->seen[ApiKey::ATTRIBUTE_CLIENT]);
        $this->assertSame(200, $this->call('/status')->getStatusCode(), 'ignore_paths still applies');
    }

    public function testControlByDefaultTheQueryParamIsStillAccepted(): void
    {
        $this->assertSame(200, $this->call('/v1/things?apikey=k-123')->getStatusCode());

        $this->configure(['accept_query_param' => true]);
        $this->assertSame(200, $this->call('/v1/things?apikey=k-123')->getStatusCode());
    }

    public function testAWrongOrMissingKeyIsStillRefused(): void
    {
        $this->assertSame(401, $this->call(key: 'k-999')->getStatusCode());
        $this->assertSame(401, $this->call('/v1/things?apikey=k-12')->getStatusCode(), 'A prefix of a key is not the key');
        $this->assertSame(401, $this->call(key: 'k-1234')->getStatusCode(), 'Nor is a key with more on the end');
        $this->assertSame(401, $this->call()->getStatusCode());
        $this->assertNull($this->seen);
    }

    public function testAKeyGivenTwiceInTheQueryIsRefusedNotAnError(): void
    {
        // ?apikey[]=... arrives as an array. 1.41 failed with a TypeError; now it is a missing key.
        $this->assertSame(401, $this->call('/v1/things?apikey%5B%5D=k-123')->getStatusCode());
    }

    public function testTheRefusalNeverEchoesThePresentedKey(): void
    {
        $presented = 'secret-presented-key-4f1c';
        ob_start();
        $inHeader = (string) $this->call(key: $presented)->getBody();
        $inQuery = (string) $this->call('/v1/things?apikey=' . $presented)->getBody();
        $printed = (string) ob_get_clean();

        $envelope = ['code' => 401, 'status' => 'UNAUTHENTICATED', 'data' => 'Missing or invalid key'];
        $this->assertSame($envelope, json_decode($inHeader, true));
        $this->assertSame($envelope, json_decode($inQuery, true));
        $this->assertStringNotContainsString($presented, $inHeader);
        $this->assertStringNotContainsString($presented, $inQuery);
        $this->assertSame('', $printed, 'Nothing is printed or logged to output');
    }

    public function testTheMiddlewareWritesNoLogLine(): void
    {
        // The handler has no logger: a log line cannot carry the key. This fails if one is added without a test.
        $source = (string) file_get_contents(__DIR__ . '/../../../src/Handlers/ApiKey.php');
        $this->assertDoesNotMatchRegularExpression('/Logger|->(debug|info|notice|warning|error|critical|alert|emergency|log)\(/', $source);
    }
}
