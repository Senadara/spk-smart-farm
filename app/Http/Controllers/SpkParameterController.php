<?php

namespace App\Http\Controllers;

use App\Models\SpkParameter;
use App\Models\SpkSupplierParameterValue;
use Illuminate\Http\Request;

class SpkParameterController extends Controller
{
    public function index()
    {
        $parameters = SpkParameter::all();
        return response()->json($parameters);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_parameter' => 'required|string|max:255',
            'tipe' => 'required|in:benefit,cost'
        ]);

        $parameter = SpkParameter::create($validated);
        return response()->json($parameter, 201);
    }

    public function assignValue(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:master_suppliers,id',
            'produk_id' => 'required|exists:master_produks,id',
            'parameter_id' => 'required|exists:spk_parameters,id',
            'value' => 'required|numeric'
        ]);

        $value = SpkSupplierParameterValue::updateOrCreate(
            [
                'supplier_id' => $validated['supplier_id'],
                'produk_id' => $validated['produk_id'],
                'parameter_id' => $validated['parameter_id'],
            ],
            [
                'value' => $validated['value']
            ]
        );

        return response()->json($value);
    }
}
