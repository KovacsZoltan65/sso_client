<?php

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct()
    {
        $modelClass = $this->model();
        $model = app($modelClass);

        if (! $model instanceof Model) {
            throw new \RuntimeException("Repository model [{$modelClass}] must be an Eloquent model.");
        }

        $this->model = $model;
    }

    abstract public function model(): string;
}
