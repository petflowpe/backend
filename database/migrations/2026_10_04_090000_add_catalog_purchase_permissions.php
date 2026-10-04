<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const CATEGORIES = ['services', 'suppliers', 'purchases'];

    private const ROLE_GRANTS = [
        'company_admin' => ['services.*', 'suppliers.*', 'purchases.*'],
        'company_user' => [
            'products.view',
            'services.view',
            'kardex.view',
            'suppliers.view',
            'purchases.view',
            'purchases.create',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $all = Permission::getSystemPermissions();
        foreach (self::CATEGORIES as $category) {
            foreach ($all[$category] ?? [] as $name => $data) {
                Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'display_name' => $data['display_name'],
                        'description' => $data['description'],
                        'category' => $category,
                        'is_system' => true,
                        'active' => true,
                    ]
                );
            }
        }

        foreach (self::ROLE_GRANTS as $roleName => $grants) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $json = is_array($role->permissions) ? $role->permissions : [];
            $role->permissions = array_values(array_unique(array_merge($json, $grants)));
            $role->save();

            $names = collect($grants)
                ->flatMap(fn (string $p) => Permission::expandWildcardPermission($p))
                ->unique()
                ->all();
            $ids = Permission::whereIn('name', $names)->pluck('id')->all();
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        foreach (self::ROLE_GRANTS as $roleName => $grants) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }
            $json = is_array($role->permissions) ? $role->permissions : [];
            $role->permissions = array_values(array_diff($json, $grants));
            $role->save();
        }

        Permission::whereIn('category', self::CATEGORIES)->delete();
    }
};
