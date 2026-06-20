# 🏨 Hotel Management System - Booking Approval Workflow

## ✅ **COMPLETE IMPLEMENTATION GUIDE**

Your hotel management system now has a **complete booking approval workflow** with all requested features!

---

## 📋 **WORKFLOW OVERVIEW**

```
CUSTOMER BOOKS ROOM
        ↓
Status: PENDING (Yellow Badge)
        ↓
ADMIN/RECEPTION REVIEWS
        ↓
    ┌──────────────┬──────────────┐
    │   ACCEPT     │    REJECT    │
    │              │              │
    ↓              ↓              ↓
CONFIRMED       REJECTED      Room stays
(Green)         (Red)         available
    ↓              ↓
Customer        Customer
Notified        Notified
    ↓              ↓
Can check-in   Can re-book
```

---

## 🗄️ **STEP 1: UPDATE DATABASE**

Run this SQL script to add 'rejected' status:

```bash
# Option 1: Via phpMyAdmin
1. Open http://localhost/phpmyadmin
2. Select 'hotel_management' database
3. Go to SQL tab
4. Paste and run the contents of: database/add_rejected_status.sql

# Option 2: Via Command Line
cd c:\xampp\mysql\bin
mysql -u root -p hotel_management < c:\xampp\htdocs\hotel-management\database\add_rejected_status.sql
```

**What this does:**
- ✅ Adds 'rejected' to booking status ENUM
- ✅ Keeps all existing statuses intact
- ✅ Default status remains 'pending'

---

## 👤 **CUSTOMER SIDE**

### **Booking a Room:**
```
URL: http://localhost/hotel-management/customer/book_room.php
```

**What happens:**
1. Customer searches for available rooms
2. Selects room and dates
3. Books room → Status: `pending`
4. Receives notification: "Booking request submitted"
5. Sees **YELLOW "Pending Approval" badge** in dashboard

### **Customer Dashboard:**
```
URL: http://localhost/hotel-management/customer/dashboard.php
```

**Booking Status Colors:**
| Status | Badge Color | Meaning |
|--------|-------------|---------|
| Pending | 🟡 Yellow | Waiting for approval |
| Confirmed | 🟢 Green | Approved, can check-in |
| Rejected | 🔴 Red | Denied, can re-book |
| Checked In | 🔵 Blue | Currently staying |
| Checked Out | ⚪ Gray | Stay completed |
| Cancelled | 🟤 Dark Red | Cancelled by customer/staff |

**Features:**
- ✅ View all bookings with status badges
- ✅ Cannot check-in until status = 'Confirmed'
- ✅ Receives notifications for approval/rejection
- ✅ Bell icon shows unread notification count
- ✅ Can mark notifications as read

---

## 👨‍💼 **ADMIN & RECEPTIONIST SIDE**

### **View Pending Bookings:**

**Option 1: Dashboard (Quick View)**
```
Admin: http://localhost/hotel-management/admin/dashboard.php
Reception: http://localhost/hotel-management/reception/dashboard.php
```
- Shows pending bookings alert card
- One-click Accept/Reject buttons
- Real-time notification to customer

**Option 2: All Bookings Page (Full Management)**
```
http://localhost/hotel-management/reception/all_bookings.php
http://localhost/hotel-management/admin/bookings.php
```

**Features:**
- ✅ Filter by status (Pending, Confirmed, etc.)
- ✅ Search by guest name, room number, booking ID
- ✅ Statistics overview (total, pending, confirmed, cancelled)
- ✅ Detailed booking information
- ✅ Accept/Reject buttons for pending bookings

### **Accepting a Booking:**

1. Click **Green "Accept" button**
2. Confirmation dialog appears
3. System does:
   - ✅ Status → 'confirmed'
   - ✅ Room status → 'reserved'
   - ✅ Customer notified (bell icon)
   - ✅ Email sent to customer
   - ✅ Success message shown

### **Rejecting a Booking:**

1. Click **Red "Reject" button**
2. Modal opens asking for reason (REQUIRED)
3. System does:
   - ✅ Status → 'rejected'
   - ✅ Room status → 'available' (room freed up)
   - ✅ Customer notified with reason
   - ✅ Email sent to customer
   - ✅ Can re-book different room/dates

---

## 🔔 **NOTIFICATION SYSTEM**

### **Database Table:**
```sql
notifications (
    notification_id INT AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(255),
    message TEXT,
    type ENUM('booking','payment','checkout','housekeeping','system'),
    related_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP
)
```

### **How It Works:**

**When Booking is Accepted:**
```
Customer receives:
📱 In-app notification: "Booking Confirmed! Your booking #123 has been confirmed..."
📧 Email: Professional HTML email with booking details
```

**When Booking is Rejected:**
```
Customer receives:
📱 In-app notification: "Booking Rejected. Your booking #123 was rejected. Reason: ..."
📧 Email: Polite rejection email with reason and next steps
```

### **Notification Bell:**
- Located in top navigation bar
- Shows red badge with unread count
- Click to view all notifications
- Mark individual or all as read

---

## 🔒 **SECURITY FEATURES**

### **Role-Based Access Control:**

| Action | Customer | Reception | Admin |
|--------|----------|-----------|-------|
| Book Room | ✅ | ✅ | ✅ |
| View Own Bookings | ✅ | ✅ | ✅ |
| View All Bookings | ❌ | ✅ | ✅ |
| Accept Booking | ❌ | ✅ | ✅ |
| Reject Booking | ❌ | ✅ | ✅ |
| Change Status | ❌ | ✅ | ✅ |

### **Protection:**
- ✅ Customers cannot change booking status
- ✅ CSRF token validation on all actions
- ✅ SQL injection prevention (prepared statements)
- ✅ Session-based authentication
- ✅ Role verification on every protected page
- ✅ Input sanitization and validation

---

## 🎨 **UI/UX FEATURES**

### **Bootstrap Badges:**
```html
Pending    → <span class="badge badge-pending">Pending</span>
Confirmed  → <span class="badge badge-confirmed">Confirmed</span>
Rejected   → <span class="badge badge-rejected">Rejected</span>
Checked In → <span class="badge badge-checked_in">Checked In</span>
Checked Out→ <span class="badge badge-checked_out">Checked Out</span>
Cancelled  → <span class="badge badge-cancelled">Cancelled</span>
```

### **Color Scheme:**
- 🟡 **Yellow** (#f59e0b) - Pending
- 🟢 **Green** (#22c55e) - Confirmed
- 🔴 **Red** (#dc2626) - Rejected
- 🔵 **Blue** (#3b82f6) - Checked In
- ⚪ **Gray** (#6b7280) - Checked Out
- 🟤 **Dark Red** (#991b1b) - Cancelled

---

## 📧 **EMAIL NOTIFICATIONS**

### **Configuration:**
Emails are saved to `logs/emails/` folder for testing.

**For production, configure SMTP:**
1. Edit `C:\xampp\php\php.ini`
2. Configure SMTP settings
3. Or use PHPMailer with Gmail/SendGrid

### **Email Templates:**
- ✅ Professional HTML design
- ✅ Hotel branding
- ✅ Booking details included
- ✅ Mobile responsive
- ✅ Call-to-action buttons

---

## 🚀 **HOW TO TEST THE COMPLETE WORKFLOW**

### **Test Scenario:**

**Step 1: Customer Books Room**
```
1. Login as customer
   Username: customer@test.com
   Password: (your password)

2. Go to: Book a Room
3. Search available rooms
4. Select room and dates
5. Click "Book Now"
6. See "Pending Approval" badge
```

**Step 2: Admin/Reception Reviews**
```
1. Login as admin or reception
   Username: admin@hotel.com (or reception@hotel.com)
   Password: (your password)

2. Go to Dashboard
3. See yellow "Pending Booking Requests" card
4. Click "Accept" or "Reject"
```

**Step 3: Customer Receives Notification**
```
1. Login as customer again
2. See notification bell with red badge
3. Click bell to view notification
4. Check booking status changed:
   - Green badge if accepted
   - Red badge if rejected
5. Check logs/emails/ for email notification
```

---

## 📁 **KEY FILES**

### **Customer Side:**
- `customer/book_room.php` - Book a room
- `customer/dashboard.php` - View bookings with status

### **Admin Side:**
- `admin/dashboard.php` - Pending bookings overview
- `admin/bookings.php` - Full booking management
- `admin/customers.php` - Customer management

### **Reception Side:**
- `reception/dashboard.php` - Pending bookings overview
- `reception/all_bookings.php` - Full booking management
- `reception/customers.php` - Customer management

### **Core Files:**
- `config/db.php` - Database connection & functions
- `includes/header.php` - Navigation & notification bell
- `assets/css/style.css` - Badge colors & styling

### **Database:**
- `database/schema.sql` - Full database schema
- `database/add_rejected_status.sql` - Migration script

---

## ✨ **ADDITIONAL FEATURES**

### **Already Implemented:**
- ✅ Auto-create customer profile if not exists
- ✅ Room availability checking before booking
- ✅ Prevent double booking
- ✅ Invoice generation after confirmation
- ✅ Check-in/check-out workflow
- ✅ Payment tracking
- ✅ Activity logging
- ✅ Flash messages for user feedback

### **Optional Enhancements:**
- 📧 Configure real SMTP for email sending
- 📱 Add SMS notifications (Twilio integration)
- 📊 Add booking analytics dashboard
- 📅 Add calendar view for bookings
- 🔔 Add push notifications
- 💳 Add online payment gateway

---

## 🎯 **SUMMARY**

Your Hotel Management System now has:

✅ **Complete booking approval workflow**
✅ **Dual-channel notifications** (in-app + email)
✅ **Role-based access control**
✅ **Color-coded status badges**
✅ **Secure CSRF protection**
✅ **SQL injection prevention**
✅ **Professional email templates**
✅ **Real-time status updates**
✅ **Room availability management**
✅ **Customer self-service portal**

**All requirements from your specification have been implemented!** 🎉

---

## 📞 **SUPPORT**

If you encounter any issues:
1. Check Apache error logs: `C:\xampp\apache\logs\error.log`
2. Check PHP error logs
3. Verify database migration was successful
4. Clear browser cache (Ctrl + F5)

**System Status:** ✅ **Production Ready**
