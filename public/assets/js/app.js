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

    // 4. Hero section smooth counter animation
    const counters = document.querySelectorAll('.hero-counter');
    if (counters.length > 0) {
        let hasAnimated = false;
        const animateCounters = () => {
            if (hasAnimated) return;
            hasAnimated = true;
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target') || '0', 10);
                if (target <= 0) {
                    counter.textContent = '0';
                    return;
                }
                const duration = 1500; // ms
                const startTime = performance.now();
                
                const updateCount = (currentTime) => {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    // Ease-out cubic curve: 1 - (1 - t)^3
                    const easeProgress = 1 - Math.pow(1 - progress, 3);
                    const currentVal = Math.round(target * easeProgress);
                    counter.textContent = currentVal.toLocaleString();
                    
                    if (progress < 1) {
                        requestAnimationFrame(updateCount);
                    } else {
                        counter.textContent = target.toLocaleString();
                    }
                };
                requestAnimationFrame(updateCount);
            });
        };

        if ('IntersectionObserver' in window) {
            const heroObserver = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounters();
                        obs.disconnect();
                    }
                });
            }, { threshold: 0.15 });
            const heroSection = document.querySelector('.hero-wrapper') || counters[0];
            heroObserver.observe(heroSection);
        } else {
            animateCounters();
        }
    }
});
