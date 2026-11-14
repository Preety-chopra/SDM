<?php

namespace App\Models;

use App\Enums\SdmType;
use App\Support\PenaltyClauseTemplates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['contract_group', 'version', 'contractor_id', 'line_id', 'station_id', 'service_type', 'contract_number', 'title', 'effective_from', 'effective_to', 'cmc_staff_id', 'status', 'source_document_name', 'source_document_path', 'source_document_mime', 'source_document_size', 'published_at', 'created_by', 'updated_by', 'is_station_unassigned'])]
class ServiceContract extends Model
{
    protected $attributes = ['version' => 1, 'status' => 'draft'];

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'service_contract_line');
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class, 'service_contract_station');
    }

    public function scopeCoversStations(Builder $query, array $stationIds, bool $includeUnrestricted = true): Builder
    {
        return $query->where('is_station_unassigned', false)
            ->where(fn (Builder $locations): Builder => $locations
                ->whereHas('stations', fn (Builder $stations): Builder => $stations->whereKey($stationIds))
                ->orWhere(fn (Builder $legacy): Builder => $legacy
                    ->doesntHave('stations')
                    ->where(fn (Builder $fallback): Builder => $fallback
                        ->whereIn('station_id', $stationIds)
                        ->when($includeUnrestricted, fn (Builder $fallback): Builder => $fallback->orWhereNull('station_id')))));
    }

    public function cmcStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cmc_staff_id');
    }

    public function penaltyClauses(): HasMany
    {
        return $this->hasMany(ContractPenaltyClause::class)->orderBy('sort_order');
    }

    public function memos(): HasMany
    {
        return $this->hasMany(ServiceDeficiencyMemo::class);
    }

    public function seedPenaltyClauses(): void
    {
        if ($this->penaltyClauses()->exists()) {
            return;
        }

        $this->penaltyClauses()->createMany(PenaltyClauseTemplates::for($this->service_type));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    protected function casts(): array
    {
        return [
            'service_type' => SdmType::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'published_at' => 'datetime',
            'is_station_unassigned' => 'boolean',
        ];
    }
}
