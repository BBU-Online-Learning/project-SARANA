$ErrorActionPreference = 'Stop'

Write-Host ''
Write-Host 'Local development is starting.' -ForegroundColor Cyan
Write-Host 'Open: http://127.0.0.1:8000' -ForegroundColor Green
Write-Host 'Press Ctrl+C to stop all services.' -ForegroundColor DarkGray
Write-Host ''

php artisan config:clear

if ($LASTEXITCODE -ne 0) {
    throw 'Laravel configuration could not be cleared.'
}

npx concurrently --kill-others-on-fail `
    --names 'server,queue,reverb,schedule,vite' `
    --prefix-colors '#93c5fd,#c4b5fd,#67e8f9,#fca5a5,#fdba74' `
    'php artisan serve --host=127.0.0.1 --port=8000' `
    'php artisan queue:work --tries=1' `
    'php artisan reverb:start --host=127.0.0.1 --port=8080' `
    'php artisan schedule:work' `
    'npm run dev'
