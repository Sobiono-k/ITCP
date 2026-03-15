<?php $current_page = 'new_applicant.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Applicant - DSWD AICS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dswd-dark: #2c3e50;
            --sidebar-bg: #1e293b;
            --bg-color: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --sidebar-width: 260px;
            --primary: #3b82f6;
            --success: #10b981;
        }
        
        body {  font-family: 'Inter', sans-serif;  margin: 0;  background: var(--bg-color);  display: grid; grid-template-columns: var(--sidebar-width) 1fr; min-height: 100vh; scrollbar-gutter: stable; 
        }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); position: fixed; left: 0; top: 0; color: #fff; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; background: rgba(0,0,0,0.2); }
        .sidebar a { padding: 15px 25px; font-size: 17px; text-decoration: none; color: #94a3b8; display: flex; align-items: center; transition: 0.3s; border-left: 4px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: #334155; color: #fff; border-left: 4px solid #3b82f6; }

        .main { 
            grid-column: 2; 
            padding: 40px; 
            width: 100%; 
            box-sizing: border-box;
            animation: fadeIn 0.4s ease-in-out; 
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Success Alert Styling */
        .alert-success {
            background: #dcfce7;
            color: #166534;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bbf7d0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .page-content { display: flex; gap: 30px; align-items: flex-start; }

        .form-card { background: white; padding: 30px; border-radius: 12px; box-shadow: var(--card-shadow); flex: 2; min-width: 0; }
        .checklist-card { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; width: 450px; box-shadow: var(--card-shadow); overflow: hidden; flex-shrink: 0; }

        .form-section-title { font-size: 12px; font-weight: 800; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; margin: 25px 0 15px 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .row { display: flex; gap: 15px; margin-bottom: 15px; }
        .col { flex: 1; }
        label { display: block; margin-bottom: 5px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        input, select { width: 100%; padding: 10px; border: 1px solid #d2d6da; border-radius: 6px; box-sizing: border-box; font-size: 13px; }
        
        .analysis-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 8px; 
            background: #f8fafc; 
            padding: 15px; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0;
        }
        .option-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #344767; cursor: pointer; }
        .option-item input { width: auto; margin: 0; }

        .btn-submit { background: var(--primary); color: white; border: none; padding: 16px; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 15px; margin-top: 25px; transition: background 0.2s; }
        .btn-submit:hover { background: #2563eb; }

        .checklist-header { background: #3b82f6; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .checklist-body { padding: 20px; font-size: 11.5px; height: 800px; overflow-y: auto; line-height: 1.4; }
        .cat-title { font-weight: 800; color: #1e293b; margin: 15px 0 8px 0; font-size: 10.5px; text-transform: uppercase; border-left: 3px solid #3b82f6; padding-left: 8px; }
        .item { display: flex; gap: 8px; margin-bottom: 8px; color: #475569; }

        @media print {
            .sidebar, .form-card, .main > div:first-child, .print-btn, .alert-success { display: none !important; }
            body { display: block; }
            .main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .checklist-card { border: none !important; box-shadow: none !important; width: 100% !important; }
            .checklist-body { height: auto !important; overflow: visible !important; }
        }
    </style>
</head>
<body>

<?php if(file_exists('sidebar.php')) include 'sidebar.php'; ?>

<div class="main">
    <div style="margin-bottom: 25px;">
        <h1 style="color: #344767; font-size: 24px; margin: 0;">New Applicant Registration</h1>
        <p style="color: #8392ab; font-size: 14px;">Encoding Portal - Medical Assistance Program</p>
    </div>

    <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> Applicant successfully registered and data synced.
        </div>
    <?php endif; ?>

    <div class="page-content">
        <div class="form-card">
            <form action="save_applicant.php" method="POST">
                <div class="row">
                    <div class="col"><label>Date of Request</label><input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="col"><label>Email Address</label><input type="email" name="email" placeholder="email@example.com"></div>
                </div>

                <div class="form-section-title">Patient's Personal Information</div>
                <div class="row">
                    <div class="col"><label>First Name</label><input type="text" name="fname" required></div>
                    <div class="col"><label>Middle Name</label><input type="text" name="mname"></div>
                    <div class="col"><label>Last Name</label><input type="text" name="lname" required></div>
                </div>
                <div class="row">
                    <div class="col"><label>Date of Birth</label><input type="date" name="dob" required></div>
                    <div class="col"><label>Age</label><input type="number" name="age" required></div>
                    <div class="col"><label>Sex</label>
                        <select name="sex" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col"><label>Contact Number</label><input type="text" name="contact" required></div>
                </div>
                <div class="row">
                    <div class="col"><label>Complete Address</label><input type="text" name="address" required></div>
                </div>

                <div class="form-section-title">Monthly Household Income</div>
                <div class="analysis-grid" style="margin-bottom: 15px;">
                    <?php 
                    $incomes = ["Less than 10,000", "10,000 - 20,000", "21,000 - 40,000", "41,000 - 100,000", "101,000 and above"];
                    foreach($incomes as $inc): ?>
                        <label class="option-item">
                            <input type="radio" name="income" value="<?php echo $inc; ?>" required> <?php echo $inc; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-section-title">Medical Case Information</div>
                <div class="row">
                    <div class="col"><label>DOH Hospital</label><input type="text" name="hospital"></div>
                </div>
                <div class="row">
                    <div class="col"><label>Diagnosis</label><input type="text" name="diagnosis"></div>
                </div>

                <div class="form-section-title" style="color: #3b82f6;">Medical Cause (For Data Analysis)</div>
                <div class="analysis-grid">
                    <?php 
                    $causes = ["Medication Assistance", "Medical Checkup", "Emergency Treatment", "Maternity Care", "Chemotherapy", "Surgery", "Hospitalization", "Laboratory Tests", "Accident Injury", "Dialysis"];
                    foreach($causes as $c): ?>
                        <label class="option-item">
                            <input type="radio" name="medical_cause" value="<?php echo $c; ?>" required> <?php echo $c; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-section-title" style="color: #10b981;">Type of Assistance Requested</div>
                <div class="analysis-grid" style="background: #f0fdf4; border-color: #10b981;">
                    <?php 
                    $types = ["Chemotherapy Assistance", "Cash Guarantee", "Surgery Financial Support", "Laboratory Assistance", "Dialysis Assistance", "Medical Cash Aid", "Medicine Assistance", "Hospital Bill Assistance"];
                    foreach($types as $t): ?>
                        <label class="option-item">
                            <input type="radio" name="cause" value="<?php echo $t; ?>" required> <?php echo $t; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-submit">SYNC DATA & REGISTER APPLICANT</button>
            </form>
        </div>

        <div class="checklist-card">
            <div class="checklist-header">
                <div>
                    <div style="font-weight: 800; font-size: 14px;">MEDICAL ASSISTANCE</div>
                    <div style="font-size: 11px; opacity: 0.9;">REQUIREMENTS LIST</div>
                </div>
                <button onclick="window.print()" class="print-btn" style="background: white; color: #3b82f6; border: none; padding: 5px 12px; border-radius: 4px; font-weight: 700; cursor: pointer; font-size: 11px;">
                    <i class="fas fa-print"></i> PRINT
                </button>
            </div>
            <div class="checklist-body">
                <div class="item"><span>• Valid ID ng taong maglalakad/iinterviewhin</span></div>
                <div class="item"><span>• Authorization Letter (if applicable)</span></div>
                <div class="item"><span>• Personal letter to the Senator (for SPAO)</span></div>
                <div class="item"><span>• Brgy. Certificate of Indigency</span></div>

                <div class="cat-title">Para sa Babayarang Hospital Bill:</div>
                <div class="item"><span>• Medical Certificate/Clinical Abstract/Discharge Summary with diagnosis and signature of the Physician (issued within 3 months)</span></div>
                <div class="item"><span>• Hospital Bill or Statement of Account with signature of billing clerk</span></div>
                <div class="item"><span>• Social Case Study Report or Case Summary</span></div>

                <div class="cat-title">Para sa Gamot o Assistive Device:</div>
                <div class="item"><span>• Medical Certificate/Clinical Abstract (issued within 3 months)</span></div>
                <div class="item"><span>• Prescription with date, complete name, license number and signature of Physician</span></div>
                <div style="font-size: 10.5px; color: #64748b; padding-left: 20px; font-style: italic;">Kung hihigit sa PhP 10,000: Quotation and Social Case Study Report needed</div>

                <div style="margin-top:20px; padding: 15px; border-radius: 8px; background: #fee2e2; border: 1px solid #fecaca;">
                    <div style="color: #991b1b; font-weight: 800; font-size: 11px; margin-bottom: 5px;">PAALALA:</div>
                    <div style="font-size: 10px; color: #b91c1c;">
                        <strong>NO FIXER:</strong> Ang lahat ng pinansyal na tulong mula sa DSWD ay buong matatanggap ng mga benepisyaryo.
                        <br><br>
                        <strong>FAKE DOCUMENTS:</strong> Submission of fake documents is punishable by law.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>