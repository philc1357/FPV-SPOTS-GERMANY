// =============================================================
// Login-Modal
// Ausgelagert aus public/includes/login_modal.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
(function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('showLogin') === '1') {
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('loginModal');
            if (el && window.bootstrap) {
                new bootstrap.Modal(el).show();
            }
            params.delete('showLogin');
            var qs = params.toString();
            var url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
            window.history.replaceState({}, '', url);
        });
    }
})();
