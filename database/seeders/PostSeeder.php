<?php

namespace Database\Seeders;

use App\Models\Content\ContentType;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default content type for posts if it doesn't exist
        $postType = ContentType::firstOrCreate(
            ['slug' => 'post'],
            [
                'name' => 'Post',
                'description' => 'User generated posts',
                'icon' => 'post',
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
            ]
        );

        // Get all users who should have posts (excluding admins)
        $users = User::whereDoesntHave('roles', function($q) {
            $q->whereIn('slug', ['admin', 'superadmin']);
        })->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found to create posts for. Please run UserSeeder first.');
            return;
        }

        $faker = \Faker\Factory::create();
        $postCount = 0;

        foreach ($users as $user) {
            // Each user will have between 5-15 posts
            $userPostCount = $faker->numberBetween(5, 15);
            
            for ($i = 0; $i < $userPostCount; $i++) {
                $this->createPost($user, $postType, $faker);
                $postCount++;
            }
            
            $this->command->info("✓ Created {$userPostCount} posts for user: {$user->name}");
        }

        $this->command->info("✓ Created a total of {$postCount} posts");
    }

    /**
     * Create a single post
     */
    private function createPost($user, $contentType, $faker): void
    {
        $content = $faker->paragraphs($faker->numberBetween(1, 5), true);
        $images = $this->generateRandomImages($faker);
        
        DB::table('posts')->insert([
            'content_type_id' => $contentType->id,
            'user_id' => $user->id,
            'parent_id' => $faker->optional(0.2, null)->randomElement(DB::table('posts')->pluck('id')->toArray()), // 20% chance of being a reply
            'content' => $content,
            'images' => !empty($images) ? json_encode($images) : null,
            'likes_count' => $faker->numberBetween(0, 1000),
            'comments_count' => $faker->numberBetween(0, 200),
            'shares_count' => $faker->numberBetween(0, 100),
            'status' => $faker->randomElement(['published', 'archived']),
            'created_at' => now()->subDays($faker->numberBetween(0, 60)),
            'updated_at' => now(),
        ]);
    }

    /**
     * Generate random image URLs (optional)
     */
    private function generateRandomImages($faker): array
    {
        $images = [];
        $imageCount = $faker->optional(0.7, 0)->numberBetween(1, 4); // 30% chance of no images
        
        for ($i = 0; $i < $imageCount; $i++) {
            $images[] = [
                'url' => $faker->imageUrl(800, 600, 'people', true, $faker->word),
                'alt' => $faker->sentence(3),
                'width' => 800,
                'height' => 600,
            ];
        }
        
        return $images;
    }
}
