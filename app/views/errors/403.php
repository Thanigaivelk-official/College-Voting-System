<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden Access | College Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
        .error-card { max-width: 520px; border: none; border-radius: 12px; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">
    <div class="container text-center">
        <div class="card error-card mx-auto shadow-sm p-4 p-md-5">
            <div class="mb-3 text-danger">
                <i class="bi bi-shield-lock-fill" style="font-size: 4rem;"></i>
            </div>
            <h1 class="h3 fw-bold text-dark mb-2">403 — Access Forbidden</h1>
            <p class="text-secondary mb-4">
                You do not have the required security permissions or role to access this resource. All unauthorized access attempts are recorded in the audit log.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="javascript:history.back()" class="btn btn-outline-secondary">Go Back</a>
                <a href="/dashboard.php" class="btn btn-primary px-4" style="background-color: #1e3a8a; border-color: #1e3a8a;">Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
