<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolClassChannelMessageController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ROOT
Route::get('/', function () {
    return redirect('/login');
});
// Login Page
Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

// Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])
    ->middleware(['auth', 'twofactor.setup'])
    ->name('home');

Route::middleware(['auth', 'twofactor.setup', 'can:access-admin'])->group(function () {
    Route::resource('roles', RoleController::class)->only('index');
    Route::resource('users', UserController::class);

    Route::post('users/{user}/recovery', [PasswordRecoveryController::class, 'assist'])
        ->middleware('throttle:auth-otp')->name('users.recovery');

    Route::post('users/{user}/two-factor/generate', [UserController::class, 'generateTwoFactorQr'])
        ->name('users.two-factor.generate');

    Route::post('users/{user}/two-factor/enable', [UserController::class, 'enableTwoFactor'])
        ->name('users.two-factor.enable');

    Route::post('users/{user}/two-factor/disable', [UserController::class, 'disableTwoFactor'])
        ->name('users.two-factor.disable');
});

// Login Submit
Route::post('/login', [LoginController::class, 'login'])
    ->middleware(['guest', 'throttle:auth-login'])
    ->name('login.submit');

// logout
Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Change Password Page
Route::get('/change-password', [LoginController::class, 'showChangePassword'])
    ->middleware('auth')->name('password.change');

// Change Password Submit
Route::post('/change-password', [LoginController::class, 'changePassword'])
    ->middleware(['auth', 'throttle:auth-otp'])->name('password.change.submit');

// School class
Route::middleware(['auth', 'twofactor.setup'])->scopeBindings()->group(function () {
    Route::get('/classes', [SchoolClassController::class, 'index'])->name('classes.index');
    Route::post('/classes', [SchoolClassController::class, 'store'])->middleware('can:manage-classes')->name('classes.store');
    Route::post('/classes/join', [SchoolClassController::class, 'join'])->name('classes.join');
    Route::get('/classes/{schoolClass}', [SchoolClassController::class, 'show'])->name('classes.show');
    Route::get('/classes/{schoolClass}/avatar', [SchoolClassController::class, 'avatar'])->name('classes.avatar');
    Route::patch('/classes/{schoolClass}', [SchoolClassController::class, 'update'])->name('classes.update');
    Route::post('/classes/{schoolClass}/enroll', [SchoolClassController::class, 'enroll'])->name('classes.enroll');
    Route::post('/classes/{schoolClass}/owner', [SchoolClassController::class, 'transferOwnership'])->name('classes.owner');

    Route::get('/classes/{schoolClass}/channels/{channel}', [SchoolClassChannelMessageController::class, 'show'])
        ->name('classes.channels.show');
    Route::post('/classes/{schoolClass}/channels/{channel}/messages', [SchoolClassChannelMessageController::class, 'store'])
        ->middleware('throttle:messages')
        ->name('classes.channels.messages.store');
    Route::get('/classes/{schoolClass}/channels/{channel}/messages', [SchoolClassChannelMessageController::class, 'index'])
        ->name('classes.channels.messages.index');

    Route::post('/classes/{schoolClass}/members', [SchoolClassController::class, 'addMember'])
        ->middleware('can:manage-school-class,schoolClass')
        ->name('classes.members.store');

    Route::delete('/classes/{schoolClass}/members/{user}', [SchoolClassController::class, 'removeMember'])
        ->middleware('can:manage-school-class,schoolClass')
        ->name('classes.members.destroy');

    Route::post('/classes/{schoolClass}/leave', [SchoolClassController::class, 'leave'])
        ->name('classes.leave');

    Route::post('/classes/{schoolClass}/channels', [SchoolClassController::class, 'addChannel'])
        ->middleware('can:manageChannels,schoolClass')
        ->name('classes.channels.store');

    Route::delete('/classes/{schoolClass}/channels/{channel}', [SchoolClassController::class, 'removeChannel'])
        ->middleware('can:manageChannels,schoolClass')
        ->name('classes.channels.destroy');
});

// 2fa
Route::get('/verify-2fa', [LoginController::class, 'showTwoFactorChallenge'])->middleware('guest')->name('2fa.challenge');
Route::post('/verify-2fa', [LoginController::class, 'verifyTwoFactor'])->middleware(['guest', 'throttle:auth-otp'])->name('2fa.challenge.submit');

Route::get('/setup-2fa', [LoginController::class, 'showTwoFactorSetup'])
    ->middleware('auth')
    ->name('2fa.setup');

Route::post('/setup-2fa', [LoginController::class, 'enableTwoFactor'])
    ->middleware(['auth', 'throttle:auth-otp'])
    ->name('2fa.setup.submit');

Route::post('/disable-2fa', [LoginController::class, 'disableTwoFactor'])
    ->middleware(['auth', 'throttle:auth-otp'])
    ->name('2fa.disable');

require __DIR__.'/chat.php';

Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'email'])->middleware('throttle:auth-recovery')->name('password.email');
    Route::get('/reset-password', [PasswordRecoveryController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])->middleware('throttle:auth-recovery')->name('password.update');
});
