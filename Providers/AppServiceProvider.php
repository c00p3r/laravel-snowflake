<?php

namespace App\Providers;

use App\Database\Snowflake\Connection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // custom Snowflake DB connection
        Connection::resolverFor('snowflake', function ($connection, $database, $prefix, $config) {
            return new Connection($connection, $database, $prefix, $config);
        });
    }
}
