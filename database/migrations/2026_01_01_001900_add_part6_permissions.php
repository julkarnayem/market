<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {
        $permissions = [
            'promotions.view','promotions.manage','promotions.feature','promotions.refund',
            'notifications.view','notifications.manage','notifications.broadcast',
            'sms.view','sms.manage','sms.resend',
            'settings.notifications','settings.sms',
        ];
        foreach ($permissions as $name) {
            \App\Models\Permission::firstOrCreate(['name'=>$name],
                ['group' => explode('.',$name)[0]]);
        }
        // Grant all to admin role
        $adminRole = \App\Models\Role::where('name','admin')->first();
        if ($adminRole) {
            $ids = \App\Models\Permission::whereIn('name',$permissions)->pluck('id');
            $adminRole->permissions()->syncWithoutDetaching($ids);
        }
        // Finance gets promotions.view + promotions.refund
        $finance = \App\Models\Role::where('name','finance')->first();
        if ($finance) {
            $ids = \App\Models\Permission::whereIn('name',['promotions.view','promotions.refund'])->pluck('id');
            $finance->permissions()->syncWithoutDetaching($ids);
        }
    }
    public function down(): void {}
};
