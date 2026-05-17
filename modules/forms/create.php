<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

// Handle form submission (saving the form structure)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $fields = json_decode($_POST['fields_json'], true);

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO forms (title, description, created_by, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$title, $description, $_SESSION['user_id'] ?? 1]);
        $form_id = $pdo->lastInsertId();

        $stmtField = $pdo->prepare("INSERT INTO form_fields (form_id, field_label, field_type, field_options, is_required, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($fields as $index => $field) {
            $stmtField->execute([
                $form_id,
                $field['label'],
                $field['type'],
                isset($field['options']) ? json_encode($field['options']) : null,
                $field['required'] ? 1 : 0,
                $index
            ]);
        }

        $pdo->commit();
        $success = "Form created successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="header-actions" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Dynamic Form Builder</h1>
    <p style="color: var(--text-muted); font-size: 0.875rem;">Create custom forms for Lead Capture, Surveys, and Onboarding.</p>
</div>

<?php if (isset($success)): ?>
    <div class="glass-card" style="padding: 1rem; background: rgba(16, 185, 129, 0.2); color: var(--success); margin-bottom: 1.5rem; border-color: var(--success);">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem;">
    <!-- Form Builder Area -->
    <div class="glass-card" style="padding: 2rem;">
        <div style="margin-bottom: 2rem;">
            <input type="text" id="form-title" placeholder="Form Title (e.g., Merchant Onboarding)" style="width: 100%; font-size: 1.5rem; background: transparent; border: none; border-bottom: 2px solid var(--border); color: white; padding: 0.5rem 0; margin-bottom: 0.5rem;">
            <textarea id="form-description" placeholder="Add a description for this form..." style="width: 100%; background: transparent; border: none; color: var(--text-muted); resize: none;"></textarea>
        </div>

        <div id="fields-container" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Fields will be added here -->
            <div id="empty-state" style="text-align: center; padding: 3rem; border: 2px dashed var(--border); border-radius: 12px; color: var(--text-muted);">
                <p>No fields added yet. Click on the right sidebar to add fields.</p>
            </div>
        </div>

        <div style="margin-top: 3rem; display: flex; justify-content: flex-end;">
            <form id="save-form-data" method="POST">
                <input type="hidden" name="title" id="hidden-title">
                <input type="hidden" name="description" id="hidden-description">
                <input type="hidden" name="fields_json" id="hidden-fields">
                <button type="submit" name="save_form" class="btn btn-primary" onclick="prepareFormData()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Save & Publish Form
                </button>
            </form>
        </div>
    </div>

    <!-- Field Toolset -->
    <div class="glass-card" style="padding: 1.5rem; height: fit-content; position: sticky; top: 2rem;">
        <h3 style="font-size: 1rem; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Add Fields</h3>
        
        <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
            <button class="btn glass-card field-tool" data-type="text" style="justify-content: flex-start; background: rgba(255,255,255,0.05);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7V4h16v3M9 20h6M12 4v16"></path></svg>
                Short Text
            </button>
            <button class="btn glass-card field-tool" data-type="number" style="justify-content: flex-start; background: rgba(255,255,255,0.05);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H7M17 19H7M12 2l-2 2M12 22l2-2"></path></svg>
                Number
            </button>
            <button class="btn glass-card field-tool" data-type="dropdown" style="justify-content: flex-start; background: rgba(255,255,255,0.05);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"></path></svg>
                Dropdown
            </button>
            <button class="btn glass-card field-tool" data-type="file" style="justify-content: flex-start; background: rgba(255,255,255,0.05);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"></path></svg>
                File Upload
            </button>
            <button class="btn glass-card field-tool" data-type="photo" style="justify-content: flex-start; background: rgba(255,255,255,0.05);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                Photo Capture
            </button>
            <button class="btn glass-card field-tool" data-type="location" style="justify-content: flex-start; background: rgba(255,255,255,0.05);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                GPS Location
            </button>
            <button class="btn glass-card field-tool" data-type="signature" style="justify-content: flex-start; background: rgba(255,255,255,0.05);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 20h16M7 14l5-5 5 5"></path></svg>
                Signature
            </button>
        </div>
    </div>
</div>

<script>
let fields = [];

document.querySelectorAll('.field-tool').forEach(button => {
    button.onclick = function() {
        const type = this.getAttribute('data-type');
        addField(type);
    };
});

function addField(type) {
    document.getElementById('empty-state').style.display = 'none';
    
    const id = Date.now();
    const field = {
        id: id,
        type: type,
        label: `New ${type.charAt(0).toUpperCase() + type.slice(1)} Field`,
        required: false,
        options: type === 'dropdown' ? ['Option 1', 'Option 2'] : null
    };
    
    fields.push(field);
    renderFields();
}

function renderFields() {
    const container = document.getElementById('fields-container');
    const emptyState = document.getElementById('empty-state');
    
    if (fields.length === 0) {
        emptyState.style.display = 'block';
        container.innerHTML = '';
        container.appendChild(emptyState);
        return;
    }

    container.innerHTML = '';
    fields.forEach((field, index) => {
        const fieldEl = document.createElement('div');
        fieldEl.className = 'glass-card field-item';
        fieldEl.style.padding = '1.25rem';
        fieldEl.style.borderLeft = '4px solid var(--primary)';
        
        let optionsHtml = '';
        if (field.type === 'dropdown') {
            optionsHtml = `
                <div style="margin-top: 1rem;">
                    <label style="font-size: 0.75rem; color: var(--text-muted);">Options (comma separated)</label>
                    <input type="text" value="${field.options.join(', ')}" onchange="updateOptions(${index}, this.value)" style="width: 100%; background: var(--bg-main); border: 1px solid var(--border); border-radius: 6px; padding: 0.5rem; color: white; margin-top: 4px;">
                </div>
            `;
        }

        fieldEl.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div style="flex: 1;">
                    <input type="text" value="${field.label}" onchange="updateLabel(${index}, this.value)" style="width: 100%; background: transparent; border: none; color: white; font-weight: 600; font-size: 1rem; margin-bottom: 0.5rem;" placeholder="Field Label">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Type: ${field.type}</span>
                        <label style="display: flex; align-items: center; gap: 5px; font-size: 0.75rem; color: var(--text-muted); cursor: pointer;">
                            <input type="checkbox" ${field.required ? 'checked' : ''} onchange="updateRequired(${index}, this.checked)"> Required
                        </label>
                    </div>
                </div>
                <button onclick="removeField(${index})" style="background: none; border: none; color: var(--danger); cursor: pointer;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </button>
            </div>
            ${optionsHtml}
        `;
        container.appendChild(fieldEl);
    });
}

function updateLabel(index, value) { fields[index].label = value; }
function updateRequired(index, value) { fields[index].required = value; }
function updateOptions(index, value) { fields[index].options = value.split(',').map(s => s.trim()); }
function removeField(index) { fields.splice(index, 1); renderFields(); }

function prepareFormData() {
    document.getElementById('hidden-title').value = document.getElementById('form-title').value;
    document.getElementById('hidden-description').value = document.getElementById('form-description').value;
    document.getElementById('hidden-fields').value = JSON.stringify(fields);
}
</script>

<style>
.field-item:hover {
    border-color: var(--primary);
    background: rgba(255,255,255,0.02);
}
.field-tool:hover {
    background: var(--primary) !important;
    color: white !important;
}
</style>

<?php include_once '../../includes/footer.php'; ?>
