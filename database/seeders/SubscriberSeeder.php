<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Subscriber;
use Illuminate\Database\Seeder;

class SubscriberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Subscriber::factory()->createMany([
            [
                'name' => 'Email Permanent Failure',
                'email' => 'permanent-failure@example.test',
                'phone' => null,
            ],
            [
                'name' => 'Email Temporary Failure',
                'email' => 'temporary-failure@example.test',
                'phone' => null,
            ],
            [
                'name' => 'SMS Permanent Failure',
                'email' => null,
                'phone' => '+10000000001',
            ],
            [
                'name' => 'SMS Temporary Failure',
                'email' => null,
                'phone' => '+10000000002',
            ],
        ]);

        Subscriber::factory()->count(10)->create();
    }
}
