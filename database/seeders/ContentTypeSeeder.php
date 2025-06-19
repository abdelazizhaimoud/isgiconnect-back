<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Content\ContentType;

class ContentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contentTypes = [
            [
                'name' => 'Article',
                'slug' => 'article',
                'description' => 'Long-form written content for in-depth coverage of topics',
                'icon' => 'document-text',
                'is_active' => true,
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 1,
                'fields_schema' => [
                    'subtitle' => [
                        'type' => 'text',
                        'label' => 'Subtitle',
                        'required' => false,
                        'max_length' => 255,
                    ],
                    'reading_time' => [
                        'type' => 'number',
                        'label' => 'Estimated Reading Time (minutes)',
                        'required' => false,
                    ],
                    'author_note' => [
                        'type' => 'textarea',
                        'label' => 'Author Note',
                        'required' => false,
                        'max_length' => 500,
                    ],
                ],
                'settings' => [
                    'enable_table_of_contents' => true,
                    'enable_social_sharing' => true,
                    'enable_print_version' => true,
                    'show_author_bio' => true,
                    'show_reading_time' => true,
                    'enable_related_articles' => true,
                ],
            ],
            [
                'name' => 'Blog Post',
                'slug' => 'blog-post',
                'description' => 'Informal posts for sharing thoughts, opinions, and updates',
                'icon' => 'pencil',
                'is_active' => true,
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 2,
                'fields_schema' => [
                    'mood' => [
                        'type' => 'select',
                        'label' => 'Mood',
                        'required' => false,
                        'options' => ['informative', 'casual', 'humorous', 'serious', 'inspirational'],
                    ],
                    'call_to_action' => [
                        'type' => 'text',
                        'label' => 'Call to Action',
                        'required' => false,
                        'max_length' => 255,
                    ],
                ],
                'settings' => [
                    'enable_social_sharing' => true,
                    'show_author_bio' => true,
                    'enable_newsletter_signup' => true,
                    'show_publication_date' => true,
                ],
            ],
            [
                'name' => 'News',
                'slug' => 'news',
                'description' => 'Timely news articles and breaking news content',
                'icon' => 'newspaper',
                'is_active' => true,
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 3,
                'fields_schema' => [
                    'location' => [
                        'type' => 'text',
                        'label' => 'Location',
                        'required' => false,
                        'max_length' => 255,
                    ],
                    'source' => [
                        'type' => 'text',
                        'label' => 'Source',
                        'required' => false,
                        'max_length' => 255,
                    ],
                    'urgency' => [
                        'type' => 'select',
                        'label' => 'Urgency Level',
                        'required' => false,
                        'options' => ['low', 'medium', 'high', 'breaking'],
                    ],
                    'verification_status' => [
                        'type' => 'select',
                        'label' => 'Verification Status',
                        'required' => false,
                        'options' => ['unverified', 'partially_verified', 'verified'],
                    ],
                ],
                'settings' => [
                    'show_publication_time' => true,
                    'show_last_updated' => true,
                    'enable_breaking_news_alert' => true,
                    'show_source_attribution' => true,
                ],
            ],
            [
                'name' => 'Tutorial',
                'slug' => 'tutorial',
                'description' => 'Step-by-step instructional content and how-to guides',
                'icon' => 'academic-cap',
                'is_active' => true,
                'is_hierarchical' => true,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 4,
                'fields_schema' => [
                    'difficulty_level' => [
                        'type' => 'select',
                        'label' => 'Difficulty Level',
                        'required' => true,
                        'options' => ['beginner', 'intermediate', 'advanced', 'expert'],
                    ],
                    'estimated_time' => [
                        'type' => 'text',
                        'label' => 'Estimated Completion Time',
                        'required' => false,
                        'max_length' => 100,
                    ],
                    'prerequisites' => [
                        'type' => 'textarea',
                        'label' => 'Prerequisites',
                        'required' => false,
                        'max_length' => 1000,
                    ],
                    'tools_required' => [
                        'type' => 'textarea',
                        'label' => 'Tools/Software Required',
                        'required' => false,
                        'max_length' => 1000,
                    ],
                ],
                'settings' => [
                    'enable_step_numbering' => true,
                    'show_progress_indicator' => true,
                    'enable_code_highlighting' => true,
                    'show_difficulty_badge' => true,
                    'enable_downloadable_resources' => true,
                ],
            ],
            [
                'name' => 'Review',
                'slug' => 'review',
                'description' => 'Product, service, or content reviews and evaluations',
                'icon' => 'star',
                'is_active' => true,
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 5,
                'fields_schema' => [
                    'overall_rating' => [
                        'type' => 'number',
                        'label' => 'Overall Rating (1-5)',
                        'required' => true,
                        'min' => 1,
                        'max' => 5,
                    ],
                    'pros' => [
                        'type' => 'textarea',
                        'label' => 'Pros',
                        'required' => false,
                        'max_length' => 1000,
                    ],
                    'cons' => [
                        'type' => 'textarea',
                        'label' => 'Cons',
                        'required' => false,
                        'max_length' => 1000,
                    ],
                    'recommendation' => [
                        'type' => 'select',
                        'label' => 'Recommendation',
                        'required' => false,
                        'options' => ['highly_recommended', 'recommended', 'neutral', 'not_recommended'],
                    ],
                    'purchase_link' => [
                        'type' => 'url',
                        'label' => 'Purchase/More Info Link',
                        'required' => false,
                    ],
                ],
                'settings' => [
                    'show_rating_stars' => true,
                    'show_pros_cons' => true,
                    'enable_user_ratings' => true,
                    'show_recommendation_badge' => true,
                ],
            ],
            [
                'name' => 'Video',
                'slug' => 'video',
                'description' => 'Video content with descriptions and metadata',
                'icon' => 'play',
                'is_active' => true,
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 6,
                'fields_schema' => [
                    'video_url' => [
                        'type' => 'url',
                        'label' => 'Video URL',
                        'required' => true,
                    ],
                    'duration' => [
                        'type' => 'text',
                        'label' => 'Duration (mm:ss)',
                        'required' => false,
                        'pattern' => '^\d{1,2}:\d{2}$',
                    ],
                    'video_quality' => [
                        'type' => 'select',
                        'label' => 'Video Quality',
                        'required' => false,
                        'options' => ['720p', '1080p', '1440p', '4K'],
                    ],
                    'transcript' => [
                        'type' => 'textarea',
                        'label' => 'Video Transcript',
                        'required' => false,
                    ],
                ],
                'settings' => [
                    'auto_play' => false,
                    'show_controls' => true,
                    'enable_fullscreen' => true,
                    'show_duration' => true,
                    'enable_transcript' => true,
                ],
            ],
            [
                'name' => 'Event',
                'slug' => 'event',
                'description' => 'Event announcements and information',
                'icon' => 'calendar',
                'is_active' => true,
                'is_hierarchical' => false,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 7,
                'fields_schema' => [
                    'event_date' => [
                        'type' => 'datetime',
                        'label' => 'Event Date & Time',
                        'required' => true,
                    ],
                    'end_date' => [
                        'type' => 'datetime',
                        'label' => 'End Date & Time',
                        'required' => false,
                    ],
                    'location' => [
                        'type' => 'text',
                        'label' => 'Location',
                        'required' => false,
                        'max_length' => 255,
                    ],
                    'ticket_price' => [
                        'type' => 'number',
                        'label' => 'Ticket Price',
                        'required' => false,
                        'min' => 0,
                    ],
                    'registration_url' => [
                        'type' => 'url',
                        'label' => 'Registration URL',
                        'required' => false,
                    ],
                    'capacity' => [
                        'type' => 'number',
                        'label' => 'Event Capacity',
                        'required' => false,
                        'min' => 1,
                    ],
                ],
                'settings' => [
                    'show_countdown' => true,
                    'enable_rsvp' => true,
                    'show_attendee_count' => true,
                    'enable_calendar_export' => true,
                ],
            ],
            [
                'name' => 'FAQ',
                'slug' => 'faq',
                'description' => 'Frequently asked questions and answers',
                'icon' => 'question-mark-circle',
                'is_active' => true,
                'is_hierarchical' => true,
                'supports_comments' => false,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 8,
                'fields_schema' => [
                    'question' => [
                        'type' => 'text',
                        'label' => 'Question',
                        'required' => true,
                        'max_length' => 500,
                    ],
                    'answer' => [
                        'type' => 'textarea',
                        'label' => 'Answer',
                        'required' => true,
                    ],
                    'helpful_count' => [
                        'type' => 'number',
                        'label' => 'Helpful Count',
                        'required' => false,
                        'default' => 0,
                    ],
                ],
                'settings' => [
                    'enable_search' => true,
                    'enable_helpful_voting' => true,
                    'group_by_category' => true,
                    'show_related_questions' => true,
                ],
            ],
            [
                'name' => 'Documentation',
                'slug' => 'documentation',
                'description' => 'Technical documentation and reference materials',
                'icon' => 'book-open',
                'is_active' => true,
                'is_hierarchical' => true,
                'supports_comments' => true,
                'supports_media' => true,
                'supports_tags' => true,
                'sort_order' => 9,
                'fields_schema' => [
                    'version' => [
                        'type' => 'text',
                        'label' => 'Version',
                        'required' => false,
                        'max_length' => 50,
                    ],
                    'last_reviewed' => [
                        'type' => 'date',
                        'label' => 'Last Reviewed Date',
                        'required' => false,
                    ],
                    'code_examples' => [
                        'type' => 'textarea',
                        'label' => 'Code Examples',
                        'required' => false,
                    ],
                    'api_endpoint' => [
                        'type' => 'text',
                        'label' => 'Related API Endpoint',
                        'required' => false,
                        'max_length' => 255,
                    ],
                ],
                'settings' => [
                    'enable_table_of_contents' => true,
                    'enable_code_highlighting' => true,
                    'show_version_history' => true,
                    'enable_search' => true,
                    'show_last_updated' => true,
                ],
            ],
        ];

        foreach ($contentTypes as $contentTypeData) {
            ContentType::create($contentTypeData);
        }

        $this->command->info('✓ Created ' . count($contentTypes) . ' content types');
    }
}