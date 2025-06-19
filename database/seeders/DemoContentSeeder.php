<?php

namespace Database\Seeders;

use App\Models\Content\Category;
use App\Models\Content\Post;
use App\Models\Content\ContentType;
use App\Models\Content\Tag;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create content types if they don't exist
        $contentTypes = [
            [
                'name' => 'Article',
                'slug' => 'article',
                'description' => 'Regular articles and blog posts',
                'icon' => 'newspaper',
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
            ],
            [
                'name' => 'News',
                'slug' => 'news',
                'description' => 'News and announcements',
                'icon' => 'megaphone',
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
            ],
            [
                'name' => 'Page',
                'slug' => 'page',
                'description' => 'Static pages',
                'icon' => 'document',
                'is_hierarchical' => true,
                'supports_comments' => false,
                'supports_media' => true,
                'supports_tags' => false,
            ],
        ];

        foreach ($contentTypes as $typeData) {
            ContentType::firstOrCreate(
                ['slug' => $typeData['slug']],
                $typeData
            );
        }

        $articleType = ContentType::where('slug', 'article')->first();
        $newsType = ContentType::where('slug', 'news')->first();

        // Create some categories
        $categories = [
            ['name' => 'Technology', 'slug' => 'technology', 'description' => 'Tech news and tutorials'],
            ['name' => 'Science', 'slug' => 'science', 'description' => 'Scientific discoveries and research'],
            ['name' => 'Business', 'slug' => 'business', 'description' => 'Business and finance'],
            ['name' => 'Health', 'slug' => 'health', 'description' => 'Health and wellness'],
            ['name' => 'Entertainment', 'slug' => 'entertainment', 'description' => 'Movies, music, and more'],
        ];

        $categoryIds = [];
        foreach ($categories as $category) {
            $cat = Category::firstOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['content_type_id' => $articleType->id])
            );
            $categoryIds[] = $cat->id;
        }

        // Create some tags
        $tags = [
            'laravel', 'php', 'javascript', 'vue', 'react', 'programming', 'webdev', 'design',
            'startup', 'productivity', 'career', 'learning', 'tutorial', 'tips', 'trends', 'innovation'
        ];

        $tagIds = [];
        foreach ($tags as $tagName) {
            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName, 'description' => ucfirst($tagName) . ' related content']
            );
            $tagIds[] = $tag->id;
        }

        // Get all users who should have content (excluding admins)
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found to assign content to. Please run UserSeeder first.');
            return;
        }

        // Create content for each user
        foreach ($users as $user) {
            $this->createUserContent($user, $articleType, $newsType, $categoryIds, $tagIds);
        }

        $this->command->info('✓ Created demo content for ' . $users->count() . ' users');
    }

    /**
     * Create content for a single user
     */
    private function createUserContent($user, $articleType, $newsType, $categoryIds, $tagIds): void
    {
        $faker = \Faker\Factory::create();
        $contentCount = 0;

        // Create 7 articles
        for ($i = 1; $i <= 7; $i++) {
            $this->createContent($user, $articleType, $faker, $categoryIds, $tagIds, 'article');
            $contentCount++;
        }

        // Create 3 news items
        for ($i = 1; $i <= 3; $i++) {
            $this->createContent($user, $newsType, $faker, $categoryIds, $tagIds, 'news');
            $contentCount++;
        }

        $this->command->info("✓ Created $contentCount content items for user: {$user->name}");
    }

    /**
     * Create a single post
     */
    private function createContent($user, $contentType, $faker, $categoryIds, $tagIds, $type): void
    {
        $content = '<p>' . implode('</p><p>', $faker->paragraphs(rand(1, 3))) . '</p>';
        $images = $this->generateRandomImages($faker);
        
        $post = Post::create([
            'content_type_id' => $contentType->id,
            'user_id' => $user->id,
            'parent_id' => $faker->optional(0.2, null)->randomElement(DB::table('posts')->pluck('id')->toArray()),
            'content' => $content,
            'images' => !empty($images) ? json_encode($images) : null,
            'likes_count' => $faker->numberBetween(0, 1000),
            'comments_count' => $faker->numberBetween(0, 200),
            'shares_count' => $faker->numberBetween(0, 100),
            'status' => $faker->randomElement(['published', 'archived']),
        ]);
        
        // Attach categories if this is an article
        if ($type === 'article' && !empty($categoryIds)) {
            $post->categories()->attach(
                $faker->randomElements($categoryIds, $faker->numberBetween(1, 3))
            );
        }
        
        // Attach tags if this content type supports them
        if ($contentType->supports_tags && !empty($tagIds)) {
            $post->tags()->attach(
                $faker->randomElements($tagIds, $faker->numberBetween(2, 5))
            );
        }
    }

    /**
     * Generate random image data
     */
    private function generateRandomImages($faker): ?array
    {
        if ($faker->boolean(30)) { // 30% chance of no images
            return null;
        }
        
        $imageCount = $faker->numberBetween(1, 4);
        $images = [];
        
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
