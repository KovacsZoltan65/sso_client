<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function model(): string
    {
        return User::class;
    }

    public function countAll(): int
    {
        return $this->model->newQuery()->count();
    }

    public function recent(int $limit = 5): Collection
    {
        return $this->model->newQuery()->latest()->limit($limit)->get();
    }
}
