document.addEventListener('DOMContentLoaded', function () {
    const varietySelect  = document.getElementById('varietySelect');
    const beginningStock = document.getElementById('beginningStock');
    const quantitySold   = document.getElementById('quantitySold');
    const remainingStock = document.getElementById('remainingStock');
    const weekStart      = document.getElementById('weekStart');
    const weekEnd        = document.getElementById('weekEnd');
    const begHint        = document.getElementById('begHint');
    const soldHint       = document.getElementById('soldHint');
    const wsHint         = document.getElementById('wsHint');

    if (!varietySelect) return;

    function todayISO() {
        const d = new Date();
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function toISODate(str) {
        if (!str) return '';
        // Accepts "YYYY-MM-DD", "YYYY-MM-DD HH:MM:SS", "YYYY/MM/DD"
        const d = new Date(str.replace(' ', 'T').replace(/\//g, '-'));
        if (isNaN(d.getTime())) return '';
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    // Week end defaults to today on load
    weekEnd.value = todayISO();

    function updateRemaining() {
        const beg  = parseFloat(beginningStock.value) || 0;
        const sold = parseFloat(quantitySold.value) || 0;
        const rem  = beg - sold;

        if (rem < 0) {
            remainingStock.value = '';
            soldHint.textContent = 'Quantity sold cannot exceed beginning stock.';
            soldHint.style.color = '#c0392b';
        } else {
            remainingStock.value = rem;
            soldHint.textContent = '';
        }
    }

    varietySelect.addEventListener('change', function () {
        const selected  = this.options[this.selectedIndex];
        const max       = parseInt(selected.dataset.max || '0', 10);
        const dateAdded = selected.dataset.dateAdded || '';

        if (!this.value) {
            beginningStock.value = '';
            remainingStock.value = '';
            weekStart.value = '';
            weekEnd.value = todayISO();
            begHint.textContent = '';
            wsHint.textContent = '';
            return;
        }

        // Auto-fill beginning stock
        beginningStock.value = max;
        begHint.style.color = '#666';

        // Auto-fill week_start with the date the rice was added
        const iso = toISODate(dateAdded);
        if (iso) {
            weekStart.value = iso;
            wsHint.style.color = '#666';
        } else {
            weekStart.value = '';
            wsHint.textContent = 'No registration date found for this rice.';
            wsHint.style.color = '#c0392b';
        }

        // Week end stays as today
        weekEnd.value = todayISO();

        updateRemaining();
    });

    quantitySold.addEventListener('input', updateRemaining);
});