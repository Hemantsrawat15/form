<?php
require_once __DIR__ . '/../cors.php';
require_once '../../vendor/autoload.php';
require_once '../config/database.php';
require_once '../config/jwt_handler.php';

// 1. Authenticate User
$jwt_handler = new JwtHandler();
$user_id = $jwt_handler->getUserId();
if (!$user_id) {
    http_response_code(401); header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

// 2. Get Data and Validate
$db = new Database();
$conn = $db->getConnection();
$data = json_decode(file_get_contents("php://input"), true);

$form_id = $data['form_id'] ?? 0;
$form_name = $data['form_name'] ?? '';
$submission_data = $data['submission_data'] ?? [];

if ($form_id <= 0) { /* ... validation error ... */ }

// 3. Fetch Original HTML Template
$stmt_fetch = $conn->prepare("SELECT form_template FROM forms WHERE id = ?");
$stmt_fetch->bind_param("i", $form_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
$form = $result->fetch_assoc();
if (!$form) { die("Form template not found."); }
$html_template = json_decode($form['form_template'])->html;

// 4. Inject Submitted Data using simple String Replacement (More reliable for this case)
$final_html = $html_template;

if (str_contains(strtolower($form_name), 'indent check list')) {
    // For the checklist, we can still use DOMDocument as it's a single table
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    $rows = $xpath->query('//table[contains(@class, "checklist")]//tr[td]');
    $rowIndex = 0;
    foreach ($rows as $row) {
        $rowIndex++;
        $cols = $xpath->query('td', $row);
        if ($cols->length >= 5) {
            if (isset($submission_data["row-{$rowIndex}-page"])) { $cols->item(2)->nodeValue = htmlspecialchars($submission_data["row-{$rowIndex}-page"]); }
            if (!empty($submission_data["row-{$rowIndex}-yes"])) { $cols->item(3)->nodeValue = '✓'; $cols->item(3)->setAttribute('style', 'font-family: DejaVu Sans; text-align:center;'); }
            if (!empty($submission_data["row-{$rowIndex}-no"])) { $cols->item(4)->nodeValue = '✓'; $cols->item(4)->setAttribute('style', 'font-family: DejaVu Sans; text-align:center;'); }
        }
    }
    $final_html = $doc->saveHTML();

} elseif (str_contains(strtolower($form_name), 'drona connectivity')) {
    // --- SIMPLER & MORE RELIABLE LOGIC FOR DRONA FORM ---
    // We will directly replace the placeholder spans and tds with the submitted data.
    
    // Replace the main input spans
    $simpleFieldIndex = 0;
    $final_html = preg_replace_callback(
        "/(<span class='input-span'><\/span>)/",
        function ($matches) use (&$simpleFieldIndex, $submission_data) {
            $simpleFieldIndex++;
            $inputName = "field-{$simpleFieldIndex}";
            $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
            return "<span class='input-span'>{$value}</span>";
        },
        $final_html
    );

    // Replace the QRS&IT table cells
    $qrsIndex = 0;
    $final_html = preg_replace_callback(
        "/(<table class='small-table'>.*?)(<td><\/td>)(.*?<\/tr>)/s",
        function ($matches) use (&$qrsIndex, $submission_data) {
            $qrsIndex++;
            $inputName = "qrs-field-{$qrsIndex}";
            $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '&nbsp;';
            return $matches[1] . "<td>{$value}</td>" . $matches[3];
        },
        $final_html,
        4 // Limit replacements to 4 for the 4 rows in this table
    );
}

// 5. Save Submission Record
// ... (code is correct, no changes needed)

// 6. Generate and Stream PDF
try {
    $default_font = 'dejavusans';
    if (str_contains(strtolower($form_name), 'drona connectivity')) {
        $default_font = 'times';
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => $default_font
    ]);

    // Give mPDF the final, modified HTML fragment. It will build the document itself.
    $mpdf->WriteHTML($final_html);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="submission-'.$submission_id.'.pdf"');
    $mpdf->Output();

} catch (\Mpdf\MpdfException $e) { /* ... error handling ... */ }
?>