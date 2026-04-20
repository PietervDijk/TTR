        <!-- Pagina-specifieke scripts laden we conditioneel om onnodige JS te voorkomen. -->
        <?php if (isset($huidige_pagina) && $huidige_pagina === 'scholen.php'): ?>
            <script src="javascript/scholen.js"></script>
        <?php elseif (isset($huidige_pagina) && $huidige_pagina === 'klassen.php'): ?>
            <script src="javascript/klassen.js"></script>
        <?php endif; ?>

        <!-- Bootstrap 5.3 Bundle (incl. Popper) -->
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
        </script>
    </body>
</html>