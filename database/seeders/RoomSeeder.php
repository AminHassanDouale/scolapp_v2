<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'ecole-demo')->first();
        if (! $school) {
            $this->command->warn('School not found. Skipping RoomSeeder.');
            return;
        }

        $rooms = [
            // Classrooms
            ['name' => 'Salle 1',   'code' => 'S01',  'type' => 'classroom', 'capacity' => 35],
            ['name' => 'Salle 2',   'code' => 'S02',  'type' => 'classroom', 'capacity' => 35],
            ['name' => 'Salle 3',   'code' => 'S03',  'type' => 'classroom', 'capacity' => 35],
            ['name' => 'Salle 4',   'code' => 'S04',  'type' => 'classroom', 'capacity' => 30],
            ['name' => 'Salle 5',   'code' => 'S05',  'type' => 'classroom', 'capacity' => 30],
            ['name' => 'Salle 6',   'code' => 'S06',  'type' => 'classroom', 'capacity' => 30],
            ['name' => 'Salle 7',   'code' => 'S07',  'type' => 'classroom', 'capacity' => 28],
            ['name' => 'Salle 8',   'code' => 'S08',  'type' => 'classroom', 'capacity' => 28],
            ['name' => 'Salle 9',   'code' => 'S09',  'type' => 'classroom', 'capacity' => 28],
            ['name' => 'Salle 10',  'code' => 'S10',  'type' => 'classroom', 'capacity' => 32],
            ['name' => 'Salle 11',  'code' => 'S11',  'type' => 'classroom', 'capacity' => 32],
            ['name' => 'Salle 12',  'code' => 'S12',  'type' => 'classroom', 'capacity' => 32],
            // Labs
            ['name' => 'Labo Sciences', 'code' => 'LAB',  'type' => 'lab',       'capacity' => 24],
            ['name' => 'Salle Informatique', 'code' => 'INFO', 'type' => 'lab',  'capacity' => 20],
            // Gym / outdoor
            ['name' => 'Gymnase',    'code' => 'GYM',  'type' => 'gym',       'capacity' => 80],
            ['name' => 'Terrain extérieur', 'code' => 'EXT', 'type' => 'outdoor', 'capacity' => 100],
        ];

        $count = 0;
        foreach ($rooms as $data) {
            Room::firstOrCreate(
                ['school_id' => $school->id, 'name' => $data['name']],
                array_merge($data, [
                    'uuid'      => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'is_active' => true,
                ])
            );
            $count++;
        }

        $this->command->info("  → {$count} salles créées.");
    }
}
