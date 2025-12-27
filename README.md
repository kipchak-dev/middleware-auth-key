# API Key Middleware for Kipchak

This Middleware package provides API Key Authentication support for Kipchak APIs.

#### Composer Package

`kipchak/middleware-auth-key`

## How to use it?

Install it via composer: `composer require kipchak/middleware-auth-key`.

Then just ensure you have appropriate settings configured in `kipchak.auth.key.php` config file in the root of your project.

For a sample, please see: https://1x.ax/mamluk/kipchak/starter/~files/master/config/kipchak.auth.key.php.

## What is a Kipchak Middleware?

Kipchak Middleware is a standard PHP PSR-15 middleware.

Middleware is a component that sits between the request and response of a web application, allowing for interception
and modification of the request and response data. In the context of Kipchak, middleware can be used to handle errors,
authentication, and other cross-cutting concerns that are common across one or more API endpoints.
