/**
 * College Voting System — Voting Module JavaScript
 * Handles: candidate selection cards, vote modal population, confirmation UX
 */

'use strict';

// ── Candidate Radio Selection Cards ───────────────────────────────────────────
document.querySelectorAll('.candidate-radio-card input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function () {
        // Remove selected state from all cards
        document.querySelectorAll('.candidate-radio-card label').forEach(lbl => {
            lbl.classList.remove('border-primary', 'bg-primary-subtle');
        });
        // Apply to selected
        const label = this.closest('.candidate-radio-card').querySelector('label');
        if (label) {
            label.classList.add('border-primary', 'bg-primary-subtle');
        }
        // Enable submit button if one is disabled until a candidate is chosen
        const submitBtn = document.getElementById('voteSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    });
});

// ── Vote Confirmation Modal ────────────────────────────────────────────────────
const voteModal = document.getElementById('voteConfirmationModal');
if (voteModal) {
    voteModal.addEventListener('show.bs.modal', function (event) {
        const triggerBtn = event.relatedTarget;
        const candidateName = triggerBtn?.getAttribute('data-candidate-name') ?? '';
        const position = triggerBtn?.getAttribute('data-candidate-position') ?? '';
        const electionTitle = triggerBtn?.getAttribute('data-election-title') ?? '';

        const nameEl = document.getElementById('confirmCandidateName');
        const posEl = document.getElementById('confirmPosition');
        const elecEl = document.getElementById('confirmElectionTitle');

        if (nameEl) nameEl.textContent = candidateName;
        if (posEl) posEl.textContent = position;
        if (elecEl) elecEl.textContent = electionTitle;
    });
}

// ── Vote Form Inline Confirmation ─────────────────────────────────────────────
// Used when modal is not available — direct form confirm dialog
const directVoteForms = document.querySelectorAll('form[data-confirm-vote="true"]');
directVoteForms.forEach(form => {
    form.addEventListener('submit', function (e) {
        const selectedRadio = form.querySelector('input[name="candidate_id"]:checked');
        if (!selectedRadio) {
            e.preventDefault();
            alert('Please select a candidate before submitting your ballot.');
            return;
        }
        const candidateName = selectedRadio.getAttribute('data-name') ?? 'selected candidate';
        if (!confirm(`Confirm your vote for: ${candidateName}\n\nThis action is final and cannot be changed.`)) {
            e.preventDefault();
        }
    });
});

// ── Countdown Timer for Election Closing ──────────────────────────────────────
const countdownEl = document.getElementById('electionCountdown');
if (countdownEl) {
    const endTime = parseInt(countdownEl.getAttribute('data-end-timestamp') ?? '0', 10) * 1000;

    function updateCountdown() {
        const remaining = endTime - Date.now();
        if (remaining <= 0) {
            countdownEl.textContent = 'Election has closed.';
            countdownEl.classList.add('text-danger');
            return;
        }
        const hours = Math.floor(remaining / 3600000);
        const mins = Math.floor((remaining % 3600000) / 60000);
        const secs = Math.floor((remaining % 60000) / 1000);
        countdownEl.textContent = `${String(hours).padStart(2, '0')}h ${String(mins).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s remaining`;
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
}
