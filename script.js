function handleCredentialResponse(response) {
    console.log("Encoded JWT ID token received.");

    // Send the ID token to our server-side auth script
    const formData = new FormData();
    formData.append('id_token', response.credential);

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
            // Redirect the user to the appropriate page
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
