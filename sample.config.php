<?php

use function Kipchak\Core\env;

return [
    'enabled' => false, // true checks every request for a key; or attach the middleware to a route group instead
    'ignore_options' => true, // let CORS preflight (OPTIONS) requests through without a key
    'ignore_paths' => [
        '/status',
    ],
    // Each authorised key maps to the name of the client that uses it.
    'authorised_keys' => [
        // env('API_KEY_CLIENT_ONE') => 'client-one',
    ],
    'header' => 'X-Api-Key',  // the request header that carries the key
    'query_param' => 'apikey', // or this query parameter
    // Optional, default true. false accepts the key from the header only. Recommended: keys in URLs end up in
    // access logs, proxy logs and browser history.
    'accept_query_param' => true,
];
