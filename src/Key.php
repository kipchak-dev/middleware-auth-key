<?php

namespace Kipchak\Middleware\Auth\Key;

use Kipchak\Core\Interfaces;
use Kipchak\Driver\Config\Config;
use Kipchak\Core\Components\App;

class Key implements Interfaces\Middleware
{
    public static function initialise(App $app): void
    {
        $container = $app->getContainer();
        if (!empty(Config::get('kipchak.auth.key'))) {
            $auth = Config::get('kipchak.auth');
            if ($auth['enabled']) {
                // Enable Key based auth globally
                $app->add(new AuthKey($container));

            }
        }

    }

}