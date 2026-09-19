<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('land_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parcel_id')->nullable()->constrained()->nullOnDelete();
            $table->date('observed_at');
            $table->string('observer')->nullable();
            $table->string('source')->nullable();
            $table->string('data_status')->default('measured');
            $table->string('burn_severity')->nullable();
            $table->string('land_cover')->nullable();
            $table->decimal('slope_deg', 6, 2)->nullable();
            $table->decimal('soil_ph', 4, 2)->nullable();
            $table->string('soil_texture')->nullable();
            $table->decimal('soil_moisture_pct', 6, 2)->nullable();
            $table->boolean('erosion_signs')->nullable();
            $table->date('fire_date')->nullable();
            $table->json('values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['land_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_observations');
    }
};
