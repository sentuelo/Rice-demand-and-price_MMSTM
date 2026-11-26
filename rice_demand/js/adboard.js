document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.filter-btn');
    const rows    = document.querySelectorAll('#reportsTable tbody tr');
    const empty   = document.getElementById('filterEmpty');

    if (!buttons.length) return;

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            // Toggle active state
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const filter = btn.getAttribute('data-filter');
            let visibleCount = 0;

            rows.forEach(function (row) {
                const demand = row.getAttribute('data-demand');
                const show = (filter === 'All') || (demand === filter);
                row.classList.toggle('hidden-row', !show);
                if (show) visibleCount++;
            });

            if (empty) {
                empty.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        });
    });
});