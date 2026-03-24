<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DashboardStatsData extends Data
{
    public function __construct(
        public int $users,
        public int $roles,
        public int $permissions,
        public int $activityEntries,
    ) {
    }
}
