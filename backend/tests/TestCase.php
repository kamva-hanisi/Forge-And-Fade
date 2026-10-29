<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function grantShopRole(User $user, string $role = 'owner', int $shop = 1, ?int $barber = null): void
    {
        $user->forceFill(['is_admin' => true])->save();
        DB::table('shop_memberships')->updateOrInsert(['shop_id' => $shop, 'user_id' => $user->id],
            ['role' => $role, 'barber_id' => $barber, 'created_at' => now(), 'updated_at' => now()]);
    }
}
