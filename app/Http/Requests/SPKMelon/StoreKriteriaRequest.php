<?php

namespace App\Http\Requests\SPKMelon;

use Illuminate\Foundation\Http\FormRequest;

class StoreKriteriaRequest extends FormRequest
{
    /**
     * Hanya inventor dan admin yang boleh membuat kriteria SPK.
     * TODO: added role 'petugas' during development phase, later will change to admin, inventor again
     */
    public function authorize(): bool
    {
        return in_array(session('user.role'), ['inventor', 'admin', 'petugas']);
    }

    public function rules(): array
    {
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
