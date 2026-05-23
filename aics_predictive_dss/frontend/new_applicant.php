<?php $current_page = 'new_applicant.php'; 

session_start(); // THIS MUST BE THE VERY FIRST LINE

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once 'auth.php';?>

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
            
            /* Blank Intake Form Print Styling Elements */
            --navy:   #003893;
            --red:    #ce1126;
            --gold:   #c8a94a;
            --light:  #f0f4ff;
            --border: #d1d9e6;
            --text:   #1e293b;
            --muted:  #64748b;
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

        /* Document layout styles for Blank Print Form */
        .printable-blank-form { display: none; }
        .printable-blank-form .document { background: #fff; border: 1.5px solid var(--border); }
        .printable-blank-form .doc-header { background: var(--navy); color: #fff; padding: 18px 28px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .printable-blank-form .doc-header .logo-wrap { display: flex; align-items: center; gap: 14px; }
        .printable-blank-form .doc-header .seal { width: 56px; height: 56px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; }
        .printable-blank-form .doc-header h1 { font-size: 15px; font-weight: 800; line-height: 1.2; }
        .printable-blank-form .doc-header p { font-size: 10px; opacity: .75; margin-top: 3px; letter-spacing: .5px; }
        .printable-blank-form .doc-header .right-info { text-align: right; font-size: 11px; opacity: .85; line-height: 1.7; }
        .printable-blank-form .accent-bar { height: 5px; background: repeating-linear-gradient(90deg, var(--gold) 0, var(--gold) 20px, var(--red) 20px, var(--red) 40px); }
        .printable-blank-form .code-banner { background: var(--light); border-bottom: 1.5px solid var(--border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .printable-blank-form .code-banner .code-line { border-bottom: 1.5px solid #cbd5e1; width: 220px; height: 28px; margin-top: 4px; }
        .printable-blank-form .status-pill { padding: 5px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; background: #f1f5f9; color: var(--muted); border: 1px solid var(--border); }
        .printable-blank-form .section-title { background: var(--navy); color: #fff; padding: 8px 28px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }
        .printable-blank-form .section-title.red { background: var(--red); }
        .printable-blank-form .section-title span.sub { font-weight: 400; font-size: 10px; opacity: .8; font-style: italic; text-transform: none; letter-spacing: 0; }
        .printable-blank-form .info-body { padding: 20px 28px; }
        .printable-blank-form .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px 24px; }
        .printable-blank-form .info-grid.cols-2 { grid-template-columns: 1fr 1fr; }
        .printable-blank-form .info-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }
        .printable-blank-form .info-grid.cols-full { grid-template-columns: 1fr; }
        .printable-blank-form .info-item label { font-size: 9px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .8px; display: block; margin-bottom: 3px; }
        .printable-blank-form .info-item .value { font-size: 14px; color: var(--text); font-weight: 600; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 5px; min-height: 26px; }
        .printable-blank-form .family-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .printable-blank-form .family-table th { background: #f8fafc; padding: 8px 12px; text-align: left; font-size: 9px; font-weight: 800; color: var(--muted); text-transform: uppercase; border-bottom: 1.5px solid var(--border); letter-spacing: .5px; }
        .printable-blank-form .family-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: var(--text); }
        .printable-blank-form .sig-area { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; padding: 32px 28px 24px; border-top: 1.5px solid var(--border); }
        .printable-blank-form .sig-box { text-align: center; }
        .printable-blank-form .sig-line { border-bottom: 1.5px solid var(--text); margin-bottom: 6px; height: 30px; }
        .printable-blank-form .sig-label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
        .printable-blank-form .doc-footer { background: var(--navy); padding: 10px 28px; display: flex; justify-content: space-between; align-items: center; font-size: 10px; color: rgba(255,255,255,.65); }
        .printable-blank-form .doc-footer span.gold { color: var(--gold); font-weight: 700; }
        .printable-blank-form .hr { border: 0; border-top: 1px solid #e2e8f0; margin: 0; }

        @media print {
            /* Hide the entire system screen portal UI */
            .sidebar, .form-card, .main > div:first-child, .print-btn, .alert-success, .checklist-card { display: none !important; }
            body { display: block; background: #fff; }
            .main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            
            /* Unhide and scale the blank DSWD template sheet */
            .printable-blank-form { display: block !important; }
            .printable-blank-form .document { box-shadow: none; border: none; max-width: 100%; }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            @page { margin: 12mm 14mm; size: A4; }
        }

        @media (max-width: 600px) {
            body { padding: 10px; }
            .info-grid      { grid-template-columns: 1fr 1fr; }
            .info-grid.cols-4 { grid-template-columns: 1fr 1fr; }
            .sig-area       { grid-template-columns: 1fr; gap: 20px; }
            .doc-header     { flex-direction: column; }
            .doc-header .right-info { text-align: left; }
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
                    <div class="col">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" id="dob_input" value="<?php echo (!empty($row['birth_date']) && $row['birth_date'] != '0000-00-00') ? date('Y-m-d', strtotime($row['birth_date'])) : ''; ?>" required>
                    </div>
                    <div class="col"><label>Age</label><input type="number" name="age" id="age_input" required></div>
                    <div class="col"><label>Sex</label>
                        <select name="sex" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col"><label>Civil Status</label>
                        <select name="civil_status" required>
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                            <option value="Common-law">Common-law / Live-in</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col"><label>Contact Number</label><input type="text" name="contact" required></div>
                </div>
                
                <div class="row">
                    <div class="col" style="position: relative;">
                        <label>Barangay (Quezon City)</label>
                        <input type="text" name="barangay" id="barangay_search" placeholder="Type to search barangay..." autocomplete="off" required>
                        
                        <div id="brgy_suggestions" style="position: absolute; width: 100%; background: white; border: 1px solid #d2d6da; border-top: none; border-radius: 0 0 6px 6px; z-index: 100; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <label>House No. / Street / Village</label>
                        <input type="text" name="address" placeholder="Unit/House No, Street Name" required>
                    </div>
                </div>

                <div class="form-section-title" style="color: #6366f1;">Client Category / Sector</div>
                <div class="analysis-grid" style="margin-bottom: 15px; background: #f5f3ff; border-color: #c084fc;">
                    <?php 
                    $categories = [
                        "Family Heads and Other Needy Adult", 
                        "Persons with Disabilities", 
                        "Senior Citizens", 
                        "Men/Women in Specially Difficult Circumstances"
                    ];
                    foreach($categories as $cat): ?>
                        <label class="option-item">
                            <input type="radio" name="client_category" value="<?php echo $cat; ?>" required> <?php echo $cat; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-section-title" style="color: #a855f7;">Client Subcategory</div>
                <div class="analysis-grid" style="margin-bottom: 15px; background: #faf5ff; border-color: #e9d5ff;">
                    <?php 
                    $subcategories = [
                        "Individuals with Cancer",
                        "Dialysis Patients",
                        "Chronic Illness / Geriatric Conditions",
                        "Tuberculosis Patients",
                        "Rare Disease / Disability caused by Rare Disease",
                        "Physical Disability / Orthopedically Handicapped",
                        "Visual Disability / Visually Impaired",
                        "Hearing/Speech Impaired",
                        "Psychosocial/Mental/Learning Disability",
                        "Intellectual Disability / Mentally Challenged",
                        "Non-apparent Speech and Language Impairment",
                        "Victims of Disaster",
                        "Internally Displaced Family",
                        "Person of Concerns - Asylum Seeker / Refugee / Stateless Persons",
                        "Physically-abused/maltreated/battered",
                        "Victims of involuntary prostitution",
                        "Recovering Person who used Drugs",
                        "Wounded in Action (WIA)",
                        "Others specify"
                    ];
                    foreach($subcategories as $sub): ?>
                        <label class="option-item">
                            <input type="radio" name="client_subcategory" value="<?php echo $sub; ?>" required> <?php echo $sub; ?>
                        </label>
                    <?php endforeach; ?>
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

                <div class="form-section-title" style="color: #3b82f6;">Medical Cause</div>
                <div class="analysis-grid">
                    <?php 
                    $causes = ["Medical Checkup", "Emergency Treatment", "Maternity Care", "Chemotherapy", "Surgery", "Hospitalization", "Laboratory Tests", "Accident Injury", "Dialysis"];
                    foreach($causes as $c): ?>
                        <label class="option-item">
                            <input type="radio" name="medical_cause" value="<?php echo $c; ?>" required> <?php echo $c; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-section-title" style="color: #10b981;">Type of Assistance Requested</div>
                <div class="analysis-grid" style="background: #f0fdf4; border-color: #10b981;">
                    <?php 
                    $types = ["Medical Assistance", "Cash Guarantee", "Surgery Financial Support", "Laboratory Assistance", "Dialysis Assistance", "Medicine Assistance", "Hospital Bill Assistance"];
                    foreach($types as $t): ?>
                        <label class="option-item">
                            <input type="radio" name="assistance_type" value="<?php echo $t; ?>" required> <?php echo $t; ?>
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
                    <i class="fas fa-print"></i> PRINT FORM
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

    <!-- ══════════════════════════════════════════
         INJECTED PRINTABLE BLANK TEMPLATE SHEET
    ══════════════════════════════════════════ -->
    <div class="printable-blank-form">
        <div class="document">
            <!-- Header -->
            <div class="doc-header">
                <div class="logo-wrap">
                    <div class="seal">🏛️</div>
                    <div>
                        <h1>Department of Social Welfare and Development</h1>
                        <p>AICS — ASSISTANCE TO INDIVIDUALS IN CRISIS SITUATION</p>
                        <p>Batasan Hills Branch &nbsp;|&nbsp; Quezon City</p>
                    </div>
                </div>
                <div class="right-info">
                    <div><strong>GENERAL INTAKE SHEET</strong></div>
                    <div>DSWD-PMB-GF-011 | REV 02</div>
                </div>
            </div>
            <div class="accent-bar"></div>

            <!-- Code Banner -->
            <div class="code-banner">
                <div>
                    <div style="font-size:10px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.8px;">Application Reference Code</div>
                    <div class="code-line"></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:10px; color:var(--muted); margin-bottom:4px;">Submission Status</div>
                    <span class="status-pill">BLANK / UNFILLED</span>
                </div>
            </div>

            <!-- Part I -->
            <div class="section-title">
                <i class="fas fa-user"></i>Part I: Beneficiary's Identifying Information <span class="sub">— Impormasyon ng Benepisyaryo</span>
            </div>
            <div class="info-body">
                <div class="info-grid cols-4" style="margin-bottom:18px;">
                    <div class="info-item" style="grid-column: span 2;"><label>Buong Pangalan / Full Name</label><div class="value"></div></div>
                    <div class="info-item"><label>Apelyido / Last Name</label><div class="value"></div></div>
                    <div class="info-item"><label>Unang Pangalan / First Name</label><div class="value"></div></div>
                </div>
                <div class="info-grid" style="margin-bottom:18px;">
                    <div class="info-item"><label>Kapanganakan / Date of Birth</label><div class="value"></div></div>
                    <div class="info-item"><label>Kasarian / Sex</label><div class="value"></div></div>
                    <div class="info-item"><label>Katayuang Sibil / Civil Status</label><div class="value"></div></div>
                </div>
                <div class="info-grid" style="margin-bottom:18px;">
                    <div class="info-item"><label>Barangay</label><div class="value"></div></div>
                    <div class="info-item"><label>Lungsod / City</label><div class="value"></div></div>
                    <div class="info-item"><label>Rehiyon / Region</label><div class="value"></div></div>
                </div>
            </div>

            <hr class="hr">

            <!-- Part II -->
            <div class="section-title red">
                <i class="fas fa-hand-holding-medical"></i>Part II: Assistance Details <span class="sub">— Detalye ng Tulong</span>
            </div>
            <div class="info-body">
                <div class="info-grid cols-2" style="margin-bottom:18px;">
                    <div class="info-item"><label>Uri ng Tulong / Type of Assistance</label><div class="value"></div></div>
                    <div class="info-item"><label>Kategorya ng Kliyente / Client Category</label><div class="value"></div></div>
                </div>
                <div class="info-grid cols-full" style="margin-bottom:18px;">
                    <div class="info-item"><label>Sub-Kategorya / Sub-Category</label><div class="value"></div></div>
                </div>
                <div class="info-grid cols-full">
                    <div class="info-item"><label>Dahilan ng Kahilingan / Medical Cause</label><div class="value" style="height: 50px;"></div></div>
                </div>
            </div>

            <hr class="hr">

            <!-- Family Composition -->
            <div class="section-title" style="background:#475569;">
                <i class="fas fa-users"></i>Komposisyon ng Pamilya / Family Composition
            </div>
            <div class="info-body" style="padding-top:16px; padding-bottom:16px;">
                <table class="family-table">
                    <thead>
                        <tr>
                            <th style="width:35%;">Buong Pangalan (Complete Name)</th>
                            <th style="width:22%;">Relasyon (Relationship)</th>
                            <th style="width:10%;">Edad (Age)</th>
                            <th style="width:18%;">Trabaho (Occupation)</th>
                            <th style="width:15%;">Buwanang Kita (Salary)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 6; $i++): ?>
                        <tr>
                            <td style="height:32px; border-bottom: 1.5px solid #e2e8f0;">&nbsp;</td>
                            <td style="border-bottom: 1.5px solid #e2e8f0;"></td>
                            <td style="border-bottom: 1.5px solid #e2e8f0;"></td>
                            <td style="border-bottom: 1.5px solid #e2e8f0;"></td>
                            <td style="border-bottom: 1.5px solid #e2e8f0;"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <hr class="hr">

            <!-- Social Worker Assessment -->
            <div class="section-title red">
                <i class="fas fa-clipboard-check"></i>Social Worker's Assessment <span class="sub">— Para sa DSWD Personnel</span>
            </div>
            <div class="info-body">
                <div style="border: 1.5px dashed var(--border); border-radius:8px; padding:16px; min-height:90px; background:#fafbff;"></div>
                <div class="info-grid cols-2" style="margin-top:18px;">
                    <div class="info-item"><label>Provided</label><div class="value">&nbsp;</div></div>
                    <div class="info-item"><label>Amount</label><div class="value">&nbsp;</div></div>
                </div>
                <div class="info-grid cols-full" style="margin-top:12px;">
                    <div class="info-item"><label>Fund Source</label><div class="value">&nbsp;</div></div>
                </div>
            </div>

            <!-- Privacy Notice -->
            <div style="padding: 14px 28px; background:#f8fafc; border-top: 1px solid var(--border); font-size:10px; color:var(--muted); line-height:1.7;">
                We are committed to protect and respect the privacy of our clients and beneficiaries and we will only collect, record, store, process and use personal information in accordance with <strong>Republic Act No. 10173 or the Data Privacy Act of 2012.</strong> By signing this form you are giving your consent to the DSWD.
            </div>

            <!-- Signatures -->
            <div class="sig-area">
                <div class="sig-box">
                    <div class="sig-line" style="height: 50px;"></div>
                    <div class="sig-label" style="margin-top: 5px;">Buong Pangalan at Pirma ng Kliyente<br>(Signature over Printed Name)</div>
                </div>
                <div class="sig-box">
                    <div class="sig-line" style="height: 50px;"></div>
                    <div class="sig-label" style="margin-top: 5px;">Interviewed by &nbsp;/&nbsp; Reviewed & Approved by<br>(Social Worker)</div>
                </div>
            </div>

            <!-- Footer -->
            <div class="doc-footer">
                <span>DSWD Field Office NCR &nbsp;|&nbsp; Batasan Hills Branch &nbsp;|&nbsp; Quezon City</span>
                <span class="gold">AICS — Blank Intake Template</span>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementsByName('dob')[0].addEventListener('change', function() {
    if(!this.value) return; 
    
    const dob = new Date(this.value);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    
    const ageField = document.getElementsByName('age')[0];
    if(ageField) {
        ageField.value = age;
    }
});

const barangays = [
    "Alicia", "Amihan", "Apolonio Samson", "Aurora", "Baesa", "Bagbag", "Bagong Lipunan ng Crame", "Bagong Pag-asa", "Bagong Silangan", "Bagumbayan", "Bagumbuhay", "Bahay Toro", "Balingasa", "Balong Bato", "Batasan Hills", "Bayanihan", "Blue Ridge A", "Blue Ridge B", "Botocan", "Bungad", "Camp Aguinaldo", "Capri", "Central", "Claro", "Commonwealth", "Culiat", "Damar", "Damayan", "Damayang Lagi", "Del Monte", "Dioquino Zobel", "Doña Aurora", "Doña Imelda", "Doña Josefa", "Don Manuel", "Duyan-duyan", "E. Rodriguez", "East Kamias", "Escopa I", "Escopa II", "Escopa III", "Escopa IV", "Fairview", "Greater Lagro", "Gulod", "Holy Spirit", "Horseshoe", "Immaculate Concepcion", "Kaligayahan", "Kalusugan", "Kamuning", "Katipunan", "Kaunlaran", "Kristong Hari", "Krus na Ligas", "Laging Handa", "Libis", "Lourdes", "Loyola Heights", "Maharlika", "Malaya", "Mangga", "Manresa", "Mariana", "Mariblo", "Marilag", "Masagana", "Masambong", "Matandang Balara", "Milagrosa", "N.S. Amoranto", "Nagkaisang Nayon", "Nayong Kanluran", "New Era", "North Fairview", "Novaliches Proper", "Obrero", "Old Capitol Site", "Paang Bundok", "Pag-ibig sa Nayon", "Paligsahan", "Paltok", "Pansol", "Paraiso", "Pasong Putik Proper", "Pasong Tamo", "Payatas", "Phil-Am", "Pinagkaisahan", "Pinyahan", "Project 6", "Quirino 2-A", "Quirino 2-B", "Quirino 2-C", "Quirino 3-A", "Ramon Magsaysay", "Roxas", "Sacred Heart", "Saint Ignatius", "Saint Peter", "Salvacion", "San Agustin", "San Antonio", "San Bartolome", "San Isidro", "San Isidro Labrador", "San Jose", "San Martin de Porres", "San Roque", "San Vicente", "Sangandaan", "Santa Cruz", "Santa Lucia", "Santa Monica", "Santa Teresita", "Santol", "Santo Cristo", "Santo Domingo", "Santo Niño", "Sauyo", "Sienna", "Sikatuna Village", "Silangan", "Socorro", "South Triangle", "Tagumpay", "Talayan", "Talipapa", "Tandang Sora", "Tatalon", "Teachers Village East", "Teachers Village West", "U.P. Campus", "U.P. Village", "Ugong Norte", "Unang Sigaw", "Valencia", "Vasra", "Veterans Village", "Villa Maria Clara", "West Kamias", "West Triangle", "White Plains"
];

const input = document.getElementById('barangay_search');
const suggestionBox = document.getElementById('brgy_suggestions');

input.addEventListener('input', function() {
    const val = this.value.toLowerCase();
    suggestionBox.innerHTML = '';
    
    if (!val) {
        suggestionBox.style.display = 'none';
        return;
    }

    const matches = barangays.filter(b => b.toLowerCase().includes(val)).slice(0, 5);

    if (matches.length > 0) {
        matches.forEach(m => {
            const div = document.createElement('div');
            div.innerHTML = m;
            div.style.padding = '10px';
            div.style.cursor = 'pointer';
            div.style.fontSize = '13px';
            div.onmouseover = () => div.style.background = '#f1f5f9';
            div.onmouseout = () => div.style.background = 'white';
            div.onclick = () => {
                input.value = m;
                suggestionBox.style.display = 'none';
            };
            suggestionBox.appendChild(div);
        });
        suggestionBox.style.display = 'block';
    } else {
        suggestionBox.style.display = 'none';
    }
});

document.addEventListener('click', (e) => {
    if (e.target !== input) suggestionBox.style.display = 'none';
});
</script>

</body>
</html>