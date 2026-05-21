<?php
include_once '../../core/config.php';

// Allow only admins/HR to export (assuming roles 1 and 7)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2, 7])) {
    die("Unauthorized Access");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=employees_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Team', 'Manager', 'Status', 'Created At']);

$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, r.role_name, t.team_name, m.name as manager_name, u.status, u.created_at
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN teams t ON u.team_id = t.id
    LEFT JOIN users m ON u.manager_id = m.id
    ORDER BY u.created_at DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['email'],
        $row['role_name'] ?: 'N/A',
        $row['team_name'] ?: 'N/A',
        $row['manager_name'] ?: 'N/A',
        $row['status'],
        $row['created_at']
    ]);
}
fclose($output);
exit();
?>
