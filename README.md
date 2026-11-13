# API Key Middleware for Kipchak

This Middleware package provides API Key Authentication support for Kipchak APIs.

#### Composer Package

`kipchak/middleware-auth-key`

## How to use it?

Install it via composer: `composer require kipchak/middleware-auth-key`.

Then just ensure you have appropriate settings configured in `kipchak.auth.key.php` config file in the root of your project.

For a sample, please see: https://github.com/kipchak-dev/examples/blob/master/config/kipchak.auth.key.php.

## Configuration

| Key | Default | Meaning |
|---|---|---|
| `enabled` | | Check every request for a key. |
| `ignore_options` | | Let OPTIONS (CORS preflight) requests through without a key. |
| `ignore_paths` | | Paths that need no key, compared exactly. |
| `authorised_keys` | | Each authorised key, mapped to the name of the client that uses it. |
| `header` | | The request header that carries the key. It is read first. |
| `query_param` | | The query parameter that carries the key when the header is absent. |
| `accept_query_param` | `true` | From 1.43. `false` accepts the key from the header only. |

We recommend setting `accept_query_param` to `false`. A key in a URL ends up in access logs, proxy logs and browser
history, where anyone who reads them can use it. It stays `true` by default so that an existing configuration keeps
working unchanged.

Keys are compared in constant time (from 1.43): the presented key and every authorised key are hashed with SHA-256 and
compared with `hash_equals()`, against every entry, so the time taken does not reveal which key matched or how long it
is.

## The client name on the request

From 1.43, a request whose key matched reaches your route with the client name, the value the key maps to in
`authorised_keys`, in the request attribute `kipchak.auth.key.client` (the constant `ApiKey::ATTRIBUTE_CLIENT`):

```php
use Kipchak\Middleware\Auth\Key\Handlers\ApiKey;

$client = $request->getAttribute(ApiKey::ATTRIBUTE_CLIENT); // e.g. "client-one"
```

- When the value in `authorised_keys` is an empty string or not a string (a number, an array, `true`), the attribute
  is set to `null`. The request is still authorised.
- A key whose value is `null` is not authorised, as in earlier releases.
- Requests let through by `ignore_paths` or `ignore_options` do not carry the attribute.
- The client name is never sent as a header, and neither the 401 response nor any log line contains the presented key.

## Responses

A request without an authorised key gets a 401 with the message `Missing or invalid key` and the status
`UNAUTHENTICATED`, the same status kipchak/middleware-error gives a 401 (from kipchak/core's `ErrorStatus`).

**Upgrading to 1.41:** it requires kipchak/core 3.18, and the status of that 401 is `UNAUTHENTICATED` instead of
`UNAUTHORIZED`. A client that compares the status string needs the new one.

## Release notes

### 1.43

No change for an existing configuration: the same keys are accepted, from the header first and then the `apikey`
query parameter, with the same `ignore_paths`, `ignore_options` and the same 401. A test runs 1.41's
`sample.config.php` unchanged through the 1.41 handler and this one and compares the results.

- A matched request carries the client name in the request attribute `kipchak.auth.key.client`
  (`ApiKey::ATTRIBUTE_CLIENT`).
- New optional setting `accept_query_param`, default `true`. Set it to `false` to accept keys from the header only.
- Keys are compared in constant time, with SHA-256 and `hash_equals()` against every authorised key, instead of an
  array lookup.
- A request whose query parameter is an array (`?apikey[]=...`) is refused with the usual 401. 1.41 failed on it with
  a PHP error.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## What is a Kipchak Middleware?

Kipchak Middleware is a standard PHP PSR-15 middleware.

Middleware is a component that sits between the request and response of a web application, allowing for interception
and modification of the request and response data. In the context of Kipchak, middleware can be used to handle errors,
authentication, and other cross-cutting concerns that are common across one or more API endpoints.
