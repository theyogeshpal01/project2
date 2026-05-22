<?php
/**
 * Global Helper Functions
 */

/**
 * Redirect to a specific URL
 */
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

/**
 * Get all roles (Roles might be global for all companies, or we can filter if needed. Let's keep roles global for now)
 */
function getRoles($pdo) {
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
    return $stmt->fetchAll();
}

/**
 * Get all teams with manager info
 */
function getTeams($pdo) {
    $company_id = $_SESSION['company_id'] ?? 1;
    $sql = "SELECT t.*, u.name as manager_name 
            FROM teams t 
            LEFT JOIN users u ON t.manager_id = u.id 
            WHERE t.company_id = $company_id
            ORDER BY t.created_at DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Format Currency
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Get Status Badge HTML
 */
function getStatusBadge($status) {
    $class = 'primary';
    switch ($status) {
        case 'qc_approved':
        case 'active':
        case 'verified':
            $class = 'success';
            break;
        case 'pending':
        case 'in_process':
            $class = 'warning';
            break;
        case 'rejected':
        case 'inactive':
            $class = 'danger';
            break;
    }
    return "<span class='badge badge-{$class}'>{$status}</span>";
}

/**
 * Get all leads with agent info
 */
function getLeads($pdo, $agent_id = null) {
    $company_id = $_SESSION['company_id'] ?? 1;
    $sql = "SELECT l.*, u.name as agent_name 
            FROM leads l 
            LEFT JOIN users u ON l.agent_id = u.id
            WHERE l.company_id = :company_id";
    
    if ($agent_id) {
        $sql .= " AND l.agent_id = :agent_id";
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':company_id', $company_id);
    if ($agent_id) {
        $stmt->bindParam(':agent_id', $agent_id);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Generate Lead ID
 */
function generateLeadID() {
    return 'LD-' . strtoupper(substr(uniqid(), -6));
}
?>
