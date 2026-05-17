<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$form_id = $_GET['id'] ?? null;
if (!$form_id) {
    redirect('modules/forms/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$form_id]);
$form = $stmt->fetch();

if (!$form) {
    redirect('modules/forms/index.php');
}

$stmtFields = $pdo->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY sort_order ASC");
$stmtFields->execute([$form_id]);
$fields = $stmtFields->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_form'])) {
    $response_data = [];
    foreach ($fields as $field) {
        $fieldName = 'field_' . $field['id'];
        if (isset($_POST[$fieldName])) {
            $response_data[$field['field_label']] = $_POST[$fieldName];
        }
    }

    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    try {
        $stmt = $pdo->prepare("INSERT INTO form_responses (form_id, agent_id, response_data, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$form_id, $_SESSION['user_id'] ?? 1, json_encode($response_data), $latitude, $longitude]);
        
        // If this is a Lead Capture form, also create a lead
        // (Simplified logic for now: assume all forms can be lead captures)
        $lead_id = generateLeadID();
        $customer_name = $response_data['Customer Name'] ?? $response_data['Name'] ?? 'Unknown';
        $mobile = $response_data['Mobile Number'] ?? $response_data['Mobile'] ?? '';
        $business_name = $response_data['Business Name'] ?? '';
        
        $stmtLead = $pdo->prepare("INSERT INTO leads (id, agent_id, customer_name, mobile, business_name, status) VALUES (?, ?, ?, ?, ?, 'new')");
        $stmtLead->execute([$lead_id, $_SESSION['user_id'] ?? 1, $customer_name, $mobile, $business_name]);

        $success = "Form submitted successfully! Lead ID: " . $lead_id;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="header-actions" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;"><?php echo $form['title']; ?></h1>
    <p style="color: var(--text-muted); font-size: 0.875rem;"><?php echo $form['description']; ?></p>
</div>

<?php if (isset($success)): ?>
    <div class="glass-card" style="padding: 1.5rem; background: rgba(16, 185, 129, 0.2); color: var(--success); margin-bottom: 2rem; border-color: var(--success); text-align: center;">
        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-bottom: 1rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <h2 style="margin-bottom: 0.5rem;">Submission Successful</h2>
        <p><?php echo $success; ?></p>
        <div style="margin-top: 1.5rem;">
            <a href="index.php" class="btn btn-primary">Back to Forms</a>
            <a href="fill.php?id=<?php echo $form_id; ?>" class="btn glass-card">Submit Another</a>
        </div>
    </div>
<?php else: ?>

<div class="glass-card" style="max-width: 800px; margin: 0 auto; padding: 2.5rem;">
    <form method="POST" id="dynamic-form">
        <input type="hidden" name="latitude" id="lat">
        <input type="hidden" name="longitude" id="lng">

        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <?php foreach($fields as $field): ?>
                <div class="form-group">
                    <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--text-main);">
                        <?php echo $field['field_label']; ?>
                        <?php if($field['is_required']): ?><span style="color: var(--danger);">*</span><?php endif; ?>
                    </label>

                    <?php if($field['field_type'] == 'text'): ?>
                        <input type="text" name="field_<?php echo $field['id']; ?>" <?php echo $field['is_required'] ? 'required' : ''; ?> style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                    
                    <?php elseif($field['field_type'] == 'number'): ?>
                        <input type="number" name="field_<?php echo $field['id']; ?>" <?php echo $field['is_required'] ? 'required' : ''; ?> style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">

                    <?php elseif($field['field_type'] == 'dropdown'): ?>
                        <?php $options = json_decode($field['field_options'], true); ?>
                        <select name="field_<?php echo $field['id']; ?>" <?php echo $field['is_required'] ? 'required' : ''; ?> style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                            <option value="">Select Option</option>
                            <?php foreach($options as $opt): ?>
                                <option value="<?php echo $opt; ?>"><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>

                    <?php elseif($field['field_type'] == 'photo' || $field['field_type'] == 'file'): ?>
                        <div style="border: 2px dashed var(--border); border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer;" onclick="this.querySelector('input').click()">
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: 0.5rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"></path></svg>
                            <div style="color: var(--text-muted);">Click to upload or take photo</div>
                            <input type="file" name="field_<?php echo $field['id']; ?>" style="display: none;" <?php echo $field['is_required'] ? 'required' : ''; ?>>
                        </div>

                    <?php elseif($field['field_type'] == 'location'): ?>
                        <button type="button" class="btn glass-card" onclick="getLocation(this)" style="width: 100%; justify-content: center; background: rgba(34, 211, 238, 0.1); color: var(--accent); border-color: var(--accent);">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            Capture Live GPS Location
                        </button>
                        <div id="location-status" style="font-size: 0.75rem; color: var(--success); margin-top: 5px; display: none;">Location captured successfully!</div>

                    <?php elseif($field['field_type'] == 'signature'): ?>
                        <div style="border: 1px solid var(--border); border-radius: 8px; background: white; height: 150px;">
                            <!-- Signature pad would go here -->
                            <div style="height: 100%; display: flex; align-items: center; justify-content: center; color: #64748b;">Signature Pad Area</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 3rem;">
            <button type="submit" name="submit_form" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.125rem;">
                Submit Form
            </button>
        </div>
    </form>
</div>

<script>
function getLocation(btn) {
    if (navigator.geolocation) {
        btn.innerHTML = 'Capturing...';
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('lat').value = position.coords.latitude;
            document.getElementById('lng').value = position.coords.longitude;
            document.getElementById('location-status').style.display = 'block';
            btn.innerHTML = 'Location Captured';
            btn.style.opacity = '0.7';
            btn.disabled = true;
        }, function(error) {
            alert('Error capturing location: ' + error.message);
            btn.innerHTML = 'Capture Live GPS Location';
        });
    } else {
        alert('Geolocation is not supported by this browser.');
    }
}
</script>

<?php endif; ?>

<?php include_once '../../includes/footer.php'; ?>
