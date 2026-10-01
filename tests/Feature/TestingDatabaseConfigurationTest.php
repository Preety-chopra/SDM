<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingDatabaseConfigurationTest extends TestCase
{
    public function test_application_and_mysql_use_the_kolkata_timezone(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->assertSame('Asia/Kolkata', date_default_timezone_get());
        $this->assertSame('+05:30', config('database.connections.mysql.timezone'));
    }

    public function test_tests_use_an_in_memory_sqlite_database(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
