<?php

namespace App\Contracts\Repositories;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AuditLogRepositoryInterface
{
    public function create(array $attributes): AuditLog;

    public function paginate(array $filters): LengthAwarePaginator;

    public function actors(): Collection;

    public function events(): Collection;
}
