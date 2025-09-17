<?php
date_default_timezone_set('America/Chicago');
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle form submission to create/update event
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set timezone to CST
    date_default_timezone_set('America/Chicago');
    
    // Function to combine date parts and AM/PM into datetime string
    function combineDateTime($month, $day, $year, $time, $ampm) {
        if (empty($month) || empty($day) || empty($year) || empty($time)) {
            return '';
        }
        return DateTime::createFromFormat(
            'm/d/Y h:i A', 
            sprintf('%02d/%02d/%04d %s %s', $month, $day, $year, $time, $ampm),
            new DateTimeZone('America/Chicago')
        )->format('Y-m-d H:i:s');
    }

    $start_date = combineDateTime(
        $_POST['start_date_month'] ?? '',
        $_POST['start_date_day'] ?? '',
        $_POST['start_date_year'] ?? '',
        $_POST['start_date_time'] ?? '',
        $_POST['start_date_ampm'] ?? 'AM'
    );

    $end_date = combineDateTime(
        $_POST['end_date_month'] ?? '',
        $_POST['end_date_day'] ?? '',
        $_POST['end_date_year'] ?? '',
        $_POST['end_date_time'] ?? '',
        $_POST['end_date_ampm'] ?? 'AM'
    );

    $eventData = [
        'title' => $_POST['event_title'] ?? '',
        'subtitle' => $_POST['event_subtitle'] ?? '',
        'start_date' => $start_date,
        'end_date' => $end_date,
        'banner_image' => '',
        'rules' => array_filter($_POST['rules'] ?? []),
        'ticket_methods' => [],
        'prizes' => [],
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];

    
    // Handle ticket methods
    if (isset($_POST['method_name'])) {
        foreach ($_POST['method_name'] as $index => $name) {
            if (!empty($name)) {
                $eventData['ticket_methods'][] = [
                    'name' => $name,
                    'wager_amount' => (int)($_POST['method_wager'][$index] ?? 100),
                    'tickets_earned' => (int)($_POST['method_tickets'][$index] ?? 1)
                ];
            }
        }
    }
    
    // Handle prizes
    if (isset($_POST['prize_name'])) {
        foreach ($_POST['prize_name'] as $index => $name) {
            if (!empty($name) && !empty($_POST['prize_value'][$index])) {
                $prizeImage = '';
                
                // Handle prize image upload
                if (isset($_FILES['prize_image']['name'][$index])) {
                    $prizeImage = handlePrizeImageUpload($index);
                }
                
                // If no new image uploaded, keep existing one
                if (empty($prizeImage)) {
                    $existingData = [];
                    if (file_exists('current_event.json')) {
                        $existingData = json_decode(file_get_contents('current_event.json'), true) ?: [];
                    }
                    if (isset($existingData['prizes'][$index]['image'])) {
                        $prizeImage = $existingData['prizes'][$index]['image'];
                    }
                }
                
                $eventData['prizes'][] = [
                    'position' => $index + 1,
                    'name' => $name,
                    'value' => $_POST['prize_value'][$index],
                    'image' => $prizeImage
                ];
            }
        }
    }
    
    // Handle banner image upload
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['banner_image']['name']);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadPath)) {
            $eventData['banner_image'] = 'uploads/' . $fileName;
        }
    } else {
        // Keep existing banner if no new one uploaded
        $existingData = [];
        if (file_exists('current_event.json')) {
            $existingData = json_decode(file_get_contents('current_event.json'), true) ?: [];
        }
        $eventData['banner_image'] = $existingData['banner_image'] ?? '';
    }
    
    // Save event data to JSON file (in production, save to database)
    file_put_contents('current_event.json', json_encode($eventData, JSON_PRETTY_PRINT));
    
    $success = "Event created/updated successfully!";
}

function handlePrizeImageUpload($index) {
    if ($_FILES['prize_image']['error'][$index] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/prizes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['prize_image']['name'][$index]);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['prize_image']['tmp_name'][$index], $uploadPath)) {
            return 'uploads/prizes/' . $fileName;
        }
    }
    return '';
}

// Load existing event data
$eventData = [];
if (file_exists('current_event.json')) {
    $eventData = json_decode(file_get_contents('current_event.json'), true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TXPLAY - Event Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow-x: hidden;
            background: linear-gradient(180deg, #000000 0%, #1a0b2e 50%, #000000 100%);
            color: white;
            min-height: 100vh;
            position: relative;
        }

        /* Half Circle Image Container */
        .half-circle-image {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            opacity: 20%;
            max-width: 100%;
            height: 400px;
            overflow: hidden;
            z-index: 5;
        }

        .half-circle-image::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 200%;
            height: 800px;
            border-radius: 50%;
            background: url('background.png') center/cover no-repeat;
            clip-path: ellipse(100% 100% at 50% 0%);
        }

        /* Animated Background */
        .background-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        }

        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .bg-orb:nth-child(1) {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, #a855f7, #ec4899);
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .bg-orb:nth-child(2) {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #ec4899, #3b82f6);
            top: 40%;
            right: 15%;
            animation-delay: -2s;
        }

        .bg-orb:nth-child(3) {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, #3b82f6, #a855f7);
            bottom: 20%;
            left: 50%;
            animation-delay: -4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-20px) scale(1.1); }
        }

        /* Container */
        .container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            margin-left: 72px;
        }

        /* Header with Logout Button */
        .header {
            position: fixed;
            top: 0;
            right: 0;
            padding: 24px;
            z-index: 100;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
            background: linear-gradient(135deg, #f87171, #ef4444);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 72px;
            height: 100vh;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(148, 163, 184, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 0;
            z-index: 50;
        }

        .sidebar-logo {
            font-size: 18px;
            font-weight: 900;
            background: linear-gradient(135deg, #06b6d4, #f97316, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 32px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 16px;
            flex-grow: 1;
        }

        .sidebar-icon {
            position: relative;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-icon:hover {
            background: rgba(51, 65, 85, 0.7);
        }

        .sidebar-icon.active {
            background: rgba(168, 85, 247, 0.2);
            border: 1px solid rgba(168, 85, 247, 0.5);
        }

        .sidebar-icon svg {
            width: 20px;
            height: 20px;
            stroke: #9ca3af;
            transition: stroke 0.3s ease;
        }

        .sidebar-icon:hover svg,
        .sidebar-icon.active svg {
            stroke: white;
        }

        /* Tooltip */
        .tooltip {
            position: absolute;
            left: 60px;
            background: rgba(31, 41, 55, 0.95);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        .sidebar-icon:hover .tooltip {
            opacity: 1;
        }

        .sidebar-expand {
            margin-top: auto;
        }

        /* Admin Dashboard Content */
        .admin-container {
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .admin-title {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #06b6d4, #f97316, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Event Management Specific Styles */
        .event-management {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.8);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title svg {
            width: 24px;
            height: 24px;
            color: #a855f7;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            color: #9ca3af;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(51, 65, 85, 0.8);
            border-radius: 8px;
            padding: 12px 16px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.3);
        }

        .form-control::placeholder {
            color: #64748b;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            opacity: 0;
            position: absolute;
            z-index: -1;
        }

        .file-input-label {
            display: block;
            width: 100%;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(51, 65, 85, 0.8);
            border-radius: 8px;
            padding: 12px 16px;
            color: #9ca3af;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .file-input-label:hover {
            border-color: #a855f7;
            background: rgba(168, 85, 247, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ec4899, #a855f7);
            color: white;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(236, 72, 153, 0.4);
            background: linear-gradient(135deg, #f472b6, #c084fc);
        }

        .btn-secondary {
            background: rgba(51, 65, 85, 0.8);
            color: white;
            border: 1px solid rgba(71, 85, 105, 0.8);
        }

        .btn-secondary:hover {
            background: rgba(71, 85, 105, 0.8);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #f87171, #ef4444);
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        /* Rules and Prizes Dynamic Lists */
        .dynamic-list {
            background: rgba(51, 65, 85, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .dynamic-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .dynamic-item:last-child {
            margin-bottom: 0;
        }

        .dynamic-item input {
            flex: 1;
        }

        .dynamic-item .btn {
            padding: 8px 12px;
            font-size: 0.875rem;
        }

        /* Ticket Methods Grid */
        .ticket-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .ticket-method-card {
            background: rgba(51, 65, 85, 0.3);
            border-radius: 12px;
            padding: 20px;
            border: 2px solid rgba(51, 65, 85, 0.5);
            transition: all 0.3s ease;
            position: relative;
        }

        .remove-method {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(239, 68, 68, 0.2);
            border: none;
            color: #ef4444;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-method:hover {
            background: rgba(239, 68, 68, 0.4);
            color: white;
        }

        .method-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .method-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            flex: 1;
        }

        .method-inputs {
            display: grid;
            gap: 12px;
        }

        .method-inputs .form-group {
            margin-bottom: 0;
        }

        /* Prize Management */
        .prize-item {
            background: rgba(51, 65, 85, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid rgba(71, 85, 105, 0.5);
            position: relative;
        }

        .remove-prize {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(239, 68, 68, 0.2);
            border: none;
            color: #ef4444;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-prize:hover {
            background: rgba(239, 68, 68, 0.4);
            color: white;
        }

        .prize-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .prize-number {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .prize-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 16px;
        }

        /* Prize Image Preview */
        .prize-image-preview {
            max-width: 100px;
            max-height: 100px;
            margin-top: 8px;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                margin-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .admin-container {
                padding: 24px;
            }

            .admin-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .ticket-methods-grid {
                grid-template-columns: 1fr;
            }

            .prize-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<script>
    // Update file input label
    function updateFileName(input) {
        const label = document.getElementById('banner_label');
        if (input.files.length > 0) {
            label.textContent = `📁 ${input.files[0].name}`;
            label.style.color = '#10b981';
        } else {
            label.textContent = '📁 Choose banner image (JPG, PNG, SVG)';
            label.style.color = '#9ca3af';
        }
    }

    // Update prize file input label and preview
    function updatePrizeFileName(input, index) {
        const label = document.getElementById(`prize_label_${index}`);
        let preview = document.getElementById(`prize_preview_${index}`);

        if (input.files.length > 0) {
            label.textContent = `📁 ${input.files[0].name}`;
            label.style.color = '#10b981';

            if (!preview) {
                const previewContainer = input.closest('.form-group');
                const newPreview = document.createElement('img');
                newPreview.id = `prize_preview_${index}`;
                newPreview.className = 'prize-image-preview';
                previewContainer.appendChild(newPreview);
                preview = newPreview;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            label.textContent = '📁 Choose prize image';
            label.style.color = '#9ca3af';
            if (preview) {
                preview.src = '';
            }
        }
    }

    // Add new rule
    function addRule() {
        const rulesList = document.getElementById('rules-list');
        const newRule = document.createElement('div');
        newRule.className = 'dynamic-item';
        newRule.innerHTML = `
            <input type="text" name="rules[]" class="form-control" placeholder="Enter event rule">
            <button type="button" class="btn btn-danger" onclick="removeRule(this)">Remove</button>
        `;
        rulesList.appendChild(newRule);
    }

    // Remove rule
    function removeRule(button) {
        const ruleItem = button.closest('.dynamic-item');
        const rulesList = document.getElementById('rules-list');
        if (rulesList.children.length > 1) {
            ruleItem.remove();
        } else {
            alert('At least one rule is required for the event.');
        }
    }

    // Add new ticket method
    function addMethod() {
        const methodsContainer = document.getElementById('methods-container');
        const methodCount = methodsContainer.children.length;

        const newMethod = document.createElement('div');
        newMethod.className = 'ticket-method-card';
        newMethod.innerHTML = `
            <button type="button" class="remove-method" onclick="removeMethod(this)">
                &times;
            </button>
            <div class="method-header">
                <div class="method-title">Method #${methodCount + 1}</div>
            </div>
            <div class="method-inputs">
                <div class="form-group">
                    <label class="form-label">Method Name</label>
                    <input type="text" name="method_name[]" class="form-control" 
                           placeholder="e.g., Slot Games" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Wager Amount Required ($)</label>
                    <input type="number" name="method_wager[]" class="form-control" 
                           value="100" min="1" step="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tickets Earned</label>
                    <input type="number" name="method_tickets[]" class="form-control" 
                           value="1" min="1" step="1" required>
                </div>
            </div>
        `;
        methodsContainer.appendChild(newMethod);
    }

    // Remove ticket method
    function removeMethod(button) {
        const methodCard = button.closest('.ticket-method-card');
        const methodsContainer = document.getElementById('methods-container');
        if (methodsContainer.children.length > 1) {
            methodCard.remove();
        } else {
            alert('At least one ticket earning method is required.');
        }
    }

    // Add new prize
    function addPrize() {
        const prizesContainer = document.getElementById('prizes-container');
        const prizeCount = prizesContainer.children.length;

        const newPrize = document.createElement('div');
        newPrize.className = 'prize-item';
        newPrize.innerHTML = `
            <button type="button" class="remove-prize" onclick="removePrize(this)">
                &times;
            </button>
            <div class="prize-header">
                <span class="prize-number">Prize #${prizeCount + 1}</span>
            </div>
            <div class="prize-grid">
                <div class="form-group">
                    <label class="form-label">Prize Name</label>
                    <input type="text" name="prize_name[]" class="form-control" 
                           placeholder="e.g., Grand Prize Cash" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Prize Value</label>
                    <input type="text" name="prize_value[]" class="form-control" 
                           placeholder="e.g., $5,000" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Prize Image</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="prize_image_new_${prizeCount}" name="prize_image[]" class="file-input" 
                               accept="image/*" onchange="updatePrizeFileName(this, 'new_${prizeCount}')">
                        <label for="prize_image_new_${prizeCount}" class="file-input-label" id="prize_label_new_${prizeCount}">
                            📁 Choose prize image
                        </label>
                    </div>
                </div>
            </div>
        `;
        prizesContainer.appendChild(newPrize);
    }

    // Remove prize
    function removePrize(button) {
        const prizeItem = button.closest('.prize-item');
        prizeItem.remove();
    }

    // Validate form before submission
    document.querySelector('form').addEventListener('submit', function(e) {
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);

        if (startDate >= endDate) {
            e.preventDefault();
            alert('End date must be after start date.');
            return false;
        }

        const methodNames = document.querySelectorAll('input[name="method_name[]"]');
        let hasMethod = false;
        methodNames.forEach(input => {
            if (input.value.trim() !== '') {
                hasMethod = true;
            }
        });

        if (!hasMethod) {
            e.preventDefault();
            alert('Please configure at least one ticket earning method.');
            return false;
        }

        const prizeNames = document.querySelectorAll('input[name="prize_name[]"]');
        let hasPrize = false;
        prizeNames.forEach(input => {
            if (input.value.trim() !== '') {
                hasPrize = true;
            }
        });

        if (!hasPrize) {
            e.preventDefault();
            alert('Please configure at least one prize.');
            return false;
        }
    });

    // Auto-save functionality
    let saveTimeout;
    function autoSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            console.log('Auto-saving...');
        }, 5000);
    }

    document.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('input', autoSave);
    });

    // DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        function formatAmericanDate(month, day, year, time) {
            return `${month.padStart(2, '0')}/${day.padStart(2, '0')}/${year} ${time}`;
        }

        function combineDateParts(prefix) {
            const month = document.getElementById(`${prefix}_month`).value;
            const day = document.getElementById(`${prefix}_day`).value;
            const year = document.getElementById(`${prefix}_year`).value;
            const time = document.getElementById(`${prefix}_time`).value;

            if (month && day && year && time) {
                return formatAmericanDate(month, day, year, time);
            }
            return '';
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            const startDateStr = combineDateParts('start_date');
            const endDateStr = combineDateParts('end_date');

            if (!startDateStr || !endDateStr) {
                e.preventDefault();
                alert('Please enter valid start and end dates in MM/DD/YYYY HH:MM format');
                return false;
            }

            const startHidden = document.createElement('input');
            startHidden.type = 'hidden';
            startHidden.name = 'start_date';
            startHidden.value = startDateStr;
            this.appendChild(startHidden);

            const endHidden = document.createElement('input');
            endHidden.type = 'hidden';
            endHidden.name = 'end_date';
            endHidden.value = endDateStr;
            this.appendChild(endHidden);
        });

        document.querySelectorAll('input[name$="_month"]').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.length === 2) {
                    this.nextElementSibling.focus();
                }
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });

        document.querySelectorAll('input[name$="_day"]').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.length === 2) {
                    this.nextElementSibling.focus();
                }
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });

        document.querySelectorAll('input[name$="_year"]').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.length === 4) {
                    this.nextElementSibling.focus();
                }
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });

        document.querySelectorAll('input[name$="_time"]').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.length === 2 && !this.value.includes(':')) {
                    this.value += ':';
                }
                this.value = this.value.replace(/[^0-9:]/g, '');

                if (this.value.length > 0) {
                    const hours = parseInt(this.value.split(':')[0]) || 0;
                    if (hours > 12) {
                        this.value = '12' + (this.value.length > 2 ? ':' + this.value.split(':')[1] : '');
                    }
                    if (hours < 1 && this.value.length === 2) {
                        this.value = '12';
                    }
                }
            });
        });
    });
</script>

<body>
    <!-- Animated Background -->
    <div class="background-overlay">
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
    </div>

    <!-- Half Circle Image Container -->
    <div class="half-circle-image"></div>

    <!-- Header -->
    <header class="header">
        <button class="logout-btn" onclick="window.location.href='?logout=1'">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Logout
        </button>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="../images/MB TXPlays-logo-emblem.png" alt="TXPLAY Logo" style="width:40px; height:auto; display:block; margin:0 auto;">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-icon" onclick="window.location.href='index.php'">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                <div class="tooltip">Dashboard</div>
            </div>
            <div class="sidebar-icon" onclick="window.location.href='leaderboard.php'">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="2" y="14" width="5" height="6" rx="1" stroke-width="2"/>
                    <rect x="9.5" y="10" width="5" height="10" rx="1" stroke-width="2"/>
                    <rect x="17" y="17" width="5" height="3" rx="1" stroke-width="2"/>
                    <path d="M12 7V4M12 4l-1.5 1.5M12 4l1.5 1.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div class="tooltip">Leaderboard</div>
            </div>
            <div class="sidebar-icon active" onclick="window.location.href='events.php'">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <div class="tooltip">Events</div>
            </div>
            <div class="sidebar-icon" onclick="window.location.href='bonuses.php'">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" stroke-width="2" />
                    <circle cx="12" cy="12" r="5" stroke-width="2" />
                    <polygon points="12,8.5 13.09,11.26 16,11.27 13.97,13.02 14.58,15.72 12,14.2 9.42,15.72 10.03,13.02 8,11.27 10.91,11.26" fill="currentColor" stroke="none"/>
                </svg>
                <div class="tooltip">Bonuses</div>
            </div>
        </nav>
    </aside>

    <!-- Main Container -->
    <div class="container">
        <div class="admin-container">
            <div class="admin-header">
                <h1 class="admin-title">Event Management</h1>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Basic Event Information -->
                <div class="event-management">
                    <h2 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Basic Information
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="event_title">Event Title</label>
                            <input type="text" id="event_title" name="event_title" class="form-control" 
                                   value="<?php echo htmlspecialchars($eventData['title'] ?? ''); ?>" 
                                   placeholder="TXPlays x Shuffle: 1 Year Anniversary" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="event_subtitle">Event Subtitle</label>
                            <input type="text" id="event_subtitle" name="event_subtitle" class="form-control" 
                                   value="<?php echo htmlspecialchars($eventData['subtitle'] ?? ''); ?>" 
                                   placeholder="Compete for amazing prizes by wagering on your favorite games!">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="start_date">Start Date & Time (CST)</label>
                            <div style="display: flex; gap: 10px;">
                                    <input type="text" id="start_date_month" name="start_date_month" class="form-control" 
                                           placeholder="MM" maxlength="2" style="width: 60px;"
                                           value="<?php echo !empty($eventData['start_date']) ? date('m', strtotime($eventData['start_date'])) : ''; ?>">
                                    <input type="text" id="start_date_day" name="start_date_day" class="form-control" 
                                           placeholder="DD" maxlength="2" style="width: 60px;"
                                           value="<?php echo !empty($eventData['start_date']) ? date('d', strtotime($eventData['start_date'])) : ''; ?>">
                                    <input type="text" id="start_date_year" name="start_date_year" class="form-control" 
                                           placeholder="YYYY" maxlength="4" style="width: 80px;"
                                           value="<?php echo !empty($eventData['start_date']) ? date('Y', strtotime($eventData['start_date'])) : ''; ?>">
                                    <!-- For Start Time -->
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="text" id="start_date_time" name="start_date_time" class="form-control" 
                                               placeholder="hh:mm" maxlength="5" style="width: 80px;"
                                               value="<?php echo !empty($eventData['start_date']) ? date('h:i', strtotime($eventData['start_date'])) : ''; ?>">
                                        <select name="start_date_ampm" class="form-control" style="width: 70px;">
                                            <option value="AM" <?php echo (!empty($eventData['start_date']) && date('A', strtotime($eventData['start_date'])) == 'AM') ? 'selected' : ''; ?>>AM</option>
                                            <option value="PM" <?php echo (!empty($eventData['start_date']) && date('A', strtotime($eventData['start_date'])) == 'PM') ? 'selected' : ''; ?>>PM</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="end_date">End Date & Time (CST)</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="text" id="end_date_month" name="end_date_month" class="form-control" 
                                       placeholder="MM" maxlength="2" style="width: 60px;"
                                       value="<?php echo !empty($eventData['end_date']) ? date('m', strtotime($eventData['end_date'])) : ''; ?>">
                                <input type="text" id="end_date_day" name="end_date_day" class="form-control" 
                                       placeholder="DD" maxlength="2" style="width: 60px;"
                                       value="<?php echo !empty($eventData['end_date']) ? date('d', strtotime($eventData['end_date'])) : ''; ?>">
                                <input type="text" id="end_date_year" name="end_date_year" class="form-control" 
                                       placeholder="YYYY" maxlength="4" style="width: 80px;"
                                       value="<?php echo !empty($eventData['end_date']) ? date('Y', strtotime($eventData['end_date'])) : ''; ?>">
                                <!-- For End Time -->
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="text" id="end_date_time" name="end_date_time" class="form-control" 
                                               placeholder="hh:mm" maxlength="5" style="width: 80px;"
                                               value="<?php echo !empty($eventData['end_date']) ? date('h:i', strtotime($eventData['end_date'])) : ''; ?>">
                                        <select name="end_date_ampm" class="form-control" style="width: 70px;">
                                            <option value="AM" <?php echo (!empty($eventData['end_date']) && date('A', strtotime($eventData['end_date'])) == 'AM') ? 'selected' : ''; ?>>AM</option>
                                            <option value="PM" <?php echo (!empty($eventData['end_date']) && date('A', strtotime($eventData['end_date'])) == 'PM') ? 'selected' : ''; ?>>PM</option>
                                        </select>
                                    </div>
                            </div>
                        </div>
                        
                        <div class="form-group form-group-full">
                            <label class="form-label" for="banner_image">Event Banner Image</label>
                            <div class="file-input-wrapper">
                                <input type="file" id="banner_image" name="banner_image" class="file-input" 
                                       accept="image/*" onchange="updateFileName(this)">
                                <label for="banner_image" class="file-input-label" id="banner_label">
                                    📁 Choose banner image (JPG, PNG, SVG)
                                </label>
                            </div>
                            <?php if (!empty($eventData['banner_image'])): ?>
                                <p style="color: #10b981; font-size: 0.875rem; margin-top: 8px;">
                                    Current: <?php echo htmlspecialchars($eventData['banner_image']); ?>
                                </p>
                                <img src="../<?php echo htmlspecialchars($eventData['banner_image']); ?>" class="prize-image-preview">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Event Rules -->
                <div class="event-management">
                    <h2 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Event Rules
                    </h2>
                    
                    <div class="dynamic-list" id="rules-list">
                        <?php 
                        $rules = $eventData['rules'] ?? ['Event runs for limited time', 'Min. $10 deposit to qualify', '1 ticket per $100 wagered'];
                        foreach ($rules as $index => $rule): 
                        ?>
                            <div class="dynamic-item">
                                <input type="text" name="rules[]" class="form-control" 
                                       value="<?php echo htmlspecialchars($rule); ?>" 
                                       placeholder="Enter event rule">
                                <button type="button" class="btn btn-danger" onclick="removeRule(this)">Remove</button>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="dynamic-item">
                            <input type="text" name="rules[]" class="form-control" placeholder="Enter event rule">
                            <button type="button" class="btn btn-danger" onclick="removeRule(this)">Remove</button>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary" onclick="addRule()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Rule
                    </button>
                </div>

                <!-- Ticket Earning Methods -->
                <div class="event-management">
                    <h2 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                        Ticket Earning Methods
                    </h2>
                    
                    <div class="ticket-methods-grid" id="methods-container">
                        <?php 
                        $methods = $eventData['ticket_methods'] ?? [
                            ['name' => 'Slot Games', 'wager_amount' => 100, 'tickets_earned' => 1],
                            ['name' => 'Table Games', 'wager_amount' => 100, 'tickets_earned' => 1],
                            ['name' => 'Live Dealer', 'wager_amount' => 100, 'tickets_earned' => 1]
                        ];
                        
                        foreach ($methods as $index => $method): 
                        ?>
                            <div class="ticket-method-card">
                                <button type="button" class="remove-method" onclick="removeMethod(this)">
                                    &times;
                                </button>
                                <div class="method-header">
                                    <div class="method-title">Method #<?php echo $index + 1; ?></div>
                                </div>
                                <div class="method-inputs">
                                    <div class="form-group">
                                        <label class="form-label">Method Name</label>
                                        <input type="text" name="method_name[]" class="form-control" 
                                               value="<?php echo htmlspecialchars($method['name']); ?>" 
                                               placeholder="e.g., Slot Games" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Wager Amount Required ($)</label>
                                        <input type="number" name="method_wager[]" class="form-control" 
                                               value="<?php echo $method['wager_amount']; ?>" 
                                               min="1" step="1" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Tickets Earned</label>
                                        <input type="number" name="method_tickets[]" class="form-control" 
                                               value="<?php echo $method['tickets_earned']; ?>" 
                                               min="1" step="1" required>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn btn-secondary" onclick="addMethod()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Method
                    </button>
                </div>

                <!-- Prizes -->
                <div class="event-management">
                    <h2 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V8a1 1 0 00-1-1H9a1 1 0 00-1 1v1M7 8V7a1 1 0 011-1h8a1 1 0 011 1v1m-7 4v6"/>
                        </svg>
                        Event Prizes
                    </h2>
                    
                    <div id="prizes-container">
                        <?php 
                        $prizes = $eventData['prizes'] ?? [
                            ['name' => 'Grand Prize', 'value' => '$5,000', 'image' => ''],
                            ['name' => 'Second Prize', 'value' => '$2,500', 'image' => ''],
                            ['name' => 'Third Prize', 'value' => '$1,000', 'image' => '']
                        ];
                        
                        foreach ($prizes as $index => $prize): 
                        ?>
                            <div class="prize-item">
                                <button type="button" class="remove-prize" onclick="removePrize(this)">
                                    &times;
                                </button>
                                <div class="prize-header">
                                    <span class="prize-number">Prize #<?php echo $index + 1; ?></span>
                                </div>
                                <div class="prize-grid">
                                    <div class="form-group">
                                        <label class="form-label">Prize Name</label>
                                        <input type="text" name="prize_name[]" class="form-control" 
                                               value="<?php echo htmlspecialchars($prize['name']); ?>" 
                                               placeholder="e.g., Grand Prize Cash" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Prize Value</label>
                                        <input type="text" name="prize_value[]" class="form-control" 
                                               value="<?php echo htmlspecialchars($prize['value']); ?>" 
                                               placeholder="e.g., $5,000" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Prize Image</label>
                                        <div class="file-input-wrapper">
                                            <input type="file" id="prize_image_<?php echo $index; ?>" name="prize_image[]" class="file-input" 
                                                   accept="image/*" onchange="updatePrizeFileName(this, <?php echo $index; ?>)">
                                            <label for="prize_image_<?php echo $index; ?>" class="file-input-label" id="prize_label_<?php echo $index; ?>">
                                                📁 Choose prize image
                                            </label>
                                        </div>
                                        <?php if (!empty($prize['image'])): ?>
                                            <p style="color: #10b981; font-size: 0.875rem; margin-top: 8px;">
                                                Current: <?php echo htmlspecialchars($prize['image']); ?>
                                            </p>
                                            <img src="../<?php echo htmlspecialchars($prize['image']); ?>" class="prize-image-preview" id="prize_preview_<?php echo $index; ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <script>
document.addEventListener('DOMContentLoaded', function() {
    // All your existing functions...
    function updateFileName(input) { /* ... */ }
    // ...
    
    // Add the reset function
    window.resetEvent = function() {
        if (confirm('Are you sure you want to reset the event?')) {
            fetch('reset_event.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Error: ' + data.message);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Reset failed');
            });
        }
    };
});
</script>
                    <button type="button" class="btn btn-secondary" onclick="addPrize()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add Prize
                    </button>
                </div>
                <!-- Add this right before or after your submit button -->
                <div style="text-align: center; margin-top: 40px; display: flex; justify-content: center; gap: 20px;">
                    <button type="submit" class="btn btn-primary" style="padding: 16px 40px; font-size: 1.1rem;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Event Configuration
                    </button>
                    
                    <button type="button" class="btn btn-danger" onclick="resetEvent()" style="padding: 16px 40px; font-size: 1.1rem;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Reset Event
                    </button>
                </div>

            </form>
        </div>
    </div>

</body>
</html>