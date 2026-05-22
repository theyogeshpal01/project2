<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$company_id = $_SESSION['company_id'] ?? 1;

$roles = getRoles($pdo);
$teams = getTeams($pdo);
$managers = $pdo->query("SELECT id, name FROM users WHERE role_id IN (1,2,3) AND company_id = $company_id ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_employee'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $name = trim($first_name . ' ' . $last_name);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = $_POST['role_id'] ?? null;
    $manager_id = $_POST['manager_id'] ?: null;
    $department_id = $_POST['department'] ?: null;
    $status = $_POST['status'] ?? 'active';
    $base_salary = $_POST['salary'] ?? 0;
    $joining_date = $_POST['doj'] ?? null;

    $alt_mobile = trim($_POST['alt_mobile'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $gender = $_POST['gender'] ?: null;
    $dob = $_POST['dob'] ?: null;
    $street = trim($_POST['street'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $office_location = trim($_POST['office_location'] ?? '');
    $work_shift = trim($_POST['work_shift'] ?? '');
    $punch_in_range = $_POST['punch_in_range'] ? (int)$_POST['punch_in_range'] : null;

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users 
            (company_id, name, email, phone, password, role_id, manager_id, department_id, status, base_salary, joining_date,
             alt_mobile, whatsapp, gender, dob, street, city, state, country, pincode, designation, office_location, work_shift, punch_in_range) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $company_id, $name, $email, $phone, $hash, $role_id, $manager_id, $department_id, $status, $base_salary, $joining_date,
            $alt_mobile, $whatsapp, $gender, $dob, $street, $city, $state, $country, $pincode, $designation, $office_location, $work_shift, $punch_in_range
        ]);
        $success = "Employee added successfully!";
    } catch (PDOException $e) {
        $error = "Error adding employee: " . $e->getMessage();
    }
}
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Add New Employee</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Fill in the employee details below</p>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger" style="color:var(--danger); background:rgba(239, 68, 68, 0.1); border:1px solid rgba(239,68,68,0.2); padding:1rem; border-radius:8px; margin-bottom:1rem;"><?php echo $error; ?></div>
<?php endif; ?>

<div class="glass-card" style="padding:2rem;">
    <form method="POST">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
            
            <!-- Personal Information -->
            <div>
                <h3 style="font-size:1.1rem;font-weight:600;color:var(--primary);margin-bottom:1.5rem;">Personal Information</h3>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">First Name *</label>
                        <input type="text" name="first_name" class="form-control" required style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['last_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Email *</label>
                    <input type="email" name="email" class="form-control" required style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['email'] ?? ''); ?>">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">Mobile *</label>
                        <input type="tel" name="mobile" class="form-control" required style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">Alternate Mobile</label>
                        <input type="tel" name="alt_mobile" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['alt_mobile'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">WhatsApp Number</label>
                    <input type="tel" name="whatsapp" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['whatsapp'] ?? ''); ?>">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">Gender</label>
                        <select name="gender" class="form-control" style="border-radius: 8px;">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($emp['gender']=='male')?'selected':''; ?>>Male</option>
                            <option value="female" <?php echo ($emp['gender']=='female')?'selected':''; ?>>Female</option>
                            <option value="other" <?php echo ($emp['gender']=='other')?'selected':''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">Date of Birth</label>
                        <input type="date" name="dob" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['dob'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div>
                <h3 style="font-size:1.1rem;font-weight:600;color:var(--primary);margin-bottom:1.5rem;">Address Information</h3>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Street</label>
                    <input type="text" name="street" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['street'] ?? ''); ?>">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">City</label>
                        <input type="text" name="city" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['city'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">State</label>
                        <input type="text" name="state" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['state'] ?? ''); ?>">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">Country</label>
                        <input type="text" name="country" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['country'] ?? 'India'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600; color:var(--text-dark);">Pincode</label>
                        <input type="text" name="pincode" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['pincode'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Employment Information -->
            <div>
                <h3 style="font-size:1.1rem;font-weight:600;color:var(--primary);margin-bottom:1.5rem;margin-top:1rem;">Employment Information</h3>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Role *</label>
                    <select name="role_id" class="form-control" required style="border-radius: 8px;">
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo ($emp['role_id']==$r['id'])?'selected':''; ?>><?php echo $r['role_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Manager</label>
                    <select name="manager_id" class="form-control" style="border-radius: 8px;">
                        <option value="">Select Manager</option>
                        <?php foreach ($managers as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo ($emp['manager_id']==$m['id'])?'selected':''; ?>><?php echo htmlspecialchars($m['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Department *</label>
                    <select name="department" class="form-control" required style="border-radius: 8px;">
                        <option value="">Select Department</option>
                        <option value="1" <?php echo ($emp['department_id']==1)?'selected':''; ?>>Sales</option>
                        <option value="2" <?php echo ($emp['department_id']==2)?'selected':''; ?>>Operations</option>
                        <option value="3" <?php echo ($emp['department_id']==3)?'selected':''; ?>>Finance</option>
                        <option value="4" <?php echo ($emp['department_id']==4)?'selected':''; ?>>Technology</option>
                        <option value="5" <?php echo ($emp['department_id']==5)?'selected':''; ?>>HR</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Designation *</label>
                    <select name="designation" class="form-control" required style="border-radius: 8px;">
                        <option value="">Select Designation</option>
                        <option value="Software Engineer" <?php echo ($emp['designation']=='Software Engineer')?'selected':''; ?>>Software Engineer</option>
                        <option value="Sales Executive" <?php echo ($emp['designation']=='Sales Executive')?'selected':''; ?>>Sales Executive</option>
                        <option value="Manager" <?php echo ($emp['designation']=='Manager')?'selected':''; ?>>Manager</option>
                    </select>
                </div>
            </div>

            <!-- Additional Information -->
            <div>
                <h3 style="font-size:1.1rem;font-weight:600;color:var(--primary);margin-bottom:1.5rem;margin-top:1rem;">Additional Information</h3>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Employment Status *</label>
                    <select name="status" class="form-control" required style="border-radius: 8px;">
                        <option value="">Select Status</option>
                        <option value="active" <?php echo ($emp['status']=='active')?'selected':''; ?>>Active</option>
                        <option value="inactive" <?php echo ($emp['status']=='inactive')?'selected':''; ?>>Inactive</option>
                        <option value="probation" <?php echo ($emp['status']=='probation')?'selected':''; ?>>Probation</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Office Location *</label>
                    <select name="office_location" class="form-control" required style="border-radius: 8px;">
                        <option value="">Select Office Location</option>
                        <option value="HQ" <?php echo ($emp['office_location']=='HQ')?'selected':''; ?>>Headquarters (HQ)</option>
                        <option value="Branch 1" <?php echo ($emp['office_location']=='Branch 1')?'selected':''; ?>>Branch 1</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Work Shift *</label>
                    <select name="work_shift" class="form-control" required style="border-radius: 8px;">
                        <option value="">Select Work Shift</option>
                        <option value="Morning" <?php echo ($emp['work_shift']=='Morning')?'selected':''; ?>>Morning (9 AM - 6 PM)</option>
                        <option value="Evening" <?php echo ($emp['work_shift']=='Evening')?'selected':''; ?>>Evening (2 PM - 11 PM)</option>
                        <option value="Night" <?php echo ($emp['work_shift']=='Night')?'selected':''; ?>>Night (10 PM - 7 AM)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Salary *</label>
                    <input type="number" name="salary" class="form-control" required style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['base_salary'] ?? ''); ?>">
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Date of Joining</label>
                    <input type="date" name="doj" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['joining_date'] ?? '2026-05-22'); ?>">
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Punch-In Range (meters) <span style="color:var(--text-muted);font-weight:400;font-size:0.8rem;">(10m - 10,000m)</span></label>
                    <input type="number" name="punch_in_range" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp['punch_in_range'] ?? 500); ?>">
                    <small style="color:var(--text-muted);display:block;margin-top:4px;">Default: 500m. Employee can punch-in within this range from office.</small>
                </div>
                
                <h3 style="font-size:1.1rem;font-weight:600;color:var(--primary);margin-bottom:1.5rem;margin-top:2rem;">Authentication</h3>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Password *</label>
                    <input type="password" name="password" class="form-control" style="border-radius: 8px;" placeholder="Leave blank to keep unchanged">
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label class="form-label" style="font-weight:600; color:var(--text-dark);">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" style="border-radius: 8px;" placeholder="Leave blank to keep unchanged">
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:15px; margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--border);">
            <button type="button" class="btn glass-card" style="padding:10px 24px; font-weight:600; border-radius:8px;">Cancel</button>
            <button type="submit" name="create_employee" class="btn btn-primary" style="padding:10px 24px; font-weight:600; border-radius:8px; background-color:#2563eb;">Create Employee</button>
        </div>
    </form>
</div>

<?php include_once '../../includes/footer.php'; ?>
