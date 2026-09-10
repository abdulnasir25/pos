<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Support\CurrentSettings;

class UpdateSettings
{
    public function __construct(private readonly CurrentSettings $currentSettings) {}

    public function handle(array $attributes): Setting
    {
        $setting = $this->currentSettings->get();
        $setting->update($attributes);

        return $setting;
    }
}
