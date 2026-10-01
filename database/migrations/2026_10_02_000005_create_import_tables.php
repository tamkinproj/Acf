<?php

use App\Sync\SyncSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One uploaded spreadsheet and how far it has got: uploaded -> mapped -> validated -> imported.
        Schema::create('import_batches', function (Blueprint $table) {
            SyncSchema::id($table);
            $table->uuid('foundation_id')->index();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('foundation_id')->references('id')->on('foundations')->restrictOnDelete();
            }
            $table->string('original_name', 255);
            $table->string('storage_path', 255);
            $table->string('file_type', 10);                           // csv | xlsx
            $table->string('status', 20)->default('uploaded')->index();   // uploaded | mapped | validated | imported | cancelled
            $table->json('headers');
            $table->json('mapping')->nullable();                       // column index -> canonical field
            $table->unsignedInteger('row_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('imported_at')->nullable();
            SyncSchema::columns($table);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('foundation_id')->index();
            $table->foreignUuid('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw');                                       // the cells as uploaded
            $table->json('data')->nullable();                          // mapped, cleaned values
            $table->json('errors')->nullable();
            $table->json('matches')->nullable();                       // possible duplicates found in the program
            $table->string('status', 20)->default('pending')->index(); // pending | valid | invalid | duplicate | imported | skipped
            $table->string('decision', 20)->nullable();                // create_new | use_existing | skip
            $table->uuid('existing_aytam_id')->nullable();
            $table->uuid('aytam_id')->nullable();                      // the record this row became
            $table->unique(['batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
