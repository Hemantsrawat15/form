<?php
require_once __DIR__ . '/../cors.php';
require_once '../../vendor/autoload.php';
require_once '../config/database.php';
require_once '../config/jwt_handler.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 1. Authenticate User
$jwt_handler = new JwtHandler();
$user_id = $jwt_handler->getUserId();
if (!$user_id) {
    http_response_code(401);
    header('Content-Type: application/json');
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

if ($form_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Invalid form ID"]);
    exit();
}

// 3. Fetch Original HTML Template
$stmt_fetch = $conn->prepare("SELECT form_template FROM forms WHERE id = ?");
$stmt_fetch->bind_param("i", $form_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
$form = $result->fetch_assoc();
if (!$form) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Form template not found"]);
    exit();
}
$html_template = json_decode($form['form_template'])->html;

// 4. Handle photo - compress and resize to EXACT passport photo dimensions
$photo_base64_for_pdf = null;

if (isset($submission_data['photo']) && !empty($submission_data['photo'])) {
    $original_photo = $submission_data['photo'];
    
    // Extract image data
    if (preg_match('/^data:image\/(\w+);base64,/', $original_photo, $type)) {
        $photo_data = substr($original_photo, strpos($original_photo, ',') + 1);
        $photo_data = base64_decode($photo_data);
        
        // Create image from string
        $image = imagecreatefromstring($photo_data);
        
        if ($image !== false) {
            // Get original dimensions
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Target dimensions for passport photo box
            $target_width = 120;
            $target_height = 140;
            
            // Calculate crop dimensions to maintain aspect ratio
            $source_aspect = $width / $height;
            $target_aspect = $target_width / $target_height;
            
            if ($source_aspect > $target_aspect) {
                // Image is wider - crop width
                $new_width = (int)($height * $target_aspect);
                $new_height = $height;
                $src_x = (int)(($width - $new_width) / 2);
                $src_y = 0;
            } else {
                // Image is taller - crop height
                $new_width = $width;
                $new_height = (int)($width / $target_aspect);
                $src_x = 0;
                $src_y = (int)(($height - $new_height) / 2);
            }
            
            // Create new image with exact target dimensions
            $new_image = imagecreatetruecolor($target_width, $target_height);
            
            // Copy and resize cropped portion to exact dimensions
            imagecopyresampled(
                $new_image, 
                $image, 
                0, 0,                          // Destination x, y
                $src_x, $src_y,                // Source x, y
                $target_width, $target_height, // Destination width, height
                $new_width, $new_height        // Source width, height
            );
            
            // Capture compressed image as JPEG with quality 70
            ob_start();
            imagejpeg($new_image, null, 70);
            $compressed_data = ob_get_clean();
            
            // Convert back to base64
            $compressed_base64 = 'data:image/jpeg;base64,' . base64_encode($compressed_data);
            
            // Use compressed version for PDF
            $photo_base64_for_pdf = $compressed_base64;
            
            // Store compressed version in database too
            $submission_data['photo'] = $compressed_base64;
            
            // Free memory
            imagedestroy($image);
            imagedestroy($new_image);
        } else {
            // If compression fails, use original
            $photo_base64_for_pdf = $original_photo;
        }
    }
}

// 5. Inject Submitted Data into HTML
$final_html = $html_template;

if (str_contains(strtolower($form_name), 'indent check list')) {
    // Handle Indent Check List Form
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    $rows = $xpath->query('//table[contains(@class, "checklist")]//tr[td]');
    $rowIndex = 0;
    
    foreach ($rows as $row) {
        $rowIndex++;
        $cols = $xpath->query('td', $row);
        if ($cols->length >= 5) {
            if (isset($submission_data["row-{$rowIndex}-page"])) {
                $cols->item(2)->nodeValue = htmlspecialchars($submission_data["row-{$rowIndex}-page"]);
            }
            if (!empty($submission_data["row-{$rowIndex}-yes"])) {
                $cols->item(3)->nodeValue = '✓';
                $cols->item(3)->setAttribute('style', 'font-family: DejaVu Sans; text-align:center;');
            }
            if (!empty($submission_data["row-{$rowIndex}-no"])) {
                $cols->item(4)->nodeValue = '✓';
                $cols->item(4)->setAttribute('style', 'font-family: DejaVu Sans; text-align:center;');
            }
        }
    }
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'drona connectivity')) {
    // Handle DRONA Connectivity Form - UPDATED TO USE DOM PARSER FOR 'field' CLASS
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    // Handle regular fields (span with class 'field')
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[contains(@class, 'field')]");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        // Clear existing content
        while ($span->firstChild) {
            $span->removeChild($span->firstChild);
        }
        
        if ($value !== '') {
            $span->appendChild($doc->createTextNode($value));
        }
    }
    
    // Handle textarea fields if any
    $textAreaIndex = 0;
    $textAreaDivs = $xpath->query("//div[contains(@class, 'text-area-box')]");
    foreach ($textAreaDivs as $div) {
        $textAreaIndex++;
        $inputName = "textarea-{$textAreaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'lan connectivity')) {
    // Handle LAN Connectivity Form (Updated to support both old and new style)
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    // Support both 'field' and 'input-span' for LAN forms
    $fieldSpans = $xpath->query("//span[contains(@class, 'field')] | //span[contains(@class, 'input-span')]");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($span->firstChild) {
            $span->removeChild($span->firstChild);
        }
        
        if ($value !== '') {
            $span->appendChild($doc->createTextNode($value));
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'disconnection')) {
    // Handle Disconnection Form
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[@class='field']");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $textAreaIndex = 0;
    $textAreaDivs = $xpath->query("//div[@class='text-area-box']");
    foreach ($textAreaDivs as $div) {
        $textAreaIndex++;
        $inputName = "textarea-{$textAreaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'home town') || str_contains(strtolower($form_name), 'change of home')) {
    // Handle Change of Home Town Form
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[@class='field']");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $textareaIndex = 0;
    $textareaDivs = $xpath->query("//div[@class='textarea-field']");
    foreach ($textareaDivs as $div) {
        $textareaIndex++;
        $inputName = "textarea-{$textareaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'permanent identity') || str_contains(strtolower($form_name), 'identity card')) {
    // Handle Permanent Identity Card Application Form
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[@class='field']");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $textareaIndex = 0;
    $textareaDivs = $xpath->query("//div[@class='textarea-field']");
    foreach ($textareaDivs as $div) {
        $textareaIndex++;
        $inputName = "textarea-{$textareaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    if ($photo_base64_for_pdf) {
        $photoBoxes = $xpath->query("//div[@id='photo-upload-box']");
        if ($photoBoxes->length > 0) {
            $photoBox = $photoBoxes->item(0);
            while ($photoBox->firstChild) {
                $photoBox->removeChild($photoBox->firstChild);
            }
            $photoBox->setAttribute('style', 'float: right; width: 100px; height: 120px; border: 2px solid #000; margin-left: 15px; margin-bottom: 10px; overflow: hidden; font-size: 0; line-height: 0;');
            $imgHtml = '<img src="' . $photo_base64_for_pdf . '" width="100" height="120" style="display: block; border: none;" />';
            $imgFragment = $doc->createDocumentFragment();
            $imgFragment->appendXML($imgHtml);
            $photoBox->appendChild($imgFragment);
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'student trainee') || str_contains(strtolower($form_name), 'trainee at cfees')) {
    // Handle Student Trainee Form
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[@class='field']");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $textareaIndex = 0;
    $textareaDivs = $xpath->query("//div[@class='textarea-field']");
    foreach ($textareaDivs as $div) {
        $textareaIndex++;
        $inputName = "textarea-{$textareaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'pensioners') || str_contains(strtolower($form_name), 'pensioner')) {
    // Handle Pensioners Form
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[@class='field']");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $textareaIndex = 0;
    $textareaDivs = $xpath->query("//div[@class='textarea-field']");
    foreach ($textareaDivs as $div) {
        $textareaIndex++;
        $inputName = "textarea-{$textareaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'leave application - director approval') || str_contains(strtolower($form_name), 'अवकाश')) {
    // Handle Hindi Leave Application
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[contains(@class, 'field')]");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $textareaIndex = 0;
    $textareaDivs = $xpath->query("//div[@class='textarea-field']");
    foreach ($textareaDivs as $div) {
        $textareaIndex++;
        $inputName = "textarea-{$textareaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'leave application')) {
    // Handle English Leave Application Form
    
    $fieldIndex = 0;
    $final_html = preg_replace_callback(
        "/<span class='field'(.*?)><\/span>/",
        function ($matches) use (&$fieldIndex, $submission_data) {
            $fieldIndex++;
            $inputName = "field-{$fieldIndex}";
            $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
            return "<span class='field'{$matches[1]}>{$value}</span>";
        },
        $final_html
    );

} elseif (str_contains(strtolower($form_name), 'cars blank format')) {
    // Handle DRDO Forms Collection
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[@class='field']");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $textareaIndex = 0;
    $textareaDivs = $xpath->query("//div[@class='textarea-field']");
    foreach ($textareaDivs as $div) {
        $textareaIndex++;
        $inputName = "textarea-{$textareaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'detention certificate') || 
          str_contains(strtolower($form_name), 'detention') ||
          str_contains(strtolower($form_name), 'डिटेन्शन')) {
    // Handle Detention Certificate
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[contains(@class, 'field')]");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'temporary duty') || str_contains(strtolower($form_name), 'move claim')) {
    // Handle Claim for Move on Temporary Duty
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[contains(@class, 'field')]");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    $textareaIndex = 0;
    $textareaDivs = $xpath->query("//div[@class='textarea-field']");
    foreach ($textareaDivs as $div) {
        $textareaIndex++;
        $inputName = "textarea-{$textareaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

} elseif (str_contains(strtolower($form_name), 'registration') || 
          str_contains(strtolower($form_name), 'aebas') ||
          str_contains(strtolower($form_name), 'biometric') ||
          str_contains(strtolower($form_name), 'e-portal')) {
    // Handle Registration Forms
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[@class='field']");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        $span->nodeValue = $value;
    }
    
    if ($photo_base64_for_pdf) {
        $photoBoxes = $xpath->query("//div[@id='photo-upload-box']");
        if ($photoBoxes->length > 0) {
            $photoBox = $photoBoxes->item(0);
            while ($photoBox->firstChild) {
                $photoBox->removeChild($photoBox->firstChild);
            }
            $photoBox->setAttribute('style', 'float: right; width: 120px; height: 140px; border: 1px solid #000; margin-left: 15px; overflow: hidden; font-size: 0; line-height: 0;');
            $imgHtml = '<img src="' . $photo_base64_for_pdf . '" width="120" height="140" style="display: block; border: none;" />';
            $imgFragment = $doc->createDocumentFragment();
            $imgFragment->appendXML($imgHtml);
            $photoBox->appendChild($imgFragment);
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);

}
elseif (str_contains(strtolower($form_name), 'cars') || 
          str_contains(strtolower($form_name), 'retaining facilities') ||
          str_contains(strtolower($form_name), 'equipment procured')) {
    // Handle CARS Retention Certificate Form
    
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    $fieldIndex = 0;
    $fieldSpans = $xpath->query("//span[@class='field']");
    foreach ($fieldSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($span->firstChild) {
            $span->removeChild($span->firstChild);
        }
        if ($value !== '') {
            $span->appendChild($doc->createTextNode($value));
        }
    }
    
    $textareaIndex = 0;
    $textareaDivs = $xpath->query("//div[@class='textarea-field']");
    foreach ($textareaDivs as $div) {
        $textareaIndex++;
        $inputName = "textarea-{$textareaIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($div->firstChild) {
            $div->removeChild($div->firstChild);
        }
        if ($value) {
            $lines = explode("\n", $value);
            foreach ($lines as $i => $line) {
                $div->appendChild($doc->createTextNode($line));
                if ($i < count($lines) - 1) {
                    $div->appendChild($doc->createElement('br'));
                }
            }
        }
    }
    
    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);
}
elseif (str_contains(strtolower($form_name), 'no objection certificate') || 
        str_contains(strtolower($form_name), 'noc') ||
        str_contains(strtolower($form_name), 'proceeding abroad') || 
        str_contains(strtolower($form_name), 'passport')) {
    // Handle No Objection Certificate for Passport/Proceeding Abroad Form

    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);

    $pinIndex = 0;
    $boxesContainers = $xpath->query("//span[@class='boxes']");
    foreach ($boxesContainers as $boxesContainer) {
        $pinBoxes = $xpath->query(".//span", $boxesContainer);
        foreach ($pinBoxes as $pinBox) {
            $pinIndex++;
            $inputName = "pin-{$pinIndex}";
            $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
            
            while ($pinBox->firstChild) {
                $pinBox->removeChild($pinBox->firstChild);
            }
            if ($value !== '') {
                $pinBox->appendChild($doc->createTextNode($value));
            }
        }
    }

    $fieldIndex = 0;
    $lineSpans = $xpath->query("//span[contains(@class, 'line')]");
    foreach ($lineSpans as $span) {
        $fieldIndex++;
        $inputName = "field-{$fieldIndex}";
        $value = isset($submission_data[$inputName]) ? htmlspecialchars($submission_data[$inputName]) : '';
        
        while ($span->firstChild) {
            $span->removeChild($span->firstChild);
        }
        if ($value !== '') {
            $span->appendChild($doc->createTextNode($value));
        }
    }

    $final_html = $doc->saveHTML();
    $final_html = str_replace('<?xml encoding="utf-8" ?>', '', $final_html);
}

// 6. Save Submission Record (with compressed photo)
$submission_data_json = json_encode($submission_data);
$stmt_insert = $conn->prepare("INSERT INTO form_submissions (form_id, user_id, submission_data, submitted_at) VALUES (?, ?, ?, NOW())");
$stmt_insert->bind_param("iis", $form_id, $user_id, $submission_data_json);

if (!$stmt_insert->execute()) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Failed to save submission"]);
    exit();
}

$submission_id = $stmt_insert->insert_id;
$stmt_insert->close();

// 7. Generate and Stream PDF with Hindi/Devanagari support
try {
    $hasHindi = (
        str_contains(strtolower($form_name), 'detention') ||
        str_contains(strtolower($form_name), 'डिटेन्शन') ||
        preg_match('/[\x{0900}-\x{097F}]/u', $final_html)
    );
    
    $default_font = 'dejavusans';
    
    $isDetentionCert = str_contains(strtolower($form_name), 'detention');
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => $default_font,
        'margin_left' => $isDetentionCert ? 28 : 15,
        'margin_right' => $isDetentionCert ? 28 : 15,
        'margin_top' => $isDetentionCert ? 25 : 15,
        'margin_bottom' => $isDetentionCert ? 25 : 15,
        'tempDir' => sys_get_temp_dir() . '/mpdf',
        'img_dpi' => 96,
        'autoScriptToLang' => true,
        'autoLangToFont' => true
    ]);

    if (ob_get_length()) {
        ob_end_clean();
    }

    $mpdf->WriteHTML($final_html);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="submission-' . $submission_id . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    $mpdf->Output('', 'I');

} catch (\Mpdf\MpdfException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "PDF generation failed: " . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    exit();
}
?>