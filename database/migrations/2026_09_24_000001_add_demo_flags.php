<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index()->after('active');
        });

        foreach (['categories', 'certificate_templates', 'courses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_demo')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['courses', 'certificate_templates', 'categories', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }
    }
};
