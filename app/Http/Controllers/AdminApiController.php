<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The identity provider's internal admin surface. Consumed by the Spurs admin
 * control plane over the internal secret. Everything the admin's "Accounts"
 * service shows or changes goes through here — accounts stays the source of
 * truth for identity, roles and permissions.
 */
class AdminApiController extends Controller
{
    /** Headline identity stats + role distribution. */
    public function overview()
    {
        return response()->json([
            'users' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'avgTrust' => (int) round((float) User::whereNotNull('trust_score')->avg('trust_score')),
            'roles' => Role::count(),
            'permissions' => Permission::where('key', 'not like', '%.*')->count(),
            'events' => SecurityEvent::count(),
            'roleDistribution' => Role::withCount('users')->orderBy('name')->get()
                ->map(fn (Role $r) => ['name' => $r->name, 'label' => $r->label, 'users' => $r->users_count]),
            'recentUsers' => User::latest()->limit(6)->get()
                ->map(fn (User $u) => $this->userSummary($u->load('roles'))),
        ]);
    }

    /** Every account with its roles. */
    public function users(Request $request)
    {
        $users = User::with('roles')
            ->when($request->query('q'), fn ($qq, $q) => $qq->where(fn ($w) =>
                $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->latest()
            ->limit((int) $request->query('limit', 500))
            ->get()
            ->map(fn (User $u) => $this->userSummary($u));

        return response()->json(['users' => $users]);
    }

    /** One account with roles, effective permissions and recent security events. */
    public function user(int $id)
    {
        $user = User::with('roles.permissions')->findOrFail($id);
        $trust = \App\Support\TrustScore::for($user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'trust' => $trust,
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles->map(fn (Role $r) => ['name' => $r->name, 'label' => $r->label]),
            'permissions' => $user->permissionKeys(),
            'connected_apps' => $user->tokens()->where('revoked', false)->distinct('client_id')->count('client_id'),
            'security_events' => $user->securityEvents()->limit(12)->get()
                ->map(fn (SecurityEvent $e) => [
                    'type' => $e->type, 'label' => $e->label(), 'ip' => $e->ip,
                    'device' => $e->device, 'at' => $e->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /** All roles with their permission keys and how many users hold each. */
    public function roles()
    {
        $roles = Role::with('permissions')->withCount('users')->orderByDesc('is_system')->orderBy('name')->get()
            ->map(fn (Role $r) => [
                'id' => $r->id, 'name' => $r->name, 'label' => $r->label,
                'description' => $r->description, 'is_system' => $r->is_system, 'is_default' => $r->is_default,
                'users' => $r->users_count,
                'permissions' => $r->permissions->pluck('key')->values(),
            ]);

        return response()->json(['roles' => $roles]);
    }

    /** The permission catalogue, grouped by service. */
    public function permissions()
    {
        $grouped = Permission::orderBy('service')->orderBy('key')->get()
            ->groupBy('service')
            ->map(fn ($perms) => $perms->map(fn (Permission $p) => [
                'key' => $p->key, 'description' => $p->description,
            ])->values());

        return response()->json(['permissions' => $grouped]);
    }

    public function createRole(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'alpha_dash', 'max:50', 'unique:roles,name'],
            'label' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $role = Role::create([
            'name' => $data['name'], 'label' => $data['label'],
            'description' => $data['description'] ?? null, 'is_system' => false,
            'is_default' => $data['is_default'] ?? false,
        ]);
        $this->syncPermissions($role, $data['permissions'] ?? []);

        return response()->json(['ok' => true, 'id' => $role->id], 201);
    }

    public function updateRole(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role->update(array_filter([
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
        ], fn ($v) => $v !== null));

        if ($request->has('is_default')) {
            $role->update(['is_default' => (bool) $data['is_default']]);
        }

        if ($request->has('permissions')) {
            $this->syncPermissions($role, $data['permissions']);
        }

        return response()->json(['ok' => true]);
    }

    public function deleteRole(int $id)
    {
        $role = Role::findOrFail($id);
        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }
        $role->delete();

        return response()->json(['ok' => true]);
    }

    /** Replace a user's roles. */
    public function assignRoles(Request $request, int $id)
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string'],
        ]);

        $user = User::findOrFail($id);
        $user->syncRoles($data['roles']);

        return response()->json(['ok' => true, 'roles' => $user->roleNames()]);
    }

    /** Platform-wide recent security events. */
    public function securityEvents(Request $request)
    {
        $events = SecurityEvent::with('user:id,name,email')->latest()
            ->limit((int) $request->query('limit', 100))->get()
            ->map(fn (SecurityEvent $e) => $this->eventPayload($e));

        return response()->json(['events' => $events]);
    }

    /** KYC review queue. Flags national IDs reused across accounts. */
    public function kycQueue(Request $request)
{
    $status = $request->query('status');
    $rows = \App\Models\KycProfile::with('user:id,name,email')
        ->when($status, fn ($q) => $q->where('status', $status))
        ->orderByRaw("case status when 'pending' then 0 when 'rejected' then 1 else 2 end")
        ->orderByDesc('submitted_at')
        ->limit((int) $request->query('limit', 200))
        ->get();

    return response()->json([
        'kyc' => $rows->map(fn ($k) => [
            'id' => $k->id,
            'user' => $k->user ? ['id' => $k->user->id, 'name' => $k->user->name, 'email' => $k->user->email] : null,
            'level' => $k->level, 'status' => $k->status, 'tier' => $k->tierLabel(),
            'id_type' => $k->id_type, 'id_masked' => $k->id_masked,
            'tier2_id_type' => $k->tier2_id_type, 'tier2_id_masked' => $k->tier2_id_masked,
            'full_name' => $k->full_name, 'phone' => $k->phone,
            'state' => $k->state, 'country' => $k->country,
            'duplicates' => $k->duplicates()->count(),
            'rejection_reason' => $k->rejection_reason,
            'submitted_at' => $k->submitted_at?->toIso8601String(),
            'reviewed_at' => $k->reviewed_at?->toIso8601String(),
            'reviewed_by' => $k->reviewed_by,
        ]),
        'counts' => [
            'pending' => \App\Models\KycProfile::where('status', 'pending')->count(),
            'verified' => \App\Models\KycProfile::where('status', 'verified')->count(),
            'rejected' => \App\Models\KycProfile::where('status', 'rejected')->count(),
        ],
    ]);
}

    /** Approve or reject a KYC submission. */
    public function reviewKyc(Request $request, int $id)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'level' => ['nullable', 'integer', 'min:1', 'max:3'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reviewer' => ['nullable', 'string', 'max:120'],
        ]);

        $kyc = \App\Models\KycProfile::findOrFail($id);
        $approve = $data['decision'] === 'approve';

        $kyc->update([
            'status' => $approve ? 'verified' : 'rejected',
            'level' => $approve ? ($data['level'] ?? max(1, $kyc->level)) : 0,
            'rejection_reason' => $approve ? null : ($data['reason'] ?? 'Did not meet requirements'),
            'reviewed_by' => $data['reviewer'] ?? 'admin',
            'reviewed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'status' => $kyc->status, 'level' => $kyc->level]);
    }

    /** Platform settings catalogue (schema + current values), grouped. */
    public function settings()
    {
        return response()->json(['settings' => \App\Support\Settings::catalog()]);
    }

    /** Persist a batch of platform settings. Takes effect immediately. */
    public function updateSettings(Request $request)
    {
        $values = $request->validate(['values' => ['required', 'array']])['values'];
        \App\Support\Settings::save($values);

        return response()->json(['ok' => true, 'settings' => \App\Support\Settings::catalog()]);
    }

    /** Time series + distributions for the Accounts overview charts. */
    public function analytics(Request $request)
    {
        $days = max(7, min(60, (int) $request->query('days', 14)));
        $from = now()->subDays($days - 1)->startOfDay();

        $dates = collect(range($days - 1, 0))->map(fn ($d) => now()->subDays($d)->toDateString());

        $logins = SecurityEvent::where('type', 'login')->where('created_at', '>=', $from)
            ->get(['created_at'])->groupBy(fn ($e) => $e->created_at->toDateString())->map->count();
        $signups = User::where('created_at', '>=', $from)
            ->get(['created_at'])->groupBy(fn ($u) => $u->created_at->toDateString())->map->count();

        $series = fn ($counts) => $dates->map(fn ($d) => [
            'date' => $d, 'count' => (int) ($counts[$d] ?? 0),
        ])->values();

        // Trust bands (ordinal green→red), fixed order.
        $order = ['excellent', 'good', 'fair', 'poor', 'at risk'];
        $bandCounts = User::whereNotNull('trust_score')->get(['trust_score'])
            ->groupBy(fn ($u) => \App\Support\TrustScore::band($u->trust_score))->map->count();
        $trustBands = collect($order)->map(fn ($b) => ['band' => $b, 'count' => (int) ($bandCounts[$b] ?? 0)]);

        return response()->json([
            'loginsSeries' => $series($logins),
            'signupsSeries' => $series($signups),
            'trustBands' => $trustBands,
        ]);
    }

    /** Anti-fraud dashboard: risk headline, top locations/IPs, recent alerts. */
    public function fraudOverview()
    {
        $since = now()->subDays(30);

        $topCountries = SecurityEvent::whereNotNull('country_name')
            ->where('created_at', '>=', $since)
            ->selectRaw('country_name, count(*) as c')->groupBy('country_name')
            ->orderByDesc('c')->limit(6)->get()
            ->map(fn ($r) => ['name' => $r->country_name, 'count' => (int) $r->c]);

        $topIps = SecurityEvent::whereNotNull('ip')
            ->where('created_at', '>=', $since)
            ->selectRaw('ip, count(*) as c, count(distinct user_id) as users')
            ->groupBy('ip')->orderByDesc('c')->limit(8)->get()
            ->map(fn ($r) => ['ip' => $r->ip, 'count' => (int) $r->c, 'users' => (int) $r->users]);

        return response()->json([
            'flagged' => SecurityEvent::where('flagged', true)->count(),
            'highRisk' => SecurityEvent::where('risk', '>=', 60)->where('created_at', '>=', $since)->count(),
            'failed24h' => SecurityEvent::where('type', 'login_failed')->where('created_at', '>=', now()->subDay())->count(),
            'countries' => SecurityEvent::whereNotNull('country_name')->distinct()->count('country_name'),
            'distinctIps' => SecurityEvent::whereNotNull('ip')->where('created_at', '>=', $since)->distinct()->count('ip'),
            'topCountries' => $topCountries,
            'topIps' => $topIps,
        ]);
    }

    /** The alert queue — flagged / high-risk sign-ins, newest first. */
    public function fraudAlerts(Request $request)
    {
        $min = (int) $request->query('min_risk', 1);
        $alerts = SecurityEvent::with('user:id,name,email')
            ->where(fn ($q) => $q->where('flagged', true)->orWhere('risk', '>=', max($min, 40)))
            ->latest()->limit((int) $request->query('limit', 100))->get()
            ->map(fn (SecurityEvent $e) => $this->eventPayload($e));

        return response()->json(['alerts' => $alerts]);
    }

    private function eventPayload(SecurityEvent $e): array
    {
        return [
            'id' => $e->id, 'type' => $e->type, 'label' => $e->label(),
            'ip' => $e->ip, 'device' => $e->device,
            'country' => $e->country, 'location' => $e->location(),
            'risk' => (int) $e->risk, 'flagged' => (bool) $e->flagged,
            'signals' => $e->signals ?? [],
            'user' => $e->user ? ['id' => $e->user->id, 'name' => $e->user->name, 'email' => $e->user->email] : null,
            'at' => $e->created_at?->toIso8601String(),
        ];
    }

    /**
     * "Did this user do X on their Spurs Account, and did they do it after
     * time T?" Spurs Earn asks this to verify an earn-by-doing task. The
     * timestamp is the point: identity verified last year must not pay out on
     * a task created today.
     */
    public function activity(Request $request)
    {
        $data = $request->validate([
            'user' => ['required', 'string'],
            'kind' => ['required', 'string'],
            'since' => ['nullable', 'date'],
        ]);

        // The SSO subject is the account id; fall back to email for callers
        // that only hold the address.
        $user = User::where('id', $data['user'])->orWhere('email', $data['user'])->first();
        if (! $user) {
            return response()->json(['ok' => true, 'count' => 0, 'latestAt' => null, 'reason' => 'unknown user']);
        }

        $since = isset($data['since']) ? \Carbon\Carbon::parse($data['since']) : null;

        return match ($data['kind']) {
            'kyc' => $this->kycActivity($user, $since, (int) $request->input('level', 2)),
            default => response()->json(['ok' => false, 'count' => 0, 'error' => 'Unsupported activity kind'], 400),
        };
    }

    private function kycActivity(User $user, ?\Carbon\Carbon $since, int $level)
    {
        $kyc = $user->kyc;

        // Must be verified, at or above the required tier, and reviewed after
        // the moment the user started the task.
        $qualifies = $kyc
            && $kyc->status === 'verified'
            && (int) $kyc->level >= $level
            && (! $since || ($kyc->reviewed_at && $kyc->reviewed_at->gt($since)));

        return response()->json([
            'ok' => true,
            'count' => $qualifies ? 1 : 0,
            'latestAt' => $kyc?->reviewed_at?->toIso8601String(),
            'detail' => [
                'status' => $kyc?->status,
                'level' => $kyc ? (int) $kyc->level : 0,
                'requiredLevel' => $level,
            ],
        ]);
    }

    private function userSummary(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'email_verified' => (bool) $u->email_verified_at,
            'trust_score' => $u->trust_score,
            'created_at' => $u->created_at?->toIso8601String(),
            'roles' => $u->roles->map(fn (Role $r) => ['name' => $r->name, 'label' => $r->label])->values(),
        ];
    }

    private function syncPermissions(Role $role, array $keys): void
    {
        $ids = Permission::whereIn('key', $keys)->pluck('id')->all();
        $role->permissions()->sync($ids);
    }
}
