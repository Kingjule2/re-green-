<?php

use App\Enums\AnalysisStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One uploaded land photo and the analysis derived from it.
     *
     * Repeated analyses of the same land form the monitoring time series:
     * `captured_at` orders them, and the headline metrics are compared between
     * neighbouring analyses to show vegetation progress.
     */
    public function up(): void
    {
        Schema::create('land_analyses', function (Blueprint $table): void {
            $table->id();

            // Ownership. user_id is nullable so imported/legacy rows survive a
            // deleted account; land_id cascades because an analysis without its
            // land has no meaning.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('land_id')->nullable()->constrained()->cascadeOnDelete();

            // Uploaded photo. Nullable because a row can also describe an
            // imported or demo period that has no photo stored here; the UI
            // then renders the detected cover grid instead of the image.
            $table->string('image_path')->nullable();
            $table->string('image_filename')->nullable();
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // Where and when the photo was taken (the monitoring period key).
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->string('capture_source')->nullable();
            $table->text('notes')->nullable();

            // Vertical metadata: drone photogrammetry input kept for the
            // survey workflow that already existed.
            $table->string('vertical_status')->default('incomplete');
            $table->json('vertical_metadata')->nullable();

            // Processing lifecycle.
            $table->string('analysis_status')->default(AnalysisStatus::Pending->value)->index();
            $table->timestamp('analysis_started_at')->nullable();
            $table->timestamp('analysis_completed_at')->nullable();
            $table->text('analysis_error')->nullable();

            // Denormalized headline metrics (source of truth stays in analysis_result).
            $table->unsignedTinyInteger('land_health_score')->nullable();
            $table->string('burn_severity')->nullable();
            $table->unsignedTinyInteger('burn_severity_score')->nullable();
            $table->decimal('burn_severity_confidence', 4, 3)->nullable();
            $table->decimal('vegetation_percentage', 5, 2)->nullable();
            $table->decimal('bare_soil_percentage', 5, 2)->nullable();
            $table->decimal('charred_percentage', 5, 2)->nullable();
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

            $table->index(['land_id', 'captured_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('land_analyses');
    }
};
