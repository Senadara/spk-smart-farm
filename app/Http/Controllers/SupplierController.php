<?php

namespace App\Http\Controllers;

use App\Models\MasterSupplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = MasterSupplier::with('produks')->get();
        return response()->json($suppliers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'kontak' => 'nullable|string|max:255',
            'produk_ids' => 'nullable|array',
            'produk_ids.*' => 'exists:master_produks,id'
        ]);

        $supplier = MasterSupplier::create($validated);

        if (!empty($validated['produk_ids'])) {
            $supplier->produks()->sync($validated['produk_ids']);
        }

        return response()->json($supplier->load('produks'), 201);
    }

    public function show($id)
    {
        $supplier = MasterSupplier::with('produks')->findOrFail($id);
        return response()->json($supplier);
    }

    public function update(Request $request, $id)
    {
        $supplier = MasterSupplier::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'sometimes|required|string|max:255',
            'alamat' => 'nullable|string',
            'kontak' => 'nullable|string|max:255',
            'produk_ids' => 'nullable|array',
            'produk_ids.*' => 'exists:master_produks,id'
        ]);

        $supplier->update($validated);

        if (array_key_exists('produk_ids', $validated)) {
            $supplier->produks()->sync($validated['produk_ids']);
        }

        return response()->json($supplier->load('produks'));
    }

    public function destroy($id)
    {
        $supplier = MasterSupplier::findOrFail($id);
        $supplier->delete();
        return response()->json(null, 204);
    }
}
