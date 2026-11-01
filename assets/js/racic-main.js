// assets/js/racic-main.js - RACIC CMS Client-side Scripts
document.addEventListener('DOMContentLoaded', function () {
    // 1. Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mobileOverlay = document.getElementById('sidebarOverlay');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.innerWidth < 992) {
                sidebar.classList.toggle('show');
                if (mobileOverlay) mobileOverlay.classList.toggle('d-block');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
    }

    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', function () {
            if (sidebar) sidebar.classList.remove('show');
            this.classList.remove('d-block');
        });
    }

    // 2. Data Confirm Handler for Deletes & Sensitive Actions
    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to proceed with this action?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // 3. Auto dismiss non-error flash alerts after 6 seconds
    setTimeout(function () {
        document.querySelectorAll('.alert-success, .alert-info').forEach(function (alertEl) {
            try {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                if (bsAlert) bsAlert.close();
            } catch (err) {}
        });
    }, 6000);

    // 4. Quick Table Client Search Filter
    document.querySelectorAll('.table-search-input').forEach(function (input) {
        input.addEventListener('keyup', function () {
            const targetTableId = this.getAttribute('data-target');
            const table = document.getElementById(targetTableId);
            if (!table) return;

            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                if (text.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });

    // 5. Trigger Print helper
    document.querySelectorAll('.btn-trigger-print').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            window.print();
        });
    });
});

/**
 * Client-side CSV Exporter for any HTML table
 */
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        // Skip hidden rows or action columns if marked .no-export
        if (rows[i].style.display === 'none') continue;

        let row = [];
        const cols = rows[i].querySelectorAll('td, th');

        for (let j = 0; j < cols.length; j++) {
            if (cols[j].classList.contains('no-export')) continue;
            // Clean text
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').trim();
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        if (row.length > 0) {
            csv.push(row.join(','));
        }
    }

    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = (filename || 'racic-export') + '.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

