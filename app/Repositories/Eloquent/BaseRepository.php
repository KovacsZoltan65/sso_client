<?php

namespace App\Repositories\Eloquent;

use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository as PrettusBaseRepository;

abstract class BaseRepository extends PrettusBaseRepository
{
    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
