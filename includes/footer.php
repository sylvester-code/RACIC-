<?php
// includes/footer.php - RACIC Church Management System Global Footer
?>
        </main>

        <!-- Footer Bar -->
        <footer class="bg-white border-top py-3 px-4 d-flex flex-column flex-sm-row justify-content-between align-items-center text-muted small mt-auto">
            <div>
                &copy; <?= date('Y') ?> <strong><?= CHURCH_NAME ?></strong>. All rights reserved.
            </div>
            <div class="mt-2 mt-sm-0">
                <span class="badge bg-light text-secondary border me-2">v2.5 Enterprise</span>
                <span>Powered by RACIC ICT & Media Directorate</span>
            </div>
        </footer>
    </div>
</div>

<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js 4.x -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- FullCalendar 6.x -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<!-- Custom Main JS -->
<script src="<?= BASE_URL ?>assets/js/racic-main.js"></script>

</body>
</html>

