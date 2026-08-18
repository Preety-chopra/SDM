<?php

namespace App\Contracts\Repositories;

use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ServiceDeficiencyMemoRepositoryInterface
{
    public function paginateFor(User $user): LengthAwarePaginator;

    public function create(array $attributes): ServiceDeficiencyMemo;

    public function update(ServiceDeficiencyMemo $memo, array $attributes): ServiceDeficiencyMemo;
}
