<!DOCTYPE html>
<html>
<head>
    <title>Housekeeping Workflow Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .success { border-left-color: #28a745; }
        .info { border-left-color: #17a2b8; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Housekeeping Workflow Test</h1>
    
    <div class="step info">
        <h3>Step 1: Guest Checks Out</h3>
        <p><strong>Action:</strong> Receptionist clicks "Check Out" on a checked-in guest</p>
        <p><strong>What happens:</strong></p>
        <ul>
            <li>Booking status → <code>checked_out</code></li>
            <li>Room status → <code>available</code> (rooms table)</li>
            <li>Housekeeping status → <code>dirty</code> (housekeeping table)</li>
            <li>Notification sent to admin & accountant</li>
        </ul>
        <p><strong>File:</strong> <code>reception/checkout.php</code> (lines 29-41)</p>
    </div>

    <div class="step">
        <h3>Step 2: Housekeeping Staff Sees Dirty Room</h3>
        <p><strong>URL:</strong> <a href="/hotel-management/housekeeping/dashboard.php" target="_blank">http://localhost/hotel-management/housekeeping/dashboard.php</a></p>
        <p><strong>What they see:</strong></p>
        <ul>
            <li>ONLY rooms with <code>housekeeping.status = 'dirty'</code></li>
            <li>Room number, type, floor, checkout time</li>
            <li>✔ Cleaned button for each room</li>
        </ul>
        <p><strong>Access:</strong> housekeeping, admin, receptionist roles</p>
    </div>

    <div class="step success">
        <h3>Step 3: Housekeeping Marks Room as Cleaned</h3>
        <p><strong>Action:</strong> Staff clicks "✔ Cleaned" button</p>
        <p><strong>What happens:</strong></p>
        <ul>
            <li>Housekeeping status → <code>clean</code></li>
            <li>Room remains <code>available</code> (ready for new guest)</li>
            <li>Room removed from housekeeping pending list</li>
            <li>Notification sent to admin & receptionist</li>
            <li>Success message displayed</li>
        </ul>
        <p><strong>File:</strong> <code>housekeeping/dashboard.php</code> (lines 15-51)</p>
    </div>

    <div class="step info">
        <h3>Database Flow</h3>
        <pre>
CHECKOUT (reception/checkout.php):
├─ UPDATE bookings SET status='checked_out' WHERE booking_id=?
├─ UPDATE rooms SET status='available' WHERE room_id=?
└─ INSERT INTO housekeeping (room_id, status='dirty', notes='Room needs cleaning after checkout')

CLEAN (housekeeping/dashboard.php):
├─ UPDATE housekeeping SET status='clean', last_cleaned=NOW() WHERE room_id=?
└─ (Room already 'available', stays 'available')
        </pre>
    </div>

    <div class="step success">
        <h3>✅ Workflow Complete</h3>
        <p><strong>Key Points:</strong></p>
        <ul>
            <li>✅ No impact on existing booking/billing/checkout logic</li>
            <li>✅ Housekeeping shows ONLY checkout rooms (dirty status)</li>
            <li>✅ One-click clean button (✔ Cleaned)</li>
            <li>✅ Room becomes fully available after cleaning</li>
            <li>✅ Room removed from housekeeping list after cleaning</li>
            <li>✅ Notifications sent at each step</li>
        </ul>
    </div>

    <h2>How to Test:</h2>
    <ol>
        <li><strong>Login as Receptionist</strong> → Go to Check-Out page</li>
        <li><strong>Check out a guest</strong> → Room marked as dirty in housekeeping</li>
        <li><strong>Login as Housekeeping Staff</strong> (or Admin/Receptionist)</li>
        <li><strong>Go to Housekeeping Dashboard</strong> → See the dirty room</li>
        <li><strong>Click "✔ Cleaned"</strong> → Room marked clean, removed from list</li>
        <li><strong>Verify</strong> → Room status is 'available', housekeeping status is 'clean'</li>
    </ol>

    <p><a href="/hotel-management/housekeeping/dashboard.php">👉 Go to Housekeeping Dashboard</a></p>
</body>
</html>
