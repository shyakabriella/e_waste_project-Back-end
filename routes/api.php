<?php

use App\Http\Controllers\API\AuditLogController;
use App\Http\Controllers\API\CommissionController;
use App\Http\Controllers\API\CompanyOfferController;
use App\Http\Controllers\API\CompanyPickupController;
use App\Http\Controllers\API\CompanyDashboardController;
use App\Http\Controllers\API\PayoutController;
use App\Http\Controllers\API\PickupController;
use App\Http\Controllers\API\PickupLocationController;
use App\Http\Controllers\API\QrTagController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\MeController;
use App\Http\Controllers\API\PasswordResetController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\SystemSettingController;
use App\Http\Controllers\API\WalletTransactionController;
use App\Http\Controllers\API\WasteAiAnalysisController;
use App\Http\Controllers\API\WasteCategoryController;
use App\Http\Controllers\API\WasteListingController;
use App\Http\Controllers\API\WasteOfferController;
use App\Http\Controllers\API\WastePhotoController;
use App\Http\Controllers\API\WasteVerificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DriverPickupController;
use App\Http\Controllers\API\DeepWasteListingController;
use App\Http\Controllers\API\LocalWasteVisionController;
use App\Http\Controllers\API\GeminiStatusController;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('login', [RegisterController::class, 'login']);
Route::post('company-register', [RegisterController::class, 'companyRegister']);
Route::post('forgot-password/send-otp', [PasswordResetController::class, 'sendOtp']);
Route::post('forgot-password/verify-otp', [PasswordResetController::class, 'verifyOtp']);

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class);
    Route::get('company/dashboard', [CompanyDashboardController::class, 'summary']);
    Route::get('company/waste-listings', [CompanyDashboardController::class, 'listings']);
    Route::get('company/offers', [CompanyOfferController::class, 'index']);
    Route::get('company/pickups', [CompanyPickupController::class, 'index']);
    Route::get('company/pickups/{id}', [CompanyPickupController::class, 'show']);
    Route::get('company/waste-listings/{id}', [CompanyDashboardController::class, 'listingDetails']);

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::get('users', [RegisterController::class, 'users']);
    Route::post('register', [RegisterController::class, 'register']);

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::apiResource('roles', RoleController::class);

    /*
    |--------------------------------------------------------------------------
    | Waste Categories
    |--------------------------------------------------------------------------
    | Important:
    | Put custom category routes before apiResource so Laravel does not treat
    | "sync-standard-library" or "ai-suggest" as a waste category ID.
    */

    Route::post('waste-categories/sync-standard-library', [WasteCategoryController::class, 'syncStandardLibrary']);
    Route::post('waste-categories/ai-suggest', [WasteCategoryController::class, 'aiSuggest']);
    Route::apiResource('waste-categories', WasteCategoryController::class);

    /*
    |--------------------------------------------------------------------------
    | Waste Listings
    |--------------------------------------------------------------------------
    | Important:
    | Put ai-preview and ai-create before apiResource so Laravel does not treat
    | "ai-preview" or "ai-create" as a waste listing ID.
    */

    Route::post('waste-listings/ai-preview', [WasteListingController::class, 'aiPreview']);
    Route::post('waste-listings/ai-create', [WasteListingController::class, 'aiCreate']);
    Route::apiResource('waste-listings', WasteListingController::class);

    /*
    |--------------------------------------------------------------------------
    | Waste Photos, AI Analysis, Verification
    |--------------------------------------------------------------------------
    */

    Route::apiResource('waste-photos', WastePhotoController::class);
    Route::apiResource('waste-ai-analyses', WasteAiAnalysisController::class);
    Route::apiResource('waste-verifications', WasteVerificationController::class);

    /*
    |--------------------------------------------------------------------------
    | Waste Offers
    |--------------------------------------------------------------------------
    */

    Route::apiResource('waste-offers', WasteOfferController::class);
    Route::post('waste-offers/{wasteOffer}/respond', [WasteOfferController::class, 'respond']);

    /*
    |--------------------------------------------------------------------------
    | Pickups
    |--------------------------------------------------------------------------
    */

    Route::apiResource('pickups', PickupController::class);
    Route::post('pickups/{pickup}/confirm-institution', [PickupController::class, 'confirmInstitution']);
    Route::post('pickups/{pickup}/confirm-staff', [PickupController::class, 'confirmStaff']);

    Route::apiResource('pickup-locations', PickupLocationController::class);

    /*
    |--------------------------------------------------------------------------
    | QR Tags
    |--------------------------------------------------------------------------
    */

    Route::apiResource('qr-tags', QrTagController::class);
    Route::post('qr-tags/{qrTag}/scan', [QrTagController::class, 'scan']);

    /*
    |--------------------------------------------------------------------------
    | Finance
    |--------------------------------------------------------------------------
    */

    Route::apiResource('wallet-transactions', WalletTransactionController::class);
    Route::apiResource('payouts', PayoutController::class);
    Route::apiResource('commissions', CommissionController::class);

    /*
    |--------------------------------------------------------------------------
    | System
    |--------------------------------------------------------------------------
    */

    Route::apiResource('audit-logs', AuditLogController::class);
    Route::apiResource('system-settings', SystemSettingController::class);
});

Route::prefix('driver')->middleware('auth:sanctum')->group(function () {
    Route::get('dashboard', [DriverPickupController::class, 'dashboard']);
    Route::get('pickups', [DriverPickupController::class, 'index']);
    Route::get('pickups/{id}', [DriverPickupController::class, 'show']);
    Route::post('pickups/{id}/scan-qr', [DriverPickupController::class, 'scanQr']);
    Route::post('pickups/{id}/confirm-collection', [DriverPickupController::class, 'confirmCollection']);
    Route::post('pickups/{id}/complete', [DriverPickupController::class, 'complete']);
    Route::get('history', [DriverPickupController::class, 'history']);
});

Route::post('waste-listings/deep-ai-preview', [DeepWasteListingController::class, 'deepAiPreview'])->middleware('auth:sanctum');

Route::post('waste-listings/local-ai-preview', [LocalWasteVisionController::class, 'localAiPreview'])->middleware('auth:sanctum');

Route::get('ai/gemini-status', [GeminiStatusController::class, 'status'])->middleware('auth:sanctum');
