<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {

        // itqan
        Schema::table('student_itqan_details', function (Blueprint $table) {
            if (!Schema::hasColumn('student_itqan_details', 'tajweed_matn')) {
                $table->string('tajweed_matn')->nullable()->after('self_evaluation');
            }
        });

        // ibda
        if (!Schema::hasColumn('student_ibda_details', 'supervisor_name')) {
            Schema::table('student_ibda_details', function (Blueprint $table) {
                $table->string('supervisor_name')->nullable()->after('preferred_time');
            });
        }
    }

    public function down()
{
    if (Schema::hasColumn('student_ibda_details', 'supervisor_name')) {
        Schema::table('student_ibda_details', function (Blueprint $table) {
            $table->dropColumn('supervisor_name');
        });
    }

    if (Schema::hasColumn('student_itqan_details', 'tajweed_matn')) {
        Schema::table('student_itqan_details', function (Blueprint $table) {
            $table->dropColumn('tajweed_matn');
        });
    }
}
};
