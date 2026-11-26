
document.addEventListener('DOMContentLoaded', function () {

    // delete confirmation
    document.querySelectorAll('.delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('Delete this stall? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // update
    document.querySelectorAll('.update-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var id = form.dataset.stallId;
            var nameInput = document.getElementById('stall_name_' + id);
            var numInput  = document.getElementById('stall_number_' + id);

            if (!nameInput || !numInput) return;

            var nameVal = nameInput.value.trim();
            var numVal  = parseInt(numInput.value, 10) || 0;

            if (nameVal === '') {
                e.preventDefault();
                alert('Stall name cannot be empty.');
                nameInput.focus();
                return;
            }
            if (numVal <= 0) {
                e.preventDefault();
                alert('Stall number must be greater than 0.');
                numInput.focus();
                return;
            }

            form.querySelector('input[name="stall_name"]').value   = nameVal;
            form.querySelector('input[name="stall_number"]').value = numVal;
        });
    });

});