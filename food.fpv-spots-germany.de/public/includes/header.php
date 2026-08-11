<?php
declare(strict_types=1);

$isLoggedIn = $isLoggedIn ?? false;
$username   = $username ?? '';
?>
<!-- ============================================================
     Navbar mit Dropdown-Menü
============================================================ -->
<header class="bg-dark sticky-top">
    <nav class="navbar navbar-dark px-3" style="height:56px;">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/" title="Startseite">
            <span>Wild Food Spot Map</span>
        </a>
        <div class="dropdown">
            <button class="btn btn-outline-light btn-sm dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="Menü öffnen">
                Menü
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <button class="dropdown-item" type="button" disabled
                            title="Bald verfügbar">
                        Login
                    </button>
                </li>
                <li>
                    <button class="dropdown-item" type="button" disabled
                            title="Bald verfügbar">
                        Registrieren
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item"
                       href="https://fpv-spots-germany.de/impressum.php"
                       target="_blank" rel="noopener noreferrer">
                        Impressum
                    </a>
                </li>
                <li>
                    <a class="dropdown-item"
                       href="https://fpv-spots-germany.de/datenschutz.php"
                       target="_blank" rel="noopener noreferrer">
                        Datenschutz
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</header>
