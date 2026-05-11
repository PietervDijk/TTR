<footer class="technolab-footer">
    <div class="technolab-footer__purple"></div>
    <div class="technolab-footer__white">
        <div class="container py-3">
            <div class="text-center small">
                &copy; <?= date('Y') ?> Technolab. Alle rechten voorbehouden.
            </div>
        </div>
    </div>
</footer>

        <!-- Laad pagina-specifieke scripts waar nodig -->
        <?php if (isset($huidige_pagina) && $huidige_pagina === 'scholen.php'): ?>
            <script src="javascript/scholen.js"></script>
        <?php elseif (isset($huidige_pagina) && $huidige_pagina === 'klassen.php'): ?>
            <script src="javascript/klassen.js"></script>
        <?php elseif (isset($huidige_pagina) && $huidige_pagina === 'bezoeken.php'): ?>
            <script src="javascript/bezoeken.js"></script>
        <?php endif; ?>

        <!-- Bootstrap 5.3 (incl. Popper) -->
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
        </script>
    </body>
</html>