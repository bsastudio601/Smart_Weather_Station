<?php
// Include the database connection file
include_once('config.php');

// Check if the necessary POST data exists
if (isset($_POST['api_key'], $_POST['station_id'], $_POST['temperature'], $_POST['humidity'], $_POST['pressure'], $_POST['latitude'], $_POST['longitude'])) {
    $api_key = $_POST['api_key'];
    $station_id = $_POST['station_id'];
    $temperature = $_POST['temperature'];
    $humidity = $_POST['humidity'];
    $pressure = $_POST['pressure']; // Added pressure field
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];

    // Define the correct API key
    $correct_api_key = "........"; // This should match the key used in your ESP32 code

    // Validate API key
    if ($api_key === $correct_api_key) {
        // Connect to the database
        $db = connectDB();

        // Prepare and bind to insert the data
        $stmt = $db->prepare("
            INSERT INTO sensor_data (station_id, temperature, humidity, pressure, latitude, longitude, timestamp)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iddddd", $station_id, $temperature, $humidity, $pressure, $latitude, $longitude); // Added pressure to binding

        // Execute the statement
        if ($stmt->execute()) {
            echo "Data inserted successfully";
        } else {
            echo "Error: " . $stmt->error;
        }

        // Close the prepared statement and connection
        $stmt->close();
        $db->close();
    } else {
        echo "Invalid API Key";
    }
} else {
    echo "Required data missing";
}
?>
