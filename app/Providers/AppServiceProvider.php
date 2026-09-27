<?php

namespace App\Providers;

use App\Events\NotificationCenterChanged;
use App\Listeners\UpdateLastSeenOnLogout;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\Quiz;
use App\Models\QuizAssignment;
use App\Models\QuizAttempt;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Policies\Chat\ChatRoomPolicy;
use App\Policies\Chat\MessagePolicy;
use App\Policies\QuizAssignmentPolicy;
use App\Policies\QuizAttemptPolicy;
use App\Policies\QuizPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Logout;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return rtrim(config('app.url'), '/').'/reset-password?'.http_build_query(['email' => $user->email]).'#token='.rawurlencode($token);
        });

        \Illuminate\Support\Facades\RateLimiter::for('auth-login', function (\Illuminate\Http\Request $request): array {
            $email = hash('sha256', strtolower(is_string($request->input('email')) ? $request->input('email') : ''));

            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('login:'.$email.':'.$request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('login-account:'.$email),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });
        \Illuminate\Support\Facades\RateLimiter::for('auth-otp', function (\Illuminate\Http\Request $request): array {
            $id = $request->user()?->id ?? $request->session()->get('pending_2fa_user_id', 'guest');

            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('otp-account:'.$id),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by('otp-ip:'.$request->ip()),
            ];
        });
        \Illuminate\Support\Facades\RateLimiter::for('auth-recovery', function (\Illuminate\Http\Request $request): array {
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('recovery-ip:'.$request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by('recovery-email:'.hash('sha256', strtolower(is_string($request->input('email')) ? $request->input('email') : ''))),
            ];
        });
        Event::listen(Logout::class, UpdateLastSeenOnLogout::class);
        DatabaseNotification::created(function (DatabaseNotification $notification): void {
            if ($notification->type !== ActivityNotification::class) {
                return;
            }

            $notification->getConnection()->afterCommit(function () use ($notification): void {
                $roomId = $notification->data['room_id'] ?? null;
                rescue(fn () => NotificationCenterChanged::dispatch((int) $notification->notifiable_id, $roomId ? (int) $roomId : null), report: true);
            });
        });
        Gate::policy(ChatRoom::class, ChatRoomPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(QuizAssignment::class, QuizAssignmentPolicy::class);
        Gate::policy(QuizAttempt::class, QuizAttemptPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(SchoolClass::class, \App\Policies\SchoolClassPolicy::class);

        Gate::define('access-admin', function (User $user): bool {
            return Role::manageableNames($user) !== [];
        });
        Gate::define('manage-classes', function (User $user): bool {
            return Gate::forUser($user)->allows('create', SchoolClass::class);
        });

        Gate::define('manage-school-class', function (User $user, SchoolClass $schoolClass): bool {
            return Gate::forUser($user)->allows('manageMembers', $schoolClass);
        });

        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();
    }
}
