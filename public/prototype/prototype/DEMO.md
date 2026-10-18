# EL Glamorous Face and Body Clinic Booking System — Demo Guide

A step-by-step script for showing the booking system: a customer books online, branch staff handle the booking and log supplies, and the admin oversees every branch.

**Running time:** about 15 minutes for Parts 1–3, or about 20–25 minutes with the optional Parts 4 and 5.

---

## 1. Before the demo (do this 10 minutes early)

### 1.1 Start the servers
1. Open the **XAMPP Control Panel** (manager-osx).
2. Start **Apache** and **MySQL**. Both should show as running (green).

### 1.2 Reset the demo data
This clears bookings from earlier runs, restores the starting stock, and clears any paused sign-ins. Accounts, branches and treatments are kept.

**Option A — phpMyAdmin**
1. Open <http://localhost/phpmyadmin>.
2. Click **Import**, choose `database/reset_demo.sql`, click **Go**.
3. Click **Import** again, choose `database/schema.sql`, click **Go**.

**Option B — Terminal** (from the project folder)
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root < database/reset_demo.sql
/Applications/XAMPP/xamppfiles/bin/mysql -u root < database/schema.sql
```

### 1.3 Have your sign-in details ready
The generated demo passwords are in `database/dev-accounts.local.txt`, which is on your computer only and not in git. Passwords you chose yourself (HR, Receptionist, Accounting & Inventory) aren't stored there.

| Account | Email | Used in | What they see |
|---------|-------|---------|---------------|
| Staff (Ava Reception) | `staff.canduman@elglam.test` | Part 2 | Canduman bookings, walk-ins and stock |
| Receptionist | `receptionist.pajac@elglam.test` | Part 4 | Pajac front desk **plus** the Office pages (customers, sales, reports, employees, expenses, POs) |
| Admin | `admin@elglam.test` | Part 3 | Every branch and every page, including Staff accounts and the service catalog |

> No accounts yet (for example, on a new computer)? See [Setting up on a new computer](#6-setting-up-on-a-new-computer).

### 1.4 Prepare your browser windows
You'll be signed in as two different people, and **one browser window can only hold one sign-in at a time**. Open:

| Window | Used for | Address |
|--------|----------|---------|
| Normal window | Public site (branches, map and services), registered customer booking, then **staff** | <http://localhost/el-glam-booking-system/prototype/guest-view/> |
| Private / Incognito window | **Admin** | <http://localhost/el-glam-booking-system/prototype/login.php> |

Always use the `http://localhost/...` addresses. Opening the files directly (double-clicking them) won't work, because the pages can't reach the database that way.

### 1.5 Quick check (30 seconds)
- [ ] The public site opens without signing in and shows the branches, map and services. Booking requires a customer account.
- [ ] Sign in as staff, check the dashboard loads, then **Sign out**.

---

## 2. The demo script

### Part 1 — The customer books online (≈3 min)
*Window: normal*

1. Open Guest View and scroll through **Services** and **Branches**, including the map, without signing in.
2. Choose **Book now**. Sign in or create a customer account to request an appointment; the booking form is not available to guests.
3. Fill it in:
   - **Branch:** Canduman *(the staff demo account belongs to Canduman)*
   - **Service:** Face
   - **Preferred date:** **today**, so it appears on staff's *Today's schedule*
   - **Preferred time:** any available slot
   - **Name / Mobile:** e.g. *Maria Santos*, *0917 123 4567*
4. Click **Request appointment**. Point out the confirmation and its **booking reference** (e.g. `EG-00001`).
5. **Show the double-booking protection.** Choose the same branch and date again, then open the time list. The slot you just booked now shows **"already booked"** and can't be selected.

> 💬 *"Bookings go straight into the clinic's database, and no two people can book the same branch at the same time."*

### Part 2 — Branch staff handle it (≈5 min)
*Window: normal*

1. Scroll to the bottom of the customer page and click **Team sign in** in the footer.
2. Sign in with the **staff** account. You go straight to the **staff** dashboard.

> 💬 *"Everyone uses the same sign-in page. The system knows who you are and opens your own workspace."*

3. **Today's schedule.** Maria's booking is listed as **Pending**. Point out:
   - The header says **Canduman branch**. Staff only ever see their own branch.
   - The **low-stock watch** panel on the right.
4. Click **Confirm** on Maria's booking. The status changes to **Confirmed**.
5. **Walk-in booking** (sidebar):
   - Enter a customer, choose a treatment such as *Classic Manicure*, today's date, and a different time.
   - Click **Save walk-in booking**.
   - Optional: try the **same time as Maria's booking** first. It's refused with *"That time is already booked at Canduman."*
6. **Supplies used** (sidebar):
   - Item: *Massage oil*, quantity **2**, service category *Body*. Click **Log stock out**.
   - The **Recent stock movements** table shows the entry with the staff member's name.
7. **Low-stock alerts** (sidebar). Items at or below their reorder level are flagged **Reorder**. Staff can view these but can't change stock levels.

> 💬 *"Staff can do their daily work — confirm, add walk-ins, log supplies — but can't change stock levels or see other branches."*

### Part 3 — The admin oversees everything (≈5 min)
*Window: private / incognito*

1. Sign in with the **admin** account. You go straight to the **admin** dashboard.
2. **Overview.** Today's appointments across **all branches**, items at their reorder point, and a strip summarising each branch.
3. **Appointments:**
   - Both Maria's online booking and the walk-in are there. The service column shows *Online* or *Walk-in*.
   - Use the filters (search, status, branch).
   - Change the walk-in's status with the **Manage** dropdown, e.g. to **Completed**.
   - **Add appointment:** book a customer at **Pajac**, choosing *Booked via → Phone or online request*.
4. **Inventory:**
   - Find **Massage oil (Canduman)**. Its quantity is 2 lower, because of what staff logged in Part 2.
   - Click **Adjust stock**, choose **Add quantity**, enter 10, then **Save quantity**.
   - Click **Add stock item** to show adding a new product to a branch.
5. **Service catalog.** The 12 treatments by category. Click **Add treatment** to add one (name, category, duration, price), or **Edit** to set a price or hide a treatment from booking. Changes appear on every booking form straight away.
6. **Branches.** Addresses, phone numbers and opening hours for all four clinics (Pusok uses placeholders until details are confirmed).
7. **Staff accounts.** Every staff and admin account. Click **Add account** to create one (a password is generated and shown once), or **Manage** to change a job title, branch, role or office access, reset a password, or deactivate an account.
8. **Inventory audit.** Adjust a stock item and enter a reason. The Stock movement audit table shows the user, time, branch, quantity change and reason.
9. **Service packages.** Create a session plan, record its sale for a customer and branch, then use **Redeem session**. The remaining balance and redemption history update immediately.
10. **Sales and costs.** When a payment is recorded, select the service staff member and enter the commission amount. For Card payments, enter the actual processing fee. Daily sales, employee breakdowns and month-end net include those recorded amounts; no rates are assumed.

> 💬 *"Admin sees every branch in one place, and stock changes made by staff show up immediately."*

### Part 4 — The Pajac receptionist's office tools (≈4 min, optional)
*Window: normal (sign out of staff first)*

Only the **Pajac receptionist** account has these extra areas. Other staff, even at the same branch, don't see them.

1. Sign in as `receptionist.pajac@elglam.test`. The sidebar has an extra **Office** section.
2. Book a Pajac appointment for today from the customer website first (or add a walk-in), then go to **Bookings** and click **Complete**. A form asks for the **amount paid**, **payment method**, service staff, commission amount and (for card payments) card processing fee. Save it, and the booking is marked Completed and shows *Paid ₱…*.
3. **Daily sales:** today's booking payments and package purchases, with combined sales totals and separate breakdowns, plus commission and card-fee totals.
4. **Daily expenses:** add one (e.g. *Transportation · Tricycle fare · ₱80*). Staff cannot edit or delete expenses. Admin edits and deletions are preserved in the **Expense change history**.
5. **Purchase orders:** add one (e.g. *Beauty Supply Co. · Hair color, 10 tubes · ₱3,200*). It starts as *Ordered*; admin marks it Received or Cancelled.
6. **Month-end report:** booking and package sales, expenses, purchase orders, commissions, card fees and net for the month, with a day-by-day table and a **Print report** button.
7. **Customers** and **Employees:** the Pajac customer list (visits, next booking, total spent) and Pajac staff with this month's activity.
8. Optional: sign in as admin. The same **Office** pages show every branch, with a branch filter, plus expense edit/delete history, package/session tracking, payment corrections and PO status controls.

> 💬 *"Access is set per account. The receptionist was given exactly these six areas, and the server enforces it."*

### Part 5 — Security in action (≈2 min, optional)

1. **Wrong role is turned away.** In the *staff* window, paste the admin address:
   `http://localhost/el-glam-booking-system/prototype/admin-view/admin.php`
   The staff member is sent straight back to the staff dashboard, and the admin page is never sent to them.
2. **Signing out.** Click **Sign out**. You land on the sign-in page with *"You have signed out."* Pressing **Back** does not reopen the dashboard.
3. **Wrong passwords pause sign-in.** On the sign-in page, use a **made-up email** (e.g. `test@example.com`) and enter a wrong password 5 times. The 5th attempt shows *"Too many unsuccessful sign-in attempts… wait 15 minutes."*
   > ⚠️ Use a made-up email, **not** the staff or admin email, or you'll lock yourself out for 15 minutes. If it happens, run the reset in step 1.2.
4. **Mention, don't show:** after **30 minutes without activity**, the dashboard signs itself out. Sessions also end **12 hours** after sign-in.

> 💬 *"Permissions are checked on the server for every action, not just hidden in the page."*

---

## 3. Good to know during the demo

| Situation | What to say / do |
|-----------|------------------|
| Prices show "Price TBC" | The clinic hasn't confirmed prices yet. Admin can set them in **Service catalog → Edit**. |
| Which staff accounts exist? | Canduman: Ava Reception, Jennifer Yaon. Pajac: HR, Receptionist, Accounting & Inventory. Admin can add more in **Staff accounts**. |
| Why can't other staff see sales or expenses? | Those areas are given per account. Only the Pajac receptionist has them; admins have them for all branches. |
| Net is negative on the month-end report | Purchase orders and expenses can exceed sales in a demo month with few payments. |
| Earlier times today show "time has passed" | Slots that have already started today can't be booked. |
| The service cards and branch photos on the customer website are fixed | Only the booking form uses the database; the rest of the website is static content. |
| Booking for a future date | It appears under staff **Bookings** and admin **Appointments**, but not on *Today's schedule*. |

---

## 4. Troubleshooting

| Problem | Fix |
|---------|-----|
| "Times unavailable" or "Can't reach the clinic server" | Apache or MySQL isn't running, or the page was opened as a file. Start both in XAMPP and use the `http://localhost/...` address. |
| "Email or password is incorrect" | Check `database/dev-accounts.local.txt`. Emails are not case-sensitive; passwords are. |
| "Too many unsuccessful sign-in attempts" | Wait 15 minutes, or run the reset in step 1.2. |
| Signed in as the wrong person | Click **Sign out**, or use the other (private) window. |
| Sent back to the sign-in page unexpectedly | The session timed out after 30 minutes idle. Sign in again. |
| Old bookings from a practice run | Run the reset in step 1.2. |
| `admin.html` / `staff.html` shows "Not Found" | Those pages were renamed. Use `login.php`, which sends you to the right dashboard. |

---

## 5. Addresses at a glance

| Page | Address |
|------|---------|
| Public guest website | <http://localhost/el-glam-booking-system/prototype/guest-view/> |
| Registered customer area | <http://localhost/el-glam-booking-system/prototype/customer-view/> |
| Sign in (staff & admin) | <http://localhost/el-glam-booking-system/prototype/login.php> |
| phpMyAdmin | <http://localhost/phpmyadmin> |

---

## 6. Setting up on a new computer

1. Copy the project into `/Applications/XAMPP/xamppfiles/htdocs/el-glam-booking-system`.
2. Start Apache and MySQL.
3. Create the database: in phpMyAdmin, **Import** `database/schema.sql`.
4. Create the demo accounts from the project folder:
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php database/create_user.php --name="Ava Reception" --email=staff.canduman@elglam.test --role=staff --branch=canduman
   /Applications/XAMPP/xamppfiles/bin/php database/create_user.php --name="EL Glamorous Face and Body Clinic Admin" --email=admin@elglam.test --role=admin
   /Applications/XAMPP/xamppfiles/bin/php database/create_user.php --name="EL Glamorous Face and Body Clinic Receptionist" --email=receptionist.pajac@elglam.test --role=staff --branch=pajac --title="Receptionist"
   /Applications/XAMPP/xamppfiles/bin/php database/permissions.php --email=receptionist.pajac@elglam.test --grant=customers.view,sales,reports.month_end,employees.view,expenses.daily,expenses.po
   ```
   Each command prints a password **once**. Save them somewhere private; don't commit them.
5. For staff at other branches, use `--branch=pajac` or `--branch=cebu-city`. Add `--title="Senior Hairdresser"` (or any job title) to show it on their profile, for example:
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php database/create_user.php --name="Jennifer Yaon" --email=jennifer.yaon@elglam.test --role=staff --branch=canduman --title="Senior Hairdresser"
   ```
6. After that, admin can add and manage everyone from **Staff accounts** in the admin dashboard. The commands below still work if you prefer the terminal.
7. To give a staff account extra areas (customer data, sales, month-end report, employees, daily expenses, purchase orders):
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php database/permissions.php --email=receptionist.pajac@elglam.test --grant=customers.view,sales,reports.month_end,employees.view,expenses.daily,expenses.po
   ```
   Use `--revoke=...` to take areas away, or just `--email=...` to see what an account has.
