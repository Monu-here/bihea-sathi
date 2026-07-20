<?php

namespace App\Console\Commands;

use App\Models\PostCommentModel;
use App\Models\PostLikeModel;
use App\Models\PostModel;
use App\Models\ProfileModel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateDummySocialData extends Command
{
    /**
     * php artisan dummy:social 30
     * php artisan dummy:social 30 --posts=5 --connections=8
     */
    protected $signature = 'dummy:social
        {users=20 : Number of dummy users/profiles to create}
        {--posts=3 : Avg number of posts per user}
        {--connections=6 : Avg number of connection requests per user}
        {--unverified : Create profiles as NOT verified/complete}';

    protected $description = 'Generate dummy Users, Profiles, Connections, Posts, Likes and Comments';

    protected array $genders = [0, 1, 2];

    protected array $educations = ['High School', 'Bachelors', 'Masters', 'PhD', 'Diploma'];

    protected array $professions = ['Engineer', 'Doctor', 'Teacher', 'Business Owner', 'Designer', 'Lawyer', 'Accountant'];

    protected array $incomes = ['0-5 LPA', '5-10 LPA', '10-20 LPA', '20-50 LPA', '50+ LPA'];

    protected array $familyTypes = ['Nuclear', 'Joint'];

    protected array $familyValues = ['Traditional', 'Moderate', 'Liberal'];

    protected array $diets = ['Vegetarian', 'Non-Vegetarian', 'Vegan', 'Eggetarian'];

    protected array $habits = ['Never', 'Occasionally', 'Regularly'];

    protected array $religions = ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain'];

    protected array $locations = ['Kathmandu', 'Pokhara', 'Lalitpur', 'Bhaktapur', 'Biratnagar', 'Chitwan'];

    protected array $heights = ["4'10\"", "5'0\"", "5'2\"", "5'4\"", "5'6\"", "5'8\"", "5'10\"", "6'0\""];

    protected array $captions = [
        'Beautiful day out!', 'Family time is the best time.', 'Grateful for this moment.',
        'Weekend vibes.', 'New beginnings ahead.', 'Blessed and thankful.',
        'Just living life one day at a time.', null, null,
    ];

    protected array $commentsPool = [
        'Looks great!', 'Congratulations!', 'So happy for you.', 'Beautiful!',
        'Amazing photo.', 'Love this!', 'Wishing you all the best.', 'Nice one!',
    ];

    public function handle(): int
    {
        $userCount = (int) $this->argument('users');
        $avgPosts = (int) $this->option('posts');
        $avgConnections = (int) $this->option('connections');
        $verified = ! $this->option('unverified');

        $this->info("Creating {$userCount} users + profiles...");
        $userIds = $this->createUsersAndProfiles($userCount, $verified);

        $this->info('Creating connections...');
        $acceptedPairs = $this->createConnections($userIds, $avgConnections);

        $this->info('Creating posts, likes and comments...');
        $this->createPosts($userIds, $acceptedPairs, $avgPosts);

        $this->info('Done!');
        $this->table(
            ['Users', 'Connections (accepted pairs)', 'Posts'],
            [[$userCount, count($acceptedPairs), PostModel::count()]]
        );

        return Command::SUCCESS;
    }

    protected function createUsersAndProfiles(int $count, bool $verified): array
    {
        $userIds = [];
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $userId = DB::transaction(function () use ($verified) {
                $fullName = fake()->name();

                $user = User::create([
                    'name' => $fullName,
                    'email' => Str::uuid().'@example.com',
                    'password' => Hash::make('password'),
                    'email_verified_at' => $verified ? now() : null,
                ]);

                ProfileModel::create([
                    'user_id' => $user->id,
                    'profile_for' => fake()->randomElement(['Self', 'Son', 'Daughter', 'Sibling']),
                    'full_name' => $fullName,
                    'gender' => fake()->randomElement($this->genders),
                    'date_of_birth' => fake()->dateTimeBetween('-45 years', '-20 years')->format('Y-m-d'),
                    'education' => fake()->randomElement($this->educations),
                    'profession' => fake()->randomElement($this->professions),
                    'annual_income' => fake()->randomElement($this->incomes),
                    'company_name' => fake()->company(),
                    'father_occupation' => fake()->jobTitle(),
                    'mother_occupation' => fake()->jobTitle(),
                    'siblings' => fake()->numberBetween(0, 4),
                    'family_type' => fake()->randomElement($this->familyTypes),
                    'family_values' => fake()->randomElement($this->familyValues),
                    'diet' => fake()->randomElement($this->diets),
                    'drinking' => fake()->randomElement($this->habits),
                    'smoking' => fake()->randomElement($this->habits),
                    'partner_age_min' => fake()->numberBetween(18, 30),
                    'partner_age_max' => fake()->numberBetween(31, 60),
                    'partner_height_min' => fake()->randomElement($this->heights),
                    'partner_height_max' => fake()->randomElement($this->heights),
                    'partner_religion' => json_encode(fake()->randomElements($this->religions, rand(1, 2))),
                    'partner_locations' => json_encode(fake()->randomElements($this->locations, rand(1, 3))),
                    'main_photo' => 'profiles/dummy_'.Str::random(10).'.jpg',
                    'additional_photos' => json_encode([
                        'profiles/dummy_'.Str::random(10).'.jpg',
                        'profiles/dummy_'.Str::random(10).'.jpg',
                    ]),
                    'current_step' => 5,
                    'is_complete' => $verified,
                    'is_verified' => $verified,
                    'verification_pin' => $verified ? null : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                ]);

                return $user->id;
            });

            $userIds[] = $userId;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $userIds;
    }

    /**
     * Creates random connection requests between users (mostly accepted, some
     * pending/rejected) and returns the list of accepted [from, to] pairs.
     */
    protected function createConnections(array $userIds, int $avgConnections): array
    {
        $pairsSeen = [];
        $acceptedPairs = [];
        $targetCount = (int) (count($userIds) * $avgConnections / 2);

        $bar = $this->output->createProgressBar($targetCount);
        $bar->start();

        $attempts = 0;
        $created = 0;
        while ($created < $targetCount && $attempts < $targetCount * 20) {
            $attempts++;

            $from = fake()->randomElement($userIds);
            $to = fake()->randomElement($userIds);

            if ($from === $to) {
                continue;
            }

            $key = $from < $to ? "{$from}-{$to}" : "{$to}-{$from}";
            if (isset($pairsSeen[$key])) {
                continue;
            }
            $pairsSeen[$key] = true;

            $status = fake()->randomElement(['accepted', 'accepted', 'accepted', 'pending', 'rejected']);

            DB::table('connection_models')->insert([
                'from_user_id' => $from,
                'to_user_id' => $to,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($status === 'accepted') {
                $acceptedPairs[] = [$from, $to];
            }

            $created++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $acceptedPairs;
    }

    protected function createPosts(array $userIds, array $acceptedPairs, int $avgPosts): void
    {
        // Build a map of userId => [connected user ids] for likes/comments
        $connectionsMap = [];
        foreach ($acceptedPairs as [$from, $to]) {
            $connectionsMap[$from][] = $to;
            $connectionsMap[$to][] = $from;
        }

        $postCount = count($userIds) * $avgPosts;
        $bar = $this->output->createProgressBar($postCount);
        $bar->start();

        foreach ($userIds as $userId) {
            $numPosts = random_int(0, $avgPosts * 2);

            for ($p = 0; $p < $numPosts; $p++) {
                $mediaType = fake()->randomElement(['image', 'image', 'image', 'video']);
                $ext = $mediaType === 'video' ? 'mp4' : 'jpg';

                $post = PostModel::create([
                    'user_id' => $userId,
                    'caption' => fake()->randomElement($this->captions),
                    'media_path' => '/uploads/posts/dummy_'.Str::random(10).'.'.$ext,
                    'media_type' => $mediaType,
                ]);

                // Only connected users (+ the author) can like/comment, matching
                // canInteractWithPost() logic in PostController.
                $eligibleUsers = array_unique(array_merge([$userId], $connectionsMap[$userId] ?? []));

                if (count($eligibleUsers) > 1) {
                    $likers = fake()->randomElements(
                        $eligibleUsers,
                        min(count($eligibleUsers), random_int(0, 5))
                    );
                    foreach (array_unique($likers) as $likerId) {
                        PostLikeModel::create([
                            'post_id' => $post->id,
                            'user_id' => $likerId,
                        ]);
                    }

                    $numComments = random_int(0, 3);
                    for ($c = 0; $c < $numComments; $c++) {
                        PostCommentModel::create([
                            'post_id' => $post->id,
                            'user_id' => fake()->randomElement($eligibleUsers),
                            'content' => fake()->randomElement($this->commentsPool),
                        ]);
                    }
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
    }
}