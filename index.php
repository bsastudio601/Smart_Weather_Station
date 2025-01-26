<?php
require 'config.php';

$db = connectDB(); // Connect to the database

// Fetch station IDs and their coordinates to display on the map
$sql_stations = "SELECT DISTINCT station_id, latitude, longitude FROM sensor_data WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$stations_result = $db->query($sql_stations);

// If a station ID is passed via GET, fetch data for that station
$station_id = isset($_GET['station_id']) ? intval($_GET['station_id']) : 1; // Default to station 1
$sql = "SELECT * FROM sensor_data WHERE station_id = $station_id ORDER BY timestamp DESC LIMIT 30";
$result = $db->query($sql);

if (!$result) {
    echo "Error: " . $sql . "<br>" . $db->error;
}

// Create an array of stations for JavaScript
$stations_data = [];
while ($station = mysqli_fetch_assoc($stations_result)) {
    $stations_data[] = [
        'id' => $station['station_id'],
        'latitude' => $station['latitude'],
        'longitude' => $station['longitude']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather Station Dashboard</title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        #map {
            height: 400px; /* Default height for desktop */
            width: 100%;  /* Full width for both mobile and desktop */
        }

        @media screen and (max-width: 768px) {
            #map {
                height: 300px; /* Adjusted height for mobile devices */
            }

            .row {
                margin: 0 auto; /* Ensure rows are centered properly */
            }

            .table {
                width: 100%; /* Full width on smaller screens */
                overflow-x: auto; /* Enable horizontal scrolling if needed */
                display: block; /* Prevent breaking out of the layout */
            }

            .chart-container {
                max-width: 200px; /* Resize charts for mobile */
                margin: 0 auto; /* Center charts */
            }

            .chart-text {
                font-size: 0.8em; /* Adjust text size for smaller screens */
            }

            h1, h2 {
                font-size: 1.5rem; /* Adjust header size for better readability */
                text-align: center; /* Center align for mobile */
            }
        }


    </style>
</head>
<body>

<div class="container">
    <h1 class="text-center">ESP Weather Station Dashboard</h1>
    <div class="row">
        <div class="col-md-12">
            <h2>Select Station</h2>
            <div>
                <select id="stationSelect" onchange="changeStation()">
                    <?php foreach ($stations_data as $station) { ?>
                        <option value="<?php echo $station['id']; ?>" <?php echo $station['id'] == $station_id ? 'selected' : ''; ?>>
                            Station <?php echo $station['id']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Map -->
    <div class="row">
        <div class="col-md-12">
            <div id="map"></div>
        </div>
    </div>

    <!-- Donut Charts -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="chart-container">
                <canvas id="donut_temperature"></canvas>
                <div class="chart-text" id="temperatureText">--°C</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-container">
                <canvas id="donut_humidity"></canvas>
                <div class="chart-text" id="humidityText">--%</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-container">
                <canvas id="donut_pressure"></canvas>
                <div class="chart-text" id="pressureText">-- hPa</div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="row mt-4">
        <div class="col-md-12">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Temperature</th>
                        <th scope="col">Humidity</th>
                        <th scope="col">Pressure</th>
                        <th scope="col">Date Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <th scope="row"><?php echo $i++;?></th>
                            <td><?php echo $row['temperature'];?></td>
                            <td><?php echo $row['humidity'];?></td>
                            <td><?php echo $row['pressure'];?></td>
                            <td><?php echo date("Y-m-d h:i A", strtotime($row['timestamp']));?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Station coordinates and data from PHP
    const stations = <?php echo json_encode($stations_data); ?>;

    // Set up the map
    const map = L.map('map').setView([stations[0].latitude, stations[0].longitude], 15); // Default to the first station

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Add markers for the stations
    stations.forEach(station => {
        const marker = L.marker([station.latitude, station.longitude]).addTo(map).bindPopup(`Station ${station.id}`);
        marker.on('click', function () {
            // Change the station ID based on the clicked marker
            window.location.href = `?station_id=${station.id}`;
        });
    });

    // Chart.js Donut Charts Setup
    const donutConfig = (ctx, label, color) => new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [label, ],
            datasets: [{
                data: [0, 100],
                backgroundColor: [color, '#e0e0e0'],
                hoverOffset: 4
            }]
        },
        options: {
            cutout: '70%',  // Defines the size of the donut hole
            responsive: true,
            plugins: {
                tooltip: { enabled: false }  // Disable tooltips
            }
        }
    });

    // Create donut charts
    const temperatureChart = donutConfig(document.getElementById('donut_temperature'), 'Temperature', '#f39c12');
    const humidityChart = donutConfig(document.getElementById('donut_humidity'), 'Humidity', '#3498db');
    const pressureChart = donutConfig(document.getElementById('donut_pressure'), 'Pressure', '#2ecc71');

    // Update Donut Charts
    function updateChart(chart, value) {
        console.log("Updating chart with value:", value);
        const maxValue = 100;  // Define the max value for the donut chart
        const chartValue = Math.min(Math.max(value, 0), maxValue); // Ensure value is between 0 and maxValue
        chart.data.datasets[0].data = [chartValue, maxValue - chartValue];
        chart.update();
    }

    // Function to refresh data
    function refreshData() {
        const stationId = document.getElementById('stationSelect').value;
        $.ajax({
            url: 'getdata.php',
            data: { station_id: stationId },
            dataType: 'json',
            success: function (response) {
                // Log the response to the console for debugging
                console.log(response);

                if (response.error) {
                    console.error(response.error);  // Log error if no data is found
                    return;
                }

                // Update display text
                document.getElementById('temperatureText').textContent = `${response.temperature}°C`;
                document.getElementById('humidityText').textContent = `${response.humidity}%`;
                document.getElementById('pressureText').textContent = `${response.pressure} hPa`;

                // Update charts with new data
                updateChart(temperatureChart, response.temperature);
                updateChart(humidityChart, response.humidity);
                updateChart(pressureChart, response.pressure);
            },
            error: function(xhr, status, error) {
                console.error("Error fetching data: ", status, error); // Log any AJAX errors
            }
        });
    }

    // Function to handle station change
    function changeStation() {
        const stationId = document.getElementById('stationSelect').value;
        window.location.href = `?station_id=${stationId}`;
    }

    // Set an interval to refresh data every 5 seconds
    setInterval(refreshData, 5000);
</script>

</body>
</html>

<?php
$db->close(); // Close the database connection
?>
