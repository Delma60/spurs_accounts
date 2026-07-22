<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'description', 'is_system', 'is_default'];

    protected $casts = ['is_system' => 'boolean', 'is_default' => 'boolean'];

    /** The role slugs auto-granted to every new account. */
    public static function defaultRoleNames(): array
    {
        return static::where('is_default', true)->pluck('name')->all();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
