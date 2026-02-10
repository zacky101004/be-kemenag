<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaporanBulanan;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // === KASI PENMAD ===

    // Monitoring Dashboard
    public function dashboard(Request $request) 
    {
        // Summary Stats
        $stats = [
            'total_madrasah' => \App\Models\Madrasah::count(),
            'laporan_masuk' => LaporanBulanan::whereIn('status_laporan', ['submitted', 'verified', 'revisi'])->count(),
            'terverifikasi' => LaporanBulanan::where('status_laporan', 'verified')->count(),
            'perlu_revisi' => LaporanBulanan::where('status_laporan', 'revisi')->count(),
            'recent_submissions' => LaporanBulanan::with('madrasah')
                ->where('status_laporan', '!=', 'draft')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(),
            // Kecamatan progress akan ditampilkan setelah ada kolom kecamatan di tabel madrasah
            'kecamatan_progress' => []
        ];

        return response()->json($stats);
    }

    // List Validasi Laporan
    public function index(Request $request)
    {
        $query = LaporanBulanan::with('madrasah');

        if ($request->has('status')) {
            $query->where('status_laporan', $request->status);
        }

        if ($request->has('bulan')) {
            $query->whereMonth('bulan_tahun', date('m', strtotime($request->bulan)))
                  ->whereYear('bulan_tahun', date('Y', strtotime($request->bulan)));
        }

        return response()->json($query->orderBy('updated_at', 'desc')->get());
    }

    // Validasi Action (Terima / Revisi)
    public function verify(Request $request, $id)
    {
        $request->validate([
            'status_laporan' => 'required|in:verified,revisi',
            'catatan_revisi' => 'required_if:status_laporan,revisi'
        ]);

        $laporan = LaporanBulanan::findOrFail($id);
        
        $laporan->update([
            'status_laporan' => $request->status_laporan,
            'catatan_revisi' => $request->catatan_revisi
        ]);

        return response()->json(['message' => 'Status laporan diperbarui', 'data' => $laporan]);
    }

    // Rekapitulasi Data (For Excel Export)
    public function recap(Request $request)
    {
        // Get all submitted/verified reports for preview
        $bulan = $request->input('bulan', date('Y-m-d')); // Month Needed

        // For preview: show all non-draft reports
        $data = LaporanBulanan::with(['madrasah', 'siswa', 'guru'])
            ->whereIn('status_laporan', ['submitted', 'verified', 'revisi'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($data);
    }
}
