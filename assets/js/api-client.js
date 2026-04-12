// assets/js/api-client.js
const API = {
    async post(url, data) {
        const formData = new FormData();
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        formData.append('csrf_token', csrfToken);
        
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Server returned non-JSON:', text);
                return { error: 'Server error: Invalid response format' };
            }
        } catch (error) {
            console.error('API Error:', error);
            return { error: 'Network error or server unavailable' };
        }
    },
    
    async get(url) {
        try {
            const response = await fetch(url);
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Server returned non-JSON:', text);
                return { error: 'Server error: Invalid response format' };
            }
        } catch (error) {
            console.error('API Error:', error);
            return { error: 'Network error or server unavailable' };
        }
    }
};
