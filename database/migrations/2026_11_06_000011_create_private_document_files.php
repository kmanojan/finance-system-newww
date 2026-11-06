<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_document_files', function (Blueprint $table) {
            $table->string('path', 512)->primary();
            $table->longText('contents');
            $table->unsignedBigInteger('size');
            $table->string('mime_type');
            $table->unsignedBigInteger('modified_at');
        });
    }

    public function down(): void
    {
        // Retain uploaded documents if application code is rolled back.
    }
};
