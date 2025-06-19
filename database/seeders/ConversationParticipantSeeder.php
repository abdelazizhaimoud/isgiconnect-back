<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Chat\Conversation;
use App\Models\User\User;
use App\Models\Chat\ConversationParticipant;

class ConversationParticipantSeeder extends Seeder
{
    public function run(): void
    {
        $conversations = Conversation::all();
        $users = User::all();

        if ($users->isEmpty() || $conversations->isEmpty()) {
            $this->command->info('Cannot seed participants without users and conversations.');
            return;
        }

        foreach ($conversations as $conversation) {
            if ($conversation->type === 'direct') {
                $user1 = User::find($conversation->created_by);
                $user2 = $users->where('id', '!=', $user1->id)->random();

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user1->id,
                    'joined_at' => now(),
                ]);
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user2->id,
                    'joined_at' => now(),
                ]);
            } else { // group
                // Add all users to group chats for simplicity
                foreach ($users as $user) {
                    ConversationParticipant::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'role' => ($user->id === $conversation->created_by) ? 'admin' : 'member',
                        'joined_at' => now(),
                    ]);
                }
            }
        }
    }
}
