<?php
$c = file_get_contents("edit_employee.php");

$find_logic = <<<'EOD'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
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
EOD;

$replace_logic = <<<'EOD'
$emp_id = $_GET['id'] ?? null;
if (!$emp_id) {
    echo "Employee ID is missing.";
    exit;
}

$emp = $pdo->query("SELECT * FROM users WHERE id = " . (int)$emp_id . " AND company_id = $company_id")->fetch();
if (!$emp) {
    echo "Employee not found.";
    exit;
}

// Extract first and last name from full name
$name_parts = explode(' ', $emp['name'], 2);
$emp['first_name'] = $name_parts[0];
$emp['last_name'] = $name_parts[1] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
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
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET 
                name=?, email=?, phone=?, password=?, role_id=?, manager_id=?, department_id=?, status=?, base_salary=?, joining_date=?,
                alt_mobile=?, whatsapp=?, gender=?, dob=?, street=?, city=?, state=?, country=?, pincode=?, designation=?, office_location=?, work_shift=?, punch_in_range=?
                WHERE id=? AND company_id=?");
            $stmt->execute([
                $name, $email, $phone, $hash, $role_id, $manager_id, $department_id, $status, $base_salary, $joining_date,
                $alt_mobile, $whatsapp, $gender, $dob, $street, $city, $state, $country, $pincode, $designation, $office_location, $work_shift, $punch_in_range,
                $emp_id, $company_id
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET 
                name=?, email=?, phone=?, role_id=?, manager_id=?, department_id=?, status=?, base_salary=?, joining_date=?,
                alt_mobile=?, whatsapp=?, gender=?, dob=?, street=?, city=?, state=?, country=?, pincode=?, designation=?, office_location=?, work_shift=?, punch_in_range=?
                WHERE id=? AND company_id=?");
            $stmt->execute([
                $name, $email, $phone, $role_id, $manager_id, $department_id, $status, $base_salary, $joining_date,
                $alt_mobile, $whatsapp, $gender, $dob, $street, $city, $state, $country, $pincode, $designation, $office_location, $work_shift, $punch_in_range,
                $emp_id, $company_id
            ]);
        }
        $success = "Employee updated successfully!";
        // Refresh emp data
        $emp = $pdo->query("SELECT * FROM users WHERE id = " . (int)$emp_id . " AND company_id = $company_id")->fetch();
        $name_parts = explode(' ', $emp['name'], 2);
        $emp['first_name'] = $name_parts[0];
        $emp['last_name'] = $name_parts[1] ?? '';
    } catch (PDOException $e) {
        $error = "Error updating employee: " . $e->getMessage();
    }
}
?>
EOD;

$c = str_replace($find_logic, $replace_logic, $c);

// Also I will need to replace HTML form inputs to prepopulate with values
function prepop($name, $col = null) {
    if (!$col) $col = $name;
    return 'value="<?php echo htmlspecialchars($emp[\''.$col.'\'] ?? \'\'); ?>"';
}

$replacements = [
    'name="first_name" class="form-control" required style="border-radius: 8px;"' => 'name="first_name" class="form-control" required style="border-radius: 8px;" '.prepop("first_name"),
    'name="last_name" class="form-control" required style="border-radius: 8px;"' => 'name="last_name" class="form-control" required style="border-radius: 8px;" '.prepop("last_name"),
    'name="email" class="form-control" required style="border-radius: 8px;"' => 'name="email" class="form-control" required style="border-radius: 8px;" '.prepop("email"),
    'name="mobile" class="form-control" required style="border-radius: 8px;"' => 'name="mobile" class="form-control" required style="border-radius: 8px;" '.prepop("mobile", "phone"),
    'name="alt_mobile" class="form-control" style="border-radius: 8px;"' => 'name="alt_mobile" class="form-control" style="border-radius: 8px;" '.prepop("alt_mobile"),
    'name="whatsapp" class="form-control" style="border-radius: 8px;"' => 'name="whatsapp" class="form-control" style="border-radius: 8px;" '.prepop("whatsapp"),
    'name="dob" class="form-control" style="border-radius: 8px;"' => 'name="dob" class="form-control" style="border-radius: 8px;" '.prepop("dob"),
    'name="street" class="form-control" style="border-radius: 8px;"' => 'name="street" class="form-control" style="border-radius: 8px;" '.prepop("street"),
    'name="city" class="form-control" style="border-radius: 8px;"' => 'name="city" class="form-control" style="border-radius: 8px;" '.prepop("city"),
    'name="state" class="form-control" style="border-radius: 8px;"' => 'name="state" class="form-control" style="border-radius: 8px;" '.prepop("state"),
    'name="country" class="form-control" value="India" style="border-radius: 8px;"' => 'name="country" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp[\'country\'] ?? \'India\'); ?>"',
    'name="pincode" class="form-control" style="border-radius: 8px;"' => 'name="pincode" class="form-control" style="border-radius: 8px;" '.prepop("pincode"),
    'name="salary" class="form-control" required style="border-radius: 8px;"' => 'name="salary" class="form-control" required style="border-radius: 8px;" '.prepop("salary", "base_salary"),
    'name="doj" class="form-control" value="2026-05-22" style="border-radius: 8px;"' => 'name="doj" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp[\'joining_date\'] ?? \'2026-05-22\'); ?>"',
    'name="punch_in_range" class="form-control" value="500" style="border-radius: 8px;"' => 'name="punch_in_range" class="form-control" style="border-radius: 8px;" value="<?php echo htmlspecialchars($emp[\'punch_in_range\'] ?? 500); ?>"',
    'name="password" class="form-control" required style="border-radius: 8px;"' => 'name="password" class="form-control" style="border-radius: 8px;" placeholder="Leave blank to keep unchanged"',
    'name="confirm_password" class="form-control" required style="border-radius: 8px;"' => 'name="confirm_password" class="form-control" style="border-radius: 8px;" placeholder="Leave blank to keep unchanged"',
];

foreach ($replacements as $k => $v) {
    $c = str_replace($k, $v, $c);
}

// Select options
$c = preg_replace('/<option value="male">Male<\/option>/', '<option value="male" <?php echo ($emp[\'gender\']==\'male\')?\'selected\':\'\'; ?>>Male</option>', $c);
$c = preg_replace('/<option value="female">Female<\/option>/', '<option value="female" <?php echo ($emp[\'gender\']==\'female\')?\'selected\':\'\'; ?>>Female</option>', $c);
$c = preg_replace('/<option value="other">Other<\/option>/', '<option value="other" <?php echo ($emp[\'gender\']==\'other\')?\'selected\':\'\'; ?>>Other</option>', $c);

$c = preg_replace('/<option value="<\?php echo \$r\[\'id\'\]; \?>">/', '<option value="<?php echo $r[\'id\']; ?>" <?php echo ($emp[\'role_id\']==$r[\'id\'])?\'selected\':\'\'; ?>>', $c);
$c = preg_replace('/<option value="<\?php echo \$m\[\'id\'\]; \?>">/', '<option value="<?php echo $m[\'id\']; ?>" <?php echo ($emp[\'manager_id\']==$m[\'id\'])?\'selected\':\'\'; ?>>', $c);

// Department
for($i=1;$i<=5;$i++) {
    $c = preg_replace('/<option value="'.$i.'">/', '<option value="'.$i.'" <?php echo ($emp[\'department_id\']=='.$i.')?\'selected\':\'\'; ?>>', $c);
}

// Designation
$desig = ['Software Engineer', 'Sales Executive', 'Manager'];
foreach($desig as $d) {
    $c = preg_replace('/<option value="'.$d.'">/', '<option value="'.$d.'" <?php echo ($emp[\'designation\']==\''.$d.'\')?\'selected\':\'\'; ?>>', $c);
}

// Status
$stat = ['active', 'inactive', 'probation'];
foreach($stat as $s) {
    $c = preg_replace('/<option value="'.$s.'">/', '<option value="'.$s.'" <?php echo ($emp[\'status\']==\''.$s.'\')?\'selected\':\'\'; ?>>', $c);
}

// Office location
$ol = ['HQ', 'Branch 1'];
foreach($ol as $o) {
    $c = preg_replace('/<option value="'.$o.'">/', '<option value="'.$o.'" <?php echo ($emp[\'office_location\']==\''.$o.'\')?\'selected\':\'\'; ?>>', $c);
}

// Work Shift
$ws = ['Morning', 'Evening', 'Night'];
foreach($ws as $w) {
    $c = preg_replace('/<option value="'.$w.'">/', '<option value="'.$w.'" <?php echo ($emp[\'work_shift\']==\''.$w.'\')?\'selected\':\'\'; ?>>', $c);
}

file_put_contents("edit_employee.php", $c);
echo "Patched edit_employee.php\n";
?>
