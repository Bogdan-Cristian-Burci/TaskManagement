<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->integer('level')->default(1);
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->boolean('overrides_system')->default(false);
            $table->unsignedBigInteger('system_role_id')->nullable();
            $table->timestamps();

            // A role name must be unique within an organization
            $table->unique(['name', 'organisation_id']);

            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('cascade');

            $table->foreign('template_id')
                ->references('id')
                ->on('role_templates')
                ->onDelete('set null');

            $table->foreign('system_role_id')
                ->references('id')
                ->on('roles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
