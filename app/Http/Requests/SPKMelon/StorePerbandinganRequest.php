<?php

namespace App\Http\Requests\SPKMelon;

use App\Models\SPKMelon\SpkMelonKriteria;
use App\Models\SPKMelon\SpkMelonSesiPenilaian;
use App\Services\SPKMelon\TfnHelper;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request untuk validasi matriks perbandingan berpasangan (SPK-03).
 *
 * Format input dari form:
 * perbandingan = [
 *   "kriteria1Id::kriteria2Id" => "3",       // string dari form select
 *   "kriteria1Id::kriteria3Id" => "0.333333",
 *   ...
 * ]
 */
class StorePerbandinganRequest extends FormRequest
{
    /**
     * Authorization: role inventor atau admin, dan sesi masih editable.
     * Menggunakan session('user') sesuai auth pattern codebase (bukan auth()->user()).
     */
    public function authorize(): bool
    {
        $user = session('user');
        if (! $user || ! in_array($user['role'] ?? '', ['inventor', 'admin'], true)) {
            return false;
        }

        $sesiId = $this->route('id');
        $sesi = SpkMelonSesiPenilaian::find($sesiId);
        if (! $sesi) {
            return false;
        }

        return $sesi->isPerbandinganEditable();
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'perbandingan'   => ['required', 'array'],
            'perbandingan.*' => ['required', 'numeric', 'in:' . implode(',', TfnHelper::VALID_SAATY_VALUES)],
        ];
    }

    public function messages(): array
    {
        return [
            'perbandingan.required'   => 'Matriks perbandingan tidak boleh kosong.',
            'perbandingan.*.required' => 'Seluruh pasangan kriteria di segitiga atas wajib diisi.',
            'perbandingan.*.numeric'  => 'Nilai perbandingan harus berupa angka.',
            'perbandingan.*.in'       => 'Nilai perbandingan harus salah satu nilai Skala Saaty (1-9 atau 1/2-1/9).',
        ];
    }

    /**
     * Validasi tambahan: kelengkapan upper triangle.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sesiId = $this->route('id');
            $sesi = SpkMelonSesiPenilaian::find($sesiId);
            if (! $sesi) {
                return;
            }

            $kriteriaList = SpkMelonKriteria::whereIn('kategori', [$sesi->tipeEvaluasi, 'lingkungan'])
                ->orderBy('kode')
                ->get();

            if ($kriteriaList->count() < 2) {
                $validator->errors()->add(
                    'perbandingan',
                    'Sesi ini memiliki kurang dari 2 kriteria. Tambah kriteria di SPK-01 terlebih dahulu.'
                );
                return;
            }

            $expected = [];
            foreach ($kriteriaList as $i => $kRow) {
                foreach ($kriteriaList as $j => $kCol) {
                    if ($i < $j) {
                        $expected[] = $kRow->id . '::' . $kCol->id;
                    }
                }
            }

            $submitted = array_keys($this->input('perbandingan', []));
            $missing = array_diff($expected, $submitted);

            if (! empty($missing)) {
                $validator->errors()->add(
                    'perbandingan',
                    'Beberapa pasangan kriteria belum diisi (jumlah: ' . count($missing) . '). Semua sel di segitiga atas matriks wajib diisi.'
                );
            }
        });
    }
}
