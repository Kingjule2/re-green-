<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('land_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('data_status')->default('demo');
            $table->string('crs')->nullable();
            $table->json('geometry')->nullable();
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['land_id', 'data_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
