<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Content\Category;
use App\Models\Content\ContentType;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create main categories first
        $this->createMainCategories();
        
        // Create subcategories
        $this->createSubcategories();
        
        // Update nested set values
        $this->updateNestedSetValues();
    }

    /**
     * Create main categories.
     */
    private function createMainCategories(): void
    {
        $mainCategories = [
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Latest trends, news, and insights in technology and innovation',
                'color' => '#3b82f6',
                'icon' => 'cpu-chip',
                'is_active' => true,
                'sort_order' => 1,
                'seo_title' => 'Technology News & Trends',
                'seo_description' => 'Stay updated with the latest technology news, trends, and innovations.',
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Business news, entrepreneurship, and professional development',
                'color' => '#059669',
                'icon' => 'briefcase',
                'is_active' => true,
                'sort_order' => 2,
                'seo_title' => 'Business News & Insights',
                'seo_description' => 'Get the latest business news, entrepreneurship tips, and professional insights.',
            ],
            [
                'name' => 'Lifestyle',
                'slug' => 'lifestyle',
                'description' => 'Health, wellness, travel, and personal development content',
                'color' => '#dc2626',
                'icon' => 'heart',
                'is_active' => true,
                'sort_order' => 3,
                'seo_title' => 'Lifestyle & Wellness',
                'seo_description' => 'Discover lifestyle tips, wellness advice, and personal development content.',
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'description' => 'Learning resources, tutorials, and educational content',
                'color' => '#7c3aed',
                'icon' => 'academic-cap',
                'is_active' => true,
                'sort_order' => 4,
                'seo_title' => 'Education & Learning',
                'seo_description' => 'Access educational resources, tutorials, and learning materials.',
            ],
            [
                'name' => 'Entertainment',
                'slug' => 'entertainment',
                'description' => 'Movies, music, games, and entertainment industry news',
                'color' => '#ea580c',
                'icon' => 'film',
                'is_active' => true,
                'sort_order' => 5,
                'seo_title' => 'Entertainment News',
                'seo_description' => 'Latest entertainment news, movie reviews, music, and gaming content.',
            ],
            [
                'name' => 'Science',
                'slug' => 'science',
                'description' => 'Scientific discoveries, research, and academic content',
                'color' => '#0891b2',
                'icon' => 'beaker',
                'is_active' => true,
                'sort_order' => 6,
                'seo_title' => 'Science & Research',
                'seo_description' => 'Explore scientific discoveries, research findings, and academic content.',
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'description' => 'Sports news, analysis, and athletic performance content',
                'color' => '#16a34a',
                'icon' => 'trophy',
                'is_active' => true,
                'sort_order' => 7,
                'seo_title' => 'Sports News & Analysis',
                'seo_description' => 'Get the latest sports news, game analysis, and athletic performance insights.',
            ],
            [
                'name' => 'Politics',
                'slug' => 'politics',
                'description' => 'Political news, analysis, and current affairs',
                'color' => '#4338ca',
                'icon' => 'scale',
                'is_active' => true,
                'sort_order' => 8,
                'seo_title' => 'Politics & Current Affairs',
                'seo_description' => 'Stay informed with political news, analysis, and current affairs coverage.',
            ],
        ];

        foreach ($mainCategories as $index => $categoryData) {
            $categoryData['depth'] = 0;
            $categoryData['lft'] = ($index * 2) + 1;
            $categoryData['rgt'] = ($index * 2) + 2;
            
            Category::create($categoryData);
        }

        $this->command->info('✓ Created ' . count($mainCategories) . ' main categories');
    }

    /**
     * Create subcategories.
     */
    private function createSubcategories(): void
    {
        $subcategoriesData = [
            'Technology' => [
                [
                    'name' => 'Software Development',
                    'slug' => 'software-development',
                    'description' => 'Programming, frameworks, and software engineering',
                    'color' => '#1e40af',
                    'icon' => 'code',
                ],
                [
                    'name' => 'Artificial Intelligence',
                    'slug' => 'artificial-intelligence',
                    'description' => 'AI, machine learning, and automation',
                    'color' => '#7c2d12',
                    'icon' => 'cpu-chip',
                ],
                [
                    'name' => 'Mobile Technology',
                    'slug' => 'mobile-technology',
                    'description' => 'Smartphones, apps, and mobile development',
                    'color' => '#0c4a6e',
                    'icon' => 'device-phone-mobile',
                ],
                [
                    'name' => 'Cybersecurity',
                    'slug' => 'cybersecurity',
                    'description' => 'Security, privacy, and data protection',
                    'color' => '#991b1b',
                    'icon' => 'shield-check',
                ],
            ],
            'Business' => [
                [
                    'name' => 'Entrepreneurship',
                    'slug' => 'entrepreneurship',
                    'description' => 'Starting and growing businesses',
                    'color' => '#065f46',
                    'icon' => 'light-bulb',
                ],
                [
                    'name' => 'Marketing',
                    'slug' => 'marketing',
                    'description' => 'Digital marketing and advertising strategies',
                    'color' => '#0f766e',
                    'icon' => 'megaphone',
                ],
                [
                    'name' => 'Finance',
                    'slug' => 'finance',
                    'description' => 'Personal finance, investing, and economics',
                    'color' => '#134e4a',
                    'icon' => 'currency-dollar',
                ],
            ],
            'Lifestyle' => [
                [
                    'name' => 'Health & Fitness',
                    'slug' => 'health-fitness',
                    'description' => 'Physical health, exercise, and nutrition',
                    'color' => '#b91c1c',
                    'icon' => 'heart',
                ],
                [
                    'name' => 'Travel',
                    'slug' => 'travel',
                    'description' => 'Travel guides, tips, and experiences',
                    'color' => '#c2410c',
                    'icon' => 'map',
                ],
                [
                    'name' => 'Food & Cooking',
                    'slug' => 'food-cooking',
                    'description' => 'Recipes, cooking tips, and food culture',
                    'color' => '#dc2626',
                    'icon' => 'cake',
                ],
            ],
            'Education' => [
                [
                    'name' => 'Online Learning',
                    'slug' => 'online-learning',
                    'description' => 'E-learning platforms and digital education',
                    'color' => '#6d28d9',
                    'icon' => 'computer-desktop',
                ],
                [
                    'name' => 'Career Development',
                    'slug' => 'career-development',
                    'description' => 'Professional skills and career advancement',
                    'color' => '#7c3aed',
                    'icon' => 'chart-bar',
                ],
                [
                    'name' => 'Programming Tutorials',
                    'slug' => 'programming-tutorials',
                    'description' => 'Coding tutorials and programming guides',
                    'color' => '#5b21b6',
                    'icon' => 'code',
                ],
            ],
            'Entertainment' => [
                [
                    'name' => 'Movies & TV',
                    'slug' => 'movies-tv',
                    'description' => 'Film and television reviews and news',
                    'color' => '#ea580c',
                    'icon' => 'film',
                ],
                [
                    'name' => 'Gaming',
                    'slug' => 'gaming',
                    'description' => 'Video games, reviews, and gaming culture',
                    'color' => '#dc2626',
                    'icon' => 'puzzle-piece',
                ],
                [
                    'name' => 'Music',
                    'slug' => 'music',
                    'description' => 'Music reviews, artist news, and industry updates',
                    'color' => '#c2410c',
                    'icon' => 'musical-note',
                ],
            ],
        ];

        foreach ($subcategoriesData as $parentName => $subcategories) {
            $parentCategory = Category::where('name', $parentName)->first();
            
            if ($parentCategory) {
                foreach ($subcategories as $index => $subcategoryData) {
                    $subcategoryData['parent_id'] = $parentCategory->id;
                    $subcategoryData['depth'] = 1;
                    $subcategoryData['is_active'] = true;
                    $subcategoryData['sort_order'] = $index + 1;
                    
                    Category::create($subcategoryData);
                }
            }
        }

        $this->command->info('✓ Created subcategories for main categories');
    }

    /**
     * Update nested set values for proper hierarchy.
     */
    private function updateNestedSetValues(): void
    {
        $categories = Category::orderBy('sort_order')->get();
        $currentLeft = 1;

        foreach ($categories->where('depth', 0) as $parent) {
            $parent->lft = $currentLeft++;
            
            $children = $categories->where('parent_id', $parent->id);
            foreach ($children as $child) {
                $child->lft = $currentLeft++;
                $child->rgt = $currentLeft++;
                $child->save();
            }
            
            $parent->rgt = $currentLeft++;
            $parent->save();
        }

        $this->command->info('✓ Updated nested set values for categories');
    }
}