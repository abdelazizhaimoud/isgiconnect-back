<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Chat\Conversation;
use App\Models\User\User;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->count() < 2) {
            $this->command->info('Not enough users to create conversations. Please seed users first.');
            return;
        }

        // Create 5 direct conversations
        for ($i = 0; $i < 5; $i++) {
            $user1 = $users->random();
            $user2 = $users->where('id', '!=', $user1->id)->random();

            Conversation::create([
                'type' => 'direct',
                'created_by' => $user1->id,
                'last_message_at' => now(),
            ]);
        }

        // Create 2 group conversations
        Conversation::create([
            'type' => 'group',
            'name' => 'General Chat',
            'description' => 'A place for everyone to chat.',
            'created_by' => $users->first()->id,
            'last_message_at' => now(),
        ]);

        Conversation::create([
            'type' => 'group',
            'name' => 'Project Alpha',
            'description' => 'Discussion about Project Alpha.',
            'created_by' => $users->first()->id,
            'last_message_at' => now(),
        ]);
    }
}
