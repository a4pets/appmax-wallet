<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed transaction types for every test that uses database
        if ($this->usesDatabase()) {
            $this->seed(\Database\Seeders\TransactionTypeSeeder::class);
        }
    }

    protected function usesDatabase(): bool
    {
        $uses = array_flip(class_uses_recursive(static::class));

        return isset($uses[\Illuminate\Foundation\Testing\RefreshDatabase::class]) ||
               isset($uses[\Illuminate\Foundation\Testing\DatabaseMigrations::class]) ||
               isset($uses[\Illuminate\Foundation\Testing\DatabaseTransactions::class]);
    }
}
