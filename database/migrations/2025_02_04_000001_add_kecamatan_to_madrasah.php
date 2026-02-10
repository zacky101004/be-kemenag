<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('madrasah', function (Blueprint $table) {
            $table->string('kecamatan')->nullable()->after('alamat');
        });
    }

    public function down()
    {
        Schema::table('madrasah', function (Blueprint $table) {
            $table->dropColumn('kecamatan');
        });
    }
};
