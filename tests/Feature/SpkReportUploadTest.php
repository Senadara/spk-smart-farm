<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpkReportUploadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_task_report_photo_is_uploaded_as_a_file_and_waits_for_owner_review(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        config(['services.node_notifications.base_url' => 'http://node.test']);

        [$owner, $petugas] = $this->demoOwnerAndPetugas();

        $taskId = (string) Str::uuid();
        DB::table('spk_action_tasks')->insert([
            'id' => $taskId,
            'assigned_by' => $owner->id,
            'assigned_to' => $petugas->id,
            'title' => 'Uji upload laporan',
            'description' => 'Tugas sementara untuk feature test.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'review_status' => 'none',
            'system_validation_status' => 'not_checked',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $response = $this->withSession([
            '_token' => 'spk-report-token',
            'api_token' => 'testing-token',
            'user' => [
                'id' => $petugas->id,
                'name' => $petugas->name,
                'email' => $petugas->email,
                'role' => $petugas->role,
            ],
        ])->post("/penugasan/{$taskId}/report", [
            '_token' => 'spk-report-token',
            'description' => 'Pekerjaan telah selesai dan bukti foto terlampir.',
            'status_update' => 'done',
            'photo' => UploadedFile::fake()->createWithContent(
                'bukti.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=')
            ),
        ]);

        $response->assertRedirect(route('spk.tasks.index'));

        $report = DB::table('spk_action_reports')->where('task_id', $taskId)->first();
        $this->assertNotNull($report);
        $this->assertStringStartsWith("spk-reports/{$taskId}/", $report->photo);
        Storage::disk('public')->assertExists($report->photo);

        $this->assertSame(
            'in_progress',
            DB::table('spk_action_tasks')->where('id', $taskId)->value('status')
        );
        $this->assertSame('pending', DB::table('spk_action_tasks')->where('id', $taskId)->value('review_status'));
        $this->assertNotNull(DB::table('spk_action_tasks')->where('id', $taskId)->value('completion_requested_at'));
        $this->assertNull(DB::table('spk_action_tasks')->where('id', $taskId)->value('completed_at'));

        Http::assertSent(fn ($request) => $request->url() === 'http://node.test/internal/notifications/mobile'
            && $request['target']['userId'] === $owner->id
            && $request['data']['notificationType'] === 'SPK_TASK_REVIEW_REQUESTED');
    }

    public function test_owner_can_approve_pending_task_review(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        config(['services.node_notifications.base_url' => 'http://node.test']);
        [$owner, $petugas] = $this->demoOwnerAndPetugas();
        $taskId = $this->insertPendingReviewTask($owner->id, $petugas->id);

        $response = $this->withOwnerSession($owner)->patch("/penugasan/{$taskId}/review", [
            '_token' => 'spk-review-token',
            'action' => 'approve',
            'review_note' => 'Bukti pengerjaan diterima.',
        ]);

        $response->assertRedirect();
        $task = DB::table('spk_action_tasks')->where('id', $taskId)->first();

        $this->assertSame('done', $task->status);
        $this->assertSame('approved', $task->review_status);
        $this->assertSame($owner->id, $task->reviewed_by);
        $this->assertNotNull($task->completed_at);

        $reviewReport = DB::table('spk_action_reports')->where('task_id', $taskId)->first();
        $this->assertNotNull($reviewReport);
        $this->assertSame($owner->id, $reviewReport->reported_by);
        $this->assertSame('done', $reviewReport->status_update);
        $this->assertStringContainsString('Validasi pjawab', $reviewReport->description);

        Http::assertSent(fn ($request) => $request['target']['userId'] === $petugas->id
            && $request['data']['notificationType'] === 'SPK_TASK_APPROVED');
    }

    public function test_owner_can_reject_pending_task_review(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        config(['services.node_notifications.base_url' => 'http://node.test']);
        [$owner, $petugas] = $this->demoOwnerAndPetugas();
        $taskId = $this->insertPendingReviewTask($owner->id, $petugas->id);

        $response = $this->withOwnerSession($owner)->patch("/penugasan/{$taskId}/review", [
            '_token' => 'spk-review-token',
            'action' => 'reject',
            'review_note' => 'Bukti foto belum jelas, cek ulang kondisi kandang.',
        ]);

        $response->assertRedirect();
        $task = DB::table('spk_action_tasks')->where('id', $taskId)->first();

        $this->assertSame('in_progress', $task->status);
        $this->assertSame('rejected', $task->review_status);
        $this->assertSame($owner->id, $task->reviewed_by);
        $this->assertNull($task->completed_at);

        $reviewReport = DB::table('spk_action_reports')->where('task_id', $taskId)->first();
        $this->assertNotNull($reviewReport);
        $this->assertSame($owner->id, $reviewReport->reported_by);
        $this->assertSame('in_progress', $reviewReport->status_update);
        $this->assertStringContainsString('Revisi pjawab', $reviewReport->description);
        $this->assertStringContainsString('Bukti foto belum jelas', $reviewReport->description);

        Http::assertSent(fn ($request) => $request['target']['userId'] === $petugas->id
            && $request['data']['notificationType'] === 'SPK_TASK_REVISION_REQUESTED');
    }

    public function test_petugas_cannot_create_spk_task(): void
    {
        [, $petugas] = $this->demoOwnerAndPetugas();

        $response = $this->withSession([
            '_token' => 'spk-task-token',
            'api_token' => 'testing-token',
            'user' => [
                'id' => $petugas->id,
                'name' => $petugas->name,
                'email' => $petugas->email,
                'role' => $petugas->role,
            ],
        ])->post('/penugasan', [
            '_token' => 'spk-task-token',
            'title' => 'Tidak boleh dibuat petugas',
            'priority' => 'medium',
        ]);

        $response->assertForbidden();
    }

    public function test_owner_creating_task_sends_mobile_notification_to_petugas(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        config(['services.node_notifications.base_url' => 'http://node.test']);
        [$owner, $petugas] = $this->demoOwnerAndPetugas();
        $title = 'Tugas notifikasi '.Str::uuid();

        $response = $this->withOwnerSession($owner)->post('/penugasan', [
            '_token' => 'spk-review-token',
            'title' => $title,
            'description' => 'Tugas dibuat dari hasil SPK.',
            'priority' => 'urgent',
            'assigned_to' => $petugas->id,
            'due_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('spk.tasks.index'));
        $task = DB::table('spk_action_tasks')->where('title', $title)->first();

        $this->assertNotNull($task);
        $this->assertSame('todo', $task->status);
        $this->assertSame('none', $task->review_status);

        Http::assertSent(fn ($request) => $request['target']['userId'] === $petugas->id
            && $request['data']['notificationType'] === 'SPK_TASK_ASSIGNED'
            && $request['data']['targetScreen'] === 'spk_task_detail');
    }

    public function test_peternakan_dashboard_can_render_for_owner(): void
    {
        [$owner] = $this->demoOwnerAndPetugas();

        $response = $this->withOwnerSession($owner)->get('/peternakan');

        $response->assertOk();
        $response->assertSee('Daftar Kandang');
    }

    public function test_peternakan_recommendations_ignore_units_with_active_tasks(): void
    {
        [$owner, $petugas] = $this->demoOwnerAndPetugas();
        DB::table('spk_action_recommendations')->delete();
        DB::table('spk_action_reports')->delete();
        DB::table('spk_action_tasks')->delete();
        DB::table('spk_fuzzy_logs')->delete();

        $blockedUnitId = (string) Str::uuid();
        $openUnitId = (string) Str::uuid();
        $blockedLogId = (string) Str::uuid();
        $openLogId = (string) Str::uuid();

        foreach ([[$blockedLogId, $blockedUnitId], [$openLogId, $openUnitId]] as [$logId, $unitId]) {
            DB::table('spk_fuzzy_logs')->insert([
                'id' => $logId,
                'unit_budidaya_id' => $unitId,
                'input_json' => json_encode(['hdp' => 50, 'fcr' => 3.1, 'mortalitas' => 2.5]),
                'fuzzified_json' => json_encode([]),
                'rule_result_json' => json_encode([]),
                'status_lingkungan' => 'Baik',
                'status_kesehatan' => 'Buruk',
                'diagnosis_kausalitas' => 'Waspada',
                'output_value' => 45,
                'output_label' => 'Perlu tindakan',
                'narrative' => 'Kondisi membutuhkan tindak lanjut.',
                'recommendation' => 'Buat tugas pengecekan kandang.',
                'createdAt' => now(),
            ]);
        }

        DB::table('spk_action_tasks')->insert([
            'id' => (string) Str::uuid(),
            'assigned_by' => $owner->id,
            'assigned_to' => $petugas->id,
            'title' => 'Tugas aktif untuk kandang bermasalah',
            'description' => 'Task aktif harus menahan rekomendasi baru di kandang yang sama.',
            'priority' => 'urgent',
            'status' => 'todo',
            'review_status' => 'none',
            'system_validation_status' => 'not_checked',
            'unit_budidaya_id' => $blockedUnitId,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $controller = app(\App\Http\Controllers\Peternakan\PeternakanController::class);
        $method = new \ReflectionMethod($controller, 'buildDailySpkSummary');
        $method->setAccessible(true);

        $summary = $method->invoke($controller, null, [], ['isReady' => true]);

        $this->assertSame(1, $summary['needs_action_count']);
        $this->assertCount(1, $summary['action_candidates']);
        $this->assertSame($openLogId, $summary['action_candidates'][0]['spk_id']);
        $this->assertDatabaseHas('spk_action_recommendations', [
            'spk_fuzzy_log_id' => $openLogId,
            'status' => 'open',
        ]);
    }

    public function test_spk_recommendation_is_assigned_after_task_is_created(): void
    {
        [$owner, $petugas] = $this->demoOwnerAndPetugas();
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        config(['services.node_notifications.base_url' => 'http://node.test']);
        DB::table('spk_action_recommendations')->delete();
        DB::table('spk_action_reports')->delete();
        DB::table('spk_action_tasks')->delete();
        DB::table('spk_fuzzy_logs')->delete();

        $unitId = (string) Str::uuid();
        $logId = (string) Str::uuid();

        DB::table('spk_fuzzy_logs')->insert([
            'id' => $logId,
            'unit_budidaya_id' => $unitId,
            'input_json' => json_encode(['hdp' => 48, 'fcr' => 3.2, 'mortalitas' => 2.8]),
            'fuzzified_json' => json_encode([]),
            'rule_result_json' => json_encode([]),
            'status_lingkungan' => 'Baik',
            'status_kesehatan' => 'Buruk',
            'diagnosis_kausalitas' => 'Waspada',
            'output_value' => 44,
            'output_label' => 'Perlu tindakan',
            'narrative' => 'Kondisi membutuhkan tindak lanjut.',
            'recommendation' => 'Buat tugas pengecekan kandang.',
            'createdAt' => now(),
        ]);

        $controller = app(\App\Http\Controllers\Peternakan\PeternakanController::class);
        $method = new \ReflectionMethod($controller, 'buildDailySpkSummary');
        $method->setAccessible(true);

        $summary = $method->invoke($controller, null, [], ['isReady' => true]);
        $this->assertSame(1, $summary['needs_action_count']);

        $recommendationId = $summary['action_candidates'][0]['recommendation_id'];

        $response = $this->withOwnerSession($owner)->post('/penugasan', [
            '_token' => 'spk-review-token',
            'recommendation_id' => $recommendationId,
            'spk_fuzzy_log_id' => $logId,
            'unit_budidaya_id' => $unitId,
            'title' => 'Tindak lanjut rekomendasi SPK',
            'description' => 'Konversi rekomendasi menjadi tugas petugas.',
            'priority' => 'urgent',
            'assigned_to' => $petugas->id,
            'due_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('spk.tasks.index'));
        $task = DB::table('spk_action_tasks')->where('spk_fuzzy_log_id', $logId)->first();
        $this->assertNotNull($task);

        $recommendation = DB::table('spk_action_recommendations')->where('id', $recommendationId)->first();
        $this->assertSame('assigned', $recommendation->status);
        $this->assertSame($task->id, $recommendation->assigned_task_id);
        $this->assertNotNull($recommendation->assigned_at);

        $summaryAfterTask = $method->invoke($controller, null, [], ['isReady' => true]);
        $this->assertSame(0, $summaryAfterTask['needs_action_count']);
        $this->assertCount(0, $summaryAfterTask['action_candidates']);
    }

    public function test_task_creation_resolves_spk_source_from_recommendation_id_only(): void
    {
        [$owner, $petugas] = $this->demoOwnerAndPetugas();
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        config(['services.node_notifications.base_url' => 'http://node.test']);
        DB::table('spk_action_recommendations')->delete();
        DB::table('spk_action_reports')->delete();
        DB::table('spk_action_tasks')->delete();
        DB::table('spk_fuzzy_logs')->delete();

        $unitId = (string) Str::uuid();
        $logId = (string) Str::uuid();

        DB::table('spk_fuzzy_logs')->insert([
            'id' => $logId,
            'unit_budidaya_id' => $unitId,
            'input_json' => json_encode(['hdp' => 45, 'fcr' => 3.3, 'mortalitas' => 3]),
            'fuzzified_json' => json_encode([]),
            'rule_result_json' => json_encode([]),
            'status_lingkungan' => 'Baik',
            'status_kesehatan' => 'Buruk',
            'diagnosis_kausalitas' => 'Waspada',
            'output_value' => 42,
            'output_label' => 'Perlu tindakan',
            'narrative' => 'Kondisi membutuhkan tindak lanjut.',
            'recommendation' => 'Buat tugas pengecekan kandang.',
            'createdAt' => now(),
        ]);

        app(\App\Services\Spk\SpkActionRecommendationService::class)
            ->syncForLog(\App\Models\SpkFuzzyLog::findOrFail($logId));

        $recommendationId = DB::table('spk_action_recommendations')
            ->where('spk_fuzzy_log_id', $logId)
            ->value('id');

        $response = $this->withOwnerSession($owner)->post('/penugasan', [
            '_token' => 'spk-review-token',
            'recommendation_id' => $recommendationId,
            'title' => 'Tindak lanjut rekomendasi tanpa field sumber',
            'description' => 'Form hanya membawa recommendation_id.',
            'priority' => 'urgent',
            'assigned_to' => $petugas->id,
            'due_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('spk.tasks.index'));

        $task = DB::table('spk_action_tasks')
            ->where('title', 'Tindak lanjut rekomendasi tanpa field sumber')
            ->first();

        $this->assertNotNull($task);
        $this->assertSame($logId, $task->spk_fuzzy_log_id);
        $this->assertSame($unitId, $task->unit_budidaya_id);

        $this->assertDatabaseHas('spk_action_recommendations', [
            'id' => $recommendationId,
            'status' => 'assigned',
            'assigned_task_id' => $task->id,
        ]);
    }

    public function test_open_recommendation_is_locked_by_active_task_for_same_unit(): void
    {
        [$owner, $petugas] = $this->demoOwnerAndPetugas();
        DB::table('spk_action_recommendations')->delete();
        DB::table('spk_action_reports')->delete();
        DB::table('spk_action_tasks')->delete();
        DB::table('spk_fuzzy_logs')->delete();

        $unitId = (string) Str::uuid();
        $logId = (string) Str::uuid();
        $taskId = (string) Str::uuid();

        DB::table('spk_fuzzy_logs')->insert([
            'id' => $logId,
            'unit_budidaya_id' => $unitId,
            'input_json' => json_encode(['hdp' => 46, 'fcr' => 3.1, 'mortalitas' => 2]),
            'fuzzified_json' => json_encode([]),
            'rule_result_json' => json_encode([]),
            'status_lingkungan' => 'Baik',
            'status_kesehatan' => 'Buruk',
            'diagnosis_kausalitas' => 'Waspada',
            'output_value' => 45,
            'output_label' => 'Perlu tindakan',
            'narrative' => 'Kondisi membutuhkan tindak lanjut.',
            'recommendation' => 'Buat tugas pengecekan kandang.',
            'createdAt' => now(),
        ]);

        app(\App\Services\Spk\SpkActionRecommendationService::class)
            ->syncForLog(\App\Models\SpkFuzzyLog::findOrFail($logId));

        DB::table('spk_action_tasks')->insert([
            'id' => $taskId,
            'assigned_by' => $owner->id,
            'assigned_to' => $petugas->id,
            'title' => 'Tugas aktif untuk kandang yang sama',
            'description' => 'Tugas ini harus mengunci rekomendasi open pada kandang tersebut.',
            'priority' => 'urgent',
            'status' => 'todo',
            'review_status' => 'none',
            'system_validation_status' => 'not_checked',
            'unit_budidaya_id' => $unitId,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $open = app(\App\Services\Spk\SpkActionRecommendationService::class)
            ->openRecommendations(null, 12, [$unitId], false);

        $this->assertCount(0, $open);
        $this->assertDatabaseHas('spk_action_recommendations', [
            'spk_fuzzy_log_id' => $logId,
            'status' => 'assigned',
            'assigned_task_id' => $taskId,
        ]);
    }

    public function test_same_spk_issue_does_not_reopen_recommendation_after_task_is_done(): void
    {
        [$owner, $petugas] = $this->demoOwnerAndPetugas();
        DB::table('spk_action_recommendations')->delete();
        DB::table('spk_action_reports')->delete();
        DB::table('spk_action_tasks')->delete();
        DB::table('spk_fuzzy_logs')->delete();

        $unitId = (string) Str::uuid();
        $handledLogId = (string) Str::uuid();
        $newLogId = (string) Str::uuid();

        DB::table('spk_fuzzy_logs')->insert([
            [
                'id' => $handledLogId,
                'unit_budidaya_id' => $unitId,
                'input_json' => json_encode(['hdp' => 48, 'fcr' => 3.2, 'mortalitas' => 2.8]),
                'fuzzified_json' => json_encode([]),
                'rule_result_json' => json_encode([]),
                'status_lingkungan' => 'Sangat Nyaman',
                'status_kesehatan' => 'Kritis',
                'diagnosis_kausalitas' => 'Indikasi Faktor Non-Lingkungan',
                'output_value' => 30,
                'output_label' => 'Indikasi Faktor Non-Lingkungan',
                'narrative' => 'Kondisi membutuhkan tindak lanjut.',
                'recommendation' => 'Audit pakan dan cek kondisi kandang.',
                'createdAt' => now()->subHour(),
            ],
            [
                'id' => $newLogId,
                'unit_budidaya_id' => $unitId,
                'input_json' => json_encode(['hdp' => 49, 'fcr' => 3.1, 'mortalitas' => 2.7]),
                'fuzzified_json' => json_encode([]),
                'rule_result_json' => json_encode([]),
                'status_lingkungan' => 'Sangat Nyaman',
                'status_kesehatan' => 'Kritis',
                'diagnosis_kausalitas' => 'Indikasi Faktor Non-Lingkungan',
                'output_value' => 30,
                'output_label' => 'Indikasi Faktor Non-Lingkungan',
                'narrative' => 'Kondisi masih membutuhkan tindak lanjut.',
                'recommendation' => 'Audit pakan dan cek kondisi kandang.',
                'createdAt' => now(),
            ],
        ]);

        DB::table('spk_action_tasks')->insert([
            'id' => (string) Str::uuid(),
            'assigned_by' => $owner->id,
            'assigned_to' => $petugas->id,
            'title' => 'Tugas audit pakan yang sudah divalidasi',
            'description' => 'Tugas selesai tidak boleh membuat rekomendasi isu sama muncul berulang.',
            'priority' => 'urgent',
            'status' => 'done',
            'review_status' => 'approved',
            'system_validation_status' => 'manual_override',
            'unit_budidaya_id' => $unitId,
            'spk_fuzzy_log_id' => $handledLogId,
            'completed_at' => now()->subMinutes(30),
            'createdAt' => now()->subHour(),
            'updatedAt' => now()->subMinutes(30),
        ]);

        $controller = app(\App\Http\Controllers\Peternakan\PeternakanController::class);
        $method = new \ReflectionMethod($controller, 'buildDailySpkSummary');
        $method->setAccessible(true);

        $summary = $method->invoke($controller, null, [], ['isReady' => true]);

        $this->assertSame(0, $summary['needs_action_count']);
        $this->assertCount(0, $summary['action_candidates']);
        $this->assertDatabaseMissing('spk_action_recommendations', [
            'spk_fuzzy_log_id' => $newLogId,
            'status' => 'open',
        ]);
        $this->assertTrue(collect($summary['hints'])->contains(
            fn (string $hint) => str_contains($hint, 'tugas sebelumnya selesai')
        ));
    }

    public function test_newer_stable_spk_log_disables_open_recommendation(): void
    {
        $this->demoOwnerAndPetugas();
        DB::table('spk_action_recommendations')->delete();
        DB::table('spk_action_reports')->delete();
        DB::table('spk_action_tasks')->delete();
        DB::table('spk_fuzzy_logs')->delete();

        $unitId = (string) Str::uuid();
        $badLogId = (string) Str::uuid();
        $stableLogId = (string) Str::uuid();

        DB::table('spk_fuzzy_logs')->insert([
            [
                'id' => $badLogId,
                'unit_budidaya_id' => $unitId,
                'input_json' => json_encode(['hdp' => 48, 'fcr' => 3.2, 'mortalitas' => 2.8]),
                'fuzzified_json' => json_encode([]),
                'rule_result_json' => json_encode([]),
                'status_lingkungan' => 'Baik',
                'status_kesehatan' => 'Buruk',
                'diagnosis_kausalitas' => 'Waspada',
                'output_value' => 44,
                'output_label' => 'Perlu tindakan',
                'narrative' => 'Kondisi membutuhkan tindak lanjut.',
                'recommendation' => 'Buat tugas pengecekan kandang.',
                'createdAt' => now()->subMinutes(10),
            ],
            [
                'id' => $stableLogId,
                'unit_budidaya_id' => $unitId,
                'input_json' => json_encode(['hdp' => 92, 'fcr' => 2.1, 'mortalitas' => 0]),
                'fuzzified_json' => json_encode([]),
                'rule_result_json' => json_encode([]),
                'status_lingkungan' => 'Baik',
                'status_kesehatan' => 'Baik',
                'diagnosis_kausalitas' => 'Normal',
                'output_value' => 90,
                'output_label' => 'Normal',
                'narrative' => 'Kondisi stabil.',
                'recommendation' => 'Tidak ada tindakan khusus.',
                'createdAt' => now(),
            ],
        ]);

        app(\App\Services\Spk\SpkActionRecommendationService::class)
            ->syncForLog(\App\Models\SpkFuzzyLog::findOrFail($badLogId));

        $this->assertDatabaseHas('spk_action_recommendations', [
            'spk_fuzzy_log_id' => $badLogId,
            'status' => 'open',
        ]);

        $controller = app(\App\Http\Controllers\Peternakan\PeternakanController::class);
        $method = new \ReflectionMethod($controller, 'buildDailySpkSummary');
        $method->setAccessible(true);

        $summary = $method->invoke($controller, null, [], ['isReady' => true]);

        $this->assertSame(0, $summary['needs_action_count']);
        $this->assertCount(0, $summary['action_candidates']);
        $this->assertDatabaseHas('spk_action_recommendations', [
            'spk_fuzzy_log_id' => $badLogId,
            'status' => 'disabled',
        ]);
    }

    private function demoOwnerAndPetugas(): array
    {
        $owner = DB::table('user')->where('email', 'pjawab@email.com')->first()
            ?: DB::table('user')->where('role', 'pjawab')->first();
        $petugas = DB::table('user')->where('email', 'petugas@email.com')->first()
            ?: DB::table('user')->where('role', 'petugas')->first();

        $this->assertNotNull($owner, 'Data user penanggung jawab dibutuhkan untuk pengujian penugasan.');
        $this->assertNotNull($petugas, 'Data user petugas dibutuhkan untuk pengujian penugasan.');

        if (Schema::hasColumn('user', 'owner_id')) {
            DB::table('user')->where('id', $petugas->id)->update(['owner_id' => $owner->id]);
            $petugas = DB::table('user')->where('id', $petugas->id)->first();
        }

        return [$owner, $petugas];
    }

    private function withOwnerSession(object $owner): self
    {
        return $this->withSession([
            '_token' => 'spk-review-token',
            'api_token' => 'testing-token',
            'user' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => $owner->role,
            ],
        ]);
    }

    private function insertPendingReviewTask(string $ownerId, string $petugasId): string
    {
        $taskId = (string) Str::uuid();

        DB::table('spk_action_tasks')->insert([
            'id' => $taskId,
            'assigned_by' => $ownerId,
            'assigned_to' => $petugasId,
            'title' => 'Tugas menunggu validasi',
            'description' => 'Tugas sementara untuk alur validasi.',
            'priority' => 'high',
            'status' => 'in_progress',
            'completion_requested_at' => now(),
            'review_status' => 'pending',
            'system_validation_status' => 'not_checked',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        return $taskId;
    }
}
