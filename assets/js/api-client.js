const API = {
    async request(url, options = {}) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(url, { credentials: 'same-origin', ...options, signal: controller.signal });
            const result = await response.json();
            if (!response.ok && !result.error) return { error: 'Request failed. Please try again.', status: response.status };
            return { ...result, httpStatus: response.status };
        } catch (error) {
            return { error: error.name === 'AbortError' ? 'The request timed out. Check your history before retrying a transaction.' : 'Unable to reach the server. Please try again.' };
        } finally { clearTimeout(timeout); }
    },
    post(url, data = {}) {
        const formData = new FormData();
        for (const [key, value] of Object.entries(data)) formData.append(key, value);
        formData.set('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
        return this.request(url, { method: 'POST', body: formData });
    },
    get(url) { return this.request(url); }
};
