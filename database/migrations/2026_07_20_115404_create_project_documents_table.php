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
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 50);
            $table->string('source_type', 50);
            $table->string('file_path', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('link_label')->nullable();
            $table->unsignedBigInteger('change_request_id')->nullable();
            $table->date('document_date')->nullable();
            $table->text('tags')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('visible_to_client')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('change_request_id')->references('id')->on('change_requests')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
