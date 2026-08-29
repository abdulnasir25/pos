<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Exceptions\DuplicatePermissionSlugException;
use App\Modules\Access\Models\Permission;

class CreatePermission
{
    public function handle(string $slug, string $description): Permission
    {
        if (Permission::where('slug', $slug)->exists()) {
            throw DuplicatePermissionSlugException::forSlug($slug);
        }

        return Permission::create([
            'slug' => $slug,
            'description' => $description,
        ]);
    }
}
