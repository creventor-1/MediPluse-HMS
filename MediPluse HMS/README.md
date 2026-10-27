# MediPulse HMS

MediPulse HMS is a server-rendered hospital management application built with PHP, MySQL via PDO, and vanilla JavaScript. It has seven operational sections: Patients, Appointments, Doctors, Lab Results, Billing, Staff, and Reports. The Dashboard is a separate, permission-filtered overview.

## Requirements

- PHP 8.1 or later with `pdo_mysql`, `openssl`, and `mbstring`
- MySQL 8 or a compatible MariaDB version
- Apache with `.htaccess` enabled, such as XAMPP
- `pdo_sqlite` to run the included test suite
- An SMTP account for password-reset email delivery

The application has no Composer or frontend build dependencies.

## Installation

1. Copy the project into the Apache document root and start Apache and MySQL.
2. Create the database by importing `database/schema.sql` with phpMyAdmin or the MySQL command-line client. The current schema uses the database name `medipulse_hms`.
3. Copy `.env.example` to `.env` and set the database credentials, public application origin, and SMTP settings. Keep `.env` out of source control.
4. Run `database/migrations/001_permissions_auth.sql` once against `medipulse_hms`. It adds account state, display names, role templates, section permissions, reset tokens, and audit events without dropping existing clinical records. The migration also supports existing installations where `tblUsers.email` is already present.
5. Run `database/migrations/002_shared_care_foundation.sql` once. It adds the explicit patient-account ownership relation and shared encounter anchor for future portal and clinical workflows. It does not enable those workflows by itself.
6. For public account requests and Doctor profiles, run `database/migrations/003_signup_role_requests.sql` and then `database/migrations/009_doctor_signup_specialty.sql`. Existing installations that already ran migration 003 only need migration 009.
7. Provision a first user manager if the migration did not find an existing active Administrator:

   ```text
   php database/bootstrap_admin.php admin admin@example.test "System Administrator"
   ```

   The script creates an active Administrator with **only** the essential user-management permission. It prints a random temporary password once. Keep it private. Grant operational section permissions separately from the permissions screen.
8. Open the project URL and sign in. Authorized managers can open **Manage users & permissions** in the sidebar to approve registrations, assign roles, edit role templates, and set individual overrides.

For an existing XAMPP installation, use a `.env` such as:

```dotenv
DB_HOST=127.0.0.1
DB_NAME=medipulse_hms
DB_USER=root
DB_PASS=
APP_URL=http://localhost
MAIL_HOST=smtp.example.test
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=no-reply@example.test
MAIL_FROM_NAME="MediPulse HMS"
PASSWORD_RESET_TTL_MINUTES=30
```

`APP_URL` is the public **origin only**; the application path is detected automatically. For a local project under a subfolder, this normally remains `http://localhost`. Port 465 uses implicit TLS; other SMTP ports use STARTTLS. Use a dedicated SMTP credential and never put real secrets in `.env.example`.

The bundled `.htaccess` disables directory listings and blocks web access to `.env` and the database directory. Keep Apache overrides enabled; do not expose this project using a server configuration that ignores those protections.

## Authentication and account approval

- Signup accepts a name, email, and password; usernames are generated internally and users sign in with their email. Passwords must be at least 12 characters, with live length feedback and show/hide controls on the signup form.
- Signup applicants select a requested account type from eligible `tblRoles` entries. Administrator and Supervisor are excluded from public signup.
- Every signup still receives the `Pending` role and `pending` account status. The requested role is saved separately in `tblUserRoleRequests`; it grants no access. An authorized manager reviews the request, selects the approved role, and assigns permissions.
- Login accepts a username or email and asks the user to select one of the seven account types. The selected type must match the account's stored role; it cannot change roles or grant permissions. Disabled accounts cannot sign in, and pending accounts cannot use section permissions.
- Forgot-password responses do not say whether an email address is registered. Reset links contain a random token; only its SHA-256 hash is stored. Tokens expire after `PASSWORD_RESET_TTL_MINUTES`, are single-use, and resetting a password invalidates existing sessions.
- Logout is a CSRF-protected POST request.

## Roles and section permissions

The seven operational sections are shown to every signed-in user. Sections without `view` permission are displayed as locked; direct requests are rejected by server-side checks. Each section independently supports `view`, `create`, `update`, and `delete` grants.

Role templates are explicit:

- **Administrator:** no operational section permissions are granted by the role. User/role/permission administration is a separate, explicit system permission.
- **Supervisor:** read-only `view` access to all seven sections by default.
- **Doctor:** patient and appointment viewing, appointment updates, doctor-directory viewing, and lab test requests.
- **Receptionist:** patient viewing/registration/updates, appointment viewing/booking/updates, and doctor-directory viewing.
- **Lab Technician:** lab viewing and result updates.
- **Billing Officer:** billing viewing, bill creation, and payment recording.
- **Patient:** no access to staff-wide section routes. Patient accounts use a separate portal that only displays patient records explicitly linked and approved for that login.
- **Pending approval:** not an assignable role and has no section access.

Managers can change templates and assign per-user `Role default`, `Allow`, or `Deny` overrides. Individual overrides take precedence. Managers cannot change their own role or permissions from the management screen. Doctors continue to be restricted to their own appointments and requested lab tests.

Legacy active Administrator accounts receive the separate user-management permission during the migration, but receive **no** operational section grants. Existing users with no email can have one added by a manager before using password recovery.

Doctor applicants provide a specialty during public signup. When a manager approves the account as an active Doctor, the application creates a doctor-directory profile linked to that login, so the doctor's name and specialty appear in the Doctors directory and appointment booking choices. Managers can enter a specialty for older pending Doctor requests on the approval screen.

## Role dashboards and workflow status

After login, Doctors go directly to Appointments, Receptionists to Patients, Lab Technicians to Lab Results, and Billing Officers to Billing, provided that section is permitted. If their preferred section is not granted, they are sent to another section they are permitted to view. Supervisor, Patient, and Pending roles retain their role-specific dashboard pages. The Administrator dashboard requires the explicit `system.manage_users` permission and displays every record from Patients, Appointments, Doctors, Lab Results, Billing, and Staff, plus a Reports summary with record totals. These tables are shown only on the administrator dashboard and include the existing stored fields.

The dashboard routes are role-specific entry points. Patient logins go to a private portal and cannot open global patient, billing, or other staff-section pages, even if a section permission is accidentally granted. A user manager who also has patient-view permission must verify a patient’s identity and link an active Patient account to exactly one record in **Manage users & permissions**. Signup email or name is never used to link records automatically. Accounts without an approved link see no patient data. Doctor logins must also be explicitly linked to their doctor profile by a user manager from the Doctors page; this identity link determines which referred appointments appear in that doctor’s list.

The patient portal shows only the linked patient’s personal details, doctor directory, appointments, lab results, and bills. Patients may book for one of their linked patient records and cannot create or change charges. They may request cash, bank-transfer, or ATM-card payment for an outstanding balance; this is a request only, not a charge or proof of payment. Billing staff must receive or verify the funds and explicitly record the payment before the bill changes to Paid. The application has no payment gateway and does not collect or store card numbers or PINs. Staff-recorded payments support cash, card, and bank transfer, including partial payments, and provide printable receipts. Reconciliation and refunds are not implemented; bills with payment history cannot be deleted. Lab records do not implement specimen tracking, result validation/release, or critical-result escalation; the portal displays recorded lab results but does not create clinical diagnoses or notes.

The application defaults to dark mode. Use the fixed theme button on any page to switch between dark and light appearance; the choice is saved in that browser. Receptionists land on Patients and do not have a Dashboard sidebar link.

The `tblPatientAccounts` relation supports explicit, reviewer-approved links between user accounts and patient records. `tblEncounters` provides a shared relationship anchor for a patient, optional appointment, and clinician. Neither table contains clinical notes or grants access until later workflow code enforces the relationship and status.

## Database upgrade notes

Back up the database before applying migrations. `001_permissions_auth.sql` is a one-time upgrade that preserves existing patient, doctor, appointment, laboratory, billing, and staff records. It detects already-present user columns, including `email`, and removes the original fixed role check when present so `Supervisor` and `Pending` can be stored. `002_shared_care_foundation.sql` creates its new tables with `IF NOT EXISTS` and adds no changes to existing clinical records. `003_signup_role_requests.sql` adds a separate table for signup role requests.

For a fresh installation, import `database/schema.sql`, then apply migrations `001` through `008` in order. Migration `004_billing_payments.sql` creates the payment ledger required by Billing and receipts. Migration `005_patient_portal_payments.sql` creates the patient payment-request ledger; patients’ method selections remain pending until staff verification. Migration `006_doctor_appointment_booking.sql` enables doctors to book appointments. Migration `007_appointment_attending_doctor.sql` adds the field used to record which doctor attended the patient. Migration `008_appointment_booking_doctor.sql` records the referring doctor: referrals appear in both the referring doctor’s list and the assigned doctor’s list, but only the assigned doctor can edit or delete them. A Doctor login must be explicitly linked to its doctor profile before it can make bookings. For an existing installation, back up the database and apply any missing migration in order. If the schema has previously been customized, review the migration against that database before running it.

## Tests

Run the focused permission and authentication tests from the project directory:

```text
php tests/run.php
```

The suite uses an in-memory SQLite database and covers permission precedence, the Administrator no-implicit-access rule, supervisor access, pending signup, password hashing, valid/expired/reused reset tokens, session invalidation, and view-permission guards for all seven sections. It does not modify the configured MySQL database or send email.

## Project layout

```text
config.php                    Environment, sessions, CSRF, database connection
auth/                         Login, signup, logout, password recovery
admin/permissions.php         Role templates and per-user permission management
patients/                     Patient records
appointments/                 Appointment scheduling
doctors/                      Doctor records
lab/                          Laboratory tests and results
billing/                      Bills, manual payments, and printable receipts
staff/                        Staff accounts
reports/                      Permission-filtered summaries
dashboard/                    Role- and permission-filtered overview
partials/helpers.php          Permission checks, auth, reset tokens, SMTP
database/schema.sql           Base clinical schema
database/migrations/          Permission/auth, shared-care, and billing upgrades
database/bootstrap_admin.php  CLI-only first manager provisioning
tests/run.php                 Dependency-free focused tests
```
