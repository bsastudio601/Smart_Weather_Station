#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SH110X.h>
#include <Adafruit_BMP280.h>  // Include the BMP280 library

// Define the pin for the DHT22 sensor
#define DHT_PIN 23
#define DHTTYPE DHT22

DHT dht(DHT_PIN, DHTTYPE);

// OLED I2C pins
#define OLED_SDA_PIN 25  // Define the SDA pin for OLED
#define OLED_SCL_PIN 26  // Define the SCL pin for OLED

// SH1106 OLED settings
#define i2c_Address 0x3c  // Default OLED I2C address
#define SCREEN_WIDTH 128  // OLED display width, in pixels
#define SCREEN_HEIGHT 64  // OLED display height, in pixels
#define OLED_RESET -1     // No reset pin
Adafruit_SH1106G display = Adafruit_SH1106G(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// BMP280 settings
Adafruit_BMP280 bmp;  // Create an instance of the BMP280 sensor

// Arrays for multiple WiFi credentials
const char* ssids[] = {"realme_C11", "Arthi",  "realme_C12"};  // Add your SSIDs here
const char* passwords[] = {"artthhii", "01707275528",  "aabbcc112233"};  // Corresponding passwords

// Enter domain name and path
const char* SERVER_NAME = "http://studiozzzzprojects.atwebpages.com/sensordata.php";

// PROJECT_API_KEY must match the value in your server-side config file
String PROJECT_API_KEY = "iloveher143";

// Define the station ID manually for each ESP32 device
int station_id = 2;  // Change this ID for each device (1, 2, 3, etc.)

// Hardcoded latitude and longitude for the station
float latitude = 22.471614;  // Replace with actual latitude
float longitude = 89.591606; // Replace with actual longitude

// Send an HTTP POST request every 30 seconds
unsigned long lastMillis = 0;
long interval = 5000;

void setup() {
  Serial.begin(115200);
  Serial.println("ESP32 serial initialized");

  // Initialize DHT22
  dht.begin();
  Serial.println("DHT22 initialized");

  // Initialize OLED display (using default I2C pins)
  Wire.begin();  // Default I2C pins (SDA = 21, SCL = 22 for ESP32)
  if (!display.begin(i2c_Address, true)) {
    Serial.println(F("OLED initialization failed!"));
    while (1); // Halt execution if display fails
  }

  display.display();  // Show splash screen
  delay(2000);        // Pause for the splash screen
  display.clearDisplay();

  // Initialize BMP280 sensor
  if (!bmp.begin(0x76)) {  // Default I2C address for BMP280 is 0x76
    Serial.println(F("Could not find a valid BMP280 sensor, check wiring!"));
    displayStatusMessage("BMP280 Error!");
    while (1);  // Halt execution if sensor initialization fails
  }

  Serial.println("BMP280 initialized");

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
      // Read sensor data and update display
      float t = dht.readTemperature();
      float h = dht.readHumidity();

      if (isnan(t) || isnan(h)) {
        Serial.println(F("Failed to read from DHT sensor!"));
        displayStatusMessage("Sensor Error!");
        return;
      }

      // Read pressure from BMP280
      float pressure = bmp.readPressure() / 100.0F;  // Convert from Pa to hPa

      // Display sensor data
      displaySensorData(t, h, pressure, station_id);

      // Send data to the server
      upload_data(t, h, pressure);
      lastMillis = millis();
    }
  } else {
    displayStatusMessage("WiFi Disconnected!");
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
      displayStatusMessage("WiFi Connected!");
      break;  // Exit loop if connected
    } else {
      Serial.println("\nFailed to connect to WiFi.");
    }
  }

  if (!connected) {
    displayStatusMessage("WiFi Connection Failed!");
    Serial.println("Could not connect to any WiFi networks.");
    while (1);  // Halt execution if connection fails
  }
}

void upload_data(float temperature, float humidity, float pressure) {
  // Prepare HTTP POST request data
  String postData;
  postData = "api_key=" + PROJECT_API_KEY;
  postData += "&station_id=" + String(station_id);
  postData += "&temperature=" + String(temperature, 2);
  postData += "&humidity=" + String(humidity, 2);
  postData += "&pressure=" + String(pressure, 2);  // Include pressure data
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

// Function to display sensor data on OLED
void displaySensorData(float temp, float hum, float pressure, int station) {
  display.clearDisplay();

  display.setTextSize(1);
  display.setTextColor(SH110X_WHITE);
  display.setCursor(0, 0);
  display.println("Station ID: " + String(station));

  display.setTextSize(1.2);
  display.setCursor(0, 28);
  display.print("Temp: ");
  display.print(temp, 1);
  display.println("C");

  display.setCursor(0, 40);
  display.print("Humidity: ");
  display.print(hum, 1);
  display.println("%");

  display.setCursor(0, 52);
  display.print("Pressure: ");
  display.print(pressure, 2);
  display.println(" hPa");

  display.display();
}

// Function to display status messages on OLED
void displayStatusMessage(String message) {
  display.clearDisplay();

  display.setTextSize(1);
  display.setTextColor(SH110X_WHITE);
  display.setCursor(0, 0);
  display.println("STATUS:");
  display.setCursor(0, 16);
  display.println(message);

  display.display();
}
