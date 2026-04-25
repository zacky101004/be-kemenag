<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaporanBulanan;
use App\Models\DataSiswa;
use App\Models\DataGuru;
use App\Models\DataSarpras;
use App\Models\DataMobiler;
use App\Models\DataKeuangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LaporanController extends Controller
{
    // === OPERATOR SEKOLAH ===

    // Get List of Laporan (Dashboard)
    public function index(Request $request)
    {
        $user = $request->user();
        $query = LaporanBulanan::where('id_madrasah', $user->id_madrasah)
            ->whereNull('permanently_deleted_at_operator'); // Sembunyikan jika sudah hapus permanen

        // Filter untuk tempat sampah
        if ($request->query('trashed') == '1') {
            $query->whereNotNull('deleted_at_operator');
        } else {
            $query->whereNull('deleted_at_operator');
        }

        if ($request->has('year')) {
            $query->whereYear('bulan_tahun', $request->year);
        }

        return response()->json($query->orderBy('bulan_tahun', 'desc')->get());
    }

    // Create Draft for a Month
    public function store(Request $request)
    {
        $request->validate([
            'bulan_tahun' => 'required|date', // YYYY-MM-DD (e.g., 2025-01-01)
        ]);

        $user = $request->user();

        // Check if exists
        $exists = LaporanBulanan::where('id_madrasah', $user->id_madrasah)
            ->whereYear('bulan_tahun', date('Y', strtotime($request->bulan_tahun)))
            ->whereMonth('bulan_tahun', date('m', strtotime($request->bulan_tahun)))
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Laporan bulan ini sudah ada'], 400);
        }

        return DB::transaction(function () use ($user, $request) {
            $laporan = LaporanBulanan::create([
                'id_madrasah' => $user->id_madrasah,
                'bulan_tahun' => $request->bulan_tahun,
                'status_laporan' => 'draft'
            ]);

            // Seed Default Bagian A: Siswa
            $defaultSiswa = ['Kel A', 'Kel B'];
            foreach ($defaultSiswa as $kelas) {
                $laporan->siswa()->create(['kelas' => $kelas]);
            }

            // Seed Default Bagian B: Rekapitulasi Personal (Common Categories)
            $defaultRekap = [
                'Guru Tetap/PNS', 'Guru PNS Dinas', 'Guru Honor Tk II/Tk I', 'Guru Honor Madrasah',
                'Sertifikasi Guru PNS', 'Sertifikasi Guru Non PNS', 'Pegawai TU PNS', 'Pegawai TU Honorer',
                'Petugas Pustaka', 'Petugas UKS', 'Satpam', 'Petugas Kebersihan', 'Petugas Madrasah'
            ];
            foreach ($defaultRekap as $kat) {
                $laporan->rekap_personal()->create([
                    'keadaan' => $kat
                ]);
            }

            // Bagian F: Guru/TU (Header list) - Optional based on whether schools want to fill from scratch or keep old ones
            // Usually, list of persons starts empty or copied from last month.

            // Seed Default Bagian C: Sarpras
            $defaultSarpras = [
                'Luas Tanah yg terbangun', 'Luas tanah Pekarangan', 'Total Luas Tanah Seluruh nya', 'Status Tanah',
                'Jumlah Lokal Belajar', 'Ruang Kantor TU', 'Ruang kepala Madrasah', 'Ruang Tamu', 
                'Ruang Majelis Guru', 'Ruang Perpustakaan', 'WC Guru', 'WC Siswa', 'Mushalla', 'Gudang'
            ];
            foreach ($defaultSarpras as $aset) {
                $laporan->sarpras()->create(['jenis_aset' => $aset]);
            }

            // Seed Default Bagian Mobiler
            $defaultMobiler = ['Almari Guru', 'Meja Guru', 'Kursi Guru', 'Meja Siswa', 'Kursi Siswa', 'Almari Siswa'];
            foreach ($defaultMobiler as $item) {
                $laporan->mobiler()->create(['nama_barang' => $item]);
            }

            // Seed Default Bagian D: Keuangan
            $defaultKeuangan = [
                'Jam Wajib PNS/Sertifikasi', 'NON PNS dan Non sertifikasi', 'Kepala', 'Waka Kur & Kesis',
                'Operator', 'Transport Rapat Anggota', 'Kegiatan PMT', 'Biaya tak di duga'
            ];
            foreach ($defaultKeuangan as $keu) {
                $laporan->keuangan()->create(['uraian_kegiatan' => $keu]);
            }

            return response()->json($laporan->load(['siswa', 'rekap_personal', 'guru', 'sarpras', 'mobiler', 'keuangan']), 201);
        });
    }

    // Get Detail Laporan (Full Data)
    public function show($id)
    {
        $laporan = LaporanBulanan::with(['siswa', 'rekap_personal', 'guru', 'sarpras', 'mobiler', 'keuangan', 'madrasah'])
            ->findOrFail($id);

        $this->authorizeAccess($laporan);

        return response()->json($laporan);
    }

    // Update Section A: Siswa
    public function updateSiswa(Request $request, $id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeEdit($laporan);

        $data = $request->input('data'); 
        
        DB::transaction(function () use ($laporan, $data) {
            $laporan->siswa()->delete();
            foreach ($data as $row) {
                $row['jumlah_rombel'] = $row['jumlah_rombel'] ?? 0;
                $row['jumlah_lk'] = $row['jumlah_lk'] ?? 0;
                $row['jumlah_pr'] = $row['jumlah_pr'] ?? 0;
                $row['mutasi_masuk'] = $row['mutasi_masuk'] ?? 0;
                $row['mutasi_keluar'] = $row['mutasi_keluar'] ?? 0;
                $laporan->siswa()->create($row);
            }
        });

        return response()->json(['message' => 'Data Siswa updated']);
    }

    // Update Section B: Rekap Personal
    public function updateRekapPersonal(Request $request, $id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeEdit($laporan);
        
        $data = $request->input('data');
        DB::transaction(function () use ($laporan, $data) {
            $laporan->rekap_personal()->delete();
            foreach ($data as $row) {
                $row['jumlah_lk'] = $row['jumlah_lk'] ?? 0;
                $row['jumlah_pr'] = $row['jumlah_pr'] ?? 0;
                $row['mutasi_masuk'] = $row['mutasi_masuk'] ?? 0;
                $row['mutasi_keluar'] = $row['mutasi_keluar'] ?? 0;
                $laporan->rekap_personal()->create($row);
            }
        });

        return response()->json(['message' => 'Rekap Personal updated']);
    }

    // Update Section F: Guru/TU List
    public function updateGuru(Request $request, $id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeEdit($laporan);
        
        $data = $request->input('data');
        DB::transaction(function () use ($laporan, $data) {
            $laporan->guru()->delete();
            foreach ($data as $row) {
                $row['jabatan'] = $row['jabatan'] ?? '-';
                $row['lp'] = $row['lp'] ?? 'L';
                $row['jumlah_jam'] = $row['jumlah_jam'] ?? 0;
                $row['sertifikasi'] = $row['sertifikasi'] ?? false;
                $laporan->guru()->create($row);
            }
        });

        return response()->json(['message' => 'Data Guru updated']);
    }

    // Update Section C: Sarpras
    public function updateSarpras(Request $request, $id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeEdit($laporan);
        
        $data = $request->input('data');
        DB::transaction(function () use ($laporan, $data) {
            $laporan->sarpras()->delete();
            foreach ($data as $row) {
                $row['kondisi_baik'] = $row['kondisi_baik'] ?? 0;
                $row['kondisi_rusak_ringan'] = $row['kondisi_rusak_ringan'] ?? 0;
                $row['kondisi_rusak_berat'] = $row['kondisi_rusak_berat'] ?? 0;
                $row['kekurangan'] = $row['kekurangan'] ?? 0;
                $row['perlu_rehab'] = $row['perlu_rehab'] ?? 0;
                $laporan->sarpras()->create($row);
            }
        });

        return response()->json(['message' => 'Data Sarpras updated']);
    }

    // Update Mobiler
    public function updateMobiler(Request $request, $id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeEdit($laporan);
        
        $data = $request->input('data');
        DB::transaction(function () use ($laporan, $data) {
            $laporan->mobiler()->delete();
            foreach ($data as $row) {
                $row['jumlah_total'] = $row['jumlah_total'] ?? 0;
                $row['kondisi_baik'] = $row['kondisi_baik'] ?? 0;
                $row['kondisi_rusak_ringan'] = $row['kondisi_rusak_ringan'] ?? 0;
                $row['kondisi_rusak_berat'] = $row['kondisi_rusak_berat'] ?? 0;
                $row['kekurangan'] = $row['kekurangan'] ?? 0;
                $laporan->mobiler()->create($row);
            }
        });

        return response()->json(['message' => 'Data Mobiler updated']);
    }

    // Update Keuangan
    public function updateKeuangan(Request $request, $id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeEdit($laporan);
        
        $data = $request->input('data');
        DB::transaction(function () use ($laporan, $data) {
            $laporan->keuangan()->delete();
            foreach ($data as $row) {
                $row['volume'] = $row['volume'] ?? 0;
                $row['harga_satuan'] = $row['harga_satuan'] ?? 0;
                $laporan->keuangan()->create($row);
            }
        });

        return response()->json(['message' => 'Data Keuangan updated']);
    }

    // Submit Laporan
    public function submit(Request $request, $id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeEdit($laporan);

        $laporan->update([
            'status_laporan' => 'submitted',
            'submitted_at' => now()
        ]);

        \App\Models\ActivityLog::log('SUBMIT_REPORT', $laporan->madrasah->nama_madrasah, 'Periode: ' . $laporan->bulan_tahun->format('M Y'));

        return response()->json(['message' => 'Laporan berhasil disubmit. Menunggu verifikasi Kasi Penmad.']);
    }

    public function destroy($id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeAccess($laporan);

        // Validasi: Status submitted tidak boleh dihapus
        if ($laporan->status_laporan === 'submitted') {
            return response()->json([
                'message' => 'Laporan sedang dalam proses validasi admin, tidak bisa dihapus.'
            ], 400);
        }
        
        // Soft delete (operator view)
        $laporan->update([
            'deleted_at_operator' => now()
        ]);
        
        return response()->json(['message' => 'Laporan dipindahkan ke tempat sampah']);
    }

    public function restore($id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeAccess($laporan);
        
        $laporan->update([
            'deleted_at_operator' => null
        ]);
        
        return response()->json(['message' => 'Laporan berhasil dikembalikan']);
    }

    public function permanentDelete($id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        $this->authorizeAccess($laporan);

        if (!$laporan->deleted_at_operator) {
            return response()->json(['message' => 'Laporan harus di tempat sampah dulu'], 400);
        }
        
        // Tandai sebagai dihapus permanen oleh Operator
        $laporan->update(['permanently_deleted_at_operator' => now()]);

        // Logic Baru: Kapan benar-benar hilang dari database?
        // 1. Jika statusnya draft atau revisi (admin tidak berkepentingan)
        // 2. Jika statusnya verified TAPI Admin juga sudah menghapus permanen
        if (
            $laporan->status_laporan === 'draft' || 
            $laporan->status_laporan === 'revisi' || 
            ($laporan->status_laporan === 'verified' && $laporan->permanently_deleted_at_admin !== null)
        ) {
            $laporan->delete(); // Hard Delete dari DB
        }

        return response()->json(['message' => 'Laporan berhasil dihapus selamanya dari daftar anda']);
    }

    // Helper: Verify Ownership & Status
    private function authorizeAccess($laporan)
    {
        $user = Auth::user();
        if ($user->role === 'kasi_penmad' || $user->role === 'staff_penmad') return true;
        if ($user->id_madrasah !== $laporan->id_madrasah) {
            abort(403, 'Unauthorized');
        }
    }

    private function authorizeEdit($laporan)
    {
        $this->authorizeAccess($laporan);
        if ($laporan->status_laporan !== 'draft' && $laporan->status_laporan !== 'revisi') {
            abort(403, 'Laporan sudah disubmit atau diverifikasi, tidak bisa diedit.');
        }
    }
}
