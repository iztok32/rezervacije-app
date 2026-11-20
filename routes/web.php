<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BookingFlowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManageBookingController;
use App\Http\Controllers\MyBookingsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ── Javni del: pregled ponudbe, rezervacija (tudi kot gost), upravljanje rezervacije ──
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/locale', LocaleController::class)->name('locale.update');

Route::get('/book/{resourceType}', [BookingFlowController::class, 'show'])->name('book.show');
Route::get('/book/{resourceType}/availability', [BookingFlowController::class, 'availability'])
    ->middleware('throttle:120,1')
    ->name('book.availability');
Route::post('/book/{resourceType}', [BookingFlowController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('book.store');

// Ogled in odpoved: prijavljen lastnik, osebje ali gost s skrivnim žetonom v povezavi
Route::get('/bookings/{booking}', [ManageBookingController::class, 'show'])->name('bookings.show');
Route::post('/bookings/{booking}/cancel', [ManageBookingController::class, 'cancel'])
    ->middleware('throttle:20,1')
    ->name('bookings.cancel');

// ── Prijavljeni uporabniki ──
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-bookings', [MyBookingsController::class, 'index'])->name('my-bookings');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ── Osebje: rezervacije in koledar ──
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('bookings', [Admin\BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [Admin\BookingController::class, 'show'])->name('bookings.show');
    Route::patch('bookings/{booking}', [Admin\BookingController::class, 'update'])->name('bookings.update');
    Route::post('bookings/{booking}/status', [Admin\BookingController::class, 'status'])->name('bookings.status');

    Route::get('calendar', [Admin\CalendarController::class, 'index'])->name('calendar');
    Route::get('customers/search', [Admin\CustomerSearchController::class, 'index'])->name('customers.search');
});

// ── Administrator: ponudba (tipi virov, viri, storitve, termini, delovni čas), uporabniki, nastavitve ──
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('resource-types', [Admin\ResourceTypeController::class, 'index'])->name('resource-types.index');
    Route::get('resource-types/create', [Admin\ResourceTypeController::class, 'create'])->name('resource-types.create');
    Route::post('resource-types', [Admin\ResourceTypeController::class, 'store'])->name('resource-types.store');
    Route::post('resource-types/reorder', [Admin\ResourceTypeController::class, 'reorder'])->name('resource-types.reorder');
    Route::get('resource-types/{resourceType}', [Admin\ResourceTypeController::class, 'edit'])->name('resource-types.edit');
    Route::put('resource-types/{resourceType}', [Admin\ResourceTypeController::class, 'update'])->name('resource-types.update');
    Route::delete('resource-types/{resourceType}', [Admin\ResourceTypeController::class, 'destroy'])->name('resource-types.destroy');

    Route::post('resource-types/{resourceType}/resources', [Admin\ResourceController::class, 'store'])->name('resources.store');
    // POST (namesto PUT), ker obrazec lahko vsebuje sliko
    Route::post('resources/{resource}', [Admin\ResourceController::class, 'update'])->name('resources.update');
    Route::delete('resources/{resource}', [Admin\ResourceController::class, 'destroy'])->name('resources.destroy');

    Route::post('resource-types/{resourceType}/services', [Admin\ServiceController::class, 'store'])->name('services.store');
    Route::put('services/{service}', [Admin\ServiceController::class, 'update'])->name('services.update');
    Route::delete('services/{service}', [Admin\ServiceController::class, 'destroy'])->name('services.destroy');

    Route::post('resource-types/{resourceType}/timeslots', [Admin\TimeslotController::class, 'store'])->name('timeslots.store');
    Route::put('timeslots/{timeslot}', [Admin\TimeslotController::class, 'update'])->name('timeslots.update');
    Route::delete('timeslots/{timeslot}', [Admin\TimeslotController::class, 'destroy'])->name('timeslots.destroy');

    Route::put('resource-types/{resourceType}/working-hours', [Admin\WorkingHoursController::class, 'update'])->name('working-hours.update');

    Route::get('closures', [Admin\ClosureController::class, 'index'])->name('closures.index');
    Route::post('closures', [Admin\ClosureController::class, 'store'])->name('closures.store');
    Route::delete('closures/{closure}', [Admin\ClosureController::class, 'destroy'])->name('closures.destroy');

    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    Route::get('settings', [Admin\SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
});

require __DIR__.'/auth.php';
