<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create permissions table (or modify existing one)
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->string('guard_name')->default('api');
                $table->timestamps();
            });
        }

        // Create role templates table
        Schema::create('role_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('permissions'); // JSON array of permission names
            $table->unsignedBigInteger('organisation_id');
            $table->timestamps();

            // Name should be unique per organization
            $table->unique(['name', 'organisation_id']);

            // Add foreign key
            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('cascade');
        });

        // Modify roles table to include template reference
        Schema::table('roles', function (Blueprint $table) {
            // Add template_id if it doesn't exist
            if (!Schema::hasColumn('roles', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable();
                $table->foreign('template_id')
                    ->references('id')
                    ->on('role_templates')
                    ->onDelete('set null');
            }
        });

        // Modify model_has_permissions to include permission type (grant/deny)
        Schema::table('model_has_permissions', function (Blueprint $table) {
            // Add type column if it doesn't exist
            if (!Schema::hasColumn('model_has_permissions', 'type')) {
                $table->enum('type', ['grant', 'deny'])->default('grant');
            }
        });
    }

    public function down(): void
    {
        // Drop foreign key and column from roles
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });

        // Drop type column from model_has_permissions
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        // Drop role_templates table
        Schema::dropIfExists('role_templates');
    }
};
