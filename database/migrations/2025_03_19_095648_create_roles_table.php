<?php

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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id')->nullable(); // Which org this role belongs to
            $table->unsignedBigInteger('template_id');                // Which template this role uses
            $table->boolean('overrides_system')->default(false);      // If it overrides a system role
            $table->unsignedBigInteger('system_role_id')->nullable(); // Which system role is overridden (if applicable)
            $table->timestamps();

            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('cascade');

            $table->foreign('template_id')
                ->references('id')
                ->on('role_templates')
                ->onDelete('cascade');

            $table->foreign('system_role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('set null');

            $table->unique(['template_id', 'organisation_id'], 'role_template_org_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
