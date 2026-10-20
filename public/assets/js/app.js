/**
 * College Voting System - Client Application JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Double-click prevention on forms
    const forms = document.querySelectorAll('form[data-prevent-double-submit="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                if (submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                submitBtn.disabled = true;
                const originalHtml = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';
            }
        });
    });

    // 2. Voting modal handler
    const voteModalEl = document.getElementById('voteConfirmationModal');
    if (voteModalEl) {
        voteModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const candidateId = button.getAttribute('data-candidate-id');
            const candidateName = button.getAttribute('data-candidate-name');
            const candidatePosition = button.getAttribute('data-candidate-position');

            document.getElementById('modalCandidateId').value = candidateId;
            document.getElementById('modalCandidateName').textContent = candidateName;
            document.getElementById('modalCandidatePosition').textContent = candidatePosition;
        });
    }

    // 3. Search and filter debouncing (if present)
    const searchInputs = document.querySelectorAll('.table-search-input');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const targetTable = document.querySelector(this.getAttribute('data-table'));
            if (!targetTable) return;
            const rows = targetTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    });
});
