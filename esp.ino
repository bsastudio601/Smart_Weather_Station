#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SH110X.h>
#include <Adafruit_BMP280.h>
#include <TinyGPS++.h>

// ----- DHT22 Sensor -----
#define DHT_PIN 23
#define DHTTYPE DHT22
DHT dht(DHT_PIN, DHTTYPE);

// ----- OLED SH1106 -----
#define i2c_Address 0x3c
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET -1
Adafruit_SH1106G display = Adafruit_SH1106G(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// ----- BMP280 -----
Adafruit_BMP280 bmp;
bool bmpAvailable = false;

// ----- WiFi Credentials -----
const char* ssids[] = {"realme_C11", "Arthi", "realme_C12"};
const char* passwords[] = {"artthhii", "01707275528", "aabbcc112233"};

// ----- Server Info -----
const char* SERVER_NAME = "http://studiozzzzprojects.atwebpages.com/sensordata.php";
String PROJECT_API_KEY = "API KEY HERE";
int station_id = 2;  // ✅ keep station_id for server

// ----- GPS (NEO-6M) -----
#define RXD2 16  // GPS TX → ESP RX
#define TXD2 17  // GPS RX → ESP TX
HardwareSerial neogps(1);
TinyGPSPlus gps;

// ----- Timer -----
unsigned long lastMillis = 0;
long interval = 5000;

void setup() {
  Serial.begin(115200);
  dht.begin();

  Wire.begin();
  if (!display.begin(i2c_Address, true)) {
    Serial.println(F("OLED init failed!"));
    while (1);
  }

  display.display(); delay(2000); display.clearDisplay();

  // BMP280 setup
  if (bmp.begin(0x76)) {
    bmpAvailable = true;
  } else {
    displayStatusMessage("BMP Missing!");
    delay(1500);
  }

  // GPS begin
  neogps.begin(9600, SERIAL_8N1, RXD2, TXD2);

  // Connect WiFi
  connectToWiFi();
  Serial.println("Ready!");
}

void loop() {
  // Update GPS
  while (neogps.available()) {
    gps.encode(neogps.read());
  }

  if (WiFi.status() == WL_CONNECTED) {
    if (millis() - lastMillis > interval) {
      float t = dht.readTemperature();
      float h = dht.readHumidity();
      float pressure = bmpAvailable ? bmp.readPressure() / 100.0F : -1.0;

      float latitude = gps.location.isValid() ? gps.location.lat() : 0.0;
      float longitude = gps.location.isValid() ? gps.location.lng() : 0.0;

      if (isnan(t) || isnan(h)) {
        displayStatusMessage("Sensor Error!");
        return;
      }

      displaySensorData(t, h, pressure, latitude, longitude);
      upload_data(t, h, pressure, latitude, longitude);

      lastMillis = millis();
    }
  } else {
    displayStatusMessage("WiFi Lost!");
  }

  delay(1000);
}

void connectToWiFi() {
  int numNetworks = sizeof(ssids) / sizeof(ssids[0]);
  for (int i = 0; i < numNetworks; i++) {
    WiFi.begin(ssids[i], passwords[i]);
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 10) {
      delay(500);
      attempts++;
    }
    if (WiFi.status() == WL_CONNECTED) {
      displayStatusMessage("WiFi OK");
      return;
    }
  }
  displayStatusMessage("WiFi Failed!");
  while (1);
}

void upload_data(float temperature, float humidity, float pressure, float lat, float lon) {
  String postData = "api_key=" + PROJECT_API_KEY;
  postData += "&station_id=" + String(station_id);  // ✅ station_id kept
  postData += "&temperature=" + String(temperature, 2);
  postData += "&humidity=" + String(humidity, 2);
  postData += "&pressure=" + (pressure >= 0 ? String(pressure, 2) : "null");
  postData += "&latitude=" + String(lat, 6);
  postData += "&longitude=" + String(lon, 6);

  HTTPClient http;
  WiFiClient client;
  http.begin(client, SERVER_NAME);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  int response = http.POST(postData);
  Serial.print("HTTP Response: ");
  Serial.println(response);
  http.end();
}

void displaySensorData(float temp, float hum, float pressure, float lat, float lon) {
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SH110X_WHITE);

  // Line 1 - Coords
  display.setCursor(0, 0);
  display.print("Lat:");
  display.print(lat, 2);
  display.print(" Lon:");
  display.print(lon, 2);

  // Line 2 - Temp
  display.setCursor(0, 16);
  display.print("T:");
  display.print(temp, 1);
  display.print("C");

  // Line 3 - Humidity
  display.setCursor(64, 16);
  display.print("H:");
  display.print(hum, 1);
  display.print("%");

  // Line 4 - Pressure
  display.setCursor(0, 32);
  display.print("P:");
  if (pressure >= 0) {
    display.print(pressure, 1);
    display.print("hPa");
  } else {
    display.print("N/A");
  }

  display.display();
}

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
