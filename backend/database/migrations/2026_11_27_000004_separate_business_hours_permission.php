<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const HOURS = 'shop.hours.write';

    public function up(): void
    {
        $this->updateAssignments(true);
    }

    public function down(): void
    {
        $this->updateAssignments(false);
    }

    private function permissions(array $permissions, bool $add): array
    {
        if (! $add) {
            return array_values(array_diff($permissions, [self::HOURS]));
        }
        if (in_array('shop.manage', $permissions, true) && ! in_array(self::HOURS, $permissions, true)) {
            $permissions[] = self::HOURS;
        }

        return $permissions;
    }

    private function updateAssignments(bool $add): void
    {
        DB::transaction(function () use ($add) {
            DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
            $grants = DB::table('website_shop_access')
                ->when($add, fn ($query) => $query->whereIn('user_id', DB::table('users')->where('website_role', 'super_admin')->select('id')))
                ->get(['user_id', 'shop_id', 'permissions']);
            $changedUsers = [];
            foreach ($grants as $grant) {
                $current = json_decode($grant->permissions, true, flags: JSON_THROW_ON_ERROR);
                $permissions = $this->permissions($current, $add);
                if ($permissions !== $current) {
                    DB::table('website_shop_access')->where('user_id', $grant->user_id)->where('shop_id', $grant->shop_id)
                        ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR)]);
                    $changedUsers[$grant->user_id] = $grant->user_id;
                }
            }
            DB::table('users')->whereIn('id', array_values($changedUsers))->increment('website_access_version');

            DB::table('website_admin_invitations')->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->select(['id', 'assignments'])->eachById(function ($invitation) use ($add) {
                    $current = json_decode($invitation->assignments, true, flags: JSON_THROW_ON_ERROR);
                    $assignments = array_map(fn ($permissions) => $this->permissions($permissions, $add), $current);
                    if ($assignments !== $current) {
                        DB::table('website_admin_invitations')->where('id', $invitation->id)
                            ->update(['assignments' => json_encode((object) $assignments, JSON_THROW_ON_ERROR)]);
                    }
                });
        }, 5);
    }
};
