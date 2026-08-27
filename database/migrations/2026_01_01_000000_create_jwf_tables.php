<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('jwf_form_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('jwf_form_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('jwf_form_templates')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('state', 16)->index();
            $table->timestamps();
            $table->unique(['template_id', 'number']);
        });

        Schema::create('jwf_form_nodes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_version_id')->constrained('jwf_form_versions')->cascadeOnDelete();
            $table->uuid('node_id');
            $table->foreignUuid('parent_id')->nullable()->constrained('jwf_form_nodes')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->unsignedInteger('position');
            $table->string('type', 24)->nullable();
            $table->string('name')->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->json('attributes');
            $table->json('configuration');
            $table->timestamps();
            $table->unique(['form_version_id', 'node_id']);
            $table->index(['form_version_id', 'parent_id', 'position']);
        });

        Schema::create('jwf_input_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_node_id')->constrained('jwf_form_nodes')->cascadeOnDelete();
            $table->uuid('option_id');
            $table->string('value');
            $table->string('label');
            $table->boolean('disabled')->default(false);
            $table->json('attributes');
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['form_node_id', 'option_id']);
        });

        Schema::create('jwf_validation_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('jwf_validation_profile_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('jwf_validation_profiles')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->json('compatible_types');
            $table->json('rules');
            $table->timestamps();
            $table->unique(['profile_id', 'number']);
        });

        Schema::create('jwf_form_node_profile_versions', function (Blueprint $table): void {
            $table->foreignUuid('form_node_id')->constrained('jwf_form_nodes')->cascadeOnDelete();
            $table->foreignUuid('profile_version_id')
                ->constrained('jwf_validation_profile_versions')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->primary(['form_node_id', 'profile_version_id']);
        });

        Schema::create('jwf_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_version_id')->constrained('jwf_form_versions')->restrictOnDelete();
            $table->uuid('form_id');
            $table->timestampTz('submitted_at')->index();
            $table->timestamps();
            $table->index(['form_version_id', 'form_id']);
        });

        Schema::create('jwf_submission_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('jwf_submissions')->cascadeOnDelete();
            $table->uuid('input_id');
            $table->longText('value');
            $table->boolean('sensitive')->default(false);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['submission_id', 'input_id']);
        });

        Schema::create('jwf_file_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_value_id')
                ->nullable()
                ->constrained('jwf_submission_values')
                ->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index('submission_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jwf_file_artifacts');
        Schema::dropIfExists('jwf_submission_values');
        Schema::dropIfExists('jwf_submissions');
        Schema::dropIfExists('jwf_form_node_profile_versions');
        Schema::dropIfExists('jwf_validation_profile_versions');
        Schema::dropIfExists('jwf_validation_profiles');
        Schema::dropIfExists('jwf_input_options');
        Schema::dropIfExists('jwf_form_nodes');
        Schema::dropIfExists('jwf_form_versions');
        Schema::dropIfExists('jwf_form_templates');
    }
};
