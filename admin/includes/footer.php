</div> <!-- End container-fluid -->
</div> <!-- End main-content -->

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Custom Modals Script -->
<script src="<?= $currentDir === 'admin' ? 'assets/js/custom-modals.js' : '../assets/js/custom-modals.js' ?>"></script>

<!-- Mobile Sidebar Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }
});

// Reemplazar confirmaciones nativas con modales personalizados
document.addEventListener('DOMContentLoaded', function() {
    // Interceptar formularios de eliminación
    const deleteForms = document.querySelectorAll('.delete-form');
    deleteForms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const confirmed = await customConfirm(
                '¿Estás seguro de que deseas eliminar este elemento?',
                'Confirmar Eliminación'
            );
            
            if (confirmed) {
                this.submit();
            }
        });
    });
    
    // También interceptar formularios con onsubmit que contengan confirm
    const confirmForms = document.querySelectorAll('form[onsubmit*="confirm"]');
    confirmForms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const confirmed = await customConfirm(
                '¿Estás seguro de que deseas eliminar este elemento?',
                'Confirmar Eliminación'
            );
            
            if (confirmed) {
                // Remover el onsubmit para evitar bucle infinito
                this.removeAttribute('onsubmit');
                this.submit();
            }
        });
        
        // Remover el onsubmit original
        form.removeAttribute('onsubmit');
    });
    
    // Mostrar mensajes de éxito/error usando toast
    <?php if (isset($message) && $message): ?>
        customModals.toast('<?= addslashes($message) ?>', '<?= $messageType === 'success' ? 'success' : 'error' ?>');
    <?php endif; ?>
});
</script>
