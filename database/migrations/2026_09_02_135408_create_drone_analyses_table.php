<?php

use App\Enums\AnalysisStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('drone_analyses', function (Blueprint $table) {
            $table->id();

            // Ownership. user_id is nullable because the MVP upload endpoint is
            // not yet behind authentication; project_id has no FK because a
            // projects table does not exist yet (reserved for future linking).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable()->index();

            // Uploaded image.
            $table->string('image_path');
            $table->string('image_filename');
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // Optional geospatial metadata.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('area_name')->nullable();

            // Optional survey metadata.
            $table->date('survey_date')->nullable();
            $table->string('drone_model')->nullable();
            $table->string('flight_altitude')->nullable();
            $table->string('image_type')->nullable();

            // Processing lifecycle.
            $table->string('analysis_status')->default(AnalysisStatus::Pending->value)->index();
            $table->timestamp('analysis_started_at')->nullable();
            $table->timestamp('analysis_completed_at')->nullable();
            $table->text('analysis_error')->nullable();

            // Denormalized headline metrics (source of truth stays in analysis_result).
            $table->unsignedTinyInteger('land_health_score')->nullable();
            $table->decimal('vegetation_percentage', 5, 2)->nullable();
            $table->decimal('bare_soil_percentage', 5, 2)->nullable();
            $table->decimal('water_percentage', 5, 2)->nullable();
            $table->decimal('degraded_percentage', 5, 2)->nullable();
            $table->string('restoration_potential')->nullable();

            // Model provenance.
            $table->string('ai_model')->nullable();
            $table->string('ai_model_version')->nullable();

            // Flexible AI output.
            $table->json('analysis_result')->nullable();
            $table->json('recommendation_result')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drone_analyses');
    }
};
