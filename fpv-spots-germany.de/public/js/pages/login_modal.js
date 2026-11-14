// =============================================================
// Login-Modal
// Ausgelagert aus public/includes/login_modal.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================

// -------------------------------------------------------------
// Modal automatisch öffnen (?showLogin=1) und Modal-Parameter aus der
// Adresszeile entfernen. Das Rücksprungziel steht serverseitig bereits
// im Hidden-Feld, daher darf "redirect" hier verschwinden.
// -------------------------------------------------------------
(function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('showLogin') === '1') {
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('loginModal');
            if (el && window.bootstrap) {
                new bootstrap.Modal(el).show();
            }
            params.delete('showLogin');
            params.delete('redirect');
            var qs = params.toString();
            var url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
            window.history.replaceState({}, '', url);
        });
    }
})();

// -------------------------------------------------------------
// Anker (#...) an das Rücksprungziel hängen – der Server sieht ihn nie.
// -------------------------------------------------------------
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('loginForm');
        var redirect = document.getElementById('loginRedirect');
        if (!form || !redirect) return;
        form.addEventListener('submit', function () {
            if (window.location.hash && redirect.value.indexOf('#') === -1) {
                redirect.value += window.location.hash;
            }
        });
        var modal = document.getElementById('loginModal');
        if (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                var u = form.querySelector('input[name="username"]');
                if (u) u.focus();
            });
        }
    });
})();
