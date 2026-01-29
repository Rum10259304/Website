<?php
session_start();
header('Content-Type: application/json');

// Include database connection and logging function
include __DIR__ . '/../connect.php';
include 'auto_log_function.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $country = trim($_POST['new_country'] ?? '');
    
    if (!empty($country)) {
        // Check if country already exists
        $check = $conn->prepare("SELECT country_id FROM countries WHERE country = ?");
        $check->bind_param("s", $country);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Country already exists.']);
            $check->close();
            exit;
        }
        $check->close();
        
        // Insert new country
        $stmt = $conn->prepare("INSERT INTO countries (country) VALUES (?)");
        $stmt->bind_param("s", $country);
        
        if ($stmt->execute()) {
            // Log the action if session exists
            if (isset($_SESSION['user_id'])) {
                $details = "Added country: $country";
                log_action($conn, $_SESSION['user_id'], "users", "add", $details);
            }
            echo json_encode(['success' => true, 'message' => 'Country added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Country name is required.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>