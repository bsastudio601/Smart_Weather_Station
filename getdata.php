<?php
require 'config.php';

header('Content-Type: application/json');

// Debugging: Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get the station ID from the query string
$station_id = isset($_GET['station_id']) ? intval($_GET['station_id']) : 1; // Default to station 1

// Connect to the database
$db = connectDB();

if (!$db) {
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// Function to fetch coordinates for a station
function getCoordinates($station_id, $db) {
    $sql = "SELECT latitude, longitude FROM sensor_data WHERE station_id = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $station_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    return null; // If no coordinates found or query fails
}

// Fetch coordinates
$coordinates = getCoordinates($station_id, $db);

// Debugging: Log the coordinates
file_put_contents('debug.log', "Coordinates for Station ID $station_id: " . json_encode($coordinates) . "\n", FILE_APPEND);

// Prepare the query to fetch the latest record for the specified station
$sql = $db->prepare("SELECT temperature, humidity, pressure, timestamp FROM sensor_data WHERE station_id = ? ORDER BY id DESC LIMIT 1");

if (!$sql) {
    echo json_encode(['error' => 'SQL preparation failed: ' . $db->error]);
    exit;
}

$sql->bind_param("i", $station_id);
$sql->execute();

// Fetch the result
$result = $sql->get_result();

if (!$result) {
    echo json_encode(['error' => 'SQL execution failed: ' . $sql->error]);
    exit;
}

// Check if there are results
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Debugging: Log the fetched data
    file_put_contents('debug.log', "Fetched Data: " . json_encode($row) . "\n", FILE_APPEND);

    // Return the data as a JSON object
    echo json_encode([
        'latitude' => $coordinates['latitude'] ?? null, // Station latitude
        'longitude' => $coordinates['longitude'] ?? null, // Station longitude
        'temperature' => $row['temperature'], // Latest temperature
        'humidity' => $row['humidity'], // Latest humidity
        'pressure' => $row['pressure'], // Latest pressure
        'timestamp' => date("Y-m-d H:i:s", strtotime($row['timestamp'])) // Latest timestamp
    ]);
} else {
    // Debugging: Log no data found
    file_put_contents('debug.log', "No data found for Station ID: $station_id\n", FILE_APPEND);

    // Return a response with null values if no data is found
    echo json_encode([
        'latitude' => null,
        'longitude' => null,
        'temperature' => null,
        'humidity' => null,
        'pressure' => null,
        'timestamp' => null
    ]);
}

// Close the database connection
$db->close();
?>
