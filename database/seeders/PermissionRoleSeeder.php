<?php
namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users'         => ['users.view','users.edit','users.suspend','users.delete'],
            'listings'      => ['listings.view','listings.approve','listings.edit','listings.suspend','listings.restore'],
            'verification'  => ['verification.view','verification.review'],
            'orders'        => ['orders.view','orders.manage'],
            'offers'        => ['offers.view'],
            'disputes'      => ['disputes.view','disputes.manage','disputes.resolve'],
            'refunds'       => ['refunds.view','refunds.create','refunds.approve'],
            'withdrawals'   => ['withdrawals.view','withdrawals.approve','withdrawals.reject','withdrawals.process','withdrawals.complete'],
            'wallets'       => ['wallets.view','wallets.reconcile','wallets.adjust'],
            'payments'      => ['payments.view'],
            'categories'    => ['categories.manage'],
            'promotions'    => ['promotions.view','promotions.manage','promotions.feature','promotions.refund'],
            'notifications' => ['notifications.view','notifications.manage','notifications.broadcast'],
            'sms'           => ['sms.view','sms.manage','sms.resend'],
            'settings'      => ['settings.view','settings.manage','settings.notifications','settings.sms'],
            'reports'       => ['reports.view','reports.export'],
            'tickets'       => ['tickets.view','tickets.manage','tickets.assign'],
            'staff'         => ['staff.view','staff.manage'],
            'roles'         => ['roles.view','roles.manage'],
            'audit'         => ['audit.view'],
            'fraud'         => ['fraud.view','fraud.manage'],
        ];

        foreach ($permissions as $group => $names) {
            foreach ($names as $name) {
                Permission::firstOrCreate(['name' => $name], ['group' => $group, 'description' => '']);
            }
        }

        $all = Permission::pluck('name')->all();

        $roles = [
            // Super admin: all permissions + protected
            'super_admin' => [
                'display' => 'Super Admin', 'protected' => true,
                'perms'   => $all,
            ],
            // Admin: broad ops, no sensitive financial mutate or staff manage
            'admin' => [
                'display' => 'Administrator', 'protected' => false,
                'perms'   => array_merge(
                    ['users.view','users.edit','users.suspend'],
                    ['listings.view','listings.approve','listings.edit','listings.suspend','listings.restore'],
                    ['verification.view','verification.review'],
                    ['orders.view','orders.manage'],
                    ['offers.view'],
                    ['disputes.view','disputes.manage','disputes.resolve'],
                    ['promotions.view','promotions.manage','promotions.feature'],
                    ['notifications.view','notifications.manage'],
                    ['categories.manage'],
                    ['settings.view','settings.manage'],
                    ['reports.view','reports.export'],
                    ['tickets.view','tickets.manage','tickets.assign'],
                    ['audit.view'],
                    ['fraud.view','fraud.manage'],
                ),
            ],
            // Moderator: marketplace safety, no finance
            'moderator' => [
                'display' => 'Moderator', 'protected' => false,
                'perms'   => [
                    'users.view',
                    'listings.view','listings.approve','listings.edit','listings.suspend','listings.restore',
                    'verification.view','verification.review',
                    'offers.view',
                    'disputes.view',
                    'promotions.view',
                    'reports.view',
                    'tickets.view',
                    'categories.manage',
                ],
            ],
            // Support: customer help, no finance
            'support' => [
                'display' => 'Support', 'protected' => false,
                'perms'   => [
                    'users.view',
                    'orders.view',
                    'offers.view',
                    'disputes.view','disputes.manage',
                    'tickets.view','tickets.manage','tickets.assign',
                    'listings.view',
                    'notifications.view',
                ],
            ],
            // Finance: financial operations only
            'finance' => [
                'display' => 'Finance Admin', 'protected' => false,
                'perms'   => [
                    'payments.view',
                    'wallets.view','wallets.reconcile','wallets.adjust',
                    'withdrawals.view','withdrawals.approve','withdrawals.reject','withdrawals.process','withdrawals.complete',
                    'refunds.view','refunds.create','refunds.approve',
                    'disputes.view','disputes.resolve',
                    'promotions.view','promotions.refund',
                    'orders.view',
                    'reports.view','reports.export',
                    'audit.view',
                ],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $data['display'],
                    'is_admin_role'=> true,
                    'is_protected' => $data['protected'],
                ]
            );
            $ids = Permission::whereIn('name', $data['perms'])->pluck('id');
            $role->permissions()->sync($ids);
        }
    }
}
