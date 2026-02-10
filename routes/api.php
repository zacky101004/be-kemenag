<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MasterDataController;
use App\Http\Middleware\CheckRole;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // === ROLE: OPERATOR SEKOLAH ===
    Route::middleware(CheckRole::class.':operator_sekolah')->group(function () {
        Route::get('/operator/dashboard', [LaporanController::class, 'index']); // Dashboard List
        Route::get('/laporan/{id}', [LaporanController::class, 'show']); //ail Det Report
        Route::post('/laporan', [LaporanController::class, 'store']); 
        
        // Data Updates
        Route::put('/laporan/{id}/siswa', [LaporanController::class, 'updateSiswa']);
        Route::put('/laporan/{id}/rekap-personal', [LaporanController::class, 'updateRekapPersonal']);
        Route::put('/laporan/{id}/guru', [LaporanController::class, 'updateGuru']);
        Route::put('/laporan/{id}/sarpras', [LaporanController::class, 'updateSarpras']);
        Route::put('/laporan/{id}/mobiler', [LaporanController::class, 'updateMobiler']);
        Route::put('/laporan/{id}/keuangan', [LaporanController::class, 'updateKeuangan']);
        
        Route::post('/laporan/{id}/submit', [LaporanController::class, 'submit']);
        
        // Profile/Madrasah Management
        Route::get('/operator/madrasah', [MasterDataController::class, 'showMyMadrasah']);
        Route::put('/operator/madrasah', [MasterDataController::class, 'updateMyMadrasah']);
        
        // Pengumuman Read
        Route::get('/pengumuman', [MasterDataController::class, 'indexPengumuman']);
    });

    // === ROLE: KASI PENMAD ===
    Route::middleware(CheckRole::class.':kasi_penmad')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard']); // Stats
        Route::get('/admin/laporan', [AdminController::class, 'index']); // List Submission
        Route::post('/admin/laporan/{id}/verify', [AdminController::class, 'verify']); // Terima/Revisi
        Route::get('/admin/recap', [AdminController::class, 'recap']); // Export Data Source
        
        // Master Data
        Route::get('/master/madrasah', [MasterDataController::class, 'indexMadrasah']);
        Route::post('/master/madrasah', [MasterDataController::class, 'storeMadrasah']);
        Route::put('/master/madrasah/{id}', [MasterDataController::class, 'updateMadrasah']);
        Route::delete('/master/madrasah/{id}', [MasterDataController::class, 'destroyMadrasah']);
        
        Route::get('/master/users', [MasterDataController::class, 'indexUsers']);
        Route::post('/master/users', [MasterDataController::class, 'storeUser']);
        Route::put('/master/users/{id}', [MasterDataController::class, 'updateUser']);
        Route::delete('/master/users/{id}', [MasterDataController::class, 'destroyUser']);
        
        // Manage Pengumuman
        Route::post('/master/pengumuman', [MasterDataController::class, 'storePengumuman']);
         // Users also need to create announcements? Prompt says "dashboard yg isinya pengumuman dan info dari kasi penmad". Kasi creates. Operator reads.
    });
});
