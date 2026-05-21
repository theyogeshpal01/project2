        </div><!-- /.main-inner -->
    </main>
</div><!-- /.dashboard-container -->

<script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
<script>
// ── Dark Mode ──────────────────────────────────────────────
(function () {
    const body = document.body;
    const moonIcon = document.getElementById('moonIcon');
    const sunIcon  = document.getElementById('sunIcon');

    function applyDark(on) {
        if (on) {
            body.classList.add('dark-mode');
            if (moonIcon) moonIcon.style.display = 'none';
            if (sunIcon)  sunIcon.style.display  = '';
        } else {
            body.classList.remove('dark-mode');
            if (moonIcon) moonIcon.style.display = '';
            if (sunIcon)  sunIcon.style.display  = 'none';
        }
    }

    // Apply on load
    applyDark(localStorage.getItem('darkMode') === '1');

    window.toggleDarkMode = function () {
        const isDark = !body.classList.contains('dark-mode');
        localStorage.setItem('darkMode', isDark ? '1' : '0');
        applyDark(isDark);
    };
})();

// ── Close modals on overlay click ─────────────────────────
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function (e) {
        if (e.target === this) this.classList.remove('open');
    });
});

// ── Close sidebar on mobile when clicking outside ─────────
document.addEventListener('click', function (e) {
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarToggle');
    if (sidebar && sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) && toggle && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});

// ── Tab system (generic, data-tab-target) ─────────────────
document.querySelectorAll('[data-tab-target]').forEach(btn => {
    btn.addEventListener('click', function () {
        const group = this.closest('[data-tab-group]');
        if (!group) return;
        group.querySelectorAll('[data-tab-target]').forEach(b => b.classList.remove('active'));
        group.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const target = document.getElementById(this.dataset.tabTarget);
        if (target) target.classList.add('active');
    });
});
</script>
</body>
</html>
