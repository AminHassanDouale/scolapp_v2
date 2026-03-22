<?php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    private array $roleLabels = [
        'super-admin' => 'Super Admin',
        'admin'       => 'Admin',
        'director'    => 'Directeur',
        'teacher'     => 'Enseignant',
        'accountant'  => 'Comptable',
        'guardian'    => 'Tuteur',
    ];

    private array $roleColors = [
        'super-admin' => 'badge-error',
        'admin'       => 'badge-primary',
        'director'    => 'badge-secondary',
        'teacher'     => 'badge-accent',
        'accountant'  => 'badge-warning',
        'guardian'    => 'badge-ghost',
    ];

    private array $groupLabels = [
        'academic'        => 'Structure académique',
        'students'        => 'Élèves',
        'teachers'        => 'Enseignants',
        'enrollments'     => 'Inscriptions',
        'attendance'      => 'Présences',
        'timetable'       => 'Emploi du temps',
        'assessments'     => 'Évaluations',
        'report-cards'    => 'Bulletins',
        'invoices'        => 'Factures',
        'payments'        => 'Paiements',
        'fee-schedules'   => 'Barèmes',
        'announcements'   => 'Annonces',
        'messages'        => 'Messages',
        'reports'         => 'Rapports & Analyses',
        'scheduled-tasks' => 'Tâches planifiées',
        'settings'        => 'Paramètres',
    ];

    // Toggle a single permission on a role (super-admin only)
    public function toggle(int $roleId, int $permissionId): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);

        $role       = Role::findById($roleId);
        $permission = Permission::findById($permissionId);

        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);
            $this->warning(
                "Permission retirée : {$permission->name}",
                "Rôle : {$role->name}",
                position: 'toast-top toast-end',
                icon: 'o-minus-circle',
                css: 'alert-warning',
                timeout: 2500
            );
        } else {
            $role->givePermissionTo($permission);
            $this->success(
                "Permission accordée : {$permission->name}",
                "Rôle : {$role->name}",
                position: 'toast-top toast-end',
                icon: 'o-plus-circle',
                css: 'alert-success',
                timeout: 2500
            );
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // Toggle an entire permission group for a role
    public function toggleGroup(int $roleId, string $group): void
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);

        $role        = Role::findById($roleId);
        $permissions = Permission::where('name', 'like', $group . '.%')->get();
        if ($permissions->isEmpty()) return;

        $hasAll = $permissions->every(fn($p) => $role->hasPermissionTo($p));

        if ($hasAll) {
            $role->revokePermissionTo($permissions->pluck('name')->toArray());
            $this->warning("Groupe retiré : {$group}", "Rôle : {$role->name}",
                position: 'toast-top toast-end', icon: 'o-x-circle', css: 'alert-warning', timeout: 3000);
        } else {
            $role->givePermissionTo($permissions->pluck('name')->toArray());
            $this->success("Groupe accordé : {$group}", "Rôle : {$role->name}",
                position: 'toast-top toast-end', icon: 'o-check-circle', css: 'alert-success', timeout: 3000);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function with(): array
    {
        $roles = Role::with('permissions')
            ->orderByRaw("FIELD(name,'super-admin','admin','director','accountant','teacher','guardian')")
            ->get();
        $permissions = Permission::orderBy('name')->get();
        $grouped     = $permissions->groupBy(fn($p) => explode('.', $p->name)[0] ?? $p->name);

        return [
            'roles'       => $roles,
            'permissions' => $permissions,
            'grouped'     => $grouped,
            'roleLabels'  => $this->roleLabels,
            'roleColors'  => $this->roleColors,
            'groupLabels' => $this->groupLabels,
            'isSuperAdmin'=> auth()->user()->hasRole('super-admin'),
        ];
    }
};
?>

<div>
    <x-header title="Rôles & Permissions" subtitle="Matrice de contrôle d'accès" separator progress-indicator>
        <x-slot:actions>
            @if($isSuperAdmin)
                <x-badge value="Édition activée" class="badge-error badge-sm" />
            @else
                <x-badge value="Lecture seule" class="badge-ghost badge-sm" />
            @endif
        </x-slot:actions>
    </x-header>

    @if($isSuperAdmin)
    <x-alert icon="o-shield-exclamation" class="alert-warning mb-6">
        <strong>Mode super-admin :</strong> Cliquez sur une cellule pour basculer la permission. Les changements sont immédiats.
    </x-alert>
    @else
    <x-alert icon="o-information-circle" class="alert-info mb-6">
        Affichage en lecture seule. Seul le <strong>super-admin</strong> peut modifier les permissions.
    </x-alert>
    @endif

    {{-- Role summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
        @foreach($roles as $role)
        @php
            $badge = $roleColors[$role->name] ?? 'badge-ghost';
            $label = $roleLabels[$role->name]  ?? $role->name;
            $pct   = $permissions->count() > 0
                ? round(($role->permissions->count() / $permissions->count()) * 100)
                : 0;
        @endphp
        <x-card wire:key="rc-{{ $role->id }}" class="text-center border border-base-200 hover:shadow-md transition-shadow">
            <x-badge :value="$label" class="{{ $badge }} badge-sm mb-2" />
            <p class="text-3xl font-black mt-1">{{ $role->permissions->count() }}</p>
            <p class="text-xs text-base-content/50 mb-2">/ {{ $permissions->count() }} perms</p>
            <div class="w-full bg-base-200 rounded-full h-1.5">
                <div class="h-1.5 rounded-full bg-primary transition-all" style="width: {{ $pct }}%"></div>
            </div>
            <p class="text-xs text-base-content/40 mt-1">{{ $pct }}% couvert</p>
        </x-card>
        @endforeach
    </div>

    {{-- Permission matrix --}}
    @if($permissions->count() > 0)
    <x-card class="p-0 overflow-x-auto">
        <table class="table table-xs w-full">
            <thead class="bg-base-200">
                <tr>
                    <th class="min-w-56 py-3 px-4 text-left text-sm font-bold">Permission</th>
                    @foreach($roles as $role)
                    <th class="text-center py-3 px-2 min-w-28">
                        <x-badge
                            :value="$roleLabels[$role->name] ?? $role->name"
                            class="{{ $roleColors[$role->name] ?? 'badge-ghost' }} badge-sm"
                        />
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($grouped as $group => $groupPerms)

                {{-- Group header row --}}
                <tr class="border-t-2 border-base-200 bg-base-100">
                    <td class="py-2 px-4 font-bold text-xs uppercase tracking-wider text-base-content/60">
                        <div class="flex items-center gap-2">
                            <x-icon name="o-folder" class="w-4 h-4 text-primary/60" />
                            {{ $groupLabels[$group] ?? ucfirst($group) }}
                            <x-badge :value="$groupPerms->count()" class="badge-ghost badge-xs" />
                        </div>
                    </td>
                    @foreach($roles as $role)
                    @php
                        $hasAll  = $groupPerms->every(fn($p) => $role->permissions->contains($p));
                        $hasNone = $groupPerms->every(fn($p) => !$role->permissions->contains($p));
                    @endphp
                    <td class="text-center py-2">
                        @if($isSuperAdmin)
                            <button
                                wire:click="toggleGroup({{ $role->id }}, '{{ $group }}')"
                                wire:loading.attr="disabled"
                                class="btn btn-xs {{ $hasAll ? 'btn-success' : (!$hasNone ? 'btn-warning btn-outline' : 'btn-ghost') }} tooltip"
                                data-tip="{{ $hasAll ? 'Tout retirer' : 'Tout accorder' }}"
                            >
                                @if($hasAll)
                                    <x-icon name="o-check-circle" class="w-3.5 h-3.5" />
                                @elseif(!$hasNone)
                                    <x-icon name="o-minus-circle" class="w-3.5 h-3.5" />
                                @else
                                    <x-icon name="o-plus-circle" class="w-3.5 h-3.5 opacity-30" />
                                @endif
                            </button>
                        @else
                            @if($hasAll)
                                <x-icon name="o-check-circle" class="w-4 h-4 text-success inline" />
                            @elseif(!$hasNone)
                                <x-icon name="o-minus-circle" class="w-4 h-4 text-warning inline" />
                            @else
                                <span class="text-base-content/20">—</span>
                            @endif
                        @endif
                    </td>
                    @endforeach
                </tr>

                {{-- Individual permission rows --}}
                @foreach($groupPerms->sortBy('name') as $permission)
                @php $action = explode('.', $permission->name, 2)[1] ?? $permission->name; @endphp
                <tr class="hover border-b border-base-100" wire:key="perm-{{ $permission->id }}">
                    <td class="py-1.5 pl-10 pr-4">
                        <code class="text-xs text-base-content/60 font-mono">{{ $action }}</code>
                    </td>
                    @foreach($roles as $role)
                    @php $has = $role->permissions->contains($permission); @endphp
                    <td class="text-center py-1">
                        @if($isSuperAdmin)
                            <button
                                wire:click="toggle({{ $role->id }}, {{ $permission->id }})"
                                wire:loading.attr="disabled"
                                class="btn btn-xs btn-ghost btn-circle tooltip"
                                data-tip="{{ $has ? 'Retirer' : 'Accorder' }}"
                            >
                                @if($has)
                                    <x-icon name="o-check-circle" class="w-4 h-4 text-success" />
                                @else
                                    <x-icon name="o-x-circle" class="w-4 h-4 text-base-content/15 hover:text-error transition-colors" />
                                @endif
                            </button>
                        @else
                            @if($has)
                                <x-icon name="o-check-circle" class="w-4 h-4 text-success inline" />
                            @else
                                <span class="text-base-content/20">—</span>
                            @endif
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach

                @endforeach
            </tbody>
        </table>
    </x-card>

    <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-base-content/50">
        <span class="flex items-center gap-1"><x-icon name="o-check-circle" class="w-4 h-4 text-success" /> Accordée</span>
        <span class="flex items-center gap-1"><x-icon name="o-minus-circle" class="w-4 h-4 text-warning" /> Partielle</span>
        <span class="flex items-center gap-1"><x-icon name="o-x-circle" class="w-4 h-4 text-base-content/20" /> Non accordée</span>
        @if($isSuperAdmin)
        <span class="ml-auto flex items-center gap-1 text-warning font-medium">
            <x-icon name="o-cursor-arrow-ripple" class="w-4 h-4" /> Cliquez pour basculer
        </span>
        @endif
    </div>

    @else
    <x-alert icon="o-exclamation-triangle" class="alert-warning">
        Aucune permission définie. Exécutez
        <code class="font-mono bg-base-200 px-1.5 rounded text-xs">php artisan db:seed --class=RolePermissionSeeder</code>
        pour initialiser.
    </x-alert>
    @endif
</div>
