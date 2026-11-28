<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
            $grants = DB::table('website_shop_access')->get(['user_id', 'shop_id', 'permissions']);
            $changedUsers = [];
            foreach ($grants as $grant) {
                $current = json_decode($grant->permissions, true, flags: JSON_THROW_ON_ERROR);
                $permissions = $this->preservePaymentAccess($current);
                if ($permissions !== $current) {
                    DB::table('website_shop_access')->where('user_id', $grant->user_id)->where('shop_id', $grant->shop_id)
                        ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR)]);
                    $changedUsers[] = $grant->user_id;
                }
            }
            DB::table('users')->whereIn('id', array_unique($changedUsers))->increment('website_access_version');

            DB::table('website_admin_invitations')->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->select(['id', 'assignments'])->eachById(function ($invitation) {
                    $current = json_decode($invitation->assignments, true, flags: JSON_THROW_ON_ERROR);
                    $assignments = array_map($this->preservePaymentAccess(...), $current);
                    if ($assignments !== $current) {
                        DB::table('website_admin_invitations')->where('id', $invitation->id)
                            ->update(['assignments' => json_encode((object) $assignments, JSON_THROW_ON_ERROR)]);
                    }
                });
        }, 5);
    }

    public function down(): void
    {
        // Keep explicit grants stored so a later upgrade preserves the owner's choices.
    }

    private function preservePaymentAccess(array $permissions): array
    {
        if (array_intersect(['reports.read', 'payments.record'], $permissions) && ! in_array('payments.read', $permissions, true)) {
            $permissions[] = 'payments.read';
        }

        return $permissions;
    }
};
