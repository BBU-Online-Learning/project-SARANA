document.getElementById('toggle-password')?.addEventListener('click', event => {
    const password = document.getElementById('password');
    const visible = password.type === 'password';
    password.type = visible ? 'text' : 'password';
    event.currentTarget.textContent = visible ? 'Hide password' : 'Show password';
    event.currentTarget.setAttribute('aria-pressed', String(visible));
});
