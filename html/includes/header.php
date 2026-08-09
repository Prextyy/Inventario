<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$isActive = function(array $pages) use ($currentPage) {
    return in_array($currentPage, $pages, true) ? 'active' : '';
};

$adminMenuActive = $isActive(['admin-users.php', 'admin-areas.php', 'backup.php']);
?>

<header class="main-header">
    <div class="header-container">
        <a class="site-brand" href="dashboard.php" aria-label="Ir al inicio">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" focusable="false">
                    <path d="M5 8.5 16 3l11 5.5v15L16 29 5 23.5z" class="brand-box"></path>
                    <path d="M5 8.5 16 14l11-5.5M16 14v15" class="brand-fold"></path>
                    <path d="M10 11v8m3-6.5V21m3-7v9m3-10.5V21m3-10v8" class="brand-bars"></path>
                </svg>
            </span>
            <span class="brand-copy">
                <strong>Inventario Fanasa</strong>
                <small>Almacén y suministros</small>
            </span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation">
            <span class="sr-only">Abrir menu principal</span>
            <span></span><span></span><span></span>
        </button>

        <div class="header-navigation" id="main-navigation">
            <nav class="main-nav" aria-label="Navegacion principal">
                <a href="dashboard.php" class="nav-link <?php echo $isActive(['dashboard.php']); ?>">Resumen</a>
                <a href="my-requests.php" class="nav-link <?php echo $isActive(['my-requests.php']); ?>">Solicitudes</a>

                <?php if (isAdmin()): ?>
                    <a href="products.php" class="nav-link <?php echo $isActive(['products.php']); ?>">Productos</a>
                    <a href="quick-entry.php" class="nav-link <?php echo $isActive(['quick-entry.php']); ?>">Movimientos</a>
                    <a href="manage-requests.php" class="nav-link <?php echo $isActive(['manage-requests.php']); ?>">Aprobaciones</a>
                    <a href="export.php" class="nav-link <?php echo $isActive(['export.php']); ?>">Reportes</a>

                    <details class="nav-dropdown" <?php echo $adminMenuActive ? 'open' : ''; ?>>
                        <summary class="nav-link <?php echo $adminMenuActive; ?>">Administracion</summary>
                        <div class="nav-dropdown-menu">
                            <a href="admin-users.php">Usuarios</a>
                            <a href="admin-areas.php">Departamentos</a>
                            <a href="backup.php">Respaldo</a>
                        </div>
                    </details>
                <?php endif; ?>
            </nav>

            <div class="header-right">
                <span class="user-avatar" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr(getCurrentUsername(), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="user-name"><?php echo htmlspecialchars(getCurrentUsername(), ENT_QUOTES, 'UTF-8'); ?></span>
                <a href="logout.php" class="logout-link">Cerrar sesion</a>
            </div>
        </div>
    </div>
</header>

<script>
(function () {
    const csrfToken = <?php echo json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const nativeFetch = window.fetch;

    window.fetch = function (input, options) {
        const requestOptions = options ? { ...options } : {};
        const method = String(requestOptions.method || 'GET').toUpperCase();
        if (method === 'POST') {
            const headers = new Headers(requestOptions.headers || {});
            headers.set('X-CSRF-Token', csrfToken);
            requestOptions.headers = headers;
        }
        return nativeFetch.call(this, input, requestOptions);
    };

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || String(form.method).toLowerCase() !== 'post') return;
        if (form.querySelector('input[name="csrf_token"]')) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = csrfToken;
        form.appendChild(input);
    }, true);

    const toggle = document.querySelector('.nav-toggle');
    const navigation = document.querySelector('.header-navigation');
    if (toggle && navigation) {
        toggle.addEventListener('click', function () {
            const isOpen = navigation.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    }
})();
</script>
