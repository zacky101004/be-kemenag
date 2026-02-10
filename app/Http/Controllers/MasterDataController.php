<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Madrasah;
use App\Models\User;
use App\Models\Pengumuman;
use Illuminate\Support\Facades\Hash;

class MasterDataController extends Controller
{
    // === MADRASAH ===
    public function indexMadrasah()
    {
        return response()->json(Madrasah::all());
    }

    public function storeMadrasah(Request $request)
    {
        $validated = $request->validate([
            'npsn' => 'required|unique:madrasah',
            'nama_madrasah' => 'required',
            'status_aktif' => 'boolean'
        ]);

        return response()->json(Madrasah::create($validated));
    }

    public function updateMadrasah(Request $request, $id)
    {
        $madrasah = Madrasah::findOrFail($id);
        $madrasah->update($request->all());
        return response()->json($madrasah);
    }

    public function showMyMadrasah(Request $request)
    {
        $id_madrasah = $request->user()->id_madrasah;
        if (!$id_madrasah) {
            return response()->json(['message' => 'User does not have an assigned madrasah'], 404);
        }
        return response()->json(Madrasah::findOrFail($id_madrasah));
    }

    public function updateMyMadrasah(Request $request)
    {
        $id_madrasah = $request->user()->id_madrasah;
        if (!$id_madrasah) {
            return response()->json(['message' => 'User does not have an assigned madrasah'], 404);
        }
        $madrasah = Madrasah::findOrFail($id_madrasah);
        $madrasah->update($request->all());
        return response()->json($madrasah);
    }

    // === USERS ===
    public function indexUsers()
    {
        return response()->json(User::with('madrasah')->get());
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:operator_sekolah,kasi_penmad',
            'id_madrasah' => 'required_if:role,operator_sekolah'
        ]);

        $validated['password'] = Hash::make($validated['password']);

        return response()->json(User::create($validated));
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $data = $request->all();
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']); // Don't update password if not provided
        }
        $user->update($data);
        return response()->json($user);
    }

    public function destroyMadrasah($id)
    {
        $madrasah = Madrasah::findOrFail($id);
        $madrasah->delete();
        return response()->json(['message' => 'Madrasah deleted successfully']);
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete your own account'], 400);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    // === PENGUMUMAN ===
    public function indexPengumuman()
    {
        return response()->json(Pengumuman::with('creator')->orderBy('created_at', 'desc')->get());
    }

    public function storePengumuman(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required',
            'isi_info' => 'required'
        ]);

        $validated['created_by'] = $request->user()->id;

        return response()->json(Pengumuman::create($validated));
    }
}
