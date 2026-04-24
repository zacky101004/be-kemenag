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
        $user = $request->user();
        // Summary Stats (Exclude deleted records)
        $stats = [
            'total_madrasah' => \App\Models\Madrasah::count(),
            'laporan_masuk' => LaporanBulanan::where('status_laporan', 'submitted')
                ->whereNull('deleted_at_admin')
                ->count(),
            'terverifikasi' => LaporanBulanan::where('status_laporan', 'verified')
                ->whereNull('deleted_at_admin')
                ->count(),
            'perlu_revisi' => LaporanBulanan::where('status_laporan', 'revisi')
                ->whereNull('deleted_at_admin')
                ->count(),
            'recent_submissions' => LaporanBulanan::with('madrasah')
                ->when($user && $user->role === 'staff_penmad', function($query) {
                    $query->where('status_laporan', '!=', 'revisi');
                })
                ->where('status_laporan', '!=', 'draft')
                ->whereNull('deleted_at_admin')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(),
            'kecamatan_progress' => \App\Models\Madrasah::select('kecamatan', DB::raw('count(*) as total'))
                ->whereNotNull('kecamatan')
                ->groupBy('kecamatan')
                ->get()
        ];

        return response()->json($stats);
    }

    // List Validasi Laporan
    public function index(Request $request)
    {
        $user = $request->user();
        $statusTerlihat = ['submitted', 'verified', 'revisi'];
        
        if ($user && $user->role === 'staff_penmad') {
            $statusTerlihat = ['submitted', 'verified'];
        }

        // Admin hanya melihat status 'submitted', 'verified', dan 'revisi' (tergantung role)
        $query = LaporanBulanan::with('madrasah')
            ->whereIn('status_laporan', $statusTerlihat)
            ->whereNull('permanently_deleted_at_admin');
        
        if ($request->query('trashed') == '1') {
            $query->whereNotNull('deleted_at_admin');
        } else {
            $query->whereNull('deleted_at_admin');
        }

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
        
        $laporan->status_laporan = $request->status_laporan;
        
        if ($request->status_laporan === 'verified') {
            $laporan->catatan_revisi = null;
        } else {
            $laporan->catatan_revisi = $request->catatan_revisi;
        }

        $laporan->save();

        $action = $request->status_laporan === 'verified' ? 'APPROVE_REPORT' : 'REVISE_REPORT';
        $madrasahName = $laporan->madrasah->nama_madrasah;
        \App\Models\ActivityLog::log($action, $madrasahName, 'Periode: ' . $laporan->bulan_tahun->format('M Y'));

        return response()->json([
            'status' => 'success',
            'message' => 'Status laporan berhasil diperbarui menjadi ' . $request->status_laporan,
            'data' => $laporan
        ]);
    }

    // Rekapitulasi Data (For Excel Export)
    public function recap(Request $request)
    {
        $bulanStr = $request->query('bulan'); 
        $kecamatan = $request->query('kecamatan');
        $jenjang = $request->query('jenjang');

        // Untuk Rekapitulasi: Ambil semua status agar Admin bisa lihat progress (kecuali draft jika rekapan nilai)
        // Tapi kita bebaskan dulu agar filter terlihat jalan
        $query = LaporanBulanan::with(['madrasah', 'siswa', 'rekap_personal', 'guru', 'sarpras', 'mobiler', 'keuangan']);

        $user = $request->user();
        if ($user && $user->role === 'staff_penmad') {
            $query->where('status_laporan', '!=', 'revisi');
        }

        // Filter: Periode (Bulan - Tahun)
        if ($bulanStr && $bulanStr !== 'undefined') {
            $query->where('bulan_tahun', 'LIKE', $bulanStr . '%');
        }

        // Filter: Kecamatan (Case Insensitive)
        if ($kecamatan && $kecamatan !== 'Semua Kec.') {
            $query->whereHas('madrasah', function($q) use ($kecamatan) {
                $q->where('kecamatan', 'LIKE', trim($kecamatan));
            });
        }

        // Filter: Jenjang (MI/MTS/MA) - Cek awalan nama madrasah
        if ($jenjang && $jenjang !== 'Semua Jenjang') {
            $query->whereHas('madrasah', function($q) use ($jenjang) {
                $q->where('nama_madrasah', 'REGEXP', '^(' . $jenjang . ')[[:space:]|[:punct:]]');
            });
        }

        if ($request->query('trashed') == '1') {
            $query->whereNotNull('deleted_at_admin');
        } else {
            $query->whereNull('deleted_at_admin');
        }

        $data = $query->orderBy('updated_at', 'desc')->get();

        return response()->json($data);
    }
    public function destroy($id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        
        // Hanya bisa hapus jika status verified (sudah approve)
        if ($laporan->status_laporan !== 'verified') {
            return response()->json([
                'message' => 'Hanya laporan yang sudah disetujui yang bisa dihapus. Silakan approve atau reject terlebih dahulu.'
            ], 400);
        }
        
        $laporan->update([
            'deleted_at_admin' => now()
        ]);

        \App\Models\ActivityLog::log('DELETE_LAPORAN', $laporan->madrasah->nama_madrasah ?? '-', 'Laporan dipindahkan ke tempat sampah. Periode: ' . optional($laporan->bulan_tahun)->format('M Y'));

        return response()->json(['message' => 'Laporan berhasil dipindahkan ke tempat sampah']);
    }

    public function restore($id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        
        $laporan->update([
            'deleted_at_admin' => null
        ]);

        \App\Models\ActivityLog::log('RESTORE_LAPORAN', $laporan->madrasah->nama_madrasah ?? '-', 'Laporan dikembalikan dari tempat sampah. Periode: ' . optional($laporan->bulan_tahun)->format('M Y'));

        return response()->json(['message' => 'Laporan berhasil dikembalikan dari tempat sampah']);
    }

    public function permanentDelete($id)
    {
        $laporan = LaporanBulanan::findOrFail($id);
        
        if (!$laporan->deleted_at_admin) {
            return response()->json(['message' => 'Laporan harus dipindahkan ke tempat sampah dulu.'], 400);
        }

        $madrasahName = $laporan->madrasah->nama_madrasah ?? '-';
        $periode = optional($laporan->bulan_tahun)->format('M Y');
        
        // Tandai dihapus permanen oleh Admin
        $laporan->update(['permanently_deleted_at_admin' => now()]);

        // Jika status Verified: Hanya hapus dari DB jika Operator juga sudah hapus permanen
        // Jika status Draft/Revisi: Bisa langsung hapus dari DB
        if ($laporan->permanently_deleted_at_operator !== null || $laporan->status_laporan !== 'verified') {
            $laporan->delete(); 
        }

        \App\Models\ActivityLog::log('PERMANENT_DELETE_LAPORAN', $madrasahName, 'Laporan dihapus permanen. Periode: ' . $periode);

        return response()->json(['message' => 'Laporan berhasil dihapus selamanya dari daftar Admin']);
    }

    public function getActivityLogs()
    {
        $logs = \App\Models\ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id'         => $log->id,
                    'user_id'    => $log->user_id,
                    'username'   => $log->username,
                    'role'       => $log->user?->role ?? null,
                    'action'     => $log->action,
                    'subject'    => $log->subject,
                    'details'    => $log->details,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                ];
            });

        return response()->json($logs);
    }

    public function destroyLog($id)
    {
        $log = \App\Models\ActivityLog::findOrFail($id);
        $log->delete();
        return response()->json(['message' => 'Log aktivitas berhasil dihapus permanen.']);
    }

    public function bulkDestroyLogs(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'Tidak ada log yang dipilih.'], 400);
        }
        
        \App\Models\ActivityLog::whereIn('id', $ids)->delete();
        return response()->json(['message' => count($ids) . ' Log aktivitas berhasil dihapus permanen.']);
    }
}
