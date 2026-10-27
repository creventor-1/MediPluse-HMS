<?php
/**
 * Development & Testing Sample Data Seeder for MediPulse HMS.
 * Populates realistic Nigerian hospital records without altering the database schema.
 * All passwords are set to 'password123'.
 *
 * Usage:
 *   php database/seed_sample_data.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden. CLI execution only.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../partials/helpers.php';

function logMsg(string $msg): void
{
    echo "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
}

$pdo = getDb();
$todayStr = date('Y-m-d');
$passwordRaw = 'password123';
$passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);

logMsg("Starting MediPulse HMS sample data seeding...");

$pdo->beginTransaction();

try {
    // 1. Update Administrator password to password123
    $pdo->prepare("
        UPDATE tblUsers 
        SET passwordHash = ?, sessionVersion = sessionVersion + 1 
        WHERE username = 'admin'
    ")->execute([$passwordHash]);
    logMsg("Updated Administrator password to 'password123'.");

    // 2. Representative Test Accounts Across All Roles
    $testUsers = [
        ['USR0002', 'supervisor', 'supervisor@medipulse.ng', 'Dr. Kalu Nnamdi', 'Supervisor'],
        ['USR0003', 'doctor.ibrahim', 'dr.ibrahim@medipulse.ng', 'Dr. Ibrahim Danladi', 'Doctor'],
        ['USR0004', 'doctor.amaka', 'dr.amaka@medipulse.ng', 'Dr. Amaka Okonkwo', 'Doctor'],
        ['USR0005', 'doctor.tunde', 'dr.tunde@medipulse.ng', 'Dr. Babatunde Adeleke', 'Doctor'],
        ['USR0006', 'doctor.fatima', 'dr.fatima@medipulse.ng', 'Dr. Fatima Abubakar', 'Doctor'],
        ['USR0007', 'receptionist', 'reception@medipulse.ng', 'Ngozi Eze', 'Receptionist'],
        ['USR0008', 'reception.yetunde', 'yetunde.olatunji@medipulse.ng', 'Yetunde Olatunji', 'Receptionist'],
        ['USR0009', 'lab', 'lab@medipulse.ng', 'Tunde Balogun', 'Lab Technician'],
        ['USR0010', 'lab.chidinma', 'chidinma.nwosu@medipulse.ng', 'Chidinma Nwosu', 'Lab Technician'],
        ['USR0011', 'billing', 'billing@medipulse.ng', 'Amina Bello', 'Billing Officer'],
        ['USR0012', 'billing.emeka', 'emeka.nwachukwu@medipulse.ng', 'Emeka Nwachukwu', 'Billing Officer'],
        ['USR0013', 'patient', 'patient@medipulse.ng', 'Chukwuemeka Okoye', 'Patient'],
        ['USR0014', 'patient.aisha', 'aisha.garba@example.ng', 'Aisha Garba', 'Patient'],
        ['USR0015', 'patient.femi', 'femi.ogundipe@example.ng', 'Oluwafemi Ogundipe', 'Patient'],
        ['USR0016', 'pending', 'pending@medipulse.ng', 'Chinedu Ani', 'Pending approval'],
    ];

    $userStmt = $pdo->prepare("
        INSERT INTO tblUsers (userID, username, email, displayName, passwordHash, role, accountStatus, sessionVersion)
        VALUES (?, ?, ?, ?, ?, ?, 'active', 0)
        ON DUPLICATE KEY UPDATE
            displayName = VALUES(displayName),
            passwordHash = VALUES(passwordHash),
            role = VALUES(role),
            accountStatus = 'active'
    ");

    foreach ($testUsers as $u) {
        $userStmt->execute([$u[0], $u[1], $u[2], $u[3], $passwordHash, $u[4]]);
    }
    logMsg("Created/updated test users across all system roles with password 'password123'.");

    // Pending Role Request
    $pdo->prepare("
        INSERT INTO tblUserRoleRequests (userID, requestedRole, doctorSpecialty, requestedAt)
        VALUES ('USR0016', 'Doctor', 'Dermatology', NOW())
        ON DUPLICATE KEY UPDATE requestedRole = VALUES(requestedRole), doctorSpecialty = VALUES(doctorSpecialty)
    ")->execute();

    // 3. Doctors Directory (tblDoctors)
    $doctors = [
        ['DOC0001', 'Dr. Ibrahim Danladi', 'Cardiology', '+2348031234567', 'USR0003'],
        ['DOC0002', 'Dr. Amaka Okonkwo', 'Pediatrics', '+2348023456789', 'USR0004'],
        ['DOC0003', 'Dr. Babatunde Adeleke', 'General Medicine & Surgery', '+2348145678901', 'USR0005'],
        ['DOC0004', 'Dr. Fatima Abubakar', 'Obstetrics & Gynecology', '+2348056789012', 'USR0006'],
        ['DOC0005', 'Dr. Osasere Igbinedion', 'Orthopedic Surgery', '+2348078901234', null],
        ['DOC0006', 'Dr. Terver Akume', 'Internal Medicine', '+2348189012345', null],
        ['DOC0007', 'Dr. Folashade Alabi', 'Ophthalmology', '+2348090123456', null],
    ];

    $docStmt = $pdo->prepare("
        INSERT INTO tblDoctors (doctorID, fullName, specialty, phoneNumber, userID)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            fullName = VALUES(fullName),
            specialty = VALUES(specialty),
            phoneNumber = VALUES(phoneNumber),
            userID = VALUES(userID)
    ");
    foreach ($doctors as $d) {
        $docStmt->execute($d);
    }
    logMsg("Populated Doctors directory.");

    // 4. Staff Directory (tblStaff)
    $staff = [
        ['STF0001', 'Dr. Kalu Nnamdi', 'Medical Director / Operations Supervisor', 'USR0002'],
        ['STF0002', 'Ngozi Eze', 'Front Desk Lead Receptionist', 'USR0007'],
        ['STF0003', 'Yetunde Olatunji', 'Admissions & Records Officer', 'USR0008'],
        ['STF0004', 'Tunde Balogun', 'Chief Medical Laboratory Scientist', 'USR0009'],
        ['STF0005', 'Chidinma Nwosu', 'Senior Clinical Pathology Technician', 'USR0010'],
        ['STF0006', 'Amina Bello', 'Finance Lead & Chief Billing Officer', 'USR0011'],
        ['STF0007', 'Emeka Nwachukwu', 'Revenue Officer & Hospital Cashier', 'USR0012'],
        ['STF0008', 'Sister Folashade Adeleke', 'Chief Nursing Officer (Matron)', null],
        ['STF0009', 'Nurse Musa Haruna', 'Intensive Care Unit Staff Nurse', null],
        ['STF0010', 'Pharm. Chuka Okafor', 'Director of Hospital Pharmacy', null],
        ['STF0011', 'Halima Umar', 'Health Records & Archives Manager', null],
        ['STF0012', 'Blessing Edet', 'Senior Emergency Ward Nurse', null],
    ];

    $staffStmt = $pdo->prepare("
        INSERT INTO tblStaff (staffID, fullName, designation, userID)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            fullName = VALUES(fullName),
            designation = VALUES(designation),
            userID = VALUES(userID)
    ");
    foreach ($staff as $s) {
        $staffStmt->execute($s);
    }
    logMsg("Populated Staff directory.");

    // 5. Patients (tblPatients)
    $patients = [
        ['PAT0001', 'Chukwuemeka Okoye', 'Male', '1988-04-12', '+2348034567812', '14 Admiralty Way, Lekki Phase 1, Lagos', '2026-05-10'],
        ['PAT0002', 'Aisha Garba', 'Female', '1995-09-23', '+2348023456789', '7 Bida Road, Kaduna South, Kaduna', '2026-05-18'],
        ['PAT0003', 'Oluwafemi Ogundipe', 'Male', '1976-11-05', '+2348145678901', '15 Ring Road, Challenge, Ibadan, Oyo', '2026-05-25'],
        ['PAT0004', 'Chioma Okonkwo', 'Female', '1992-02-14', '+2348056789012', '8 Ogui Road, Asata, Enugu', '2026-06-02'],
        ['PAT0005', 'Babatunde Adeyemi', 'Male', '1981-07-30', '+2348078901234', '3 Alausa Secretariat Road, Ikeja, Lagos', '2026-06-15'],
        ['PAT0006', 'Osasere Igbinedion', 'Male', '1969-03-18', '+2348189012345', '5 Airport Road, GRA, Benin City, Edo', '2026-06-28'],
        ['PAT0007', 'Blessing Edet', 'Female', '2000-10-12', '+2348090123456', '19 Marian Road, Calabar, Cross River', '2026-07-04'],
        ['PAT0008', 'Terver Akume', 'Male', '1985-06-22', '+2348021122334', '12 High Level, Makurdi, Benue', '2026-07-11'],
        ['PAT0009', 'Musa Haruna', 'Male', '1983-12-08', '+2348133456789', '10 Yakubu Gowon Way, Jos, Plateau', '2026-07-19'],
        ['PAT0010', 'Nneka Obi', 'Female', '1989-05-15', '+2348054567890', '27 Zik Avenue, Awka, Anambra', '2026-08-01'],
        ['PAT0011', 'Mohammed Sani', 'Male', '1957-01-20', '+2348186789012', '45 Post Office Road, Kano Municipal, Kano', '2026-08-14'],
        ['PAT0012', 'Ifeanyi Eze', 'Male', '2014-08-09', '+2348095789012', '9 Okpara Avenue, Coal Camp, Enugu', '2026-08-20'],
        ['PAT0013', 'Halima Abubakar', 'Female', '1998-04-03', '+2348038901234', '18 Shehu Shagari Way, Maitama, Abuja', '2026-08-29'],
        ['PAT0014', 'Folashade Adeleke', 'Female', '1980-08-17', '+2348029012345', '6 Bodija Housing Estate, Ibadan, Oyo', '2026-09-05'],
        ['PAT0015', 'Kalu Uzoma', 'Male', '1991-11-29', '+2348140123456', '11 Aba Road, Port Harcourt, Rivers', '2026-09-12'],
        ['PAT0016', 'Zainab Yakubu', 'Female', '1966-06-14', '+2348051234567', '2 Katsina Road, Funtua, Katsina', '2026-09-20'],
        ['PAT0017', 'Efe Omoruyi', 'Male', '2002-09-05', '+2348072345678', '16 Sapele Road, Warri, Delta', '2026-09-27'],
        ['PAT0018', 'Titilayo Bakare', 'Female', '1987-01-25', '+2348183456789', '4 Awolowo Road, Ikoyi, Lagos', '2026-10-02'],
        ['PAT0019', 'Chukwudi Nwankwo', 'Male', '1954-12-19', '+2348095456789', '8 Onitsha Road, Nnewi, Anambra', '2026-10-08'],
        ['PAT0020', 'Fatima Aliyu', 'Female', '2009-07-11', '+2348035678901', '31 Gidan Murtala Road, Sokoto', '2026-10-12'],
        ['PAT0021', 'Oladipo Williams', 'Male', '1975-05-04', '+2348026789012', '21 Broad Street, Marina, Lagos', '2026-10-15'],
        ['PAT0022', 'Maryam Gidado', 'Female', '1993-03-21', '+2348147890123', '14 Tafawa Balewa Way, Bauchi', '2026-10-18'],
        ['PAT0023', 'Uchechukwu Madu', 'Male', '1997-10-02', '+2348058901234', '5 Presidential Road, Independence Layout, Enugu', '2026-10-21'],
        ['PAT0024', 'Damilola Adegbite', 'Female', '1990-12-14', '+2348076543210', '12 Allen Avenue, Ikeja, Lagos', '2026-10-24'],
        ['PAT0025', 'Yakubu Dogara', 'Male', '1964-02-28', '+2348187654321', '3 Constitution Avenue, Central Business District, Abuja', '2026-10-26'],
    ];

    $patStmt = $pdo->prepare("
        INSERT INTO tblPatients (patientID, fullName, gender, dateOfBirth, phoneNumber, address, registrationDate)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            fullName = VALUES(fullName),
            gender = VALUES(gender),
            dateOfBirth = VALUES(dateOfBirth),
            phoneNumber = VALUES(phoneNumber),
            address = VALUES(address),
            registrationDate = VALUES(registrationDate)
    ");
    foreach ($patients as $p) {
        $patStmt->execute($p);
    }
    logMsg("Populated 25 realistic Nigerian patients.");

    // Link Patient Accounts
    $patientAccounts = [
        ['USR0013', 'PAT0001', 'USR0001'],
        ['USR0014', 'PAT0002', 'USR0001'],
        ['USR0015', 'PAT0003', 'USR0001'],
    ];
    $pacStmt = $pdo->prepare("
        INSERT INTO tblPatientAccounts (userID, patientID, accountStatus, approvedBy, approvedAt)
        VALUES (?, ?, 'active', ?, NOW())
        ON DUPLICATE KEY UPDATE accountStatus = 'active', approvedBy = VALUES(approvedBy)
    ");
    foreach ($patientAccounts as $pa) {
        $pacStmt->execute($pa);
    }

    // 6. Appointments (tblAppointments)
    $appointments = [
        ['APT0001', 'PAT0001', 'DOC0001', '2026-10-05', '09:00:00', 'Completed', 'DOC0001', null],
        ['APT0002', 'PAT0002', 'DOC0002', '2026-10-06', '10:30:00', 'Completed', 'DOC0002', null],
        ['APT0003', 'PAT0003', 'DOC0003', '2026-10-08', '11:00:00', 'Completed', 'DOC0003', 'DOC0001'],
        ['APT0004', 'PAT0004', 'DOC0004', '2026-10-10', '09:30:00', 'Completed', 'DOC0004', null],
        ['APT0005', 'PAT0005', 'DOC0001', '2026-10-12', '14:00:00', 'Completed', 'DOC0001', null],
        ['APT0006', 'PAT0006', 'DOC0005', '2026-10-14', '10:00:00', 'Completed', 'DOC0005', null],
        ['APT0007', 'PAT0007', 'DOC0002', '2026-10-15', '12:00:00', 'Completed', 'DOC0002', null],
        ['APT0008', 'PAT0008', 'DOC0006', '2026-10-16', '15:30:00', 'Cancelled', null, null],
        ['APT0009', 'PAT0009', 'DOC0003', '2026-10-18', '08:30:00', 'Completed', 'DOC0003', null],
        ['APT0010', 'PAT0010', 'DOC0004', '2026-10-19', '13:00:00', 'Completed', 'DOC0004', null],
        ['APT0011', 'PAT0011', 'DOC0001', '2026-10-21', '11:30:00', 'Completed', 'DOC0001', null],
        ['APT0012', 'PAT0012', 'DOC0002', '2026-10-22', '09:00:00', 'Completed', 'DOC0002', 'DOC0003'],
        ['APT0013', 'PAT0013', 'DOC0004', '2026-10-23', '10:15:00', 'Completed', 'DOC0004', null],
        ['APT0014', 'PAT0014', 'DOC0007', '2026-10-24', '14:30:00', 'Completed', 'DOC0007', null],
        ['APT0015', 'PAT0015', 'DOC0005', '2026-10-25', '11:00:00', 'Cancelled', null, null],
        ['APT0016', 'PAT0001', 'DOC0001', $todayStr, '09:00:00', 'Booked', null, null],
        ['APT0017', 'PAT0016', 'DOC0003', $todayStr, '10:00:00', 'Booked', null, null],
        ['APT0018', 'PAT0017', 'DOC0002', $todayStr, '11:30:00', 'Booked', null, null],
        ['APT0019', 'PAT0018', 'DOC0004', $todayStr, '13:00:00', 'Booked', null, 'DOC0003'],
        ['APT0020', 'PAT0019', 'DOC0001', $todayStr, '14:30:00', 'Booked', null, null],
        ['APT0021', 'PAT0020', 'DOC0006', $todayStr, '16:00:00', 'Booked', null, null],
        ['APT0022', 'PAT0021', 'DOC0003', '2026-10-28', '09:30:00', 'Booked', null, null],
        ['APT0023', 'PAT0022', 'DOC0004', '2026-10-28', '11:00:00', 'Booked', null, null],
        ['APT0024', 'PAT0023', 'DOC0002', '2026-10-29', '10:00:00', 'Booked', null, null],
        ['APT0025', 'PAT0024', 'DOC0001', '2026-10-29', '14:00:00', 'Booked', null, null],
        ['APT0026', 'PAT0025', 'DOC0005', '2026-10-30', '09:00:00', 'Booked', null, null],
        ['APT0027', 'PAT0002', 'DOC0002', '2026-10-30', '11:30:00', 'Booked', null, null],
        ['APT0028', 'PAT0003', 'DOC0003', '2026-10-31', '10:00:00', 'Booked', null, null],
        ['APT0029', 'PAT0004', 'DOC0004', '2026-11-02', '09:00:00', 'Booked', null, null],
    ];

    $aptStmt = $pdo->prepare("
        INSERT INTO tblAppointments (appointmentID, patientID, doctorID, appointmentDate, appointmentTime, status, attendedByDoctorID, bookedByDoctorID)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            patientID = VALUES(patientID),
            doctorID = VALUES(doctorID),
            appointmentDate = VALUES(appointmentDate),
            appointmentTime = VALUES(appointmentTime),
            status = VALUES(status),
            attendedByDoctorID = VALUES(attendedByDoctorID),
            bookedByDoctorID = VALUES(bookedByDoctorID)
    ");
    foreach ($appointments as $a) {
        $aptStmt->execute($a);
    }
    logMsg("Populated 29 Appointments (past completed, today's booked, and future).");

    // 7. Encounters (tblEncounters)
    $encounters = [
        ['ENC0001', 'PAT0001', 'APT0001', 'DOC0001', 'outpatient', 'closed', 'USR0003', '2026-10-05 09:00:00', '2026-10-05 09:45:00'],
        ['ENC0002', 'PAT0002', 'APT0002', 'DOC0002', 'outpatient', 'closed', 'USR0004', '2026-10-06 10:30:00', '2026-10-06 11:15:00'],
        ['ENC0003', 'PAT0003', 'APT0003', 'DOC0003', 'outpatient', 'closed', 'USR0005', '2026-10-08 11:00:00', '2026-10-08 11:50:00'],
        ['ENC0004', 'PAT0004', 'APT0004', 'DOC0004', 'outpatient', 'closed', 'USR0006', '2026-10-10 09:30:00', '2026-10-10 10:20:00'],
        ['ENC0005', 'PAT0005', 'APT0005', 'DOC0001', 'outpatient', 'closed', 'USR0003', '2026-10-12 14:00:00', '2026-10-12 14:40:00'],
        ['ENC0006', 'PAT0006', 'APT0006', 'DOC0005', 'inpatient', 'closed', 'USR0003', '2026-10-14 10:00:00', '2026-10-16 12:00:00'],
        ['ENC0007', 'PAT0007', 'APT0007', 'DOC0002', 'outpatient', 'closed', 'USR0004', '2026-10-15 12:00:00', '2026-10-15 12:35:00'],
        ['ENC0008', 'PAT0009', 'APT0009', 'DOC0003', 'outpatient', 'closed', 'USR0005', '2026-10-18 08:30:00', '2026-10-18 09:15:00'],
        ['ENC0009', 'PAT0010', 'APT0010', 'DOC0004', 'outpatient', 'closed', 'USR0006', '2026-10-19 13:00:00', '2026-10-19 13:40:00'],
        ['ENC0010', 'PAT0011', 'APT0011', 'DOC0001', 'outpatient', 'closed', 'USR0003', '2026-10-21 11:30:00', '2026-10-21 12:10:00'],
        ['ENC0011', 'PAT0012', 'APT0012', 'DOC0002', 'emergency', 'closed', 'USR0004', '2026-10-22 09:00:00', '2026-10-22 11:30:00'],
        ['ENC0012', 'PAT0013', 'APT0013', 'DOC0004', 'outpatient', 'closed', 'USR0006', '2026-10-23 10:15:00', '2026-10-23 10:55:00'],
        ['ENC0013', 'PAT0014', 'APT0014', 'DOC0007', 'outpatient', 'closed', 'USR0003', '2026-10-24 14:30:00', '2026-10-24 15:10:00'],
        ['ENC0014', 'PAT0001', 'APT0016', 'DOC0001', 'outpatient', 'open', 'USR0003', $todayStr . ' 09:00:00', null],
        ['ENC0015', 'PAT0016', 'APT0017', 'DOC0003', 'outpatient', 'planned', 'USR0005', null, null],
    ];

    $encStmt = $pdo->prepare("
        INSERT INTO tblEncounters (encounterID, patientID, appointmentID, doctorID, encounterType, status, createdBy, openedAt, closedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            patientID = VALUES(patientID),
            appointmentID = VALUES(appointmentID),
            doctorID = VALUES(doctorID),
            encounterType = VALUES(encounterType),
            status = VALUES(status),
            openedAt = VALUES(openedAt),
            closedAt = VALUES(closedAt)
    ");
    foreach ($encounters as $e) {
        $encStmt->execute($e);
    }
    logMsg("Populated 15 clinical Encounters.");

    // 8. Laboratory Results (tblLabResults)
    $labResults = [
        ['LAB0001', 'PAT0001', 'DOC0001', 'Full Blood Count (FBC)', 'WBC: 6.8 x 10^9/L, Hb: 13.8 g/dL, Platelets: 245 x 10^9/L (Normal)', '2026-10-05'],
        ['LAB0002', 'PAT0001', 'DOC0001', 'Fasting Blood Sugar (FBS)', '94 mg/dL (Normal reference: 70 - 100 mg/dL)', '2026-10-05'],
        ['LAB0003', 'PAT0002', 'DOC0002', 'Malaria Parasite (MP) by Microscopy', 'P. falciparum trophozoites seen (++), Moderate parasitemia', '2026-10-06'],
        ['LAB0004', 'PAT0002', 'DOC0002', 'Packed Cell Volume (PCV)', '34% (Normal reference for female: 36 - 46%)', '2026-10-06'],
        ['LAB0005', 'PAT0003', 'DOC0003', 'Lipid Profile', 'Total Cholesterol: 195 mg/dL, HDL: 46 mg/dL, LDL: 118 mg/dL, Triglycerides: 140 mg/dL', '2026-10-08'],
        ['LAB0006', 'PAT0004', 'DOC0004', 'Urinalysis (Routine)', 'Colour: Pale yellow, SG: 1.015, Protein: Nil, Glucose: Nil, Nitrites: Negative', '2026-10-10'],
        ['LAB0007', 'PAT0005', 'DOC0001', 'Serum Electrolytes, Urea & Creatinine', 'Na+: 139 mmol/L, K+: 4.2 mmol/L, Urea: 4.1 mmol/L, Creatinine: 88 umol/L', '2026-10-12'],
        ['LAB0008', 'PAT0006', 'DOC0005', 'Erythrocyte Sedimentation Rate (ESR)', '42 mm/hr (Elevated - suggestive of inflammatory response)', '2026-10-14'],
        ['LAB0009', 'PAT0007', 'DOC0002', 'Widal Agglutination Test (Typhoid)', "S. typhi 'O': 1/160, 'H': 1/80. S. paratyphi: Negative. Clinically reactive.", '2026-10-15'],
        ['LAB0010', 'PAT0009', 'DOC0003', 'Blood Group & Rhesus Factor', 'Blood Group: O, Rh(D): Positive', '2026-10-18'],
        ['LAB0011', 'PAT0009', 'DOC0003', 'Hemoglobin Genotype', 'Genotype: AA (Normal adult hemoglobin)', '2026-10-18'],
        ['LAB0012', 'PAT0010', 'DOC0004', 'Liver Function Test (LFT)', 'Total Bilirubin: 0.7 mg/dL, ALT: 24 U/L, AST: 28 U/L, Alkaline Phos: 72 U/L', '2026-10-19'],
        ['LAB0013', 'PAT0011', 'DOC0001', 'Cardiac Enzymes (Troponin I)', '< 0.01 ng/mL (Normal reference: < 0.04 ng/mL, non-ischemic)', '2026-10-21'],
        ['LAB0014', 'PAT0012', 'DOC0002', 'Stool Microscopy & Occult Blood', 'No ova, cysts, or parasites seen. Occult blood negative.', '2026-10-22'],
        ['LAB0015', 'PAT0013', 'DOC0004', 'HVS Microscopy, Culture & Sensitivity', 'Epithelial cells ++, Clue cells absent, No Trichomonas or Candida seen', '2026-10-23'],
        ['LAB0016', 'PAT0014', 'DOC0007', 'Glycated Hemoglobin (HbA1c)', '5.6% (Normal non-diabetic range: 4.0 - 5.6%)', '2026-10-24'],
        ['LAB0017', 'PAT0016', 'DOC0003', 'Malaria Parasite & PCV', null, null],
        ['LAB0018', 'PAT0017', 'DOC0002', 'Complete Blood Count (CBC)', null, null],
        ['LAB0019', 'PAT0018', 'DOC0004', 'Serum Electrolytes & Creatinine', null, null],
        ['LAB0020', 'PAT0019', 'DOC0001', 'Urinalysis & Urine Culture', null, null],
        ['LAB0021', 'PAT0020', 'DOC0006', 'Serum Uric Acid & Lipid Panel', null, null],
    ];

    $labStmt = $pdo->prepare("
        INSERT INTO tblLabResults (labResultID, patientID, requestedBy, testType, resultValue, dateRecorded)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            patientID = VALUES(patientID),
            requestedBy = VALUES(requestedBy),
            testType = VALUES(testType),
            resultValue = VALUES(resultValue),
            dateRecorded = VALUES(dateRecorded)
    ");
    foreach ($labResults as $l) {
        $labStmt->execute($l);
    }
    logMsg("Populated 21 Laboratory results (completed and pending).");

    // 9. Billing Records (tblBilling)
    $bills = [
        ['BIL0001', 'PAT0001', 'APT0001', 15000.00, 'Paid', '2026-10-05'],
        ['BIL0002', 'PAT0002', 'APT0002', 12500.00, 'Paid', '2026-10-06'],
        ['BIL0003', 'PAT0003', 'APT0003', 25000.00, 'Paid', '2026-10-08'],
        ['BIL0004', 'PAT0004', 'APT0004', 18500.00, 'Paid', '2026-10-10'],
        ['BIL0005', 'PAT0005', 'APT0005', 22000.00, 'Paid', '2026-10-12'],
        ['BIL0006', 'PAT0006', 'APT0006', 85000.00, 'Paid', '2026-10-14'],
        ['BIL0007', 'PAT0007', 'APT0007', 14000.00, 'Paid', '2026-10-15'],
        ['BIL0008', 'PAT0009', 'APT0009', 16500.00, 'Paid', '2026-10-18'],
        ['BIL0009', 'PAT0010', 'APT0010', 28000.00, 'Paid', '2026-10-19'],
        ['BIL0010', 'PAT0011', 'APT0011', 35000.00, 'Paid', '2026-10-21'],
        ['BIL0011', 'PAT0012', 'APT0012', 42000.00, 'Unpaid', '2026-10-22'],
        ['BIL0012', 'PAT0013', 'APT0013', 19500.00, 'Unpaid', '2026-10-23'],
        ['BIL0013', 'PAT0014', 'APT0014', 15000.00, 'Unpaid', '2026-10-24'],
        ['BIL0014', 'PAT0001', 'APT0016', 10000.00, 'Unpaid', $todayStr],
        ['BIL0015', 'PAT0016', 'APT0017', 8500.00, 'Unpaid', $todayStr],
    ];

    $billStmt = $pdo->prepare("
        INSERT INTO tblBilling (billID, patientID, appointmentID, amount, paymentStatus, dateIssued)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            patientID = VALUES(patientID),
            appointmentID = VALUES(appointmentID),
            amount = VALUES(amount),
            paymentStatus = VALUES(paymentStatus),
            dateIssued = VALUES(dateIssued)
    ");
    foreach ($bills as $b) {
        $billStmt->execute($b);
    }
    logMsg("Populated 15 Billing records.");

    // 10. Payments (tblPayments)
    $payments = [
        ['PAY0001', 'BIL0001', 15000.00, 0.00, 'Card', 'POS-STANBIC-982143', 'USR0011', '2026-10-05 10:15:00'],
        ['PAY0002', 'BIL0002', 12500.00, 0.00, 'Cash', 'CSH-REC-2026-0042', 'USR0012', '2026-10-06 11:30:00'],
        ['PAY0003', 'BIL0003', 25000.00, 0.00, 'Bank transfer', 'TRF-ZENITH-817294', 'USR0011', '2026-10-08 12:10:00'],
        ['PAY0004', 'BIL0004', 18500.00, 0.00, 'Card', 'POS-GTB-441209', 'USR0012', '2026-10-10 10:45:00'],
        ['PAY0005', 'BIL0005', 22000.00, 0.00, 'Bank transfer', 'TRF-ACCESS-339182', 'USR0011', '2026-10-12 15:05:00'],
        ['PAY0006', 'BIL0006', 85000.00, 0.00, 'Card', 'POS-UBA-771923', 'USR0011', '2026-10-16 12:20:00'],
        ['PAY0007', 'BIL0007', 14000.00, 0.00, 'Cash', 'CSH-REC-2026-0089', 'USR0012', '2026-10-15 13:00:00'],
        ['PAY0008', 'BIL0008', 16500.00, 0.00, 'Card', 'POS-FBN-552910', 'USR0011', '2026-10-18 09:35:00'],
        ['PAY0009', 'BIL0009', 28000.00, 0.00, 'Bank transfer', 'TRF-ZENITH-992144', 'USR0012', '2026-10-19 14:15:00'],
        ['PAY0010', 'BIL0010', 35000.00, 0.00, 'Card', 'POS-GTB-883190', 'USR0011', '2026-10-21 12:40:00'],
    ];

    $payStmt = $pdo->prepare("
        INSERT INTO tblPayments (paymentID, billID, amount, balanceAfter, paymentMethod, reference, receivedBy, receivedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            billID = VALUES(billID),
            amount = VALUES(amount),
            balanceAfter = VALUES(balanceAfter),
            paymentMethod = VALUES(paymentMethod),
            reference = VALUES(reference),
            receivedBy = VALUES(receivedBy),
            receivedAt = VALUES(receivedAt)
    ");
    foreach ($payments as $p) {
        $payStmt->execute($p);
    }
    logMsg("Populated 10 Payment records with printable receipts.");

    // 11. Patient Payment Requests (tblPatientPaymentRequests)
    $paymentRequests = [
        ['REQ0001', 'USR0013', 'PAT0001', 'BIL0014', 10000.00, 'ATM card', 'pending', null, null, $todayStr . ' 09:15:00', null],
        ['REQ0002', 'USR0014', 'PAT0002', 'BIL0002', 12500.00, 'Cash', 'completed', 'PAY0002', 'USR0011', '2026-10-06 11:00:00', '2026-10-06 11:30:00'],
        ['REQ0003', 'USR0015', 'PAT0003', 'BIL0003', 25000.00, 'Bank transfer', 'completed', 'PAY0003', 'USR0011', '2026-10-08 11:45:00', '2026-10-08 12:10:00'],
    ];

    $reqStmt = $pdo->prepare("
        INSERT INTO tblPatientPaymentRequests (requestID, userID, patientID, billID, amount, paymentMethod, status, paymentID, reviewedBy, requestedAt, reviewedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            paymentMethod = VALUES(paymentMethod),
            status = VALUES(status),
            paymentID = VALUES(paymentID),
            reviewedBy = VALUES(reviewedBy),
            reviewedAt = VALUES(reviewedAt)
    ");
    foreach ($paymentRequests as $pr) {
        $reqStmt->execute($pr);
    }
    logMsg("Populated Patient Payment Requests.");

    // 12. Audit Log (tblAuditLog)
    $auditLogs = [
        ['USR0001', 'system', 'login', 'USR0001', '2026-10-27 08:30:00'],
        ['USR0007', 'patients', 'register', 'PAT0025', '2026-10-26 14:15:00'],
        ['USR0007', 'appointments', 'book', 'APT0020', '2026-10-27 08:45:00'],
        ['USR0009', 'lab', 'record_result', 'LAB0016', '2026-10-24 16:30:00'],
        ['USR0011', 'billing', 'record_payment', 'PAY0010', '2026-10-21 12:40:00'],
        ['USR0003', 'appointments', 'complete', 'APT0011', '2026-10-21 12:15:00'],
        ['USR0002', 'system', 'audit_review', null, '2026-10-26 17:00:00'],
    ];

    $audStmt = $pdo->prepare("
        INSERT INTO tblAuditLog (userID, sectionKey, actionKey, targetID, createdAt)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($auditLogs as $al) {
        $audStmt->execute($al);
    }
    logMsg("Populated Audit Log entries.");

    $pdo->commit();
    logMsg("All sample records committed successfully!");

} catch (Throwable $e) {
    $pdo->rollBack();
    logMsg("Error during seeding: " . $e->getMessage());
    exit(1);
}
