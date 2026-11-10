<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('outsourced_staff', function (Blueprint $table) {
            $table->char('aadhaar_lookup_hash', 64)->nullable()->index();
        });

        // The searchable value is keyed and never contains an Aadhaar number.
        DB::table('outsourced_staff')->select(['id', 'aadhaar_number'])->whereNotNull('aadhaar_number')
            ->chunkById(200, function (Collection $rows): void {
                foreach ($rows as $row) {
                    DB::table('outsourced_staff')->where('id', $row->id)->update([
                        'aadhaar_lookup_hash' => hash_hmac('sha256', 'outsourced-staff-aadhaar:'.Crypt::decryptString($row->aadhaar_number), config('app.key')),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outsourced_staff', function (Blueprint $table) {
            $table->dropIndex(['aadhaar_lookup_hash']);
            $table->dropColumn('aadhaar_lookup_hash');
        });
    }
};
