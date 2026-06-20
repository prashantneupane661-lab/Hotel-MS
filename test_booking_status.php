<?php
/**
 * Booking Status Test Script
 * Run this to verify the booking status workflow is working
 */
require_once __DIR__ . '/config/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Booking Status Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .badge-pending { background: #f59e0b; color: white; padding: 5px 10px; border-radius: 4px; }
        .badge-confirmed { background: #22c55e; color: white; padding: 5px 10px; border-radius: 4px; }
        .badge-rejected { background: #dc2626; color: white; padding: 5px 10px; border-radius: 4px; }
        .badge-checked_in { background: #3b82f6; color: white; padding: 5px 10px; border-radius: 4px; }
        .badge-checked_out { background: #6b7280; color: white; padding: 5px 10px; border-radius: 4px; }
        .badge-cancelled { background: #991b1b; color: white; padding: 5px 10px; border-radius: 4px; }
        body { padding: 40px; background: #f8f9fa; }
    </style>
</head>
<body>
<div class='container'>
    <h2 class='mb-4'>🧪 Booking Status Test Results</h2>";

// Test 1: Check database column structure
echo "<div class='card mb-4'>
    <div class='card-header bg-primary text-white'>
        <h5 class='mb-0'>Test 1: Database Column Structure</h5>
    </div>
    <div class='card-body'>";

$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'");
if ($result && $row = $result->fetch_assoc()) {
    echo "<p><strong>Column Type:</strong> {$row['Type']}</p>";
    echo "<p><strong>Null:</strong> {$row['Null']}</p>";
    echo "<p><strong>Default:</strong> {$row['Default']}</p>";
    
    if (strpos($row['Type'], 'pending') !== false && $row['Default'] === 'pending' && $row['Null'] === 'NO') {
        echo "<div class='alert alert-success'>✅ PASS: Column is correctly configured</div>";
    } else {
        echo "<div class='alert alert-danger'>❌ FAIL: Column needs fixing</div>";
    }
} else {
    echo "<div class='alert alert-danger'>❌ FAIL: Could not read column info</div>";
}

echo "</div></div>";

// Test 2: Check existing bookings
echo "<div class='card mb-4'>
    <div class='card-header bg-success text-white'>
        <h5 class='mb-0'>Test 2: Existing Bookings Status</h5>
    </div>
    <div class='card-body'>";

$bookings = $conn->query("SELECT booking_id, status, check_in, check_out FROM bookings ORDER BY booking_id DESC LIMIT 10");
if ($bookings && $bookings->num_rows > 0) {
    echo "<table class='table table-striped'>
        <thead><tr><th>Booking ID</th><th>Status</th><th>Badge</th><th>Check In</th><th>Check Out</th></tr></thead>
        <tbody>";
    
    $hasEmpty = false;
    while ($b = $bookings->fetch_assoc()) {
        $status = $b['status'];
        $isEmpty = empty($status);
        if ($isEmpty) $hasEmpty = true;
        
        echo "<tr>
            <td>#{$b['booking_id']}</td>
            <td><code>" . ($isEmpty ? 'EMPTY' : htmlspecialchars($status)) . "</code></td>
            <td>";
        
        if (!$isEmpty) {
            echo "<span class='badge badge-{$status}'>" . ucfirst(str_replace('_', ' ', $status)) . "</span>";
        } else {
            echo "<span class='badge bg-danger'>EMPTY!</span>";
        }
        
        echo "</td>
            <td>{$b['check_in']}</td>
            <td>{$b['check_out']}</td>
        </tr>";
    }
    
    echo "</tbody></table>";
    
    if ($hasEmpty) {
        echo "<div class='alert alert-warning'>⚠️ WARNING: Some bookings have empty status. Running fix...</div>";
        $conn->query("UPDATE bookings SET status='pending' WHERE status IS NULL OR status=''");
        echo "<div class='alert alert-success'>✅ Fixed! All empty statuses set to 'pending'</div>";
    } else {
        echo "<div class='alert alert-success'>✅ PASS: All bookings have valid status</div>";
    }
} else {
    echo "<div class='alert alert-info'>ℹ️ No bookings found in database</div>";
}

echo "</div></div>";

// Test 3: Check CSS file
echo "<div class='card mb-4'>
    <div class='card-header bg-info text-white'>
        <h5 class='mb-0'>Test 3: CSS Badge Styles</h5>
    </div>
    <div class='card-body'>";

$cssFile = __DIR__ . '/assets/css/style.css';
$css = file_get_contents($cssFile);
$badges = ['badge-pending', 'badge-confirmed', 'badge-rejected', 'badge-checked_in', 'badge-checked_out', 'badge-cancelled'];
$allFound = true;

foreach ($badges as $badge) {
    if (strpos($css, ".{$badge}") !== false) {
        echo "<div class='text-success'>✅ {$badge} - Found</div>";
    } else {
        echo "<div class='text-danger'>❌ {$badge} - Missing!</div>";
        $allFound = false;
    }
}

if ($allFound) {
    echo "<div class='alert alert-success mt-3'>✅ PASS: All badge styles are defined</div>";
} else {
    echo "<div class='alert alert-danger mt-3'>❌ FAIL: Some badge styles are missing</div>";
}

echo "</div></div>";

// Test 4: Test creating a new booking
echo "<div class='card mb-4'>
    <div class='card-header bg-warning'>
        <h5 class='mb-0'>Test 4: Create Test Booking</h5>
    </div>
    <div class='card-body'>";

// Find an available room
$room = $conn->query("SELECT r.room_id, r.room_number, rt.base_price FROM rooms r JOIN room_types rt ON r.type_id = rt.type_id WHERE r.status = 'available' LIMIT 1")->fetch_assoc();

if ($room) {
    $check_in = date('Y-m-d', strtotime('+7 days'));
    $check_out = date('Y-m-d', strtotime('+8 days'));
    $days = 1;
    $total = $room['base_price'] * $days;
    
    // Insert test booking
    $stmt = $conn->prepare("INSERT INTO bookings (customer_id, room_id, user_id, check_in, check_out, total_days, price_per_night, status, total_amount) VALUES (1, ?, 1, ?, ?, ?, ?, 'pending', ?)");
    $stmt->bind_param('issid', $room['room_id'], $check_in, $check_out, $days, $room['base_price'], $total);
    
    if ($stmt->execute()) {
        $newBookingId = $conn->insert_id;
        echo "<div class='alert alert-success'>✅ Test booking created: #{$newBookingId}</div>";
        
        // Verify it was created with 'pending' status
        $verify = $conn->query("SELECT booking_id, status FROM bookings WHERE booking_id = {$newBookingId}")->fetch_assoc();
        if ($verify && $verify['status'] === 'pending') {
            echo "<div class='alert alert-success'>✅ PASS: Booking status is correctly set to 'pending'</div>";
            echo "<p><strong>Status Value:</strong> <code>{$verify['status']}</code></p>";
            echo "<p><strong>Badge:</strong> <span class='badge badge-pending'>Pending</span></p>";
        } else {
            echo "<div class='alert alert-danger'>❌ FAIL: Booking status is not 'pending'</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>❌ FAIL: Could not create test booking</div>";
    }
} else {
    echo "<div class='alert alert-info'>ℹ️ No available rooms to create test booking</div>";
}

echo "</div></div>";

// Summary
echo "<div class='card'>
    <div class='card-header bg-dark text-white'>
        <h5 class='mb-0'>📊 Summary</h5>
    </div>
    <div class='card-body'>
        <h6>Status Badge Preview:</h6>
        <div class='d-flex gap-2 flex-wrap mt-3'>
            <span class='badge badge-pending'>Pending</span>
            <span class='badge badge-confirmed'>Confirmed</span>
            <span class='badge badge-rejected'>Rejected</span>
            <span class='badge badge-checked_in'>Checked In</span>
            <span class='badge badge-checked_out'>Checked Out</span>
            <span class='badge badge-cancelled'>Cancelled</span>
        </div>
        
        <div class='alert alert-info mt-4'>
            <strong>Next Steps:</strong>
            <ol class='mb-0'>
                <li>If all tests passed, your booking status workflow is working!</li>
                <li>Clear browser cache (Ctrl + F5)</li>
                <li>Test booking a room as a customer</li>
                <li>Verify 'Pending' badge appears on customer dashboard</li>
            </ol>
        </div>
    </div>
</div>";

echo "</div></body></html>";
?>
