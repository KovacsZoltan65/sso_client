<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Prettus\Repository\Contracts\RepositoryInterface;

interface UserRepository extends RepositoryInterface
{
    public function countAll(): int;

    public function recent(int $limit = 5): Collection;
}
