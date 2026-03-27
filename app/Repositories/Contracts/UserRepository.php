<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface UserRepository
{
    public function countAll(): int;

    public function recent(int $limit = 5): Collection;
}
