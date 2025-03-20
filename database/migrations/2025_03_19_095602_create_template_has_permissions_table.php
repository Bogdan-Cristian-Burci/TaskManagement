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
        Schema::create('template_has_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_template_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->unique(['role_template_id', 'permission_id']);

            $table->foreign('role_template_id')
                ->references('id')
                ->on('role_templates')
                ->onDelete('cascade');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_has_permissions');
    }
};
