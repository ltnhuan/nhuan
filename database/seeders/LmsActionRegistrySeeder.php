<?php

namespace Database\Seeders;

use App\Services\LmsActionRegistryService;
use Illuminate\Database\Seeder;

class LmsActionRegistrySeeder extends Seeder
{
    public function run(): void
    {
        app(LmsActionRegistryService::class)->syncActions();
        app(LmsActionRegistryService::class)->syncPermissions();
    }
}
