<?php

namespace Tests\Feature\Spk;

use Tests\TestCase;
use App\Models\SpkActionTask;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SpkTaskControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure session is in-memory and disable middleware (auth, csrf) for these feature tests
        $this->app['config']->set('session.driver', 'array');
        $this->app['config']->set('app.debug', true);
        $this->withoutMiddleware();
        $this->withSession(['user' => ['id' => 1, 'role' => 'pjawab']]);

        // Ensure minimal table exists for SpkActionTask so tests don't depend on full migrations
        if (!Schema::hasTable('spk_action_tasks')) {
            Schema::create('spk_action_tasks', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('spk_fuzzy_log_id')->nullable();
                $table->string('unit_budidaya_id')->nullable();
                $table->string('assigned_to')->nullable();
                $table->integer('assigned_by')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('priority')->nullable();
                $table->string('status')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();
            });
        }

        if (!Schema::hasTable('user')) {
            Schema::create('user', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jenisBudidaya')) {
            Schema::create('jenisBudidaya', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->nullable();
                $table->boolean('isDeleted')->default(false);
            });
        }

        if (!Schema::hasTable('unitBudidaya')) {
            Schema::create('unitBudidaya', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->nullable();
                $table->unsignedBigInteger('jenisBudidayaId')->nullable();
                $table->boolean('status')->default(1);
                $table->boolean('isDeleted')->default(false);
            });
        }

        if (!Schema::hasTable('spk_fuzzy_logs')) {
            Schema::create('spk_fuzzy_logs', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->text('narrative')->nullable();
                $table->unsignedBigInteger('unit_budidaya_id')->nullable();
                $table->timestamp('createdAt')->nullable();
            });
        }
    }

    /**
     * Fitur: Penugasan SPK - Halaman Kanban & Penyimpanan Tugas
     *
     *   Sebagai pengguna dengan peran `pjawab`
     *   Ketika saya membuka halaman penugasan
     *   Maka saya melihat halaman yang memuat kanban dan daftar riwayat tugas
     *
     * Skenario: Menyimpan tugas baru
     *   Diberikan saya mengirim form tugas yang valid
     *   Ketika saya POST ke rute penyimpanan tugas
     *   Maka tugas baru tersimpan di database dan diarahkan kembali ke daftar
     */
    public function test_halaman_penugasan_muncul_dan_menampilkan_kanban()
    {
        $this->withSession(['user' => ['id' => 1, 'role' => 'pjawab']]);

        SpkActionTask::create([
            'title' => 'Tugas contoh',
            'description' => 'Deskripsi tugas contoh',
            'priority' => 'medium',
            'status' => 'todo',
            'assigned_by' => 1,
        ]);

        $response = $this->get(route('spk.tasks.index'));

        $response->assertStatus(200);
        $response->assertViewHas('kanban');
    }

    public function test_menyimpan_tugas_baru_menyimpan_di_database()
    {
        $this->withSession(['user' => ['id' => 1, 'role' => 'pjawab']]);

        $payload = [
            'title' => 'Tugas dari test',
            'description' => 'Isi tugas',
            'priority' => 'high',
            'assigned_to' => null,
        ];

        $response = $this->post(route('spk.tasks.store'), $payload);

        $response->assertRedirect(route('spk.tasks.index'));

        $this->assertDatabaseHas('spk_action_tasks', [
            'title' => 'Tugas dari test',
            'priority' => 'high',
        ]);
    }
}
