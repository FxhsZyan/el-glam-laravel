<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // "Basak" came from the old demo guide; the real branch is Pajac
        Branch::where('slug', 'basak')->update(['name' => 'Pajac', 'slug' => 'pajac']);

        $branches = [
            ['Canduman', 'canduman', '2/F Paradise Bldg., H. Abellana St., Canduman, Mandaue City'],
            ['Pajac', 'pajac', 'Helenville Apartment, Bankal Road, Barangay Pajac, Lapu-Lapu City'],
            ['Cebu City', 'cebu-city', '8V6R+8VM, Don Gil Street, Cebu City'],
            ['Pusok', 'pusok', null], // address still to be confirmed
        ];

        foreach ($branches as [$name, $slug, $address]) {
            Branch::updateOrCreate(['slug' => $slug], ['name' => $name, 'address' => $address]);
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