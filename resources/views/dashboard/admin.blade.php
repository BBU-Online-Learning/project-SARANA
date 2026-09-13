@include('dashboard.welcome', [
    'dashboardTitle' => 'Administration overview',
    'dashboardHeading' => 'Keep your school organized',
    'dashboardDescription' => 'Manage teacher and student accounts and keep class spaces organized.',
    'dashboardFocus' => 'Teachers, students and classes',
])
@include('dashboard.accounts')
@include('dashboard.counts')
@include('dashboard.activity')
