        <?php if (isset($current_page) && $current_page === 'scholen.php'): ?>
            <script src="javascript/scholen.js"></script>
        <?php elseif (isset($current_page) && $current_page === 'klassen.php'): ?>
            <script src="javascript/klassen.js"></script>
        <?php endif; ?>

        <!-- Bootstrap 5.3 Bundle (incl. Popper) -->
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
        </script>
    </body>
</html>