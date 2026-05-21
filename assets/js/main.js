// Contractum ERP — Main JS

// Active nav link highlight (fallback for server-side PHP highlight)
document.querySelectorAll('.nav-link').forEach(link => {
    if (link.href === window.location.href) {
        link.classList.add('active');
    }
});

// Auto-dismiss alerts after 4 seconds
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    }, 4000);
});

// Confirm before delete actions
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm || 'Are you sure?')) {
            e.preventDefault();
        }
    });
});

// Table row hover highlight using CSS variable
document.querySelectorAll('tbody tr').forEach(row => {
    row.addEventListener('mouseenter', () => row.style.background = 'var(--bg-main)');
    row.addEventListener('mouseleave', () => row.style.background = '');
});
