document.addEventListener('DOMContentLoaded', () => {
    const reviewDialog = document.getElementById('review-modal');
    let review = null;
    let clientId = null;
    document.querySelectorAll('[data-review-kind]').forEach(button => button.addEventListener('click', () => {
        review = { ...button.dataset };
        const reject = review.action === 'REJECT';
        document.getElementById('review-title').textContent = reject ? 'Reject request' : 'Approve request';
        document.getElementById('review-summary').textContent = review.summary;
        document.getElementById('review-note').textContent = reject ? 'The request and your reason will remain in the history.' : (review.reviewKind === 'profile' ? 'Approval applies the requested profile details after checking they are still available.' : 'Approval applies this request. Funds and account status will be checked again.');
        document.getElementById('review-reason-group').hidden = !reject;
        const reason = document.getElementById('review-reason');
        reason.required = reject;
        reason.value = '';
        document.getElementById('review-alert').replaceChildren();
        document.getElementById('review-confirm').textContent = reject ? 'Confirm rejection' : 'Confirm approval';
        reviewDialog.showModal();
    }));
    document.getElementById('review-form').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        await withBusy(form, async () => {
            const kind = review.reviewKind;
            const route = {transaction:['api/admin-tx.php','tx_id'], account:['api/admin-accounts.php','account_id'], profile:['api/admin-profile.php','request_id']}[kind];
            if (!route) return;
            const result = await API.post(route[0], { [route[1]]: review.id, action: review.action, reason: document.getElementById('review-reason').value.trim() });
            showMessage('review-alert', result.error || result.success, !result.success);
            if (result.success) { form.dataset.completed = 'true'; setTimeout(() => location.reload(), 650); }
        });
    });
    document.querySelectorAll('[data-edit-client]').forEach(button => button.addEventListener('click', () => {
        clientId = button.dataset.editClient;
        document.getElementById('edit-value').value = button.dataset.name;
        document.getElementById('edit-alert').replaceChildren();
        document.getElementById('admin-edit-modal').showModal();
    }));
    document.getElementById('admin-edit-form').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        await withBusy(form, async () => {
            const result = await API.post('api/admin-edit.php', { id: clientId, type: 'user', field: 'full_name', value: document.getElementById('edit-value').value });
            showMessage('edit-alert', result.error || result.success, !result.success);
            if (result.success) { form.dataset.completed = 'true'; setTimeout(() => location.reload(), 650); }
        });
    });
    document.getElementById('announcement-form').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        await withBusy(form, async () => {
            const result = await API.post('api/post-announcement.php', Object.fromEntries(new FormData(form)));
            showMessage('announcement-alert', result.error || result.success, !result.success);
            if (result.success) form.reset();
        });
    });
});
