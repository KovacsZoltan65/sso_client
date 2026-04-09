<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Models\Permission;

class RolePageDataService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function permissionOptions(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Permission $permission) => [
                'value' => (int) $permission->id,
                'label' => Str::headline((string) Str::of($permission->name)->afterLast('.')),
                'helper' => $permission->name,
                'groupKey' => (string) Str::of($permission->name)->before('.'),
                'groupLabel' => Str::headline((string) Str::of($permission->name)->before('.')->replace(['-', '_'], ' ')),
                'action' => (string) Str::of($permission->name)->afterLast('.'),
                'itemLabel' => Str::headline((string) Str::of($permission->name)->afterLast('.')),
            ])
            ->values()
            ->all();
    }
}
