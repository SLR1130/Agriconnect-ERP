<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\AccountSetupController;
use App\Http\Controllers\ERP\AdministrationController;
use App\Http\Controllers\ERP\DashboardController;
use App\Http\Controllers\FFRMSController;
use App\Http\Controllers\FarmerPortalController;
use App\Http\Controllers\APMSController;
use App\Http\Controllers\SetupStatusController;
use App\Http\Controllers\AISMSController;
use App\Http\Controllers\AASMSController;
use App\Http\Controllers\AEAMSController;
use App\Http\Controllers\ABPMSController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SetupStatusController::class, 'home'])->name('home');

// Detailed configuration diagnostics require an authenticated user.
Route::get('/setup-test', [SetupStatusController::class, 'show'])
    ->middleware(['auth', 'active'])
    ->name('setup-test');

// Authentication Routes
Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::get('/account/setup/{token}', [AccountSetupController::class, 'create'])->name('account.setup.create');
Route::post('/account/setup', [AccountSetupController::class, 'store'])->middleware('throttle:6,1')->name('account.setup.store');
Route::get('/register', [FarmerPortalController::class, 'create'])->name('farmer.register');
Route::post('/register', [FarmerPortalController::class, 'store'])->middleware('throttle:6,1')->name('farmer.register.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
    Route::get('/mao/dashboard', [DashboardController::class, 'mao'])->name('mao.dashboard');
    Route::get('/overview', [DashboardController::class, 'overview'])->name('overview');
    Route::post('/assistance/programs/{program}/apply', [AASMSController::class, 'apply'])->name('farmer.assistance.apply');
    Route::post('/assistance/applications/{application}/resubmit', [AASMSController::class, 'resubmit'])->name('farmer.assistance.resubmit');
    Route::get('/farmer/assistance', [AASMSController::class, 'farmerPrograms'])->middleware('can:farmer-portal')->name('farmer.assistance');

    Route::prefix('farmer')->middleware('can:farmer-portal')->group(function () {
        Route::get('/', [FarmerPortalController::class, 'dashboard'])->name('farmer.portal');
        Route::patch('/profile', [FarmerPortalController::class, 'updateProfile'])->name('farmer.profile.update');
        Route::post('/farms', [FarmerPortalController::class, 'storeFarm'])->name('farmer.farms.store');
        Route::patch('/farms/{farm}', [FarmerPortalController::class, 'updateFarm'])->name('farmer.farms.update');
    });

    // Module Routes
    Route::prefix('modules')->group(function () {
        Route::get('/ffrms', [FFRMSController::class, 'registrations'])->name('modules.ffrms.dashboard');
        Route::post('/ffrms/registrations/{farmer}/review', [FFRMSController::class, 'review'])->name('modules.ffrms.review');
        Route::patch('/ffrms/registrations/{farmer}', [FFRMSController::class, 'updateFarmer'])->name('modules.ffrms.registrations.update');
        Route::patch('/ffrms/farms/{farm}', [FFRMSController::class, 'updateFarm'])->name('modules.ffrms.farms.update');
        Route::get('/ffrms/export', [FFRMSController::class, 'export'])->name('modules.ffrms.export');
        Route::post('/ffrms/associations', [FFRMSController::class, 'storeAssociation'])->name('modules.ffrms.associations.store');
        Route::post('/ffrms/associations/{association}/members', [FFRMSController::class, 'addMember'])->name('modules.ffrms.associations.members.store');

        Route::get('/apms', [APMSController::class, 'index'])->name('modules.apms.dashboard');
        Route::post('/apms/seasons', [APMSController::class, 'season'])->name('modules.apms.seasons.store');
        Route::patch('/apms/seasons/{season}', [APMSController::class, 'updateSeason'])->name('modules.apms.seasons.update');
        Route::post('/apms/crops', [APMSController::class, 'crop'])->name('modules.apms.crops.store');
        Route::patch('/apms/crops/{record}', [APMSController::class, 'updateCrop'])->name('modules.apms.crops.update');
        Route::post('/apms/livestock', [APMSController::class, 'livestock'])->name('modules.apms.livestock.store');
        Route::patch('/apms/livestock/{record}', [APMSController::class, 'updateLivestock'])->name('modules.apms.livestock.update');
        Route::post('/apms/fisheries', [APMSController::class, 'fishery'])->name('modules.apms.fisheries.store');
        Route::patch('/apms/fisheries/{record}', [APMSController::class, 'updateFishery'])->name('modules.apms.fisheries.update');
        Route::post('/apms/incidents', [APMSController::class, 'incident'])->name('modules.apms.incidents.store');
        Route::patch('/apms/incidents/{record}', [APMSController::class, 'updateIncident'])->name('modules.apms.incidents.update');
        Route::post('/apms/monitoring', [APMSController::class, 'monitoring'])->name('modules.apms.monitoring.store');
        Route::patch('/apms/monitoring/{record}', [APMSController::class, 'updateMonitoring'])->name('modules.apms.monitoring.update');
        Route::get('/apms/export', [APMSController::class, 'export'])->name('modules.apms.export');

        Route::get('/aisms', [AISMSController::class, 'index'])->name('modules.aisms.dashboard');
        Route::post('/aisms/items', [AISMSController::class, 'item'])->name('modules.aisms.items.store');
        Route::post('/aisms/receipts', [AISMSController::class, 'receive'])->name('modules.aisms.receipts.store');
        Route::post('/aisms/releases', [AISMSController::class, 'release'])->name('modules.aisms.releases.store');
        Route::post('/aisms/adjustments', [AISMSController::class, 'adjust'])->name('modules.aisms.adjustments.store');
        Route::get('/aisms/export', [AISMSController::class, 'export'])->name('modules.aisms.export');

        Route::get('/aasms', [AASMSController::class, 'index'])->name('modules.aasms.dashboard');
        Route::post('/aasms/programs', [AASMSController::class, 'storeProgram'])->name('modules.aasms.programs.store');
        Route::patch('/aasms/programs/{program}', [AASMSController::class, 'updateProgram'])->name('modules.aasms.programs.update');
        Route::post('/aasms/programs/{program}/toggle', [AASMSController::class, 'toggleProgram'])->name('modules.aasms.programs.toggle');
        Route::post('/aasms/applications/{application}/review', [AASMSController::class, 'review'])->name('modules.aasms.review');
        Route::post('/aasms/applications/{application}/distribute', [AASMSController::class, 'distribute'])->name('modules.aasms.distribute');
        Route::post('/aasms/programs/{program}/apply', [AASMSController::class, 'apply'])->name('modules.aasms.apply');
        Route::get('/aasms/export', [AASMSController::class, 'export'])->name('modules.aasms.export');

        Route::get('/aeams', [AEAMSController::class, 'index'])->name('modules.aeams.dashboard');
        Route::post('/aeams/equipment', [AEAMSController::class, 'storeAsset'])->name('modules.aeams.equipment.store');
        Route::get('/aeams/equipment/{equipmentAsset}', [AEAMSController::class, 'show'])->name('modules.aeams.equipment.show');
        Route::patch('/aeams/equipment/{equipmentAsset}', [AEAMSController::class, 'updateAsset'])->name('modules.aeams.equipment.update');
        Route::post('/aeams/equipment/{equipmentAsset}/archive', [AEAMSController::class, 'archiveAsset'])->name('modules.aeams.equipment.archive');
        Route::post('/aeams/equipment/{equipmentAsset}/restore', [AEAMSController::class, 'restoreAsset'])->name('modules.aeams.equipment.restore');
        Route::get('/aeams/export', [AEAMSController::class, 'export'])->name('modules.aeams.export');
        Route::post('/aeams/requests', [AEAMSController::class, 'storeRequest'])->name('modules.aeams.requests.store');
        Route::post('/aeams/requests/{equipmentRequest}/review', [AEAMSController::class, 'review'])->name('modules.aeams.requests.review');
        Route::post('/aeams/requests/{equipmentRequest}/cancel', [AEAMSController::class, 'cancelRequest'])->name('modules.aeams.requests.cancel');
        Route::post('/aeams/requests/{equipmentRequest}/issue', [AEAMSController::class, 'issue'])->name('modules.aeams.requests.issue');
        Route::post('/aeams/requests/{equipmentRequest}/return', [AEAMSController::class, 'returnEquipment'])->name('modules.aeams.requests.return');
        Route::post('/aeams/equipment/{equipmentAsset}/maintenance', [AEAMSController::class, 'startMaintenance'])->name('modules.aeams.maintenance.store');
        Route::post('/aeams/maintenance/{equipmentMaintenance}/complete', [AEAMSController::class, 'completeMaintenance'])->name('modules.aeams.maintenance.complete');

        Route::get('/abpms', [ABPMSController::class, 'index'])->name('modules.abpms.dashboard');
        Route::post('/abpms/periods', [ABPMSController::class, 'storePeriod'])->name('modules.abpms.periods.store');
        Route::post('/abpms/allocations', [ABPMSController::class, 'storeAllocation'])->name('modules.abpms.allocations.store');
        Route::post('/abpms/allocations/{budgetAllocation}/adjust', [ABPMSController::class, 'adjustAllocation'])->name('modules.abpms.allocations.adjust');
        Route::post('/abpms/allocations/{budgetAllocation}/expenditure', [ABPMSController::class, 'expenditure'])->name('modules.abpms.allocations.expenditure');
        Route::post('/abpms/suppliers', [ABPMSController::class, 'storeSupplier'])->name('modules.abpms.suppliers.store');
        Route::post('/abpms/requests', [ABPMSController::class, 'storeRequest'])->name('modules.abpms.requests.store');
        Route::post('/abpms/requests/{procurementRequest}/review', [ABPMSController::class, 'reviewRequest'])->name('modules.abpms.requests.review');
        Route::post('/abpms/requests/{procurementRequest}/purchase-orders', [ABPMSController::class, 'storePurchaseOrder'])->name('modules.abpms.purchase-orders.store');
        Route::post('/abpms/purchase-orders/{purchaseOrder}/approve', [ABPMSController::class, 'approvePurchaseOrder'])->name('modules.abpms.purchase-orders.approve');
        Route::post('/abpms/purchase-orders/{purchaseOrder}/order', [ABPMSController::class, 'orderPurchaseOrder'])->name('modules.abpms.purchase-orders.order');
        Route::post('/abpms/purchase-orders/{purchaseOrder}/cancel', [ABPMSController::class, 'cancelPurchaseOrder'])->name('modules.abpms.purchase-orders.cancel');
        Route::post('/abpms/receipts', [ABPMSController::class, 'receive'])->name('modules.abpms.receipts.store');
        Route::post('/abpms/purchase-order-items/{purchaseOrderItem}/link-equipment', [ABPMSController::class, 'linkEquipment'])->name('modules.abpms.purchase-order-items.link-equipment');
        Route::get('/abpms/export', [ABPMSController::class, 'export'])->name('modules.abpms.export');
    });

    // ERP Administration Routes
    Route::prefix('erp')->group(function () {
        Route::get('/users', [AdministrationController::class, 'users'])->name('erp.users.index');
        Route::get('/users/create', [AdministrationController::class, 'createUser'])->name('erp.users.create');
        Route::post('/users', [AdministrationController::class, 'storeUser'])->name('erp.users.store');
        Route::get('/users/{user}', [AdministrationController::class, 'showUser'])->name('erp.users.show');
        Route::get('/users/{user}/edit', [AdministrationController::class, 'editUser'])->name('erp.users.edit');
        Route::put('/users/{user}', [AdministrationController::class, 'updateUser'])->name('erp.users.update');
        Route::put('/users/{user}/role', [AdministrationController::class, 'updateRole'])->name('erp.users.role.update');
        Route::patch('/users/{user}/status', [AdministrationController::class, 'updateStatus'])->name('erp.users.status.update');
        Route::put('/users/{user}/module-access', [AdministrationController::class, 'updateModuleAccess'])->name('erp.users.module-access.update');
        Route::post('/users/{user}/password-setup', [AdministrationController::class, 'issuePasswordSetup'])->name('erp.users.password-setup');
        Route::get('/roles', [AdministrationController::class, 'roles'])->name('erp.roles.index');
        Route::patch('/roles/{role}/status', [AdministrationController::class, 'updateRoleStatus'])->name('erp.roles.status.update');
        Route::get('/modules', [AdministrationController::class, 'modules'])->name('erp.modules.index');
        Route::patch('/modules/{module}', [AdministrationController::class, 'updateModule'])->name('erp.modules.update');
        Route::get('/reports', [AdministrationController::class, 'reports'])->name('erp.reports.index');
        Route::get('/activity-logs', [AdministrationController::class, 'activityLogs'])->name('erp.activity-logs.index');
        Route::get('/settings', [AdministrationController::class, 'settings'])->name('erp.settings.index');
    });
});
