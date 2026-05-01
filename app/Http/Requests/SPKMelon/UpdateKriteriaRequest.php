<?php

namespace App\Http\Requests\SPKMelon;

use App\Models\SPKMelon\SpkMelonKriteria;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKriteriaRequest extends FormRequest
{
    /**
     * Hanya inventor dan admin yang boleh mengubah kriteria SPK.
     * TODO: added role 'petugas' during development phase, later will change to admin, inventor again
     */
    public function authorize(): bool
    {
        return in_array(session('user.role'), ['inventor', 'admin', 'petugas']);
    }

    public function rules(): array
    {
        /** @var SpkMelonKriteria|null $kriteria */
        $kriteria = $this->route('kriteria');
        $isLocked = $kriteria instanceof SpkMelonKriteria ? $kriteria->isLocked() : false;

        if ($isLocked) {
            // Kriteria sudah dipakai di sesi selesai:
            // kode, tipe, dan kategori tidak boleh diubah untuk menjaga traceability historis.
            return [
                'nama'       => ['required', 'string', 'max:100'],
                'spiSumber'  => ['nullable', 'string', 'max:50'],
                'spiHitung'  => ['nullable', 'string', 'max:50'],
                'keterangan' => ['nullable', 'string'],
            ];
        }

        // Kriteria belum dipakai di sesi selesai: semua field boleh diubah.
        return [
            'nama'       => ['required', 'string', 'max:100'],
            'tipe'       => ['required', 'in:benefit,cost'],
            'kategori'   => ['required', 'in:produktivitas,kualitas,lingkungan'],
            'spiSumber'  => ['nullable', 'string', 'max:50'],
            'spiHitung'  => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required'     => 'Nama kriteria wajib diisi.',
            'nama.max'          => 'Nama kriteria maksimal 100 karakter.',
            'tipe.required'     => 'Tipe kriteria wajib dipilih.',
            'tipe.in'           => 'Tipe kriteria tidak valid. Pilih benefit atau cost.',
            'kategori.required' => 'Kategori kriteria wajib dipilih.',
            'kategori.in'       => 'Kategori tidak valid. Pilih produktivitas, kualitas, atau lingkungan.',
        ];
    }
}
