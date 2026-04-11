<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'ecole-demo')->first();
        $admin  = User::where('email', 'admin@scolapp.com')->first();

        $announcements = [
            [
                'title'        => 'Réunion des parents d\'élèves — Trimestre 1',
                'body'         => "Chers parents et tuteurs,\n\nNous avons le plaisir de vous convier à la réunion trimestrielle qui se tiendra le vendredi 15 novembre 2025 à partir de 16h00 dans le hall principal de l'établissement.\n\nVotre présence est vivement souhaitée.",
                'level'        => 'info',
                'is_pinned'    => true,
                'is_published' => true,
                'published_at' => now()->subDays(5),
                'expires_at'   => now()->addDays(10),
            ],
            [
                'title'        => 'Fermeture exceptionnelle — Fête nationale',
                'body'         => "L'établissement sera fermé le mercredi 27 juin 2025 à l'occasion de la Fête nationale de Djibouti.\n\nLes cours reprendront normalement le jeudi 28 juin.",
                'level'        => 'warning',
                'is_pinned'    => false,
                'is_published' => true,
                'published_at' => now()->subDays(3),
                'expires_at'   => now()->addDays(30),
            ],
            [
                'title'        => 'Rappel : Paiement des frais de scolarité',
                'body'         => "Nous rappelons aux familles que le paiement des frais de scolarité du 2ème trimestre est dû avant le 15 janvier 2026.\n\nTout retard de paiement entraînera des pénalités conformément au règlement intérieur.",
                'level'        => 'warning',
                'is_pinned'    => true,
                'is_published' => true,
                'published_at' => now()->subDays(10),
                'expires_at'   => now()->addDays(20),
            ],
            [
                'title'        => 'Résultats du Baccalauréat 2025 — Félicitations !',
                'body'         => "L'équipe pédagogique est fière d'annoncer un taux de réussite de 87% au Baccalauréat session 2025.\n\nNous félicitons chaleureusement tous les lauréats et remercions les enseignants pour leur dévouement.",
                'level'        => 'info',
                'is_pinned'    => false,
                'is_published' => true,
                'published_at' => now()->subDays(15),
                'expires_at'   => null,
            ],
            [
                'title'        => 'Urgence : Coupure d\'eau — Vendredi matin',
                'body'         => "En raison de travaux de maintenance sur le réseau municipal, l'eau courante sera coupée vendredi matin de 7h à 11h.\n\nDes dispositions ont été prises pour assurer les services essentiels.",
                'level'        => 'urgent',
                'is_pinned'    => true,
                'is_published' => true,
                'published_at' => now()->subDay(),
                'expires_at'   => now()->addDays(2),
            ],
            [
                'title'        => 'Concours de dessin — Appel à candidatures',
                'body'         => "Le club des arts plastiques organise son concours annuel de dessin sur le thème \"L'Afrique de demain\".\n\nInscriptions ouvertes jusqu'au 30 novembre. Ouvert à tous les élèves de CP à Terminale.",
                'level'        => 'info',
                'is_pinned'    => false,
                'is_published' => true,
                'published_at' => now()->subDays(2),
                'expires_at'   => now()->addDays(15),
            ],
        ];

        foreach ($announcements as $data) {
            Announcement::create(array_merge($data, [
                'uuid'       => (string) Str::uuid(),
                'school_id'  => $school->id,
                'created_by' => $admin->id,
                'target_audience' => json_encode(['type' => 'all']),
            ]));
        }

        $this->command->info('  → ' . count($announcements) . ' announcements created.');
    }
}
