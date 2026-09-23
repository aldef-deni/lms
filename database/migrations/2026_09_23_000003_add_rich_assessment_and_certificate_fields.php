<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('media_type')->default('text')->after('type');
            $table->string('media_path')->nullable()->after('media_type');
            $table->text('media_url')->nullable()->after('media_path');
        });

        DB::table('questions')->whereIn('type', ['true_false', 'short_answer'])->update([
            'type' => DB::raw("CASE WHEN type = 'true_false' THEN 'multiple_choice' ELSE 'essay' END"),
        ]);

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->json('question_scores')->nullable()->after('answers');
            $table->string('grading_status')->default('graded')->after('passed')->index();
            $table->text('feedback')->nullable()->after('grading_status');
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('background_image')->nullable()->after('accent');
            $table->string('signature_mode')->default('upload')->after('signatory');
            $table->string('signature_image')->nullable()->after('signature_mode');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', fn (Blueprint $table) => $table->dropColumn(['background_image', 'signature_mode', 'signature_image']));
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropColumn(['question_scores', 'grading_status', 'feedback']));
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn(['media_type', 'media_path', 'media_url']));
    }
};
