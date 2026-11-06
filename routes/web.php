<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalTemplateController;
use App\Http\Controllers\CentralTenderApplicationController;
use App\Http\Controllers\ProcurementApplicationController;
use App\Http\Controllers\ProcurementArchiveController;
use App\Http\Controllers\ProcurementArchiveRuleController;
use App\Http\Controllers\ProcurementAttachmentController;
use App\Http\Controllers\ProcurementClassificationRuleController;
use App\Http\Controllers\ProcurementExecutionController;
use App\Http\Controllers\ProcurementHistoryController;
use App\Http\Controllers\ProcurementStatisticsController;
use App\Http\Controllers\SchoolCasCallbackController;
use App\Http\Controllers\SchoolCasLoginController;
use App\Http\Controllers\SchoolIdentityController;
use App\Http\Controllers\SchoolOrganizationController;
use App\Http\Middleware\EnsureCasConfigured;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Lovevisss\LaravelZufedfcCas\Http\Controllers\LogoutController as CasLogoutController;
use Lovevisss\LaravelZufedfcCas\Http\Controllers\SloController as CasSloController;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('access-denied', 'auth/AccessDenied')->name('access-denied');
Route::get('auth/cas/login', SchoolCasLoginController::class)->middleware(EnsureCasConfigured::class)->name('cas.login');
Route::get('auth/cas/callback', SchoolCasCallbackController::class)->middleware(EnsureCasConfigured::class)->name('cas.callback');
Route::post('auth/cas/logout', CasLogoutController::class)->middleware(EnsureCasConfigured::class)->name('cas.logout');
Route::match(['get', 'post'], 'auth/cas/slo', CasSloController::class)
    ->withoutMiddleware(PreventRequestForgery::class)->name('cas.slo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('admin/school-identities', [SchoolIdentityController::class, 'index'])->name('school-identities.index');
    Route::put('admin/school-identities/{user}', [SchoolIdentityController::class, 'update'])->name('school-identities.update');
    Route::get('admin/organization', [SchoolOrganizationController::class, 'index'])->name('organization.index');
    Route::post('admin/organization/sync', [SchoolOrganizationController::class, 'sync'])->name('organization.sync');
    Route::put('admin/organization/positions/{code}/roles', [SchoolOrganizationController::class, 'updatePositionRoles'])->name('organization.position-roles.update');
    Route::get('procurement/applications', [ProcurementApplicationController::class, 'index'])->name('procurement.applications.index');
    Route::get('procurement/applications/create', [ProcurementApplicationController::class, 'create'])->name('procurement.applications.create');
    Route::post('procurement/applications', [ProcurementApplicationController::class, 'store'])->name('procurement.applications.store');
    Route::get('procurement/applications/{application}/edit', [ProcurementApplicationController::class, 'edit'])->name('procurement.applications.edit');
    Route::put('procurement/applications/{application}', [ProcurementApplicationController::class, 'update'])->name('procurement.applications.update');
    Route::get('procurement/tenders/create', [CentralTenderApplicationController::class, 'create'])->name('procurement.tenders.create');
    Route::post('procurement/tenders', [CentralTenderApplicationController::class, 'store'])->name('procurement.tenders.store');
    Route::get('procurement/tenders/{application}/edit', [CentralTenderApplicationController::class, 'edit'])->name('procurement.tenders.edit');
    Route::put('procurement/tenders/{application}', [CentralTenderApplicationController::class, 'update'])->name('procurement.tenders.update');
    Route::get('procurement/attachments/{attachment}/download', [ProcurementAttachmentController::class, 'download'])->name('procurement.attachments.download');
    Route::get('procurement/classification-rules', [ProcurementClassificationRuleController::class, 'index'])->name('procurement.classification-rules.index');
    Route::post('procurement/classification-rules', [ProcurementClassificationRuleController::class, 'store'])->name('procurement.classification-rules.store');
    Route::get('procurement/approval-templates', [ApprovalTemplateController::class, 'index'])->name('procurement.approval-templates.index');
    Route::get('procurement/history', [ProcurementHistoryController::class, 'index'])->name('procurement.history.index');
    Route::get('procurement/statistics', [ProcurementStatisticsController::class, 'index'])->name('procurement.statistics.index');
    Route::get('procurement/statistics/export', [ProcurementStatisticsController::class, 'export'])->name('procurement.statistics.export');
    Route::get('procurement/archive-rules', [ProcurementArchiveRuleController::class, 'index'])->name('procurement.archive-rules.index');
    Route::put('procurement/archive-rules', [ProcurementArchiveRuleController::class, 'update'])->name('procurement.archive-rules.update');
    Route::get('procurement/applications/{application}/archive', [ProcurementArchiveController::class, 'show'])->name('procurement.archives.show');
    Route::post('procurement/applications/{application}/archive', [ProcurementArchiveController::class, 'store'])->name('procurement.archives.store');
    Route::post('procurement/history/{application}/copy', [ProcurementHistoryController::class, 'copy'])->name('procurement.history.copy');
    Route::get('procurement/applications/{application}/execution', [ProcurementExecutionController::class, 'show'])->name('procurement.executions.show');
    Route::put('procurement/applications/{application}/execution', [ProcurementExecutionController::class, 'save'])->name('procurement.executions.save');
    Route::put('procurement/applications/{application}/executor', [ProcurementExecutionController::class, 'assign'])->name('procurement.executions.assign');
    Route::post('procurement/approval-templates', [ApprovalTemplateController::class, 'store'])->name('procurement.approval-templates.store');
    Route::get('procurement/approvals', [ApprovalController::class, 'inbox'])->name('procurement.approvals.inbox');
    Route::post('procurement/approvals/{step}/decision', [ApprovalController::class, 'decide'])->name('procurement.approvals.decide');
    Route::put('procurement/approvals/{step}/assignee', [ApprovalController::class, 'reassign'])->name('procurement.approvals.reassign');
    Route::get('procurement/applications/{application}', [ApprovalController::class, 'show'])->name('procurement.applications.show');
});

require __DIR__.'/settings.php';
