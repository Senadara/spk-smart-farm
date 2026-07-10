<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpkReportUploadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_task_report_photo_is_uploaded_as_a_file(): void
    {
        Storage::fake('public');

        $user = DB::table('user')->where('role', 'pjawab')->first();
        $this->assertNotNull($user, 'Data user penanggung jawab dibutuhkan untuk pengujian laporan.');

        $taskId = (string) Str::uuid();
        DB::table('spk_action_tasks')->insert([
            'id' => $taskId,
            'assigned_by' => $user->id,
            'assigned_to' => null,
            'title' => 'Uji upload laporan',
            'description' => 'Tugas sementara untuk feature test.',
            'priority' => 'medium',
            'status' => 'todo',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $response = $this->withSession([
            'api_token' => 'testing-token',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ])->post("/penugasan/{$taskId}/report", [
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
            'done',
            DB::table('spk_action_tasks')->where('id', $taskId)->value('status')
        );
    }
}
