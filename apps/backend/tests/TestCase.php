<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDatabaseWithoutGuard;
    }

    public function refreshDatabase()
    {
        if (
            ! app()->environment('testing')
            || DB::connection()->getDriverName() !== 'pgsql'
            || DB::connection()->getDatabaseName() !== 'salesflow_testing'
        ) {
            throw new \RuntimeException(
                'Las pruebas requieren PostgreSQL y la base salesflow_testing.'
            );
        }

        $this->refreshDatabaseWithoutGuard();
    }
}
