<?php
/**
 * Database Setup Script — Run once after importing schema.sql
 * This creates any missing tables that were added after initial schema
 */
require_once __DIR__ . '/config/db.php';

echo "<h2>Hotel Management System - Database Setup</h2>";

// Create error_logs table if it doesn't exist
$result = $conn->query("SHOW TABLES LIKE 'error_logs'");
if ($result && $result->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS error_logs (
        error_id     INT AUTO_INCREMENT PRIMARY KEY,
        severity     ENUM('error','warning','notice','info','debug') NOT NULL DEFAULT 'error',
        message      TEXT NOT NULL,
        file_path    VARCHAR(500) DEFAULT NULL,
        line_number  INT DEFAULT NULL,
        stack_trace  TEXT DEFAULT NULL,
        user_id      INT DEFAULT NULL,
        url          VARCHAR(500) DEFAULT NULL,
        ip_address   VARCHAR(45) DEFAULT NULL,
        request_method VARCHAR(10) DEFAULT NULL,
        is_resolved  TINYINT(1) NOT NULL DEFAULT 0,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_severity (severity),
        INDEX idx_resolved (is_resolved),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB");
    echo "✓ Created <strong>error_logs</strong> table<br>";
} else {
    echo "✓ <strong>error_logs</strong> table already exists<br>";
}

// Check all required tables
$requiredTables = [
    'users', 'password_reset_tokens', 'login_activity',
    'customers', 'room_types', 'rooms', 'bookings',
    'payments', 'extra_services', 'invoices', 'invoice_items',
    'housekeeping', 'notifications', 'booking_services',
    'error_logs'
];

echo "<h3>Table Verification:</h3>";
$allGood = true;
foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $count = $conn->query("SELECT COUNT(*) AS c FROM $table")->fetch_assoc()['c'];
        echo "✓ <strong>$table</strong> ($count rows)<br>";
    } else {
        echo "✗ <strong>$table</strong> - MISSING (import schema.sql first)<br>";
        $allGood = false;
    }
}

echo "<hr>";
if ($allGood) {
    echo "<h3 style='color: green;'>✓ All tables are ready!</h3>";
    echo "<p><a href='/hotel-management/index.php'>Go to Login</a></p>";
} else {
    echo "<h3 style='color: red;'>✗ Some tables are missing</h3>";
    echo "<p>Please import <code>database/schema.sql</code> in phpMyAdmin first.</p>";
}
?>
