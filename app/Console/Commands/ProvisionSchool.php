<?php

namespace App\Console\Commands;

use App\Actions\ProvisionSchoolDefaults;
use App\Models\AcademicCycle;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apply the standard French default structure (academic year, cycles, grades,
 * one class per niveau, and 2 rooms A/B per class) to an EXISTING school that
 * doesn't have it yet — for backfilling schools created before provisioning,
 * or the demo school.
 *
 *   php artisan school:provision DEMO001
 *   php artisan school:provision --all
 *   php artisan school:provision DEMO001 --force
 */
class ProvisionSchool extends Command
{
    protected $signature = 'school:provision
                            {code? : School code or UUID (omit when using --all)}
                            {--all : Provision every school that has no academic structure}
                            {--force : Provision even if the school already has cycles (may create duplicates)}';

    protected $description = 'Apply the standard French default structure (year, cycles, grades, classes, rooms A/B) to an existing school';

    public function handle(ProvisionSchoolDefaults $action): int
    {
        if ($this->option('all')) {
            $schools = School::orderBy('name')->get();
        } else {
            $code = $this->argument('code');
            if (! $code) {
                $this->error('Provide a school code/UUID, or use --all.');
                return self::FAILURE;
            }
            $school = School::where('code', strtoupper($code))
                ->orWhere('uuid', $code)
                ->orWhere('slug', $code)
                ->first();

            if (! $school) {
                $this->error("Aucune école trouvée pour : {$code}");
                return self::FAILURE;
            }
            $schools = collect([$school]);
        }

        $done = 0;
        foreach ($schools as $school) {
            $hasStructure = AcademicCycle::where('school_id', $school->id)->exists();

            if ($hasStructure && ! $this->option('force')) {
                $this->warn("↷ {$school->name} [{$school->code}] — structure déjà présente, ignorée (utilisez --force pour forcer).");
                continue;
            }

            try {
                $year = DB::transaction(fn () => $action->execute($school));
                $this->info("✓ {$school->name} [{$school->code}] — année {$year->name}, cycles/niveaux/classes/salles A-B créés.");
                $done++;
            } catch (\Throwable $e) {
                $this->error("✗ {$school->name} [{$school->code}] : " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Terminé — {$done} école(s) provisionnée(s).");

        return self::SUCCESS;
    }
}
