<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAccess;
use App\Services\WebsiteAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminRolesController extends Controller
{
    public function index(Request $request)
    {
        $identity = ShopAccess::accountIdentity($request);
        $actor = $request->user();
        $role = WebsiteAccess::role($actor);
        $canManage = in_array($role, ['owner', 'super_admin'], true);
        $scope = $role === 'super_admin' ? WebsiteAccess::assignments($actor) : [];
        $accounts = $canManage ? User::where('id', '!=', $actor->id)
            ->where(fn ($query) => $query->whereNotNull('website_role')
                ->when($role === 'owner', fn ($query) => $query->orWhere('is_platform_admin', true)))
            ->orderBy('name')->orderBy('id')->get()->filter(fn (User $target) => $this->canEdit($actor, $target, $scope))
            ->map(fn (User $target) => $this->accountData($target))->values() : [];
        $shopIds = $role === 'owner' ? null : ($role === 'super_admin' ? array_keys($scope)
            : (in_array('members.manage', $identity['permissions'], true) ? [$identity['shop']->id] : []));
        $audit = DB::table('audit_events as event')
            ->leftJoin('users as actor', 'actor.id', '=', 'event.user_id')
            ->leftJoin('users as subject', fn ($join) => $join->on('subject.id', '=', 'event.subject_id')
                ->where('event.action', '!=', 'invitation.created'))
            ->leftJoin('shops as shop', 'shop.id', '=', 'event.shop_id')
            ->whereIn('event.action', ['website.owner_initialized', 'website.role_changed', 'website.shop_access_changed',
                'member.updated', 'member.removed', 'invitation.created', 'membership.joined', 'platform.access.granted'])
            ->when($shopIds !== null, fn ($query) => $query->where(fn ($filter) => $filter
                ->whereIn('event.shop_id', $shopIds)
                ->when($role === 'super_admin', fn ($scoped) => $scoped->orWhere('event.user_id', $actor->id))))
            ->orderByDesc('event.id')->paginate(20, ['event.id', 'event.action', 'event.user_id',
                'event.subject_id', 'event.shop_id', 'event.details', 'event.created_at',
                'actor.name as actor_name', 'subject.name as subject_name', 'shop.name as shop_name'])
            ->through(function ($event) {
                $event->details = $event->details === null ? null : json_decode($event->details, true);
                $event->created_at = Carbon::parse($event->created_at)->toIso8601String();

                return $event;
            });

        return response()->json([
            'identity' => $identity,
            'my_assignments' => (object) ($role ? WebsiteAccess::assignments($actor) : []),
            'can_manage' => $canManage,
            'can_view_activity' => $canManage || (bool) $shopIds,
            'accounts' => $accounts,
            'assignable_roles' => $role === 'owner' ? ['super_admin', 'admin'] : ($canManage ? ['admin'] : []),
            'permission_options' => ['admin' => WebsiteAccess::ADMIN_PERMISSIONS,
                'super_admin' => WebsiteAccess::SUPER_ADMIN_PERMISSIONS],
            'shop_options' => $canManage ? Shop::query()->when($shopIds !== null, fn ($query) => $query->whereIn('id', $shopIds))
                ->orderBy('name')->get(['id', 'name'])->map(fn (Shop $shop) => [
                    'id' => $shop->id, 'name' => $shop->name,
                    'delegable' => $role === 'owner' ? WebsiteAccess::ADMIN_PERMISSIONS
                        : array_values(array_intersect(WebsiteAccess::ADMIN_PERMISSIONS, $scope[$shop->id])),
                ]) : [],
            'activity' => $audit,
        ])->header('Cache-Control', 'no-store');
    }

    public function account(Request $request)
    {
        $actor = $this->administrator($request);
        $input = $request->validate(['email' => 'required|email|max:150']);
        $target = User::where('email', strtolower(trim($input['email'])))->first();
        abort_unless($target && $this->canEdit($actor, $target, WebsiteAccess::assignments($actor)), 404,
            'No manageable account matches that email address.');

        return response()->json(['account' => $this->accountData($target)])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, int $account)
    {
        $this->administrator($request);
        $input = $request->validate([
            'version' => 'required|integer|min:0',
            'role' => ['present', 'nullable', Rule::in(['super_admin', 'admin'])],
            'suspended' => 'required|boolean',
            'assignments' => 'present|array|max:200',
            'assignments.*' => 'required|array:shop_id,permissions',
            'assignments.*.shop_id' => 'required|integer|distinct|exists:shops,id',
            'assignments.*.permissions' => 'required|array|max:7',
            'assignments.*.permissions.*' => ['required', 'string', Rule::in(WebsiteAccess::SUPER_ADMIN_PERMISSIONS)],
        ], [], ['assignments.*.shop_id' => 'business']);

        return DB::transaction(function () use ($request, $account, $input) {
            DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
            $request->user()->refresh();
            $actor = $this->administrator($request);
            $target = User::whereKey($account)->lockForUpdate()->firstOrFail();
            $isOwner = WebsiteAccess::role($actor) === 'owner';
            $scope = $isOwner ? [] : WebsiteAccess::assignments($actor);
            abort_unless($this->canEdit($actor, $target, $scope), 403, 'You cannot manage access for this account.');
            $role = $input['role'];
            abort_if(! $isOwner && $role === 'super_admin', 403, 'Only the Owner can grant Super Admin access.');
            abort_unless($target->website_access_version === (int) $input['version'], 409,
                'Access has changed. Reload this account before saving.');
            $assignments = $this->validatedAssignments($input, $isOwner, $scope);
            $before = WebsiteAccess::snapshot($target);
            $after = ['role' => $role, 'suspended' => $role !== null && (bool) $input['suspended'], 'legacy_platform' => false,
                'assignments' => (object) $assignments];

            if ((array) $before['assignments'] !== $assignments || $before['role'] !== $role
                || $before['suspended'] !== $after['suspended'] || $target->is_platform_admin) {
                $target->forceFill(['website_role' => $role, 'website_suspended' => $after['suspended'],
                    'website_access_version' => $target->website_access_version + 1,
                    'is_admin' => $target->is_admin || $role !== null, 'is_platform_admin' => false])->save();
                $this->saveAssignments($target, $assignments);
                $this->auditChange($request, $target, $before, $after);
            }

            return response()->json(['account' => $this->accountData($target)])->header('Cache-Control', 'no-store');
        }, 5);
    }

    private function administrator(Request $request): User
    {
        $actor = WebsiteAccess::authorizeAccount($request);
        abort_unless(in_array(WebsiteAccess::role($actor), ['owner', 'super_admin'], true), 403);

        return $actor;
    }

    private function canEdit(User $actor, User $target, array $scope): bool
    {
        if ($actor->id === $target->id || $target->website_role === 'owner') {
            return false;
        }
        if (WebsiteAccess::role($actor) === 'owner') {
            return true;
        }
        if ($target->is_platform_admin || $target->website_role === 'super_admin' || ! $scope) {
            return false;
        }
        if ($target->website_role === null) {
            return true;
        }
        $assignments = WebsiteAccess::assignments($target);

        return $assignments && ! collect($assignments)->contains(
            fn (array $permissions, int $shop) => (bool) array_diff($permissions, $scope[$shop] ?? []));
    }

    private function accountData(User $account): array
    {
        return ['id' => $account->id, 'name' => $account->name, 'email' => $account->email,
            'version' => $account->website_access_version,
            ...WebsiteAccess::snapshot($account)];
    }

    private function validatedAssignments(array $input, bool $isOwner, array $scope): array
    {
        $role = $input['role'];
        $rows = $input['assignments'];
        if ($role === null && $rows) {
            throw ValidationException::withMessages(['assignments' => 'Remove all website business assignments when removing the website role.']);
        }
        if ($role === 'admin' && ! $rows) {
            throw ValidationException::withMessages(['assignments' => 'An Admin needs at least one business assignment.']);
        }
        $allowed = $role === 'super_admin' ? WebsiteAccess::SUPER_ADMIN_PERMISSIONS : WebsiteAccess::ADMIN_PERMISSIONS;
        $result = [];
        foreach ($rows as $row) {
            $shop = (int) $row['shop_id'];
            $permissions = array_values(array_intersect($allowed, $row['permissions']));
            abort_if(array_diff($row['permissions'], $allowed)
                || (! $isOwner && array_diff($permissions, $scope[$shop] ?? [])), 403, 'A business or permission is outside your delegation scope.');
            if ((array_intersect(['bookings.write', 'reports.read', 'payments.record'], $permissions)
                    && ! in_array('bookings.read', $permissions, true))
                || (in_array('payments.record', $permissions, true) && ! in_array('reports.read', $permissions, true))) {
                throw ValidationException::withMessages(['assignments' => 'Booking edits and reports require appointment viewing. Payment recording also requires report access.']);
            }
            $result[$shop] = $permissions;
        }
        ksort($result);

        return $result;
    }

    private function saveAssignments(User $target, array $assignments): void
    {
        $query = DB::table('website_shop_access')->where('user_id', $target->id);
        $query->whereNotIn('shop_id', array_keys($assignments))->delete();
        foreach ($assignments as $shop => $permissions) {
            DB::table('website_shop_access')->updateOrInsert(['user_id' => $target->id, 'shop_id' => $shop],
                ['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR)]);
        }
    }

    private function auditChange(Request $request, User $target, array $before, array $after): void
    {
        $previous = (array) $before['assignments'];
        $current = (array) $after['assignments'];
        unset($before['assignments'], $after['assignments']);
        if ($before !== $after || $previous === $current) {
            ShopAccess::audit($request, 'website.role_changed', $target->id, compact('before', 'after'));
        }
        foreach (array_unique([...array_keys($previous), ...array_keys($current)]) as $shop) {
            if (($previous[$shop] ?? []) !== ($current[$shop] ?? []) || $before !== $after) {
                ShopAccess::audit($request, 'website.shop_access_changed', $target->id, [
                    'before' => [...$before, 'permissions' => $previous[$shop] ?? []],
                    'after' => [...$after, 'permissions' => $current[$shop] ?? []],
                ], $shop);
            }
        }
    }
}
