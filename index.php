<?php
require 'config.php';

$db = connectDB(); // Connect to the database

// Fetch station IDs and their coordinates to display on the map
$sql_stations = "
    SELECT sd.station_id, sd.latitude, sd.longitude
    FROM sensor_data sd
    INNER JOIN (
        SELECT station_id, MAX(timestamp) as latest
        FROM sensor_data
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        GROUP BY station_id
    ) latest_coords
    ON sd.station_id = latest_coords.station_id AND sd.timestamp = latest_coords.latest
";

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
    /* Base styles */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f6f9;
        margin: 0;
        padding: 0;
    }

    h1 {
        font-size: 2rem;
        font-weight: bold;
        color: #fff;
        background: linear-gradient(to right, #3498db, #2ecc71);
        padding: 20px 20px;
        text-align: center;
        border-radius: 10px;
        margin: 10px auto 0px auto;
        width: 100%;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .select-row {
            margin-top: 0px; /* Reduce top margin to tighten space */
        }

    h2 {
        font-size: 1.5rem;
        margin-top: 0px;
        margin-bottom: 0px;
        text-align: center;
        color: #333;
    }
    
       

    #stationSelect {
        display: block;
        margin: 0 auto 20px auto;
        padding: 10px;
        font-size: 1rem;
        border-radius: 6px;
        border: 1px solid #ccc;
        width: 250px;
        background-color: #fff;
    }

    /* Map */
    #map {
        height: 400px;
        width: 100%;
        border: 2px solid #ddd;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    /* Chart containers */
    .chart-container {
        background: #fff;
        border-radius: 12px;
        padding: 5px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        margin: 5px auto;
        text-align: center;
        max-width: 350px;
    }

    .chart-text {
        margin-top: 20px;
        font-size: 1.2em;
        font-weight: 500;
        color: #333;
    }

    /* Table */
    .table {
        background-color: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
    }

    .table th {
        background-color: #3498db;
        color: white;
        text-align: center;
    }

    .table td, .table th {
        vertical-align: middle !important;
        text-align: center;
    }
    

    /* Responsive tweaks */
    @media screen and (max-width: 768px) {
        h1 {
            font-size: 1.4rem;
            padding: 15px;
        }

        h2 {
            font-size: 1.2rem;
        }

        .chart-container {
            max-width: 350px;
            padding: 15px;
        }

        .chart-text {
            font-size: 0.9em;
        }

        #stationSelect {
            width: 80%;
        }

        .table {
            display: block;
            overflow-x: auto;
        }
    }
</style>

</head>
<body>

<div class="container">
        <div class="banner text-center text-white py-4 mb-4">
    		<h1 class="text-center">Dynamic Climate Monitoring System</h1>
        </div>
    <div class="row select-row">
        <div class="col-md-12 text-center py-3">
            <h2>Select Station</h2>
            <div class="pt-2">
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
  // Chart.js plugin to draw dynamic center text
  Chart.register({
    id: 'centerText',
    beforeDraw(chart) {
      const { width, height } = chart;
      const ctx = chart.ctx;
      const dataset = chart.data.datasets[0];

      if (!dataset || !dataset.data) return;

      const value = dataset.data[0];
      let unit = '';
      if (chart.canvas.id === 'donut_temperature') unit = '°C';
      else if (chart.canvas.id === 'donut_humidity') unit = '%';
      else if (chart.canvas.id === 'donut_pressure') unit = 'hPa';
      
      const formattedValue = value.toFixed(2);

      ctx.save();
      ctx.font = '30px sans-serif';
      ctx.fillStyle = '#000';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(`${formattedValue}${unit}`, width / 2, height / 2);
      ctx.restore();
    }
  });

  // Station coordinates and data from PHP
  const stations = <?php echo json_encode($stations_data); ?>;

  // Set up the map
  const map = L.map('map').setView([stations[0].latitude, stations[0].longitude], 15);

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  // Add markers for the stations
  stations.forEach(station => {
    const marker = L.marker([station.latitude, station.longitude])
      .addTo(map)
      .bindPopup(`Station ${station.id}`);
    marker.on('click', function () {
      map.setView([station.latitude, station.longitude], 15);
      history.pushState(null, null, `?station_id=${station.id}`);
    });
  });

  // Chart.js Donut Charts Setup
  const donutConfig = (ctx, label, color) => new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: [label],
      datasets: [{
        data: [0, 100],
        backgroundColor: [color, '#e0e0e0'],
        hoverOffset: 4
      }]
    },
    options: {
      cutout: '70%',
      responsive: true,
      plugins: {
        tooltip: { enabled: false }
      }
    }
  });

  // Create donut charts
  const temperatureChart = donutConfig(document.getElementById('donut_temperature'), 'Temperature', '#f39c12');
  const humidityChart = donutConfig(document.getElementById('donut_humidity'), 'Humidity', '#3498db');
  const pressureChart = donutConfig(document.getElementById('donut_pressure'), 'Pressure', '#2ecc71');

  // Update Donut Charts
  function updateChart(chart, value) {
    const maxValue = 100;
    const chartValue = Math.min(Math.max(value, 0), maxValue);
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
        console.log(response);
        if (response.error) {
          console.error(response.error);
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
      error: function (xhr, status, error) {
        console.error("Error fetching data: ", status, error);
      }
    });
  }

  // Handle station change from dropdown
  function changeStation() {
    const stationId = document.getElementById('stationSelect').value;
    history.pushState(null, null, `?station_id=${stationId}`);
  }

  // Refresh data every 5 seconds
  setInterval(refreshData, 5000);
</script>


</body>
</html>

<?php
$db->close(); // Close the database connection
?>
