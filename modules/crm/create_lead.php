<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// ---- Auto-fix missing columns ----
try {
    $cols = $pdo->query("SHOW COLUMNS FROM form_responses")->fetchAll(PDO::FETCH_COLUMN);
    $needed = [
        'customer_name' => "ALTER TABLE form_responses ADD COLUMN customer_name VARCHAR(100) AFTER agent_id",
        'mobile'        => "ALTER TABLE form_responses ADD COLUMN mobile VARCHAR(20) AFTER customer_name",
        'business_name' => "ALTER TABLE form_responses ADD COLUMN business_name VARCHAR(100) AFTER mobile",
        'category'      => "ALTER TABLE form_responses ADD COLUMN category VARCHAR(50) AFTER business_name",
    ];
    foreach ($needed as $col => $sql) {
        if (!in_array($col, $cols)) $pdo->exec($sql);
    }
} catch(Exception $e) {}

// ---- Handle Form Submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_name'])) {
    $agent_id     = $_SESSION['user_id'] ?? 1;
    $customer     = trim($_POST['customer_name']);
    $mobile       = trim($_POST['mobile']);
    $business     = trim($_POST['business_name'] ?? '');
    $category     = trim($_POST['category'] ?? 'Others');
    $gps          = trim($_POST['gps_location'] ?? '');

    // Parse lat/lng from gps string
    $lat = null; $lng = null;
    if ($gps && strpos($gps, ',') !== false) {
        [$lat, $lng] = explode(',', $gps);
        $lat = trim($lat); $lng = trim($lng);
    }

    // Handle photo upload
    $photo_path = null;
    if (!empty($_FILES['shop_photo']['name'])) {
        $upload_dir = __DIR__ . '/../../uploads/leads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext        = pathinfo($_FILES['shop_photo']['name'], PATHINFO_EXTENSION);
        $photo_path = 'leads/lead_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['shop_photo']['tmp_name'], __DIR__ . '/../../uploads/' . $photo_path);
    }

    $response_data = json_encode([
        'customer_name' => $customer,
        'mobile'        => $mobile,
        'business_name' => $business,
        'category'      => $category,
        'gps_location'  => $gps,
        'shop_photo'    => $photo_path,
    ]);

    try {
        // Get first active form, or use 0
        $form_id = $GLOBALS['pdo']->query("SELECT id FROM forms WHERE status='active' LIMIT 1")->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("INSERT INTO form_responses
            (form_id, agent_id, customer_name, mobile, business_name, category, response_data, latitude, longitude, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$form_id, $agent_id, $customer, $mobile, $business, $category, $response_data, $lat, $lng]);

        $lead_id = $pdo->lastInsertId();
        $success = "Lead #FR-" . str_pad($lead_id, 4, '0', STR_PAD_LEFT) . " submitted successfully!";
    } catch (Exception $e) {
        $error = "Error saving lead: " . $e->getMessage();
    }
}
?>

<style>
.typeform-container {
    max-width: 800px;
    margin: 0 auto;
    min-height: 60vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.form-step { display: none; animation: fadeIn 0.4s ease; }
.form-step.active { display: block; }
@keyframes fadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
.typeform-input {
    width: 100%;
    background: transparent;
    border: none;
    border-bottom: 2px solid var(--border);
    font-size: 2rem;
    color: var(--text-main);
    padding: 1rem 0;
    margin-top: 1.5rem;
    transition: border-color 0.3s;
}
.typeform-input:focus { outline: none; border-color: var(--primary); }
.step-number { font-size: 1rem; color: var(--primary); font-weight: 700; margin-bottom: 0.5rem; }
.step-label  { font-size: 1.5rem; font-weight: 600; color: var(--text-main); }
.nav-buttons { margin-top: 3rem; display: flex; gap: 16px; align-items: center; }
.progress-bar-container { position: fixed; top: 0; left: 0; width: 100%; height: 4px; background: var(--border); z-index: 1001; }
.progress-bar { height: 100%; background: var(--primary); width: 0%; transition: width 0.4s ease; }
.category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
.category-card { padding: 1.25rem; border: 2px solid var(--border); border-radius: 12px; cursor: pointer; transition: all 0.2s; text-align: center; font-weight: 600; }
.category-card:hover, .category-card.selected { background: var(--primary); border-color: var(--primary); color: white; }
</style>

<?php if (isset($success)): ?>
<div class="alert alert-success" style="max-width:800px; margin:0 auto 2rem;">
    ✅ <?php echo $success; ?>
    <a href="leads.php" style="margin-left:1rem; color:var(--primary); font-weight:600;">View All Leads →</a>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger" style="max-width:800px; margin:0 auto 2rem;"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!isset($success)): ?>
<div class="progress-bar-container">
    <div class="progress-bar" id="form-progress"></div>
</div>

<div class="typeform-container">
    <form id="lead-form" method="POST" enctype="multipart/form-data">

        <!-- Step 1 -->
        <div class="form-step active" data-step="1">
            <div class="step-number">01 / 06</div>
            <div class="step-label">Customer ka full name kya hai?</div>
            <input type="text" name="customer_name" class="typeform-input" placeholder="Type name here..." autocomplete="off">
        </div>

        <!-- Step 2 -->
        <div class="form-step" data-step="2">
            <div class="step-number">02 / 06</div>
            <div class="step-label">Mobile number?</div>
            <input type="tel" name="mobile" class="typeform-input" placeholder="10-digit number" maxlength="10">
        </div>

        <!-- Step 3 -->
        <div class="form-step" data-step="3">
            <div class="step-number">03 / 06</div>
            <div class="step-label">Business / Shop ka naam?</div>
            <input type="text" name="business_name" class="typeform-input" placeholder="Shop / Company name">
        </div>

        <!-- Step 4 -->
        <div class="form-step" data-step="4">
            <div class="step-number">04 / 06</div>
            <div class="step-label">Business category select karo</div>
            <div class="category-grid">
                <div class="category-card" onclick="selectCategory('Retail',this)">🛒 Retail</div>
                <div class="category-card" onclick="selectCategory('Grocery',this)">🥦 Grocery</div>
                <div class="category-card" onclick="selectCategory('Medical',this)">💊 Medical</div>
                <div class="category-card" onclick="selectCategory('Restaurant',this)">🍽️ Restaurant</div>
                <div class="category-card" onclick="selectCategory('Electronics',this)">📱 Electronics</div>
                <div class="category-card" onclick="selectCategory('Others',this)">📦 Others</div>
            </div>
            <input type="hidden" name="category" id="category-input">
        </div>

        <!-- Step 5 -->
        <div class="form-step" data-step="5">
            <div class="step-number">05 / 06</div>
            <div class="step-label">GPS location tag karo</div>
            <div style="margin-top:1.5rem;">
                <button type="button" class="btn btn-primary" onclick="getLocation()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    Capture Location
                </button>
                <div id="location-display" style="margin-top:1rem; color:var(--success); font-weight:600;"></div>
                <input type="hidden" name="gps_location" id="gps-input">
                <button type="button" class="btn glass-card" onclick="skipStep()" style="margin-top:1rem; font-size:0.8rem;">Skip (No GPS)</button>
            </div>
        </div>

        <!-- Step 6 -->
        <div class="form-step" data-step="6">
            <div class="step-number">06 / 06</div>
            <div class="step-label">Shop front photo upload karo</div>
            <div style="margin-top:1.5rem;">
                <input type="file" name="shop_photo" accept="image/*" style="display:none;" id="shop-photo-input" onchange="showPreview(this)">
                <label for="shop-photo-input" class="btn glass-card" style="padding:2rem; border:2px dashed var(--border); width:100%; display:flex; flex-direction:column; align-items:center; gap:10px; cursor:pointer;">
                    <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                    <span>Click to Upload Photo (Optional)</span>
                </label>
                <div id="photo-preview" style="margin-top:1rem; text-align:center;"></div>
            </div>
        </div>

        <div class="nav-buttons">
            <button type="button" class="btn glass-card" id="prev-btn" style="display:none;" onclick="changeStep(-1)">← Back</button>
            <button type="button" class="btn btn-primary" id="next-btn" onclick="changeStep(1)">Next →</button>
            <button type="submit" class="btn btn-primary" id="submit-btn" style="display:none; background:var(--success);">✓ Submit Lead</button>
            <span style="font-size:0.8rem; color:var(--text-muted);">Press <kbd style="background:var(--border); padding:2px 6px; border-radius:4px;">Enter ↵</kbd></span>
        </div>
    </form>
</div>

<script>
let currentStep = 1;
const totalSteps = 6;

function changeStep(delta) {
    const next = currentStep + delta;
    if (next < 1 || next > totalSteps) return;
    document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.remove('active');
    document.querySelector(`.form-step[data-step="${next}"]`).classList.add('active');
    currentStep = next;
    updateUI();
}

function skipStep() { changeStep(1); }

function updateUI() {
    document.getElementById('form-progress').style.width = (currentStep / totalSteps * 100) + '%';
    document.getElementById('prev-btn').style.display   = currentStep > 1 ? 'inline-flex' : 'none';
    document.getElementById('next-btn').style.display   = currentStep < totalSteps ? 'inline-flex' : 'none';
    document.getElementById('submit-btn').style.display = currentStep === totalSteps ? 'inline-flex' : 'none';
}

function selectCategory(cat, el) {
    document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('category-input').value = cat;
    setTimeout(() => changeStep(1), 300);
}

function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            const loc = pos.coords.latitude + ', ' + pos.coords.longitude;
            document.getElementById('gps-input').value = loc;
            document.getElementById('location-display').textContent = '✓ Location: ' + loc;
            setTimeout(() => changeStep(1), 800);
        }, () => {
            document.getElementById('location-display').textContent = '⚠ GPS not available';
        });
    }
}

function showPreview(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('photo-preview').innerHTML =
                `<img src="${e.target.result}" style="max-width:180px; border-radius:10px; border:2px solid var(--success);">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        if (currentStep < totalSteps) changeStep(1);
    }
});

updateUI();
</script>
<?php endif; ?>

<?php include_once '../../includes/footer.php'; ?>
