<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The canonical Spurs Cloud permission catalogue and default roles. Permissions
 * are namespaced by service ("pay.refund", "wallet.credit"); a "<service>.*"
 * permission is a wildcard for that whole service. Idempotent — safe to re-run.
 */
class RolesPermissionsSeeder extends Seeder
{
    /** service => [ key (without prefix) => description ] */
    private const CATALOG = [
        'accounts' => [
            'users.view' => 'View accounts and profiles',
            'users.manage' => 'Edit, verify, suspend and delete accounts',
            'roles.manage' => 'Create roles and assign them to users',
            'security.view' => 'View security events and sessions',
            'sessions.revoke' => 'Revoke sessions and connected apps',
            'fraud.view' => 'View fraud alerts and risk monitoring',
            'fraud.manage' => 'Resolve alerts, block IPs and manage risk rules',
            'settings.manage' => 'Change identity platform settings',
        ],
        'admin' => [
            'access' => 'Sign in to the admin control plane',
            'audit.view' => 'View the admin audit log',
            'settings.manage' => 'Change platform-wide settings',
        ],
        'pay' => [
            'view' => 'View payments and merchants',
            'refund' => 'Issue refunds',
            'payout' => 'Approve and send payouts',
            'disputes.manage' => 'Handle chargebacks and disputes',
            'settings' => 'Change processor and gateway settings',
        ],
        'wallet' => [
            'view' => 'View wallets and transactions',
            'credit' => 'Credit wallets',
            'debit' => 'Debit wallets',
            'freeze' => 'Freeze or unfreeze wallets',
            'export' => 'Export ledgers and statements',
        ],
        'cloud' => [
            'view' => 'View projects and resources',
            'projects.manage' => 'Create, configure and delete projects',
            'logs.view' => 'View project logs and metrics',
        ],
        'survey' => [
            'view' => 'View surveys and submissions',
            'manage' => 'Create and manage surveys and payouts',
            'export' => 'Export responses and results',
        ],
        'billing' => [
            'view' => 'View invoices and usage',
            'manage' => 'Manage plans and billing',
            'refund' => 'Refund invoices and credits',
        ],
    ];

    /** role slug => [label, description, is_system, is_default, permission keys | "*" for all] */
    private const ROLES = [
        'superadmin' => ['Super Admin', 'Unrestricted access to everything', true, false, '*'],
        'admin' => ['Administrator', 'Manage the platform day to day', true, false, [
            'admin.access', 'admin.audit.view', 'accounts.users.view', 'accounts.users.manage',
            'accounts.security.view', 'accounts.sessions.revoke', 'accounts.fraud.view', 'accounts.fraud.manage', 'accounts.settings.manage',
            'pay.view', 'pay.refund', 'pay.payout', 'pay.disputes.manage', 'wallet.view', 'wallet.credit', 'wallet.freeze',
            'cloud.view', 'survey.view', 'survey.manage', 'billing.view', 'billing.manage',
        ]],
        'support' => ['Support', 'Help users and handle refunds', true, false, [
            'admin.access', 'accounts.users.view', 'accounts.security.view', 'accounts.fraud.view',
            'pay.view', 'pay.refund', 'wallet.view', 'cloud.view', 'billing.view',
        ]],
        'developer' => ['Developer', 'Build on the platform', true, false, [
            'admin.access', 'cloud.view', 'cloud.projects.manage', 'pay.view', 'wallet.view',
        ]],
        // A capability marker, not an admin role: an account becomes a merchant
        // by gaining this role (when it sets up a business profile). Rides the
        // SSO claims so Pay/Business can gate merchant features on it. No admin
        // permissions — merchants act only on their own resources.
        'merchant' => ['Merchant', 'Account with a business profile that can accept payments', true, false, []],
        'user' => ['User', 'Standard account, granted to everyone by default', true, true, []],
    ];

    public function run(): void
    {
        // Permissions
        $permByKey = [];
        foreach (self::CATALOG as $service => $perms) {
            foreach ($perms as $suffix => $description) {
                $key = "{$service}.{$suffix}";
                $permByKey[$key] = Permission::updateOrCreate(
                    ['key' => $key],
                    ['service' => $service, 'description' => $description],
                );
            }
            // service wildcard
            $wild = "{$service}.*";
            $permByKey[$wild] = Permission::updateOrCreate(
                ['key' => $wild],
                ['service' => $service, 'description' => "Full access to {$service}"],
            );
        }

        // Roles + attach permissions
        foreach (self::ROLES as $name => [$label, $description, $isSystem, $isDefault, $keys]) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['label' => $label, 'description' => $description, 'is_system' => $isSystem, 'is_default' => $isDefault],
            );

            $ids = $keys === '*'
                ? array_map(fn ($p) => $p->id, $permByKey)
                : array_values(array_filter(array_map(fn ($k) => $permByKey[$k]->id ?? null, $keys)));

            $role->permissions()->sync($ids);
        }

        // Baseline: every existing account gets the default role(s).
        $defaultIds = Role::where('is_default', true)->pluck('id')->all();
        User::query()->each(function (User $u) use ($defaultIds) {
            $u->roles()->syncWithoutDetaching($defaultIds);
        });

        // Bootstrap a superadmin so the platform is manageable from day one.
        $rootEmail = env('ADMIN_ROOT_EMAIL', 'oladeleofficial@gmail.com');
        $root = User::where('email', $rootEmail)->first() ?? User::orderBy('id')->first();
        $root?->assignRole('superadmin');
    }
}
