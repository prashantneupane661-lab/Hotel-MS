<?php
/**
 * Generate Missing Invoices Script
 * Run this ONCE to create invoices for existing bookings that don't have them
 */
require_once __DIR__ . '/config/db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Generate Missing Invoices</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>body { padding: 40px; background: #f8f9fa; }</style>
</head>
<body>
<div class='container'>
    <div class='card'>
        <div class='card-header bg-primary text-white'>
            <h3 class='mb-0'>🔧 Generate Missing Invoices</h3>
        </div>
        <div class='card-body'>";

// Find all bookings without invoices
$sql = "SELECT b.booking_id, b.customer_id, b.room_id, b.total_amount, b.total_days, b.price_per_night, 
               b.status, b.check_in, b.check_out, r.room_number, rt.type_name
        FROM bookings b
        LEFT JOIN invoices i ON b.booking_id = i.booking_id
        JOIN rooms r ON b.room_id = r.room_id
        JOIN room_types rt ON r.type_id = rt.type_id
        WHERE i.invoice_id IS NULL
        ORDER BY b.booking_id";

$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo "<div class='alert alert-success'>
            <h5>✅ All bookings already have invoices!</h5>
            <p class='mb-0'>No missing invoices found.</p>
          </div>";
} else {
    echo "<div class='alert alert-info'>
            <h5>📊 Found {$result->num_rows} booking(s) without invoices</h5>
            <p class='mb-0'>Generating invoices now...</p>
          </div>";
    
    echo "<table class='table table-striped mt-3'>
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Room</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>";
    
    $success = 0;
    $failed = 0;
    
    while ($booking = $result->fetch_assoc()) {
        $bid = (int)$booking['booking_id'];
        $total_amount = (float)$booking['total_amount'];
        $total_days = (int)$booking['total_days'];
        $price_per_night = (float)$booking['price_per_night'];
        
        // Generate invoice
        $invNo = 'INV-' . str_pad($bid, 5, '0', STR_PAD_LEFT);
        $taxRate = 10.00;
        $tax = round($total_amount * ($taxRate / 100), 2);
        $grand = $total_amount + $tax;
        
        $invStmt = $conn->prepare("INSERT INTO invoices (booking_id, invoice_no, subtotal, tax_rate, tax, extra_charges, discount, grand_total, payment_status) VALUES (?,?,?,?,?,?,?,?,?)");
        $ps = 'unpaid';
        $ec = 0.00;
        $dc = 0.00;
        $invStmt->bind_param('isdddddds', $bid, $invNo, $total_amount, $taxRate, $tax, $ec, $dc, $grand, $ps);
        
        if ($invStmt->execute()) {
            $invId = $conn->insert_id;
            
            // Insert room charge line item
            $type_rc = 'room_charge';
            $roomDesc = 'Room charge — ' . $total_days . ' night(s) @ रू' . number_format($price_per_night, 2);
            $itemStmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total) VALUES (?,?,?,?,?,?)");
            $itemStmt->bind_param('isisdd', $invId, $type_rc, $roomDesc, $total_days, $price_per_night, $total_amount);
            
            if ($itemStmt->execute()) {
                echo "<tr>
                        <td>#{$bid}</td>
                        <td>{$booking['room_number']} - {$booking['type_name']}</td>
                        <td>रू" . number_format($total_amount, 2) . "</td>
                        <td><span class='badge bg-success'>{$booking['status']}</span></td>
                        <td><span class='text-success'>✅ Invoice {$invNo} created</span></td>
                      </tr>";
                $success++;
            } else {
                echo "<tr>
                        <td>#{$bid}</td>
                        <td>{$booking['room_number']}</td>
                        <td>रू" . number_format($total_amount, 2) . "</td>
                        <td><span class='badge bg-warning'>{$booking['status']}</span></td>
                        <td><span class='text-danger'>❌ Failed to create line item</span></td>
                      </tr>";
                $failed++;
            }
        } else {
            echo "<tr>
                    <td>#{$bid}</td>
                    <td>{$booking['room_number']}</td>
                    <td>रू" . number_format($total_amount, 2) . "</td>
                    <td><span class='badge bg-warning'>{$booking['status']}</span></td>
                    <td><span class='text-danger'>❌ Failed: " . htmlspecialchars($conn->error) . "</span></td>
                  </tr>";
            $failed++;
        }
    }
    
    echo "</tbody></table>";
    
    echo "<div class='alert alert-success mt-3'>
            <h5>✅ Invoice Generation Complete!</h5>
            <p class='mb-0'>
                <strong>Successful:</strong> {$success}<br>
                <strong>Failed:</strong> {$failed}<br>
                <strong>Total:</strong> " . ($success + $failed) . "
            </p>
          </div>";
}

echo "<div class='mt-4'>
        <a href='/hotel-management/reception/checkout.php' class='btn btn-primary'>
            <i class='bi bi-box-arrow-right'></i> Go to Checkout Page
        </a>
        <a href='/hotel-management/admin/dashboard.php' class='btn btn-outline-secondary ms-2'>
            <i class='bi bi-speedometer2'></i> Go to Dashboard
        </a>
      </div>";

echo "</div></div></div></body></html>";
?>
