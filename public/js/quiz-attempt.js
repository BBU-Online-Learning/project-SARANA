(() => {
    const root = document.querySelector('[data-quiz-attempt]');
    const form = root?.querySelector('[data-attempt-form]');
    if (!root || !form) return;
    const token = form.querySelector('input[name="_token"]')?.value;
    const state = root.querySelector('[data-save-state]');
    const questions = [...root.querySelectorAll('[data-quiz-question]')];
    let currentQuestion = 0;
    let timer;
    const answered = question => [...question.querySelectorAll('[data-answer]')].some(input => input.type === 'textarea' ? input.value.trim() : input.checked);
    const progress = () => {
        const count = questions.filter(answered).length;
        root.querySelector('[data-answer-count]').textContent = `${count} of ${questions.length} answered`;
        root.querySelector('[data-quiz-progress]').style.width = `${questions.length ? count / questions.length * 100 : 0}%`;
    };
    const showQuestion = index => {
        currentQuestion = Math.max(0, Math.min(index, questions.length - 1));
        questions.forEach((question, questionIndex) => { question.hidden = questionIndex !== currentQuestion; });
        root.querySelector('[data-question-index]').textContent = `Question ${currentQuestion + 1} of ${questions.length}`;
        root.querySelector('[data-question-prev]').disabled = currentQuestion === 0;
        root.querySelector('[data-question-next]').disabled = currentQuestion === questions.length - 1;
        questions[currentQuestion]?.querySelector('[data-answer]')?.focus({ preventScroll: true });
        questions[currentQuestion]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    const save = async () => {
        state.textContent = 'Saving…';
        try {
            const body = new FormData(form);
            body.set('_method', 'PATCH');
            const response = await fetch(root.dataset.saveUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body });
            if (!response.ok) throw new Error('save');
            state.textContent = 'Saved';
        } catch { state.textContent = 'Could not save. Your answers remain on this screen.'; }
    };
    form.addEventListener('input', () => { progress(); clearTimeout(timer); timer = setTimeout(save, 700); });
    root.querySelector('[data-question-prev]')?.addEventListener('click', () => showQuestion(currentQuestion - 1));
    root.querySelector('[data-question-next]')?.addEventListener('click', () => showQuestion(currentQuestion + 1));
    progress();
    if (questions.length) showQuestion(0);
    const output = root.querySelector('[data-quiz-timer]');
    const deadline = root.dataset.deadline ? new Date(root.dataset.deadline).getTime() : null;
    if (output && deadline) setInterval(() => {
        const remaining = Math.max(0, deadline - Date.now());
        const seconds = Math.floor(remaining / 1000);
        output.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
        if (remaining === 0) form.submit();
    }, 1000);
})();
