<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Content\Tag;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            // Technology Tags
            ['name' => 'JavaScript', 'description' => 'JavaScript programming language', 'color' => '#f7df1e'],
            ['name' => 'Python', 'description' => 'Python programming language', 'color' => '#3776ab'],
            ['name' => 'React', 'description' => 'React JavaScript library', 'color' => '#61dafb'],
            ['name' => 'Laravel', 'description' => 'Laravel PHP framework', 'color' => '#ff2d20'],
            ['name' => 'Vue.js', 'description' => 'Vue.js JavaScript framework', 'color' => '#4fc08d'],
            ['name' => 'Node.js', 'description' => 'Node.js JavaScript runtime', 'color' => '#339933'],
            ['name' => 'Docker', 'description' => 'Docker containerization platform', 'color' => '#2496ed'],
            ['name' => 'AWS', 'description' => 'Amazon Web Services', 'color' => '#ff9900'],
            ['name' => 'Machine Learning', 'description' => 'Machine learning and AI', 'color' => '#ff6f00'],
            ['name' => 'Data Science', 'description' => 'Data science and analytics', 'color' => '#4caf50'],
            ['name' => 'Blockchain', 'description' => 'Blockchain technology', 'color' => '#f7931a'],
            ['name' => 'API', 'description' => 'Application Programming Interface', 'color' => '#2196f3'],
            ['name' => 'Database', 'description' => 'Database systems and management', 'color' => '#336791'],
            ['name' => 'DevOps', 'description' => 'Development and Operations', 'color' => '#326ce5'],
            ['name' => 'Open Source', 'description' => 'Open source software', 'color' => '#28a745'],

            // Business Tags
            ['name' => 'Startup', 'description' => 'Startup companies and entrepreneurship', 'color' => '#e91e63'],
            ['name' => 'Investment', 'description' => 'Investment and funding', 'color' => '#4caf50'],
            ['name' => 'Leadership', 'description' => 'Leadership and management', 'color' => '#ff9800'],
            ['name' => 'Innovation', 'description' => 'Innovation and creativity', 'color' => '#9c27b0'],
            ['name' => 'Digital Marketing', 'description' => 'Digital marketing strategies', 'color' => '#3f51b5'],
            ['name' => 'SEO', 'description' => 'Search Engine Optimization', 'color' => '#009688'],
            ['name' => 'E-commerce', 'description' => 'Electronic commerce', 'color' => '#ff5722'],
            ['name' => 'Remote Work', 'description' => 'Remote work and distributed teams', 'color' => '#607d8b'],
            ['name' => 'Productivity', 'description' => 'Productivity tips and tools', 'color' => '#795548'],

            // Lifestyle Tags
            ['name' => 'Wellness', 'description' => 'Health and wellness', 'color' => '#4caf50'],
            ['name' => 'Mindfulness', 'description' => 'Mindfulness and meditation', 'color' => '#9c27b0'],
            ['name' => 'Fitness', 'description' => 'Physical fitness and exercise', 'color' => '#f44336'],
            ['name' => 'Nutrition', 'description' => 'Nutrition and healthy eating', 'color' => '#4caf50'],
            ['name' => 'Travel Tips', 'description' => 'Travel advice and tips', 'color' => '#2196f3'],
            ['name' => 'Adventure', 'description' => 'Adventure and outdoor activities', 'color' => '#ff9800'],
            ['name' => 'Photography', 'description' => 'Photography tips and techniques', 'color' => '#9e9e9e'],
            ['name' => 'Cooking', 'description' => 'Cooking and recipes', 'color' => '#ff5722'],
            ['name' => 'Sustainability', 'description' => 'Environmental sustainability', 'color' => '#4caf50'],

            // Education Tags
['name' => 'Tutorial', 'description' => 'Step-by-step tutorials', 'color' => '#2196f3'],
           ['name' => 'Tips', 'description' => 'Helpful tips and tricks', 'color' => '#ff9800'],
           ['name' => 'Guide', 'description' => 'Comprehensive guides', 'color' => '#9c27b0'],
           ['name' => 'How-to', 'description' => 'How-to instructions', 'color' => '#3f51b5'],
           ['name' => 'Best Practices', 'description' => 'Industry best practices', 'color' => '#4caf50'],
           ['name' => 'Case Study', 'description' => 'Real-world case studies', 'color' => '#ff5722'],
           ['name' => 'Research', 'description' => 'Research and analysis', 'color' => '#607d8b'],
           ['name' => 'Skills', 'description' => 'Skill development', 'color' => '#795548'],
           ['name' => 'Certification', 'description' => 'Professional certifications', 'color' => '#673ab7'],
           ['name' => 'Online Course', 'description' => 'Online learning courses', 'color' => '#e91e63'],

           // Entertainment Tags
           ['name' => 'Review', 'description' => 'Reviews and opinions', 'color' => '#ffeb3b'],
           ['name' => 'Gaming', 'description' => 'Video games and gaming', 'color' => '#9c27b0'],
           ['name' => 'Movies', 'description' => 'Movies and cinema', 'color' => '#f44336'],
           ['name' => 'TV Shows', 'description' => 'Television shows', 'color' => '#2196f3'],
           ['name' => 'Music', 'description' => 'Music and artists', 'color' => '#e91e63'],
           ['name' => 'Books', 'description' => 'Books and literature', 'color' => '#795548'],
           ['name' => 'Streaming', 'description' => 'Streaming platforms and content', 'color' => '#ff5722'],
           ['name' => 'Pop Culture', 'description' => 'Popular culture trends', 'color' => '#ff9800'],

           // Science Tags
           ['name' => 'Research', 'description' => 'Scientific research', 'color' => '#3f51b5'],
           ['name' => 'Climate', 'description' => 'Climate and environment', 'color' => '#4caf50'],
           ['name' => 'Space', 'description' => 'Space exploration and astronomy', 'color' => '#673ab7'],
           ['name' => 'Medicine', 'description' => 'Medical research and health', 'color' => '#f44336'],
           ['name' => 'Physics', 'description' => 'Physics and physical sciences', 'color' => '#2196f3'],
           ['name' => 'Biology', 'description' => 'Biology and life sciences', 'color' => '#4caf50'],
           ['name' => 'Chemistry', 'description' => 'Chemistry and chemical sciences', 'color' => '#ff9800'],
           ['name' => 'Innovation', 'description' => 'Scientific innovation', 'color' => '#9c27b0'],

           // General Tags
           ['name' => 'News', 'description' => 'Latest news and updates', 'color' => '#f44336'],
           ['name' => 'Breaking', 'description' => 'Breaking news', 'color' => '#ff5722'],
           ['name' => 'Analysis', 'description' => 'In-depth analysis', 'color' => '#607d8b'],
           ['name' => 'Opinion', 'description' => 'Opinion pieces', 'color' => '#795548'],
           ['name' => 'Interview', 'description' => 'Interviews and conversations', 'color' => '#e91e63'],
           ['name' => 'Event', 'description' => 'Events and happenings', 'color' => '#ff9800'],
           ['name' => 'Launch', 'description' => 'Product or service launches', 'color' => '#4caf50'],
           ['name' => 'Update', 'description' => 'Updates and changes', 'color' => '#2196f3'],
           ['name' => 'Announcement', 'description' => 'Official announcements', 'color' => '#9c27b0'],
           ['name' => 'Trend', 'description' => 'Current trends', 'color' => '#ff5722'],

           // Content Format Tags
           ['name' => 'Video', 'description' => 'Video content', 'color' => '#f44336'],
           ['name' => 'Podcast', 'description' => 'Podcast episodes', 'color' => '#9c27b0'],
           ['name' => 'Infographic', 'description' => 'Infographic content', 'color' => '#ff9800'],
           ['name' => 'Webinar', 'description' => 'Webinar recordings', 'color' => '#2196f3'],
           ['name' => 'Live', 'description' => 'Live content or events', 'color' => '#f44336'],
           ['name' => 'Interactive', 'description' => 'Interactive content', 'color' => '#4caf50'],

           // Difficulty/Level Tags
           ['name' => 'Beginner', 'description' => 'Beginner-level content', 'color' => '#4caf50'],
           ['name' => 'Intermediate', 'description' => 'Intermediate-level content', 'color' => '#ff9800'],
           ['name' => 'Advanced', 'description' => 'Advanced-level content', 'color' => '#f44336'],
           ['name' => 'Expert', 'description' => 'Expert-level content', 'color' => '#9c27b0'],

           // Time-based Tags
           ['name' => 'Quick Read', 'description' => 'Quick read articles', 'color' => '#4caf50'],
           ['name' => 'In-depth', 'description' => 'In-depth coverage', 'color' => '#3f51b5'],
           ['name' => 'Daily', 'description' => 'Daily content', 'color' => '#ff9800'],
           ['name' => 'Weekly', 'description' => 'Weekly content', 'color' => '#2196f3'],
           ['name' => 'Monthly', 'description' => 'Monthly content', 'color' => '#9c27b0'],

           // Popular/Trending Tags
           ['name' => 'Popular', 'description' => 'Popular content', 'color' => '#ff5722'],
           ['name' => 'Trending', 'description' => 'Trending topics', 'color' => '#e91e63'],
           ['name' => 'Viral', 'description' => 'Viral content', 'color' => '#ff9800'],
           ['name' => 'Featured', 'description' => 'Featured content', 'color' => '#ffeb3b'],
           ['name' => 'Editor\'s Pick', 'description' => 'Editor\'s choice content', 'color' => '#9c27b0'],

           // Industry-specific Tags
           ['name' => 'FinTech', 'description' => 'Financial Technology', 'color' => '#4caf50'],
           ['name' => 'EdTech', 'description' => 'Educational Technology', 'color' => '#2196f3'],
           ['name' => 'HealthTech', 'description' => 'Health Technology', 'color' => '#f44336'],
           ['name' => 'IoT', 'description' => 'Internet of Things', 'color' => '#ff9800'],
           ['name' => 'SaaS', 'description' => 'Software as a Service', 'color' => '#3f51b5'],
           ['name' => 'Mobile App', 'description' => 'Mobile applications', 'color' => '#607d8b'],
           ['name' => 'Web Development', 'description' => 'Web development topics', 'color' => '#795548'],
           ['name' => 'UX/UI', 'description' => 'User Experience and Interface Design', 'color' => '#e91e63'],
           ['name' => 'Automation', 'description' => 'Process automation', 'color' => '#673ab7'],
           ['name' => 'Cloud Computing', 'description' => 'Cloud computing services', 'color' => '#2196f3'],
       ];

       $createdTags = [];
       
       foreach ($tags as $tagData) {
           // Set default values
           $tagData['is_active'] = true;
           $tagData['usage_count'] = rand(5, 100); // Random usage count for demo
           
           $tag = Tag::create($tagData);
           $createdTags[] = $tag;
       }

       $this->command->info('✓ Created ' . count($createdTags) . ' tags with random usage counts');

       // Create some additional dynamic tags based on current trends
       $this->createTrendingTags();
   }

   /**
    * Create trending/seasonal tags.
    */
   private function createTrendingTags(): void
   {
       $currentYear = date('Y');
       $currentMonth = date('n');
       
       $seasonalTags = [];

       // Add year-specific tags
       $seasonalTags[] = [
           'name' => $currentYear,
           'description' => "Content from {$currentYear}",
           'color' => '#607d8b',
           'usage_count' => rand(20, 80),
       ];

       // Add seasonal tags based on current month
       if (in_array($currentMonth, [12, 1, 2])) {
           $seasonalTags[] = [
               'name' => 'Winter',
               'description' => 'Winter-related content',
               'color' => '#2196f3',
               'usage_count' => rand(10, 50),
           ];
       } elseif (in_array($currentMonth, [3, 4, 5])) {
           $seasonalTags[] = [
               'name' => 'Spring',
               'description' => 'Spring-related content',
               'color' => '#4caf50',
               'usage_count' => rand(10, 50),
           ];
       } elseif (in_array($currentMonth, [6, 7, 8])) {
           $seasonalTags[] = [
               'name' => 'Summer',
               'description' => 'Summer-related content',
               'color' => '#ff9800',
               'usage_count' => rand(10, 50),
           ];
       } elseif (in_array($currentMonth, [9, 10, 11])) {
           $seasonalTags[] = [
               'name' => 'Fall',
               'description' => 'Fall/Autumn-related content',
               'color' => '#ff5722',
               'usage_count' => rand(10, 50),
           ];
       }

       // Add month-specific trending topics
       $monthlyTrends = [
           1 => ['New Year', 'Resolutions', 'Planning'],
           2 => ['Valentine\'s Day', 'Love', 'Relationships'],
           3 => ['Spring Cleaning', 'Fresh Start', 'Growth'],
           4 => ['Easter', 'Renewal', 'April Fools'],
           5 => ['Mother\'s Day', 'Graduation', 'Mental Health'],
           6 => ['Father\'s Day', 'Summer Prep', 'Vacation'],
           7 => ['Independence Day', 'Summer Fun', 'Outdoor'],
           8 => ['Back to School', 'Preparation', 'Learning'],
           9 => ['Fall Season', 'Harvest', 'Preparation'],
           10 => ['Halloween', 'Autumn', 'Spooky'],
           11 => ['Thanksgiving', 'Gratitude', 'Black Friday'],
           12 => ['Christmas', 'Holidays', 'Year End'],
       ];

       if (isset($monthlyTrends[$currentMonth])) {
           foreach ($monthlyTrends[$currentMonth] as $trend) {
               $seasonalTags[] = [
                   'name' => $trend,
                   'description' => "Trending topic: {$trend}",
                   'color' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)),
                   'usage_count' => rand(15, 60),
               ];
           }
       }

       // Create seasonal tags
       foreach ($seasonalTags as $tagData) {
           $tagData['is_active'] = true;
           
           // Check if tag already exists
           $existingTag = Tag::where('name', $tagData['name'])->first();
           if (!$existingTag) {
               Tag::create($tagData);
           }
       }

       $this->command->info('✓ Created seasonal and trending tags');
   }
}