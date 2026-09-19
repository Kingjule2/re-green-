<?php

use App\Enums\LandStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A monitored restoration site ("lahan") — the central entity of the
     * platform: one burnt area, one owner, one monitoring history.
     */
    public function up(): void
    {
        Schema::create('lands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('objective')->default('restoration');
            $table->string('status')->default(LandStatus::Planned->value);
            $table->string('data_status')->default('demo');
            $table->string('location_name')->nullable();

            // The point the ML service samples DEM/soil/climate for, and the
            // marker the B2G/B2B map plots.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Location data the rule-based recommendation combines with the
            // YOLO result. Declared by the user when the ML service cannot
            // sample SoilGrids / NASA POWER for this point, so a farmer without
            // coordinates still gets a defensible crop list.
            $table->string('soil_texture')->nullable();
            $table->unsignedSmallInteger('rainfall_mm')->nullable();

            $table->string('crs')->nullable();
            $table->json('geometry')->nullable();
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->date('fire_event_date')->nullable();

            // The crop the farmer picked from the recommendations; drives the
            // planting guide shown on the land page.
            $table->string('selected_crop_id')->nullable();
            $table->timestamp('selected_crop_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['objective', 'status']);
            $table->index('data_status');
            $table->index(['latitude', 'longitude']);
            $table->index('selected_crop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lands');
    }
};
