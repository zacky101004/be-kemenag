<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('madrasah', function (Blueprint $table) {
            $table->string('nsm', 50)->nullable()->after('npsn');
            $table->string('no_piagam', 100)->nullable()->after('nama_madrasah');
            $table->string('status_madrasah', 20)->nullable()->after('no_piagam');
            $table->string('akreditasi', 50)->nullable()->after('status_madrasah');
            $table->string('tahun_berdiri', 10)->nullable()->after('akreditasi');
            $table->string('kode_satker', 50)->nullable()->after('tahun_berdiri');
            $table->string('jalan')->nullable()->after('alamat');
            $table->string('desa')->nullable()->after('jalan');
            $table->string('kabupaten')->nullable()->after('kecamatan');
            $table->string('provinsi')->nullable()->after('kabupaten');
            $table->string('telp_kepala', 30)->nullable();
            $table->string('email_madrasah')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('nama_kepala')->nullable();
            $table->string('nip_kepala', 50)->nullable();
        });
    }

    public function down()
    {
        Schema::table('madrasah', function (Blueprint $table) {
            $table->dropColumn([
                'nsm', 'no_piagam', 'status_madrasah', 'akreditasi', 'tahun_berdiri',
                'kode_satker', 'jalan', 'desa', 'kabupaten', 'provinsi',
                'telp_kepala', 'email_madrasah', 'latitude', 'longitude',
                'nama_kepala', 'nip_kepala'
            ]);
        });
    }
};
