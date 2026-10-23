<?php
require_once __DIR__ . '/../includes/api.php';
apiUser(true);
apiPost();
// Financial records cannot be removed without destroying reconciliation history.
jsonResponse(['error' => 'Financial records are retained for audit. Deletion is disabled.'], 409);
