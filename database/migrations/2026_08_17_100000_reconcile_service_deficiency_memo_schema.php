<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lines', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('stations', function (Blueprint $table): void {
            $table->foreignId('line_id')->nullable()->after('name')->constrained()->restrictOnDelete();
        });

        DB::table('stations')->whereNotNull('line')->where('line', '<>', '')
            ->select('line')->distinct()->orderBy('line')->get()
            ->each(function (object $stationLine): void {
                $base = Str::upper(Str::slug($stationLine->line, '-')) ?: 'LINE';
                $code = Str::limit($base, 30, '');
                $suffix = 1;

                while (DB::table('lines')->where('code', $code)->exists()) {
                    $code = Str::limit($base, 26, '').'-'.$suffix++;
                }

                $lineId = DB::table('lines')->insertGetId([
                    'code' => $code,
                    'name' => $stationLine->line,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('stations')->where('line', $stationLine->line)->update(['line_id' => $lineId]);
            });

        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->string('station_name')->nullable()->after('station_id');
            $table->string('line_name', 100)->nullable()->after('station_name');
            $table->string('contractor_name')->nullable()->change();
            $table->string('staff_name')->nullable()->change();
            $table->date('incident_date')->nullable()->change();
            $table->timestamp('submitted_at')->nullable()->after('status')->index();
        });

        Schema::table('memo_late_reporting_details', function (Blueprint $table): void {
            $table->time('booked_shift_from')->change();
            $table->time('booked_shift_to')->change();
            $table->renameColumn('actual_reporting_at', 'actual_reporting_time');
        });

        Schema::table('memo_late_reporting_details', function (Blueprint $table): void {
            $table->time('actual_reporting_time')->change();
        });

        Schema::table('memo_public_complaints', function (Blueprint $table): void {
            $table->unique('memo_id');
        });
    }

    public function down(): void
    {
        Schema::table('memo_public_complaints', function (Blueprint $table): void {
            $table->dropUnique(['memo_id']);
        });

        Schema::table('memo_late_reporting_details', function (Blueprint $table): void {
            $table->dateTime('booked_shift_from')->change();
            $table->dateTime('booked_shift_to')->change();
            $table->renameColumn('actual_reporting_time', 'actual_reporting_at');
        });

        Schema::table('memo_late_reporting_details', function (Blueprint $table): void {
            $table->dateTime('actual_reporting_at')->change();
        });

        Schema::table('service_deficiency_memos', function (Blueprint $table): void {
            $table->dropColumn(['station_name', 'line_name', 'submitted_at']);
        });

        Schema::table('stations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('line_id');
        });

        Schema::dropIfExists('lines');
    }
};
