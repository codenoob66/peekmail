let client;

window.onload = function () {
    client = google.accounts.oauth2.initCodeClient({
        client_id: '951139635404-1lg99r1qorsu66hu0lkr8ht767pbbihv.apps.googleusercontent.com',
        scope: 'https://www.googleapis.com/auth/gmail.readonly profile email',
        ux_mode: 'popup',
        callback: (response) => {
            if (response.code) {
                const formData = new FormData();
                formData.append('code', response.code);

                fetch('/app/auth.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => {
                    if (!res.ok) {
                        return res.text().then(text => {
                            throw new Error('Server returned ' + res.status + ': ' + text);
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    console.log('Server response:', data);
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else if (data.error) {
                        console.error('Server error:', data.error);
                        alert('Login failed: ' + data.error);
                    }
                })
                .catch(err => {
                    console.error('Login process error:', err);
                    alert('An error occurred during login: ' + err.message);
                });
            }
        }
    });

    const googleLoginBtn = document.getElementById('google-login-btn');
    if (googleLoginBtn) {
        googleLoginBtn.addEventListener('click', () => {
            client.requestCode();
        });
    }
};
