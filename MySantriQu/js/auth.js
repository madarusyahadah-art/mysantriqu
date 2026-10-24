(function () {
    function clearAuthStorage() {
        localStorage.removeItem("user_data");
        localStorage.removeItem("user_id");
        localStorage.removeItem("user_role");
    }

    window.logoutUser = function () {
        return fetch('api/logout.php', { method: 'POST' })
            .catch(function () {})
            .finally(function () {
                clearAuthStorage();
                window.location.href = "index.html";
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        var logoutButton = document.getElementById('btn-logout');
        if (!logoutButton) return;

        logoutButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.logoutUser();
        }, true);
    });
})();
