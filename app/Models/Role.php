<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    // `description` and `is_protected` are real columns (see the staff-roles
    // migration) but were missing here, so every write dropped them silently:
    // the Roles form's Description field never saved, and the seeder's
    // is_protected=true for super_admin never persisted — leaving the
    // "protected role" guard in RoleController unreachable.
    protected $fillable = ['name', 'display_name', 'is_admin_role', 'is_protected', 'description'];
    protected $casts = ['is_admin_role' => 'boolean', 'is_protected' => 'boolean'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
