<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function countAll(): int;

    public function recent(int $limit = 5): Collection;
}
