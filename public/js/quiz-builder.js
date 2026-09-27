document.querySelectorAll('[data-question-form]').forEach(form => {
    const type = form.querySelector('[data-question-type]');
    const options = form.querySelector('[data-option-fields]');
    const trueFalse = form.querySelector('[data-true-false-fields]');
    const shortAnswer = form.querySelector('[data-short-answer-fields]');
    const sync = () => {
        const value = type.value;
        options.hidden = !['multiple_choice', 'multiple_answer'].includes(value);
        trueFalse.hidden = value !== 'true_false';
        shortAnswer.hidden = value !== 'short_answer';
        options.querySelectorAll('input, select, textarea').forEach(control => { control.disabled = options.hidden; });
        trueFalse.querySelectorAll('input, select, textarea').forEach(control => { control.disabled = trueFalse.hidden; });
        shortAnswer.querySelectorAll('input, select, textarea').forEach(control => { control.disabled = shortAnswer.hidden; });
        form.querySelectorAll('[data-correct-option]').forEach(input => { input.type = value === 'multiple_answer' ? 'checkbox' : 'radio'; });
    };
    type.addEventListener('change', sync);
    sync();
});
