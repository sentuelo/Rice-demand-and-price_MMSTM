(function () {
        const filterBar   = document.getElementById('filterBar');
        const table       = document.getElementById('vendorReportsTable');
        const emptyMsg    = document.getElementById('filterEmpty');
        const hiddenInput = document.getElementById('activeFilterInput');

        if (!filterBar || !table) return;

        const rows = Array.from(table.querySelectorAll('tbody tr'));

        filterBar.addEventListener('click', function (e) {
            const btn = e.target.closest('.filter-btn');
            if (!btn) return;

            const filter = btn.getAttribute('data-filter');

            // toggle active button
            filterBar.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // sync hidden input for POST
            if (hiddenInput) hiddenInput.value = filter;

            // apply row filtering
            let visible = 0;
            rows.forEach(row => {
                const demand = row.getAttribute('data-demand') || '';
                const show = (filter === 'All') || (demand === filter);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            if (emptyMsg) {
                emptyMsg.style.display = (visible === 0) ? 'block' : 'none';
            }
        });
    })();