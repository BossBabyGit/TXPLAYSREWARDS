<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$jsonFile = 'current_event.json';

try {
    // Create an empty event structure
    $emptyEvent = [
        'title' => '',
        'subtitle' => '',
        'start_date' => '',
        'end_date' => '',
        'banner_image' => '',
        'rules' => [],
        'ticket_methods' => [],
        'prizes' => [],
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'inactive'
    ];

    // Write the empty structure to the file
    if (file_put_contents($jsonFile, json_encode($emptyEvent, JSON_PRETTY_PRINT)) !== false) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Could not write to file');
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}