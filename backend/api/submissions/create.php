<?php
// This includes the mPDF library.
require_once '../../vendor/autoload.php';
// This includes our database connection class.
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get the data posted from React: { form_id, user_id, submission_data: {...} }
$data = json_decode(file_get_contents("php://input"), true);

$form_id = $data['form_id'] ?? 0;
$user_id = $data['user_id'] ?? 1; // Default to user 1 for testing
$submission_data = $data['submission_data'] ?? [];

// === Part 1: Get the Original Form's HTML Template ===
$stmt_fetch = $conn->prepare("SELECT form_template FROM forms WHERE id = ?");
$stmt_fetch->bind_param("i", $form_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
$form = $result->fetch_assoc();

if (!$form) {
    http_response_code(404);
    echo json_encode(["message" => "Form template not found."]);
    exit();
}
$template_obj = json_decode($form['form_template']);
$html_template = $template_obj->html;

// === Part 2: Inject the User's Data into the HTML ===
$doc = new DOMDocument();
// Load the HTML. The '@' suppresses warnings from potentially imperfect HTML.
@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
$xpath = new DOMXPath($doc);
// Find all table rows `<tr>` that contain table data cells `<td>`. This cleverly skips header rows.
$rows = $xpath->query('//table[contains(@class, "checklist")]//tr[td]');

$rowIndex = 0;
foreach ($rows as $row) {
    $rowIndex++;
    $cols = $xpath->query('td', $row);

    if ($cols->length >= 4) { // Safety check
        // Fill 'Page No.' column (3rd cell, index 2)
        $page_key = "row-{$rowIndex}-page";
        if (isset($submission_data[$page_key])) {
            $cols->item(2)->nodeValue = htmlspecialchars($submission_data[$page_key]);
        }

        // Fill 'Yes' column (4th cell, index 3)
        $yes_key = "row-{$rowIndex}-yes";
        if (!empty($submission_data[$yes_key])) {
            $cols->item(3)->nodeValue = '✓'; // Use a simple checkmark character.
            $cols->item(3)->setAttribute('style', 'font-family: DejaVu Sans; text-align:center;');
        }

        // Fill 'No' column (5th cell, index 4)
        $no_key = "row-{$rowIndex}-no";
        if (!empty($submission_data[$no_key])) {
            $cols->item(4)->nodeValue = '✓';
            $cols->item(4)->setAttribute('style', 'font-family: DejaVu Sans; text-align:center;');
        }
    }
}
// Get the final, data-filled HTML string.
$final_html = $doc->saveHTML();

// === Part 3: Generate the PDF using mPDF ===
try {
    $mpdf = new \Mpdf\Mpdf();

    // This is the magic line. mPDF reads the HTML, including the <style> block,
    // and renders it into a PDF, respecting all your CSS and page breaks.
    $mpdf->WriteHTML($final_html);

    // Tell the browser that the response is a PDF file.
    header('Content-Type: application/pdf');
    // 'inline' tells the browser to display it, 'attachment' would force a download.
    header('Content-Disposition: inline; filename="generated-form.pdf"');

    // Output the generated PDF directly to the browser.
    $mpdf->Output();

} catch (\Mpdf\MpdfException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Failed to generate PDF.", "error" => $e->getMessage()]);
}

$conn->close();
?>