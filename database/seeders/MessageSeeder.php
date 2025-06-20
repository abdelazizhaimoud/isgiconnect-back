<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Chat\Conversation;
use App\Models\Chat\Message;
use Faker\Factory as Faker;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Fak4er::create();
        $conversations = Conversation::with('participants')->get();

        if ($conversations->isEmpty()) {
            $this->command->info('No conversations to seed messages into.');
            return;
        }

        foreach ($conversations as $conversation) {
            $participants = $conversation->participants;
            if ($participants->count() < 1) {
                continue;
            }

            // Create between 5 and 15 messages for each conversation
            for ($i = 0; $i < rand(5, 15); $i++) {
                $sender = $participants->random();
                
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->user_id,
                    'type' => 'text',
                    'content' => $faker->sentence(),
                    'created_at' => $faker->dateTimeBetween($conversation->created_at, 'now'),
                ]);
            }
        }
    }
}
