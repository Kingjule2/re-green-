<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carbon-credit pre-feasibility for one land: the estimated sequestration,
     * the eligibility checklist, and the application the farmer forwarded to a
     * certification partner. One row per land, refreshed whenever a new
     * analysis changes the estimate.
     */
    public function up(): void
    {
        Schema::create('carbon_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('land_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('calculated_at');

            // Inputs of the estimate, kept so a report can explain the number.
            $table->decimal('area_ha', 12, 4)->nullable();
            $table->decimal('vegetation_cover_pct', 5, 2)->nullable();
            $table->string('vegetation_class')->nullable();
            $table->string('basis')->nullable();

            // Estimate (tCO2e per year, and the 5-year projection).
            $table->decimal('sequestration_tco2e_per_year', 10, 2)->nullable();
            $table->decimal('sequestration_5yr_tco2e', 10, 2)->nullable();

            // eligible | not_yet | ineligible, with the checklist behind it.
            $table->string('eligibility_status')->default('not_yet');
            $table->json('eligibility_checklist')->nullable();
            $table->json('method')->nullable();

            // Application forwarded to a certification partner.
            $table->string('submission_status')->default('not_submitted');
            $table->string('partner')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->decimal('offered_area_ha', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index('eligibility_status');
            $table->index('submission_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carbon_assessments');
    }
};
