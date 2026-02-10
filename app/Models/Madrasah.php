<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Madrasah extends Model
{
    use HasFactory;

    protected $table = 'madrasah';
    protected $primaryKey = 'id_madrasah';

    protected $fillable = [
        'npsn',
        'nsm',
        'nama_madrasah',
        'no_piagam',
        'alamat',
        'kecamatan',
        'status_madrasah',
        'akreditasi',
        'tahun_berdiri',
        'kode_satker',
        'jalan',
        'desa',
        'kabupaten',
        'provinsi',
        'telp_kepala',
        'email_madrasah',
        'latitude',
        'longitude',
        'nama_kepala',
        'nip_kepala',
        'status_aktif'
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'id_madrasah', 'id_madrasah');
    }

    public function laporanBulanan()
    {
        return $this->hasMany(LaporanBulanan::class, 'id_madrasah', 'id_madrasah');
    }
}
