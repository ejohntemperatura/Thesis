<?php
// Backfill script to normalize leave_requests.leave_type variants to 'service_credit'
// Safe to run multiple times; only updates rows that match known variants or empty with clear context.

require_once __DIR__ . '/../config/database.php';

function normalize_type($t) {
    $t = strtolower(trim((string)$t));
    $t = str_replace(['-', ' '], '_', $t);
    $t = preg_replace('/[^a-z0-9_]/', '', $t);
    if ($t === 'service_credits' || $t === 'service' || $t === 'servicecredit' || $t === 'svc_credit' || $t === 'svc' || (strpos($t,'service') !== false && strpos($t,'credit') !== false)) {
        return 'service_credit';
    }
    return $t;
}

try {
    // 1) Update clear variants directly stored in leave_type
    $variants = [
        'service_credits','service','servicecredit','svc_credit','svc'
    ];
    $in = implode(',', array_fill(0, count($variants), '?'));
    $sql = "UPDATE leave_requests SET leave_type = 'service_credit' WHERE LOWER(REPLACE(REPLACE(leave_type,'-','_'),' ', '_')) IN ($in)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($variants);
    $updated1 = $stmt->rowCount();

    // 2) If leave_type is empty/NULL but original_leave_type indicates service credit, backfill
    $sql2 = "UPDATE leave_requests SET leave_type = 'service_credit' 
             WHERE (leave_type IS NULL OR leave_type = '') 
             AND (
                LOWER(REPLACE(REPLACE(original_leave_type,'-','_'),' ', '_')) IN ($in)
                OR (
                    original_leave_type IS NOT NULL AND 
                    LOCATE('service', LOWER(original_leave_type)) > 0 AND 
                    LOCATE('credit', LOWER(original_leave_type)) > 0
                )
             )";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($variants);
    $updated2 = $stmt2->rowCount();

    echo "Backfill complete. Updated rows: variants_in_leave_type={$updated1}, from_original_type={$updated2}\n";
} catch (Exception $e) {
    echo "Error during backfill: " . $e->getMessage() . "\n";
    exit(1);
}
