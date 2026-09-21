<?php
// modules/api_export/export_csv.php
if (!defined('SITE_URL')) die('Direct access denied.');
require_once __DIR__ . '/../../admin/auth.php'; // ensure admin

global $pdo;

$filename = "Export_Collection_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Write BOM for Excel UTF-8 display
fputs($output, "\xEF\xBB\xBF");

// Define Headers
$headers = [
    'ID', 'Reg Number', 'Title', 'Production Date', 
    'Year Start', 'Year End', 'Material', 'Category ID', 
    'Physical Description', 'Historical Significance',
    'Credit Line', 'Is Visible'
];
fputcsv($output, $headers);

// Stream data query
$stmt = $pdo->query("SELECT id, reg_number, title, production_date, year_start, year_end, material, category_id, physical_description, historical_significance, credit_line, is_visible FROM items ORDER BY id ASC");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Clean HTML from rich text fields
    $row['physical_description'] = strip_tags($row['physical_description'] ?? '');
    $row['historical_significance'] = strip_tags($row['historical_significance'] ?? '');
    
    fputcsv($output, [
        $row['id'],
        $row['reg_number'],
        $row['title'],
        $row['production_date'],
        $row['year_start'],
        $row['year_end'],
        $row['material'],
        $row['category_id'],
        $row['physical_description'],
        $row['historical_significance'],
        $row['credit_line'],
        $row['is_visible']
    ]);
}

fclose($output);
exit;
