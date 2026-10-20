<?php
/**
 * College Voting System - Public Landing Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/services/ElectionService.php';
require_once __DIR__ . '/../app/repositories/ElectionRepository.php';

$pageTitle = 'Home';

$db = Database::getConnection();
$electionService = new ElectionService($db);
$electionService->syncStatuses();

$electionRepo = new ElectionRepository($db);
$openElections = $electionRepo->getAll(['status' => 'open']);
$publishedElections = $electionRepo->getAll(['status' => 'results_published']);
$stats = $electionRepo->getStats();

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="container py-4 py-md-5">
    <!-- Hero Section with Animation & Dynamic Metrics -->
    <div class="hero-wrapper position-relative">
        <div class="hero-glow-blob-1"></div>
        <div class="hero-glow-blob-2"></div>

        <div class="row align-items-center g-4 position-relative" style="z-index: 1;">
            <div class="col-lg-7">
                <div class="hero-animate-badge mb-3">
                    <span class="badge bg-white text-primary border px-3 py-2 rounded-pill fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                        <span class="pulse-radar-dot"></span>
                        <span>Transparent &bull; Tamper-Resistant &bull; Secret Ballot</span>
                    </span>
                </div>
                <h1 class="display-5 fw-bold hero-gradient-text lh-sm mb-3 hero-animate-title">
                    Empowering Student Voices Through Secure Campus Elections
                </h1>
                <p class="lead text-secondary mb-4 hero-animate-lead" style="max-width: 620px;">
                    The official College Voting System delivers an audited, transaction-safe election platform. Cast your vote securely, review verified candidate manifestos, and track certified live election turnout.
                </p>
                <div class="d-flex flex-wrap gap-2 hero-animate-actions">
                    <?php if (Auth::check()): ?>
                        <a href="<?= Auth::isAdmin() ? admin_url('dashboard.php') : public_url('dashboard.php') ?>" class="btn btn-navy btn-lg px-4 shadow-sm">
                            <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?= public_url('login.php') ?>" class="btn btn-navy btn-lg px-4 shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Sign In to Vote
                        </a>
                        <a href="<?= public_url('register.php') ?>" class="btn btn-outline-secondary btn-lg px-4 bg-white">
                            Register Account
                        </a>
                    <?php endif; ?>
                    <a href="<?= public_url('results.php') ?>" class="btn btn-light btn-lg border px-4 shadow-sm">
                        <i class="bi bi-trophy me-2 text-warning"></i> View Results
                    </a>
                </div>
            </div>

            <div class="col-lg-5 hero-animate-visual">
                <div class="hero-visual-card p-4">
                    <!-- Floating Micro-badges -->
                    <div class="hero-float-badge hero-float-badge-1">
                        <i class="bi bi-shield-check text-success fs-6"></i>
                        <span>100% Secret Ballot</span>
                    </div>
                    <div class="hero-float-badge hero-float-badge-2">
                        <span class="pulse-radar-dot"></span>
                        <span>Live Turnout Tracking</span>
                    </div>

                    <!-- Animated Ballot Illustration -->
                    <svg class="hero-svg-illustration mb-3" viewBox="0 0 280 180" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="boxGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#1e3a8a"/>
                                <stop offset="100%" stop-color="#0f172a"/>
                            </linearGradient>
                            <linearGradient id="slotGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#0b1329"/>
                                <stop offset="100%" stop-color="#1e293b"/>
                            </linearGradient>
                            <linearGradient id="paperGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#ffffff"/>
                                <stop offset="100%" stop-color="#f8fafc"/>
                            </linearGradient>
                            <linearGradient id="accentGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#3b82f6"/>
                                <stop offset="100%" stop-color="#10b981"/>
                            </linearGradient>
                            <filter id="cardShadow" x="-20%" y="-20%" width="140%" height="140%">
                                <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#0f172a" flood-opacity="0.18"/>
                            </filter>
                        </defs>
                        <!-- Background concentric security orbit ring -->
                        <circle class="anim-glow-ring" cx="140" cy="115" r="62" stroke="url(#accentGrad)" stroke-width="2" stroke-dasharray="6 8" opacity="0.4"/>
                        
                        <!-- Ballot Box Body -->
                        <rect x="50" y="82" width="180" height="88" rx="14" fill="url(#boxGrad)" filter="url(#cardShadow)"/>
                        
                        <!-- Top Accent Line on Box -->
                        <rect x="50" y="82" width="180" height="4" rx="2" fill="url(#accentGrad)"/>
                        
                        <!-- Slot on Top Lid -->
                        <rect x="85" y="90" width="110" height="7" rx="3.5" fill="url(#slotGrad)"/>
                        
                        <!-- Animated Ballot Paper Dipping into Slot -->
                        <g class="anim-ballot-paper">
                            <rect x="105" y="30" width="70" height="66" rx="6" fill="url(#paperGrad)" stroke="#cbd5e1" stroke-width="1.5" filter="url(#cardShadow)"/>
                            <!-- Voting Lines on Paper -->
                            <line x1="117" y1="44" x2="135" y2="44" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>
                            <line x1="117" y1="52" x2="145" y2="52" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>
                            <line x1="117" y1="60" x2="130" y2="60" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>
                            <!-- Verified Check Badge on Paper -->
                            <circle cx="157" cy="48" r="9" fill="#10b981"/>
                            <path d="M153 48L156 51L162 45" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </g>

                        <!-- Front Seal Emblem on Box -->
                        <g class="anim-pulse-check">
                            <circle cx="140" cy="132" r="18" fill="rgba(255,255,255,0.12)" stroke="url(#accentGrad)" stroke-width="2"/>
                            <path d="M133 132L138 137L148 127" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        </g>
                    </svg>

                    <!-- Animated Metrics Grid -->
                    <div class="row g-2 pt-2">
                        <div class="col-6">
                            <div class="hero-stat-card text-center">
                                <div class="hero-stat-number text-primary hero-counter" data-target="<?= (int)$stats['open_elections'] ?>">0</div>
                                <div class="text-muted small fw-medium">Active Elections</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="hero-stat-card text-center">
                                <div class="hero-stat-number text-success hero-counter" data-target="<?= (int)$stats['total_votes'] ?>">0</div>
                                <div class="text-muted small fw-medium">Verified Ballots</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="hero-stat-card text-center">
                                <div class="hero-stat-number text-info hero-counter" data-target="<?= (int)$stats['completed_elections'] ?>">0</div>
                                <div class="text-muted small fw-medium">Completed Polls</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="hero-stat-card text-center">
                                <div class="hero-stat-number text-warning hero-counter" data-target="<?= (int)$stats['scheduled_elections'] ?>">0</div>
                                <div class="text-muted small fw-medium">Upcoming Polls</div>
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                        <i class="bi bi-shield-lock-fill text-success fs-5"></i>
                        <span style="font-size: 0.78rem;">Decoupled ballot records &bull; DB constraint uniqueness protection</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Active Elections Section -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h4 fw-bold mb-1">Currently Open Elections</h2>
                <p class="text-muted small mb-0">Cast your vote before the scheduled closing deadline.</p>
            </div>
            <a href="<?= base_url('elections.php') ?>" class="btn btn-outline-primary btn-sm">View All</a>
        </div>

        <?php if (empty($openElections)): ?>
            <div class="card card-academic p-4 text-center text-muted">
                <i class="bi bi-inbox fs-2 mb-2"></i>
                <p class="mb-0">There are currently no active elections open for voting.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach (array_slice($openElections, 0, 3) as $el): ?>
                    <div class="col-md-4">
                        <div class="card card-academic h-100 p-3 p-lg-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge badge-status badge-open">Open</span>
                                <span class="badge bg-secondary-subtle text-secondary small"><?= e($el['academic_year']) ?></span>
                            </div>
                            <h5 class="fw-bold mb-2"><?= e($el['title']) ?></h5>
                            <p class="text-secondary small mb-3 flex-grow-1">
                                <?= e(mb_strimwidth((string)$el['description'], 0, 110, '...')) ?>
                            </p>
                            <div class="small text-muted mb-3">
                                <div><i class="bi bi-clock me-1"></i> Closes: <?= format_datetime($el['end_time']) ?></div>
                                <div><i class="bi bi-people me-1"></i> <?= $el['candidate_count'] ?> Candidates Contesting</div>
                            </div>
                            <a href="<?= base_url('election-details.php?id=' . $el['id']) ?>" class="btn btn-outline-primary w-100">
                                View Details & Vote &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Core Security & Architecture Pillars -->
    <div class="row g-4 py-3">
        <div class="col-md-4">
            <div class="card card-academic p-4 h-100">
                <div class="stat-icon-wrapper bg-primary-subtle text-primary mb-3">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h5 class="fw-bold">Duplicate Vote Prevention</h5>
                <p class="text-secondary small mb-0">
                    Database-enforced composite uniqueness constraints `UNIQUE(election_id, student_id)` and atomic ACID transactions guarantee that double-submitting or race conditions are strictly blocked.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-academic p-4 h-100">
                <div class="stat-icon-wrapper bg-success-subtle text-success mb-3">
                    <i class="bi bi-incognito"></i>
                </div>
                <h5 class="fw-bold">Decoupled Ballot Privacy</h5>
                <p class="text-secondary small mb-0">
                    Voter participation records are separated from candidate ballot records. Cast ballots store anonymized cryptographic tokens, keeping individual choices private from dashboard operators.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-academic p-4 h-100">
                <div class="stat-icon-wrapper bg-warning-subtle text-warning mb-3">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
                <h5 class="fw-bold">Certified Result Snapshots</h5>
                <p class="text-secondary small mb-0">
                    Upon election conclusion, election outcomes are automatically calculated, tie-checked, and frozen into permanent result snapshots with turnout statistics and CSV exports.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
