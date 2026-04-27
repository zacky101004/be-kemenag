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
        return response()->json(Madrasah::with('users')->get());
    }

    public function storeMadrasah(Request $request)
    {
        $validated = $request->validate([
            'npsn' => 'required|unique:madrasah',
            'nama_madrasah' => 'required',
            'kecamatan' => 'nullable',
            'status_aktif' => 'boolean'
        ]);

        $madrasah = Madrasah::create($validated);
        \App\Models\ActivityLog::log('CREATE_MADRASAH', $madrasah->nama_madrasah, 'NPSN: ' . $madrasah->npsn);
        return response()->json($madrasah);
    }

    public function showMadrasah($id)
    {
        return response()->json(Madrasah::with('users')->findOrFail($id));
    }

    public function updateMadrasah(Request $request, $id)
    {
        $madrasah = Madrasah::findOrFail($id);
        $madrasah->update($request->all());
        \App\Models\ActivityLog::log('UPDATE_MADRASAH', $madrasah->nama_madrasah, 'Updated details for madrasah');
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
        \App\Models\ActivityLog::log('UPDATE_MADRASAH', $madrasah->nama_madrasah, 'Operator memperbarui profil madrasah');
        return response()->json($madrasah);
    }

    // === USERS ===
    public function indexUsers()
    {
        return response()->json(User::with('madrasah')->get());
    }

    public function storeUser(Request $request)
    {
        $admin = $request->user();
        
        $validated = $request->validate([
            'username' => 'required|unique:users',
            'name' => 'nullable|string',
            'password' => 'required|min:6',
            'role' => 'required|in:operator_sekolah,kasi_penmad,staff_penmad',
            'id_madrasah' => 'required_if:role,operator_sekolah'
        ]);

        // RBAC Restriction: Staff can ONLY create Operator
        if ($admin->role === 'staff_penmad' && $validated['role'] !== 'operator_sekolah') {
            return response()->json(['message' => 'Otoritas Staf terbatas: Hanya dapat mendaftarkan Operator Madrasah.'], 403);
        }

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        \App\Models\ActivityLog::log('CREATE_USER', $user->username, 'Assigned role: ' . $user->role);
        return response()->json($user);
    }

    public function updateUser(Request $request, $id)
    {
        $admin = $request->user();
        $user = User::findOrFail($id);
        $data = $request->all();

        // RBAC Restriction for Staff
        if ($admin->role === 'staff_penmad') {
            // Staff cannot edit Kasi or other Staff
            if ($user->role !== 'operator_sekolah') {
                return response()->json(['message' => 'Otoritas Staf terbatas: Tidak dapat mengubah akun Pimpinan atau Staf lain.'], 403);
            }
            // Staff cannot promote anyone to Staff or Kasi
            if (isset($data['role']) && $data['role'] !== 'operator_sekolah') {
                return response()->json(['message' => 'Otoritas Staf terbatas: Hanya dapat mengelola level Operator Madrasah.'], 403);
            }
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']); // Don't update password if not provided
        }
        $user->update($data);
        \App\Models\ActivityLog::log('UPDATE_USER', $user->username, 'Modified account details');
        return response()->json($user);
    }

    public function destroyMadrasah($id)
    {
        $madrasah = Madrasah::findOrFail($id);
        $name = $madrasah->nama_madrasah;
        $madrasah->delete();
        \App\Models\ActivityLog::log('DELETE_MADRASAH', $name, 'Removed madrasah from database');
        return response()->json(['message' => 'Madrasah deleted successfully']);
    }

    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        $name = $user->username;
        
        // Safety: Cannot delete pimpinan (kasi_penmad) accounts through this endpoint
        if ($user->role === 'kasi_penmad') {
            return response()->json(['message' => 'Akun Pimpinan (Kasi Penmad) tidak dapat dihapus melalui jalur ini demi keamanan.'], 403);
        }

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Tidak dapat menghapus akun Anda sendiri'], 400);
        }
        $user->delete();
        \App\Models\ActivityLog::log('DELETE_USER', $name, 'Menghapus akun pengguna');
        return response()->json(['message' => 'User berhasil dihapus']);
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

    public function destroyPengumuman($id)
    {
        $pengumuman = Pengumuman::findOrFail($id);
        $judul = $pengumuman->judul;
        $pengumuman->delete();
        \App\Models\ActivityLog::log('DELETE_ANNOUNCEMENT', $judul, 'Menghapus pengumuman');
        return response()->json(['message' => 'Pengumuman deleted successfully']);
    }
}
