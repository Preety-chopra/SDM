<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface StationRepositoryInterface
{
    public function active(): Collection;

    public function activeWithLines(): Collection;
}
