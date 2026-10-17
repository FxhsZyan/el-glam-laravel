<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Canduman', 'canduman'], ['Basak', 'basak'], ['Cebu City', 'cebu-city']] as [$name, $slug]) {
            Branch::firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        User::updateOrCreate(['email' => 'admin@elglam.test'], [
            'name' => 'El Glamorous Admin',
            'password' => 'demo-admin-123',
            'role' => 'admin',
            'branch_id' => null,
        ]);

        User::updateOrCreate(['email' => 'staff.canduman@elglam.test'], [
            'name' => 'Ava Reception',
            'password' => 'demo-staff-123',
            'role' => 'staff',
            'branch_id' => Branch::where('slug', 'canduman')->value('id'),
        ]);
    }
}