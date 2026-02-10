<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Madrasah;
use App\Models\LaporanBulanan;
use App\Models\DataSiswa;
use App\Models\DataRekapPersonal;
use App\Models\DataGuru;
use App\Models\DataSarpras;
use App\Models\DataMobiler;
use App\Models\DataKeuangan;
use App\Models\Pengumuman;
use Carbon\Carbon;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        // 1. Create Kasi Penmad (Admin)
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('password'),
                'role' => 'kasi_penmad',
                'id_madrasah' => null,
            ]
        );

        // 2. Create Pengumuman
        Pengumuman::updateOrCreate(
            ['judul' => 'Jadwal Pelaporan Bulan Februari'],
            [
                'isi_info' => 'Mohon Bapak/Ibu Operator segera mengupload laporan bulan Februari sebelum tanggal 10. Terima kasih.',
                'created_by' => $admin->id
            ]
        );

        // 3. Create 1 Madrasah & 1 Operator Sekolah
        $m = [
            'npsn' => '10101010',
            'nama' => 'MI NURUL HUDA',
            'alamat' => 'Jl. Mawar No. 10, Pekanbaru',
            'user' => 'operator'
        ];

        $madrasah = Madrasah::updateOrCreate(
            ['npsn' => $m['npsn']],
            [
                'nama_madrasah' => $m['nama'],
                'alamat' => $m['alamat'],
                'status_aktif' => true
            ]
        );

        User::updateOrCreate(
            ['username' => $m['user']],
            [
                'password' => Hash::make('password'),
                'role' => 'operator_sekolah',
                'id_madrasah' => $madrasah->id_madrasah
            ]
        );

        // 4. Create Historical Reports for this Madrasah (Jan, Feb, Mar)
        $this->createHistoricalReports($madrasah);
    }

    private function createHistoricalReports($madrasah)
    {
        // Report 1: January (Verified)
        $jan = LaporanBulanan::updateOrCreate(
            [
                'id_madrasah' => $madrasah->id_madrasah,
                'bulan_tahun' => Carbon::now()->subMonth(2)->startOfMonth()->format('Y-m-d')
            ],
            [
                'status_laporan' => 'verified',
                'submitted_at' => Carbon::now()->subMonth(2)->endOfMonth(),
            ]
        );
        $this->seedReportDetails($jan);

        // Report 2: February (Revisi)
        $feb = LaporanBulanan::updateOrCreate(
            [
                'id_madrasah' => $madrasah->id_madrasah,
                'bulan_tahun' => Carbon::now()->subMonth(1)->startOfMonth()->format('Y-m-d')
            ],
            [
                'status_laporan' => 'revisi',
                'catatan_revisi' => 'Mohon lengkapi data sarpras bagian tanah.',
                'submitted_at' => Carbon::now()->subMonth(1)->endOfMonth(),
            ]
        );
        $this->seedReportDetails($feb);

        // Report 3: March (Draft)
        $mar = LaporanBulanan::updateOrCreate(
            [
                'id_madrasah' => $madrasah->id_madrasah,
                'bulan_tahun' => Carbon::now()->startOfMonth()->format('Y-m-d')
            ],
            [
                'status_laporan' => 'draft',
                'submitted_at' => null,
            ]
        );
        $this->seedReportDetails($mar);
    }

    private function seedReportDetails($laporan)
    {
        // A. Data Siswa
        $kelas_list = ['Kel A', 'Kel B'];
        foreach ($kelas_list as $k) {
            DataSiswa::updateOrCreate(
                ['id_laporan' => $laporan->id_laporan, 'kelas' => $k],
                [
                    'jumlah_rombel' => 1,
                    'jumlah_lk' => 15,
                    'jumlah_pr' => 15,
                    'mutasi_masuk' => 0,
                    'mutasi_keluar' => 0,
                ]
            );
        }

        // B. Rekap Personal
        $categories = ['Guru Tetap/PNS', 'Guru Honor Madrasah'];
        foreach ($categories as $cat) {
            DataRekapPersonal::updateOrCreate(
                ['id_laporan' => $laporan->id_laporan, 'keadaan' => $cat],
                [
                    'jumlah_lk' => 2,
                    'jumlah_pr' => 3,
                ]
            );
        }

        // F. Data Guru
        $guru_names = ['Budi Santoso', 'Siti Aminah'];
        foreach ($guru_names as $name) {
            DataGuru::updateOrCreate(
                ['id_laporan' => $laporan->id_laporan, 'nama_guru' => $name],
                [
                    'nip_nik' => rand(10000000, 99999999),
                    'lp' => $name == 'Budi Santoso' ? 'L' : 'P',
                    'jabatan' => 'Guru Kelas',
                    'mutasi_status' => 'aktif'
                ]
            );
        }
        
        // C. Sarpras
        $aset = ['Ruang Kelas', 'Ruang Guru'];
        foreach ($aset as $a) {
            DataSarpras::updateOrCreate(
                ['id_laporan' => $laporan->id_laporan, 'jenis_aset' => $a],
                [
                    'luas' => '60 m2',
                    'kondisi_baik' => 1,
                    'kondisi_rusak_ringan' => 0,
                    'kondisi_rusak_berat' => 0
                ]
            );
        }

        // Mobiler
        $mobiler = ['Meja Siswa', 'Kursi Siswa'];
        foreach ($mobiler as $m) {
            DataMobiler::updateOrCreate(
                ['id_laporan' => $laporan->id_laporan, 'nama_barang' => $m],
                [
                    'jumlah_total' => 30,
                    'kondisi_baik' => 30,
                    'kondisi_rusak_ringan' => 0,
                    'kondisi_rusak_berat' => 0
                ]
            );
        }

        // D. Keuangan
        $kegiatan = ['Pembelian ATK'];
        foreach ($kegiatan as $k) {
            DataKeuangan::updateOrCreate(
                ['id_laporan' => $laporan->id_laporan, 'uraian_kegiatan' => $k],
                [
                    'volume' => 1,
                    'satuan' => 'Paket',
                    'harga_satuan' => 500000
                ]
            );
        }
    }
}
