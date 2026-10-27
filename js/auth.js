(function () {
    function clearAuthStorage() {
        localStorage.removeItem("user_data");
        localStorage.removeItem("user_id");
        localStorage.removeItem("user_role");
    }

    window.logoutUser = function () {
        return fetch('api/logout.php', { method: 'POST' })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Logout gagal.');
                    }
                    return data;
                });
            })
            .then(function () {
                clearAuthStorage();
                window.location.href = "index.html";
            })
            .catch(function (error) {
                console.error('Logout gagal:', error);
                window.alert(error.message || 'Logout gagal. Silakan coba lagi.');
            });
    };

    // Logout is handled exclusively by the gear settings panel via window.logoutUser().
// No direct #btn-logout binding here to avoid conflicts with settings.js removeHeaders().
})();
