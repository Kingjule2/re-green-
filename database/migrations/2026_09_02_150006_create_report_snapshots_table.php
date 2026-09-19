<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A versioned report the B2G/B2B side can hand to a regulator or an ESG
     * auditor. `scope` decides whether it covers one land or every land the
     * account can see; `snapshot` freezes the numbers so a report never changes
     * after it was downloaded.
     */
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('land_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope')->default('all');
            $table->unsignedInteger('version');
            $table->string('title');
            $table->string('format')->default('pdf');
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['scope', 'land_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
