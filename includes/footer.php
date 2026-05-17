        </main>
    </div>
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <script>
    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('open');
        });
    });
    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggle  = document.querySelector('.sidebar-toggle');
        if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
    </script>
</body>
</html>
