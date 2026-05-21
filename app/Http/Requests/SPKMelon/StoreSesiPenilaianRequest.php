<?php

namespace App\Http\Requests\SPKMelon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\SPKMelon\SpkMelonKriteria;
use App\Services\SPKMelon\FuzzyAhpService;

class StoreSesiPenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autentikasi & RBAC utama ditangani middleware (auth.api + role:...).
        // Di sini kita cek tambahan: hanya role inventor/admin yang boleh membuat sesi.
        $user = session('user');
        return $user && in_array($user['role'] ?? '', ['inventor', 'admin']);
    }

    /**
     * Aturan validasi field-level.
     * Naming field sesuai database-schema.md.
     */
    public function rules(): array
    {
        return [
            'namaSesi' => ['required', 'string', 'max:150'],
            'tipeEvaluasi' => ['required', Rule::in(['produktivitas', 'kualitas'])],
            'periodeMulai' => ['required', 'date'],
            'periodeSelesai' => ['required', 'date', 'after_or_equal:periodeMulai'],
            'catatanSesi' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'namaSesi.required' => 'Nama sesi harus diisi.',
            'namaSesi.max' => 'Nama sesi maksimal 150 karakter.',
            'tipeEvaluasi.required' => 'Tipe evaluasi harus dipilih.',
            'tipeEvaluasi.in' => 'Tipe evaluasi tidak valid.',
            'periodeMulai.required' => 'Tanggal mulai periode harus diisi.',
            'periodeMulai.date' => 'Format tanggal mulai tidak valid.',
            'periodeSelesai.required' => 'Tanggal selesai periode harus diisi.',
            'periodeSelesai.date' => 'Format tanggal selesai tidak valid.',
            'periodeSelesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ];
    }

    /**
     * Custom validation: pastikan jumlah kriteria dalam range valid zona standar AHP (5-9).
     * Validasi dijalankan SETELAH validasi rules() di atas.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tipe = $this->input('tipeEvaluasi');

            if (!$tipe) {
                return; // skip jika tipe belum terisi
            }

            $jumlahKriteria = SpkMelonKriteria::where(function ($q) use ($tipe) {
                $q->where('kategori', $tipe)
                    ->orWhere('kategori', 'lingkungan');
            })->count();

            $namaTipe = $tipe === 'produktivitas' ? 'Produktivitas' : 'Kualitas';
            $min = FuzzyAhpService::MIN_KRITERIA;
            $max = FuzzyAhpService::MAX_KRITERIA;

            if ($jumlahKriteria < $min) {
                $validator->errors()->add(
                    'tipeEvaluasi',
                    "Tidak dapat membuat sesi: kriteria untuk evaluasi {$namaTipe} belum cukup. "
                    . "Sistem membutuhkan minimum {$min} kriteria (zona standar AHP), saat ini {$jumlahKriteria}. "
                    . "Tambahkan kriteria di menu Kriteria SPK terlebih dahulu."
                );
            }

            if ($jumlahKriteria > $max) {
                $validator->errors()->add(
                    'tipeEvaluasi',
                    "Tidak dapat membuat sesi: kriteria untuk evaluasi {$namaTipe} melebihi batas. "
                    . "Sistem hanya mengizinkan maksimum {$max} kriteria (zona standar AHP), saat ini {$jumlahKriteria}. "
                    . "Kurangi/hapus kriteria di menu Kriteria SPK terlebih dahulu."
                );
            }
        });
    }
}

