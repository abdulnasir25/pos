<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Exceptions\DuplicateRoleSlugException;
use App\Modules\Access\Models\Role;

class CreateRole
{
    public function handle(string $name, string $slug, bool $isProtected = false): Role
    {
        if (Role::where('slug', $slug)->exists()) {
            throw DuplicateRoleSlugException::forSlug($slug);
        }

        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'is_protected' => $isProtected,
        ]);
    }
}
