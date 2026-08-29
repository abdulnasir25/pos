<?php

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Platform\Models\LandlordUser;
use Illuminate\Console\Command;

/**
 * Creates the platform owner's own login — separate from every
 * tenant's Access/Roles system. There's no self-registration for this
 * guard by design; the operator provisions it once from the CLI.
 */
class LandlordCreateAdminCommand extends Command
{
    protected $signature = 'landlord:create-admin
        {name : Display name}
        {email : Login email}
        {password : Login password}';

    protected $description = 'Create a landlord (platform admin) login for the SaaS Billing UI.';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (LandlordUser::where('email', $email)->exists()) {
            $this->error("A landlord user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        LandlordUser::create([
            'name' => $this->argument('name'),
            'email' => $email,
            'password' => $this->argument('password'),
        ]);

        $this->info("Landlord admin [{$email}] created.");

        return self::SUCCESS;
    }
}
