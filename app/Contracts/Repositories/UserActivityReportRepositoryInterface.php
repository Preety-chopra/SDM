<?php

namespace App\Contracts\Repositories;

interface UserActivityReportRepositoryInterface
{
    public function summary(array $filters): array;
}
