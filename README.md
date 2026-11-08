# API Key Middleware for Kipchak

This Middleware package provides API Key Authentication support for Kipchak APIs.

#### Composer Package

`kipchak/middleware-auth-key`

## How to use it?

Install it via composer: `composer require kipchak/middleware-auth-key`.

Then just ensure you have appropriate settings configured in `kipchak.auth.key.php` config file in the root of your project.

For a sample, please see: https://1x.ax/mamluk/kipchak/starter/~files/master/config/kipchak.auth.key.php.

## Responses

A request without an authorised key gets a 401 with the message `Missing or invalid key` and the status
`UNAUTHENTICATED`, the same status kipchak/middleware-error gives a 401 (from kipchak/core's `ErrorStatus`).

**Upgrading to 1.41:** it requires kipchak/core 3.18, and the status of that 401 is `UNAUTHENTICATED` instead of
`UNAUTHORIZED`. A client that compares the status string needs the new one.

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
