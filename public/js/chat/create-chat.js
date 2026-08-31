document.addEventListener('click', async event => {
    const button = event.target.closest('#create-direct-btn, #create-group-btn');
    if (!button || button.disabled) return;
    const buttons = [...document.querySelectorAll('#create-direct-btn, #create-group-btn')];
    const errorBox = document.getElementById('create-chat-error');
    errorBox.hidden = true;
    const label = button.textContent;
    let created = false;
    buttons.forEach(item => item.disabled = true);
    button.textContent = 'Creating...';
    try {
        const direct = button.id === 'create-direct-btn';
        const payload = direct ? { user_id: document.getElementById('direct-user-id').value } : {
            name: document.getElementById('group-name').value,
            members: [...document.getElementById('group-members').selectedOptions].map(option => option.value),
        };
        const response = await axios.post(direct ? '/chat/direct' : '/chat/group', payload);
        if (!response.data.room_id) throw new Error('Session changed');
        created = true;
        bootstrap.Modal.getInstance(document.getElementById('createChatModal'))?.hide();
        await reloadConversationList();
        await loadRoom(response.data.room_id);
    } catch (error) {
        const messages = error.response?.data?.errors;
        const text = created ? 'Conversation created, but the list could not refresh. Reload Chats before trying again.'
            : messages ? Object.values(messages).flat().join(' ')
            : 'Could not create the conversation. Check your connection or reload to sign in again.';
        errorBox.textContent = text;
        errorBox.hidden = false;
        if (created) showChatLoadStatus(text, true);
    } finally {
        buttons.forEach(item => item.disabled = created && !errorBox.hidden);
        button.textContent = label;
    }
});

async function reloadConversationList() {
    const response = await axios.get('/chat');
    const html = new DOMParser().parseFromString(response.data, 'text/html');
    const list = html.querySelector('.conversation-list');
    if (!list) throw new Error('Conversation list unavailable');
    document.querySelector('.conversation-list').innerHTML = list.innerHTML;
}
