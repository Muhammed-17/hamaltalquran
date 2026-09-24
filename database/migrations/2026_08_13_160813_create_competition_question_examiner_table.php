<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competition_question_examiner')) {
            Schema::create('competition_question_examiner', function (Blueprint $table) {
                $table->id();

                $table->foreignId('competition_question_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('competition_examiner_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->timestamps();

                $table->unique(
                    ['competition_question_id', 'competition_examiner_id'],
                    'cp_question_examiner_unique'
                );
            });
        }

        if (Schema::hasColumn('competition_questions', 'competition_examiner_id')) {
            DB::table('competition_questions')
                ->whereNotNull('competition_examiner_id')
                ->orderBy('id')
                ->select('id', 'competition_examiner_id')
                ->chunkById(200, function ($questions) {
                    $now = now();

                    $rows = $questions->map(fn($q) => [
                        'competition_question_id' => $q->id,
                        'competition_examiner_id' => $q->competition_examiner_id,
                        'created_at'              => $now,
                        'updated_at'              => $now,
                    ])->all();

                    DB::table('competition_question_examiner')->insertOrIgnore($rows);
                });

            // 1) أضف الـ Unique الجديد أولاً (يبدأ بنفس عمود competition_level_id
            //    فهيفضل يدعم الـ FK بتاعه بدل القديم)
            if (! collect(DB::select("SHOW INDEX FROM competition_questions WHERE Key_name = ?", ['cp_questions_level_name_unique']))->isNotEmpty()) {
                Schema::table('competition_questions', function (Blueprint $table) {
                    $table->unique(
                        ['competition_level_id', 'name'],
                        'cp_questions_level_name_unique'
                    );
                });
            }

            // 2) دلوقتي آمن نحذف الـ Unique القديم (مش هيتعارض مع FK)
            if (collect(DB::select("SHOW INDEX FROM competition_questions WHERE Key_name = 'cp_questions_level_examiner_name_unique'"))->isNotEmpty()) {
                Schema::table('competition_questions', function (Blueprint $table) {
                    $table->dropUnique('cp_questions_level_examiner_name_unique');
                });
            }

            // 3) احذف الـ foreign key الأول (لازم قبل أي محاولة لحذف الـ index/العمود
            //    لأن MySQL بيرفض حذف index مرتبط بـ FK constraint - error 1553)
            if (collect(DB::select("SHOW INDEX FROM competition_questions WHERE Key_name = 'competition_questions_competition_examiner_id_foreign'"))->isNotEmpty()) {
                Schema::table('competition_questions', function (Blueprint $table) {
                    $table->dropForeign('competition_questions_competition_examiner_id_foreign');
                });
            }

            // 4) دلوقتي احذف العمود نفسه (مفيش FK ولا index بيعتمد عليه)
            Schema::table('competition_questions', function (Blueprint $table) {
                $table->dropColumn('competition_examiner_id');
            });
        }
    }
    public function down(): void
    {
        if (! Schema::hasColumn('competition_questions', 'competition_examiner_id')) {
            // 1) Add the column + its FK first — don't touch cp_questions_level_name_unique yet
            Schema::table('competition_questions', function (Blueprint $table) {
                $table->foreignId('competition_examiner_id')
                    ->nullable()
                    ->after('competition_level_id')
                    ->constrained()
                    ->nullOnDelete();
            });

            // Restore approximate data
            DB::table('competition_question_examiner')
                ->select('competition_question_id', DB::raw('MIN(competition_examiner_id) as examiner_id'))
                ->groupBy('competition_question_id')
                ->orderBy('competition_question_id')
                ->chunk(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('competition_questions')
                            ->where('id', $row->competition_question_id)
                            ->update(['competition_examiner_id' => $row->examiner_id]);
                    }
                });

            // 2) Create the old composite unique — it also starts with competition_level_id,
            //    so it now supports that FK
            Schema::table('competition_questions', function (Blueprint $table) {
                $table->unique(
                    ['competition_level_id', 'competition_examiner_id', 'name'],
                    'cp_questions_level_examiner_name_unique'
                );
            });

            // 3) Now it's safe to drop the newer unique — a replacement index exists
            Schema::table('competition_questions', function (Blueprint $table) {
                $table->dropUnique('cp_questions_level_name_unique');
            });
        }

        Schema::dropIfExists('competition_question_examiner');
    }
};
