document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-feedback-form]');
    if (!form) return;

    const reasonWrap = form.querySelector('.feedback-reason-wrap');
    const reason = form.querySelector('.feedback-reason');
    const message = form.querySelector('.feedback-message');
    let rating = 0;

    async function submitFeedback() {
        if (!rating || (rating < 0 && !reason.value)) {
            message.textContent = 'Please choose a reason before sending.';
            return;
        }

        form.querySelectorAll('button').forEach(function (button) {
            button.disabled = true;
        });
        message.textContent = 'Sending…';

        const data = new FormData();
        data.append('csrf_token', form.dataset.csrfToken);
        data.append('content_type', form.dataset.contentType);
        data.append('rating', String(rating));
        data.append('reason', reason.value);

        if (form.dataset.contentType === 'lesson') {
            data.append('topic_id', form.dataset.topicId);
            data.append('activity_index', form.dataset.activityIndex);
        } else {
            data.append('study_path_id', form.dataset.studyPathId);
        }

        try {
            const response = await fetch('api/content-feedback.php', {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (response.status === 409) {
                message.textContent = result.message || 'You have already submitted feedback for this item.';
                return;
            }
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Could not submit feedback.');
            }
            message.textContent = result.message;
        } catch (error) {
            message.textContent = error.message || 'Could not submit feedback. Please try again.';
            form.querySelectorAll('button').forEach(function (button) {
                button.disabled = false;
            });
        }
    }

    form.querySelectorAll('.feedback-rating').forEach(function (button) {
        button.addEventListener('click', function () {
            rating = Number(button.dataset.rating);
            form.querySelectorAll('.feedback-rating').forEach(function (item) {
                item.classList.toggle('selected', item === button);
            });
            reasonWrap.hidden = rating !== -1;
            message.textContent = '';
            if (rating === 1) submitFeedback();
        });
    });

    form.querySelector('.feedback-submit').addEventListener('click', submitFeedback);
});
