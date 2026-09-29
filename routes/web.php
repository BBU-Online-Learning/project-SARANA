<?php

use App\Http\Controllers\AcademicCatalogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClassAnnouncementController;
use App\Http\Controllers\ClassAttendanceController;
use App\Http\Controllers\ClassMeetingController;
use App\Http\Controllers\ClassMeetingJoinRequestController;
use App\Http\Controllers\CourseworkAssignmentController;
use App\Http\Controllers\CourseworkAttachmentController;
use App\Http\Controllers\CourseworkSubmissionController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LiveKitMeetingController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Quiz\AssessmentController;
use App\Http\Controllers\Quiz\QuizAssignmentController;
use App\Http\Controllers\Quiz\QuizAttemptController;
use App\Http\Controllers\Quiz\QuizController;
use App\Http\Controllers\Quiz\QuizQuestionController;
use App\Http\Controllers\Quiz\QuizResultController;
use App\Http\Controllers\ReportingPeriodController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolClassChannelMessageController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubjectTeacherController;
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

Route::middleware(['auth', 'twofactor.setup'])->group(function (): void {
    Route::get('/search', GlobalSearchController::class)->middleware('throttle:30,1')->name('search.index');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::patch('/settings/application', [SettingsController::class, 'updateApplication'])->name('settings.application.update');
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-room', [NotificationCenterController::class, 'readRoom'])->name('notifications.read-room');
    Route::patch('/notifications/{notification}/read', [NotificationCenterController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'readAll'])->name('notifications.read-all');
    Route::delete('/notifications', [NotificationCenterController::class, 'clearAll'])->name('notifications.clear-all');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/users/{user}/profile', [ProfileController::class, 'show'])->name('users.profile');
});

Route::middleware(['auth', 'twofactor.setup', 'can:access-admin'])->group(function () {
    Route::get('/academics', [AcademicCatalogController::class, 'index'])->name('academics.index');
    Route::post('/academics/years', [AcademicCatalogController::class, 'storeYear'])->name('academics.years.store');
    Route::post('/academics/grades', [AcademicCatalogController::class, 'storeGrade'])->name('academics.grades.store');
    Route::post('/academics/subjects', [AcademicCatalogController::class, 'storeSubject'])->name('academics.subjects.store');
    Route::get('/academics/reporting-periods', [ReportingPeriodController::class, 'index'])->name('academics.reporting-periods.index');
    Route::post('/academics/reporting-periods', [ReportingPeriodController::class, 'store'])->name('academics.reporting-periods.store');
    Route::patch('/academics/reporting-periods/{reportingPeriod}', [ReportingPeriodController::class, 'update'])->name('academics.reporting-periods.update');
    Route::post('/academics/reporting-periods/{reportingPeriod}/transition', [ReportingPeriodController::class, 'transition'])->name('academics.reporting-periods.transition');
    Route::get('/academics/classes/{schoolClass}/edit', [AcademicCatalogController::class, 'edit'])->name('academics.classes.edit');
    Route::patch('/academics/classes/{schoolClass}', [AcademicCatalogController::class, 'assignClass'])->name('academics.classes.update');
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
    Route::get('/assessments', [AssessmentController::class, 'overview'])->name('assessments.index');
    Route::get('/classes', [SchoolClassController::class, 'index'])->name('classes.index');
    Route::post('/classes', [SchoolClassController::class, 'store'])->middleware('can:manage-classes')->name('classes.store');
    Route::post('/classes/join', [SchoolClassController::class, 'join'])->name('classes.join');
    Route::get('/classes/{schoolClass}', [SchoolClassController::class, 'show'])->name('classes.show');
    Route::post('/classes/{schoolClass}/subject-teachers', [SubjectTeacherController::class, 'store'])->name('classes.subject-teachers.store');
    Route::post('/classes/{schoolClass}/subject-teachers/{subjectTeacherAssignment}/end', [SubjectTeacherController::class, 'end'])->name('classes.subject-teachers.end');
    Route::get('/classes/{schoolClass}/avatar', [SchoolClassController::class, 'avatar'])->name('classes.avatar');
    Route::patch('/classes/{schoolClass}', [SchoolClassController::class, 'update'])->name('classes.update');
    Route::post('/classes/{schoolClass}/archive', [SchoolClassController::class, 'archive'])->name('classes.archive');
    Route::post('/classes/{schoolClass}/unarchive', [SchoolClassController::class, 'unarchive'])->name('classes.unarchive');
    Route::post('/classes/{schoolClass}/regenerate-code', [SchoolClassController::class, 'regenerateCode'])->name('classes.regenerate-code');
    Route::post('/classes/{schoolClass}/enroll', [SchoolClassController::class, 'enroll'])->name('classes.enroll');
    Route::post('/classes/{schoolClass}/owner', [SchoolClassController::class, 'transferOwnership'])->name('classes.owner');

    Route::get('/classes/{schoolClass}/channels/{channel}', [SchoolClassChannelMessageController::class, 'show'])
        ->name('classes.channels.show');
    Route::post('/classes/{schoolClass}/channels/{channel}/notices', [ClassAnnouncementController::class, 'store'])->name('classes.channels.notices.store');
    Route::patch('/classes/{schoolClass}/channels/{channel}/notices/{notice}', [ClassAnnouncementController::class, 'update'])->name('classes.channels.notices.update');
    Route::post('/classes/{schoolClass}/channels/{channel}/notices/{notice}/schedule', [ClassAnnouncementController::class, 'schedule'])->name('classes.channels.notices.schedule');
    Route::post('/classes/{schoolClass}/channels/{channel}/notices/{notice}/draft', [ClassAnnouncementController::class, 'returnToDraft'])->name('classes.channels.notices.draft');
    Route::post('/classes/{schoolClass}/channels/{channel}/notices/{notice}/publish', [ClassAnnouncementController::class, 'publish'])->name('classes.channels.notices.publish');
    Route::post('/classes/{schoolClass}/channels/{channel}/notices/{notice}/pin', [ClassAnnouncementController::class, 'pin'])->name('classes.channels.notices.pin');
    Route::post('/classes/{schoolClass}/channels/{channel}/notices/{notice}/unpin', [ClassAnnouncementController::class, 'unpin'])->name('classes.channels.notices.unpin');
    Route::post('/classes/{schoolClass}/channels/{channel}/notices/{notice}/archive', [ClassAnnouncementController::class, 'archive'])->name('classes.channels.notices.archive');
    Route::post('/classes/{schoolClass}/channels/{channel}/notices/{notice}/restore', [ClassAnnouncementController::class, 'restore'])->name('classes.channels.notices.restore');
    Route::post('/classes/{schoolClass}/channels/{channel}/messages', [SchoolClassChannelMessageController::class, 'store'])
        ->middleware('throttle:messages')
        ->name('classes.channels.messages.store');
    Route::get('/classes/{schoolClass}/channels/{channel}/messages', [SchoolClassChannelMessageController::class, 'index'])
        ->name('classes.channels.messages.index');
    Route::put('/classes/{schoolClass}/channels/{channel}/read', [SchoolClassChannelMessageController::class, 'markRead'])
        ->middleware('throttle:messages')->name('classes.channels.read');
    Route::patch('/classes/{schoolClass}/channels/{channel}/messages/{message}', [SchoolClassChannelMessageController::class, 'update'])
        ->middleware('throttle:messages')->name('classes.channels.messages.update');
    Route::delete('/classes/{schoolClass}/channels/{channel}/messages/{message}', [SchoolClassChannelMessageController::class, 'destroy'])
        ->withTrashed()->middleware('throttle:messages')->name('classes.channels.messages.destroy');

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

    Route::get('/classes/{schoolClass}/assessments', AssessmentController::class)->name('classes.assessments.index');
    Route::get('/classes/{schoolClass}/quizzes/create', [QuizController::class, 'create'])->name('classes.quizzes.create');
    Route::post('/classes/{schoolClass}/quizzes', [QuizController::class, 'store'])->name('classes.quizzes.store');
    Route::get('/classes/{schoolClass}/quizzes/{quiz}/edit', [QuizController::class, 'edit'])->name('classes.quizzes.edit');
    Route::patch('/classes/{schoolClass}/quizzes/{quiz}', [QuizController::class, 'update'])->name('classes.quizzes.update');
    Route::delete('/classes/{schoolClass}/quizzes/{quiz}', [QuizController::class, 'destroy'])->name('classes.quizzes.destroy');
    Route::get('/classes/{schoolClass}/quizzes/{quiz}/preview', [QuizController::class, 'preview'])->name('classes.quizzes.preview');
    Route::post('/classes/{schoolClass}/quizzes/{quiz}/publish', [QuizController::class, 'publish'])->name('classes.quizzes.publish');
    Route::post('/classes/{schoolClass}/quizzes/{quiz}/questions', [QuizQuestionController::class, 'store'])->name('classes.quizzes.questions.store');
    Route::patch('/classes/{schoolClass}/quizzes/{quiz}/questions/{question}', [QuizQuestionController::class, 'update'])->name('classes.quizzes.questions.update');
    Route::delete('/classes/{schoolClass}/quizzes/{quiz}/questions/{question}', [QuizQuestionController::class, 'destroy'])->name('classes.quizzes.questions.destroy');
    Route::post('/classes/{schoolClass}/quizzes/{quiz}/questions/{question}/duplicate', [QuizQuestionController::class, 'duplicate'])->name('classes.quizzes.questions.duplicate');
    Route::post('/classes/{schoolClass}/quizzes/{quiz}/questions/{question}/move/{direction}', [QuizQuestionController::class, 'move'])->name('classes.quizzes.questions.move');
    Route::get('/classes/{schoolClass}/quizzes/{quiz}/assign', [QuizAssignmentController::class, 'create'])->name('classes.quizzes.assign.create');
    Route::post('/classes/{schoolClass}/quizzes/{quiz}/assign', [QuizAssignmentController::class, 'store'])->name('classes.quizzes.assign.store');
    Route::post('/classes/{schoolClass}/quiz-assignments/{assignment}/release', [QuizAssignmentController::class, 'release'])->name('classes.quiz-assignments.release');
    Route::post('/classes/{schoolClass}/quiz-assignments/{assignment}/start', [QuizAttemptController::class, 'start'])->name('classes.quiz-assignments.start');
    Route::get('/classes/{schoolClass}/quiz-attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('classes.quiz-attempts.show');
    Route::patch('/classes/{schoolClass}/quiz-attempts/{attempt}/answers', [QuizAttemptController::class, 'save'])->name('classes.quiz-attempts.save');
    Route::post('/classes/{schoolClass}/quiz-attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])->name('classes.quiz-attempts.submit');
    Route::get('/classes/{schoolClass}/quiz-attempts/{attempt}/result', [QuizAttemptController::class, 'result'])->name('classes.quiz-attempts.result');
    Route::get('/classes/{schoolClass}/quiz-assignments/{assignment}/results', [QuizResultController::class, 'index'])->name('classes.quiz-assignments.results');
    Route::get('/classes/{schoolClass}/quiz-assignments/{assignment}/results/{attempt}', [QuizResultController::class, 'show'])->name('classes.quiz-assignments.results.show');
    Route::patch('/classes/{schoolClass}/quiz-assignments/{assignment}/results/{attempt}', [QuizResultController::class, 'update'])->name('classes.quiz-assignments.results.update');
});

Route::middleware(['auth', 'twofactor.setup'])->group(function (): void {
    Route::get('/classes/{schoolClass}/coursework', [CourseworkAssignmentController::class, 'index'])->name('classes.coursework.index');
    Route::get('/classes/{schoolClass}/coursework/create', [CourseworkAssignmentController::class, 'create'])->name('classes.coursework.assignments.create');
    Route::post('/classes/{schoolClass}/coursework', [CourseworkAssignmentController::class, 'store'])->name('classes.coursework.assignments.store');
    Route::get('/classes/{schoolClass}/coursework/{assignment}', [CourseworkAssignmentController::class, 'show'])->name('classes.coursework.assignments.show');
    Route::get('/classes/{schoolClass}/coursework/{assignment}/edit', [CourseworkAssignmentController::class, 'edit'])->name('classes.coursework.assignments.edit');
    Route::patch('/classes/{schoolClass}/coursework/{assignment}', [CourseworkAssignmentController::class, 'update'])->name('classes.coursework.assignments.update');
    Route::post('/classes/{schoolClass}/coursework/{assignment}/publish', [CourseworkAssignmentController::class, 'publish'])->name('classes.coursework.assignments.publish');
    Route::post('/classes/{schoolClass}/coursework/{assignment}/close', [CourseworkAssignmentController::class, 'close'])->name('classes.coursework.assignments.close');
    Route::post('/classes/{schoolClass}/coursework/{assignment}/draft', [CourseworkSubmissionController::class, 'saveDraft'])->middleware('throttle:messages')->name('classes.coursework.drafts.save');
    Route::post('/classes/{schoolClass}/coursework/{assignment}/submit', [CourseworkSubmissionController::class, 'submit'])->middleware('throttle:messages')->name('classes.coursework.submissions.submit');
    Route::post('/classes/{schoolClass}/coursework/{assignment}/resubmit', [CourseworkSubmissionController::class, 'resubmit'])->middleware('throttle:messages')->name('classes.coursework.submissions.resubmit');
    Route::get('/classes/{schoolClass}/coursework/{assignment}/submissions/{submission}', [CourseworkSubmissionController::class, 'show'])->name('classes.coursework.submissions.show');
    Route::post('/classes/{schoolClass}/coursework/{assignment}/submissions/{submission}/grades', [CourseworkSubmissionController::class, 'grade'])->name('classes.coursework.submissions.grade');
    Route::get('/classes/{schoolClass}/coursework/{assignment}/submissions/{submission}/revisions/{revision}/attachments/{attachment}', [CourseworkAttachmentController::class, 'download'])->name('classes.coursework.attachments.download');
    Route::delete('/classes/{schoolClass}/coursework/{assignment}/submissions/{submission}/revisions/{revision}/attachments/{attachment}', [CourseworkAttachmentController::class, 'destroy'])->name('classes.coursework.attachments.destroy');
});

Route::middleware(['auth', 'twofactor.setup'])->group(function (): void {
    Route::get('/attendance/mine', [ClassAttendanceController::class, 'mine'])->name('attendance.mine');
    Route::get('/classes/{schoolClass}/attendance', [ClassAttendanceController::class, 'index'])->name('classes.attendance.index');
    Route::post('/classes/{schoolClass}/attendance', [ClassAttendanceController::class, 'open'])->name('classes.attendance.open');
    Route::get('/classes/{schoolClass}/attendance/report', [ClassAttendanceController::class, 'report'])->name('classes.attendance.report');
    Route::get('/classes/{schoolClass}/attendance/export', [ClassAttendanceController::class, 'export'])->name('classes.attendance.export');
    Route::get('/classes/{schoolClass}/attendance/{register}', [ClassAttendanceController::class, 'show'])->name('classes.attendance.show');
    Route::patch('/classes/{schoolClass}/attendance/{register}/entries', [ClassAttendanceController::class, 'bulk'])->name('classes.attendance.bulk');
    Route::post('/classes/{schoolClass}/attendance/{register}/review', [ClassAttendanceController::class, 'review'])->name('classes.attendance.review');
    Route::post('/classes/{schoolClass}/attendance/{register}/finalize', [ClassAttendanceController::class, 'finalize'])->name('classes.attendance.finalize');
    Route::post('/classes/{schoolClass}/attendance/{register}/records/{record}/corrections', [ClassAttendanceController::class, 'correct'])->name('classes.attendance.correct');
});

Route::middleware(['auth', 'twofactor.setup'])->scopeBindings()->group(function (): void {
    Route::get('/classes/{schoolClass}/meetings', [ClassMeetingController::class, 'index'])->name('classes.meetings.index');
    Route::get('/classes/{schoolClass}/meetings/create', [ClassMeetingController::class, 'create'])->name('classes.meetings.create');
    Route::post('/classes/{schoolClass}/meetings', [ClassMeetingController::class, 'store'])->name('classes.meetings.store');
    Route::get('/classes/{schoolClass}/meetings/{meeting}', [ClassMeetingController::class, 'show'])->name('classes.meetings.show');
    Route::patch('/classes/{schoolClass}/meetings/{meeting}', [ClassMeetingController::class, 'update'])->name('classes.meetings.update');
    Route::post('/classes/{schoolClass}/meetings/{meeting}/cancel', [ClassMeetingController::class, 'cancel'])->name('classes.meetings.cancel');
    Route::post('/classes/{schoolClass}/meeting-series/{meetingSeries}/cancel', [ClassMeetingController::class, 'cancelSeries'])->name('classes.meetings.series.cancel');
    Route::get('/classes/{schoolClass}/meetings/{meeting}/room', [LiveKitMeetingController::class, 'show'])->name('classes.meetings.room');
    Route::post('/classes/{schoolClass}/meetings/{meeting}/credentials', [LiveKitMeetingController::class, 'credentials'])->middleware('throttle:30,1')->name('classes.meetings.credentials');
    Route::get('/classes/{schoolClass}/meetings/{meeting}/waiting-room', [ClassMeetingJoinRequestController::class, 'show'])->name('classes.meetings.waiting-room.show');
    Route::post('/classes/{schoolClass}/meetings/{meeting}/waiting-room', [ClassMeetingJoinRequestController::class, 'store'])->middleware('throttle:10,1')->name('classes.meetings.waiting-room.store');
    Route::delete('/classes/{schoolClass}/meetings/{meeting}/waiting-room', [ClassMeetingJoinRequestController::class, 'destroy'])->middleware('throttle:10,1')->name('classes.meetings.waiting-room.destroy');
    Route::get('/classes/{schoolClass}/meetings/{meeting}/join-requests', [ClassMeetingJoinRequestController::class, 'index'])->name('classes.meetings.join-requests.index');
    Route::patch('/classes/{schoolClass}/meetings/{meeting}/join-requests/{joinRequest}', [ClassMeetingJoinRequestController::class, 'update'])->middleware('throttle:30,1')->name('classes.meetings.join-requests.update');
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
