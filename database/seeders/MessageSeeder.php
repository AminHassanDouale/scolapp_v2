<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\MessageThread;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $school  = School::where('slug', 'ecole-demo')->first();
        $admin   = User::where('email', 'admin@scolapp.com')->first();
        $teachers = User::where('school_id', $school->id)->role('teacher')->take(3)->get();

        if ($teachers->isEmpty()) {
            $this->command->warn('  → No teacher users found. Run TeacherSeeder first.');
            return;
        }

        $threads = [
            [
                'subject'  => 'Absence de l\'élève Mohamed Hassan',
                'messages' => [
                    ['sender' => $teachers->first(), 'body' => "Bonjour,\n\nJe vous contacte concernant l'absence répétée de l'élève Mohamed Hassan en classe de 6ème A. Il a été absent 3 fois cette semaine sans justificatif.\n\nPourriez-vous contacter la famille ?\n\nCordialement"],
                    ['sender' => $admin, 'body' => "Bonjour,\n\nMerci pour l'information. J'ai contacté la famille ce matin. Ils ont fourni un certificat médical. L'absence est désormais justifiée.\n\nBien cordialement"],
                ],
                'participants' => [$admin, $teachers->first()],
            ],
            [
                'subject'  => 'Question sur les bulletins du 1er trimestre',
                'messages' => [
                    ['sender' => $admin, 'body' => "Bonjour à tous,\n\nJe vous rappelle que la saisie des notes pour le 1er trimestre doit être finalisée avant le 20 décembre.\n\nMerci de votre coopération."],
                    ['sender' => $teachers->get(1) ?? $teachers->first(), 'body' => "Bonjour,\n\nMes notes sont déjà saisies. Y a-t-il un format particulier pour les appréciations ?\n\nMerci"],
                    ['sender' => $admin, 'body' => "Bonjour,\n\nLes appréciations doivent être concises et constructives, entre 1 et 3 phrases. Merci."],
                ],
                'participants' => array_merge([$admin], $teachers->take(2)->all()),
            ],
        ];

        foreach ($threads as $threadData) {
            $thread = MessageThread::create([
                'uuid'       => (string) Str::uuid(),
                'school_id'  => $school->id,
                'created_by' => $admin->id,
                'subject'    => $threadData['subject'],
            ]);

            foreach ($threadData['messages'] as $msgData) {
                Message::create([
                    'uuid'      => (string) Str::uuid(),
                    'thread_id' => $thread->id,
                    'sender_id' => $msgData['sender']->id,
                    'body'      => $msgData['body'],
                ]);
            }

            foreach ($threadData['participants'] as $participant) {
                MessageRecipient::firstOrCreate(
                    ['thread_id' => $thread->id, 'user_id' => $participant->id],
                    ['is_read' => $participant->id === $admin->id, 'read_at' => $participant->id === $admin->id ? now() : null]
                );
            }
        }

        $this->command->info('  → ' . count($threads) . ' message threads created.');
    }
}
