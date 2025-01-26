#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>

// Define the pin for the DHT11 sensor
#define DHT_PIN 23
#define DHTTYPE DHT11

DHT dht(DHT_PIN, DHTTYPE);

// Arrays for multiple WiFi credentials
const char* ssids[] = {"realme_C11", "realme_C11", "SSID3"};  // Add your SSIDs here
const char* passwords[] = {"artthhii", "artthhii", "password3"};  // Corresponding passwords

// Enter domain name and path
const char* SERVER_NAME = "http://studiozzzzprojects.atwebpages.com/sensordata.php";

// PROJECT_API_KEY must match the value in your server-side config file
String PROJECT_API_KEY = "iloveher143";

// Define the station ID manually for each ESP32 device
int station_id = 1;  // Change this ID for each device (3, 4, 5, etc.)

// Hardcoded latitude and longitude for the station
float latitude = 22.469176;  // Replace with actual latitude
float longitude = 89.608533; // Replace with actual longitude

// Send an HTTP POST request every 5 seconds
unsigned long lastMillis = 0;
long interval = 5000;

void setup() {
  Serial.begin(115200);
  Serial.println("ESP32 serial initialized");

  // Initialize DHT11
  dht.begin();
  Serial.println("DHT11 initialized");

  // Connect to WiFi (Multiple SSIDs and passwords)
  connectToWiFi();

  Serial.println("");
  Serial.print("Connected to WiFi network with IP Address: ");
  Serial.println(WiFi.localIP());
  Serial.println("Timer set to 5 seconds (interval variable),");
  Serial.println("it will take 5 seconds before publishing the first reading.");
}

void loop() {
  // Check WiFi connection status
  if (WiFi.status() == WL_CONNECTED) {
    if (millis() - lastMillis > interval) {
      // Read sensor data
      float t = dht.readTemperature();
      float h = dht.readHumidity();

      if (isnan(t) || isnan(h)) {
        Serial.println(F("Failed to read from DHT sensor!"));
        return;
      }

      // Send data to the server
      upload_data(t, h);
      lastMillis = millis();
    }
  } else {
    Serial.println("WiFi Disconnected!");
  }

  delay(1000);
}

void connectToWiFi() {
  int numNetworks = sizeof(ssids) / sizeof(ssids[0]);  // Get the number of SSIDs in the array
  bool connected = false;
  
  for (int i = 0; i < numNetworks; i++) {
    Serial.print("Connecting to WiFi: ");
    Serial.println(ssids[i]);
    WiFi.begin(ssids[i], passwords[i]);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 10) {  // Try for 10 attempts
      delay(500);
      Serial.print(".");
      attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
      connected = true;
      Serial.println("WiFi Connected!");
      break;  // Exit loop if connected
    } else {
      Serial.println("\nFailed to connect to WiFi.");
    }
  }

  if (!connected) {
    Serial.println("Could not connect to any WiFi networks.");
    while (1);  // Halt execution if connection fails
  }
}

void upload_data(float temperature, float humidity) {
  // Prepare HTTP POST request data
  String postData;
  postData = "api_key=" + PROJECT_API_KEY;
  postData += "&station_id=" + String(station_id);
  postData += "&temperature=" + String(temperature, 2);
  postData += "&humidity=" + String(humidity, 2);
  postData += "&pressure=" + String(1013.25, 2);  // Using a hardcoded pressure value as an example
  postData += "&latitude=" + String(latitude, 6);
  postData += "&longitude=" + String(longitude, 6);

  Serial.print("postData: ");
  Serial.println(postData);

  WiFiClient client;
  HTTPClient http;

  http.begin(client, SERVER_NAME);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  int httpResponseCode = http.POST(postData);

  Serial.print("HTTP Response code: ");
  Serial.println(httpResponseCode);

  http.end();
}
