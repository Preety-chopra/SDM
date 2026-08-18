<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingDatabaseConfigurationTest extends TestCase
{
    public function test_tests_use_an_in_memory_sqlite_database(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
