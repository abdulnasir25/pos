<?php

namespace App\Modules\Access\Actions;

use App\Models\User;

/**
 * Creates a staff login. Uniqueness of the email is enforced by
 * validation at the controller layer (a plain unique-column check, not
 * a domain rule worth duplicating here) rather than this action.
 */
class CreateUser
{
    public function handle(string $name, string $email, string $password): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => 'active',
        ]);
    }
}
