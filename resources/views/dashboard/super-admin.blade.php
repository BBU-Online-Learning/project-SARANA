@include('dashboard.welcome', [
    'dashboardTitle' => 'System overview',
    'dashboardHeading' => 'Manage your institution',
    'dashboardDescription' => 'Manage administrator, teacher and student accounts, and oversee institution classes.',
    'dashboardFocus' => 'Accounts and class administration',
])
@include('dashboard.accounts')
@include('dashboard.counts')
@include('dashboard.activity')
