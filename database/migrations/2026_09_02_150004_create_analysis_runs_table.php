<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('land_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('input_metadata')->nullable();
            $table->json('result')->nullable();
            $table->json('provenance')->nullable();
            $table->string('model_name')->nullable();
            $table->string('model_version')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['land_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_runs');
    }
};
