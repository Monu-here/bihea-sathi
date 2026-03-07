<?php

namespace App\Console\Commands;

use App\Models\ProfileModel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateDummyProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:dummy-profiles {count=1 : Number of dummy profiles to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create dummy users and profiles (password for all users: "password")';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        if ($count < 1) {
            $this->error('Count must be at least 1.');
            return 1;
        }

        for ($i = 1; $i <= $count; $i++) {
            $email = sprintf('dummy%d_%s@example.com', $i, Str::lower(Str::substr(Str::random(8), 0, 6)));
            $name = "Dummy User {$i}";

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => 'password',
            ]);

            $age = rand(22, 38);
            $gender = rand(0, 2);
            $siblings = rand(0, 5);
            $partner_age_min = rand(22, 28);
            $partner_age_max = rand(29, 40);
            $partner_height_min = sprintf('%.1f', rand(155, 170) / 30); // approximate meters
            $partner_height_max = sprintf('%.1f', rand(171, 200) / 30);
                // choose a random single-string religion and location (stored as plain strings)
                $religions = ['Hindu', 'Buddhist', 'Christian', 'Muslim', 'Sikh', 'Other'];
                $locations = [
                    'Kathmandu, Bagamati Province, Nepal',
                    'Pokhara, Gandaki Province, Nepal',
                    'Biratnagar, Koshi Province, Nepal',
                    'Birgunj, Province No. 2, Nepal',
                    'Dhangadhi, Sudurpashchim Province, Nepal',
                ];

                $partner_religion = $religions[array_rand($religions)];
                $partner_locations = $locations[array_rand($locations)];
            $additional_photos = [];

            ProfileModel::create([
                'user_id' => $user->id,
                'profile_for' => 'Self',
                'full_name' => $name,
                'gender' => $gender,
                'date_of_birth' => now()->subYears($age)->format('Y-m-d'),
                'education' => 'Bachelor',
                'profession' => 'Engineer',
                'annual_income' => (string) rand(200000, 2000000),
                'company_name' => 'Acme Co',
                'father_occupation' => 'Retired',
                'mother_occupation' => 'Homemaker',
                'siblings' => $siblings,
                'family_type' => 'Nuclear',
                'family_values' => 'Traditional',
                'diet' => 'Vegetarian',
                'drinking' => 'No',
                'smoking' => 'No',
                'partner_age_min' => $partner_age_min,
                'partner_age_max' => $partner_age_max,
                'partner_height_min' => $partner_height_min,
                'partner_height_max' => $partner_height_max,
                'partner_religion' => is_array($partner_religion) ? implode(', ', $partner_religion) : (string) $partner_religion,
                'partner_locations' => is_array($partner_locations) ? implode(', ', $partner_locations) : (string) $partner_locations,
                'main_photo' => null,
                'additional_photos' => json_encode($additional_photos),
                'current_step' => 1,
                'is_complete' => true,
                'is_verified' => true,
                'verification_pin' => null,
            ]);

            $this->info("Created user: {$email} with password 'password'");
        }

        $this->info('Done.');
        return 0;
    }
}
