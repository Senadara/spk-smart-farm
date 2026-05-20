<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{
    public function index()
    {
        $user = session('user');
        
        // Hanya owner yang bisa melihat daftar karyawannya
        $karyawan = User::where('owner_id', $user['id'])
                        ->where('role', 'petugas')
                        ->get();

        return view('users.index', compact('karyawan'));
    }

    public function store(Request $request)
    {
        $user = session('user');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:user',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'id' => Str::uuid()->toString(),
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'petugas',
            'owner_id' => $user['id'],
            'isActive' => 1,
        ]);

        return redirect()->route('users.index')->with('success', 'Akun Petugas berhasil dibuat.');
    }

    public function update(Request $request, $id)
    {
        $owner = session('user');
        $karyawan = User::where('id', $id)->where('owner_id', $owner['id'])->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:user,email,' . $id,
            'password' => 'nullable|string|min:6',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $karyawan->update($data);

        return redirect()->route('users.index')->with('success', 'Akun Petugas berhasil diupdate.');
    }

    public function destroy($id)
    {
        $owner = session('user');
        $karyawan = User::where('id', $id)->where('owner_id', $owner['id'])->firstOrFail();
        $karyawan->delete();

        return redirect()->route('users.index')->with('success', 'Akun Petugas berhasil dihapus.');
    }
}
