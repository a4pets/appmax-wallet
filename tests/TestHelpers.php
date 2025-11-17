<?php

namespace Tests;

use App\Models\User;
use App\Models\Account;
use App\Models\Balance;

trait TestHelpers
{
    /**
     * Create a user with account and balance
     */
    protected function createUserWithAccount(float $initialBalance = 0): User
    {
        $user = User::factory()->withAccount($initialBalance)->create();
        $user->refresh();

        return $user;
    }
}
