<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AuditLogRepositoryInterface
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function actors(): Collection;

    public function events(): Collection;
}
