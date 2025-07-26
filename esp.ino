#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SH110X.h>
#include <Adafruit_BMP280.h>

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
bool bmpAvailable = false;  // Track if BMP280 is connected

// ----- WiFi Credentials -----
const char* ssids[] = {"realme_C11", "Arthi",  "realme_C12"};
const char* passwords[] = {"artthhii", "01707275528",  "aabbcc112233"};

// ----- Server Info -----
const char* SERVER_NAME = "http://studiozzzzprojects.atwebpages.com/sensordata.php";
String PROJECT_API_KEY = "iloveher143";
int station_id = 2;
float latitude = 22.467337244425735;
float longitude = 89.61335239604378;

// ----- Timers -----
unsigned long lastMillis = 0;
long interval = 5000;

void setup() {
  Serial.begin(115200);
  Serial.println("ESP32 serial initialized");

  dht.begin();
  Serial.println("DHT22 initialized");

  // Initialize OLED with default I2C pins (SDA 21, SCL 22)
  Wire.begin();
  if (!display.begin(i2c_Address, true)) {
    Serial.println(F("OLED initialization failed!"));
    while (1);
  }

  display.display();
  delay(2000);
  display.clearDisplay();

  // Initialize BMP280
  if (bmp.begin(0x76)) {
    bmpAvailable = true;
    Serial.println("BMP280 initialized");
  } else {
    bmpAvailable = false;
    Serial.println(F("BMP280 not found. Continuing without pressure data."));
    displayStatusMessage("BMP Missing, Skipping");
    delay(1500);
  }

  connectToWiFi();

  Serial.println("");
  Serial.print("Connected to WiFi network with IP Address: ");
  Serial.println(WiFi.localIP());
  Serial.println("Timer set to 5 seconds (interval variable),");
  Serial.println("it will take 5 seconds before publishing the first reading.");
}

void loop() {
  if (WiFi.status() == WL_CONNECTED) {
    if (millis() - lastMillis > interval) {
      float t = dht.readTemperature();
      float h = dht.readHumidity();

      if (isnan(t) || isnan(h)) {
        Serial.println(F("Failed to read from DHT sensor!"));
        displayStatusMessage("Sensor Error!");
        return;
      }

      float pressure = bmpAvailable ? bmp.readPressure() / 100.0F : -1.0;

      displaySensorData(t, h, pressure, station_id);
      upload_data(t, h, pressure);
      lastMillis = millis();
    }
  } else {
    displayStatusMessage("WiFi Disconnected!");
  }

  delay(1000);
}

void connectToWiFi() {
  int numNetworks = sizeof(ssids) / sizeof(ssids[0]);
  bool connected = false;

  for (int i = 0; i < numNetworks; i++) {
    Serial.print("Connecting to WiFi: ");
    Serial.println(ssids[i]);
    WiFi.begin(ssids[i], passwords[i]);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 10) {
      delay(500);
      Serial.print(".");
      attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
      connected = true;
      displayStatusMessage("WiFi Connected!");
      break;
    } else {
      Serial.println("\nFailed to connect to WiFi.");
    }
  }

  if (!connected) {
    displayStatusMessage("WiFi Failed!");
    Serial.println("Could not connect to any WiFi networks.");
    while (1);
  }
}

void upload_data(float temperature, float humidity, float pressure) {
  String postData = "api_key=" + PROJECT_API_KEY;
  postData += "&station_id=" + String(station_id);
  postData += "&temperature=" + String(temperature, 2);
  postData += "&humidity=" + String(humidity, 2);
  postData += "&pressure=" + (pressure >= 0 ? String(pressure, 2) : "null");
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
  if (pressure >= 0) {
    display.print(pressure, 2);
    display.println(" hPa");
  } else {
    display.println("N/A");
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
