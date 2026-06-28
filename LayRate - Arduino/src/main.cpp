/*
 * Arduino Uno R3 - IR Break Beam + DHT22
 *
 * IR Break Beam receiver on pin 4 (internal pullup), counts objects.
 * DHT22 on pin 2, read every 2 seconds with range-validated readings.
 * Built-in LED (pin 13) ON when beam is broken.
 * Serial output at 9600 baud.
 *
 * Prints a new block (separator + 4 data lines) only when a value
 * changes or an error state transitions.
 */

#include <Arduino.h>
#include <DHT.h>

// -- Pin assignments --
#define IR_PIN    4            // IR Break Beam receiver signal
#define LED_PIN   LED_BUILTIN  // onboard LED (pin 13)
#define DHT_PIN   2            // DHT22 data wire
#define DHT_TYPE  DHT22        // sensor model (NOT DHT11)

// -- DHT22 instance (Adafruit library) --
DHT dht(DHT_PIN, DHT_TYPE);

// -- IR beam state --
bool beamBroken     = false;
bool lastBeamState  = false;
unsigned int objectCount = 0;

// -- DHT data --
unsigned long lastDHTRead = 0;
const unsigned long DHT_INTERVAL = 2000;  // DHT22 needs >=2 s between reads
float temperature    = 0.0;
float humidity       = 0.0;
bool dhtDataValid    = false;
const char* dhtErrorMsg = "";   // populated when all retries fail

// Track last-printed values so we only print on actual change
float lastPrintedTemp    = 0.0;
float lastPrintedHum     = 0.0;
bool  lastPrintedBeam    = false;
unsigned int lastPrintedCount = 0;

// Track whether we have already printed the current error state
// so we show it once (on transition) but don't repeat every 2 s
bool dhtErrorPrinted = false;

// ----------------------------------------------------------------
// Sync the "last printed" trackers so we don't reprint same values
// ----------------------------------------------------------------
void syncPrintedValues() {
  lastPrintedCount = objectCount;
  lastPrintedBeam  = beamBroken;
  lastPrintedTemp  = temperature;
  lastPrintedHum   = humidity;
}

// ----------------------------------------------------------------
// Print one complete block — separator + all 4 data lines
// ----------------------------------------------------------------
void printBlock() {
  // Form feed clears the terminal, disposing previous output
  Serial.write('\f');
  Serial.println("--------------------");

  Serial.print("Count: ");
  Serial.println(objectCount);

  Serial.print("Beam: ");
  Serial.println(beamBroken ? "BROKEN" : "UNBROKEN");

  // Temperature line — show value, error message, or placeholder
  Serial.print("Temp: ");
  if (dhtDataValid) {
    if (temperature >= 0.0 && temperature < 10.0) Serial.print('0');
    Serial.print(temperature, 2);
    Serial.println(" C");
  } else if (dhtErrorMsg[0] != '\0') {
    Serial.println(dhtErrorMsg);
  } else {
    Serial.println("--.-- C");
  }

  // Humidity line
  Serial.print("Humidity: ");
  if (dhtDataValid) {
    if (humidity >= 0.0 && humidity < 10.0) Serial.print('0');
    Serial.print(humidity, 2);
    Serial.println(" %");
  } else if (dhtErrorMsg[0] != '\0') {
    Serial.println("--.-- %");
  } else {
    Serial.println("--.-- %");
  }
}

// ----------------------------------------------------------------
// DHT22 read with NaN check + range validation + 3 retries
// ----------------------------------------------------------------
void readDHT() {
  dhtErrorMsg = "";

  for (int attempt = 0; attempt < 3; attempt++) {
    bool force = (attempt > 0);
    float h = dht.readHumidity(force);
    float t = dht.readTemperature(false, force);

    // Step 1: Reject NaN readings
    if (isnan(h) || isnan(t)) {
      continue;
    }

    // Step 2: Reject out-of-range temperature
    // DHT22 valid ambient range: -40°C to +80°C
    if (t > 80.0 || t < -40.0) {
      continue;
    }

    // Step 3: Reject out-of-range humidity (above 99% indicates error)
    if (h > 99.0) {
      continue;
    }

    // All checks passed — store valid data
    humidity     = h;
    temperature  = t;
    dhtDataValid = true;
    return;
  }

  // All 3 attempts failed validation
  dhtDataValid = false;
  dhtErrorMsg = "DHT22 Error - Check wiring";
}

// ----------------------------------------------------------------
// Setup
// ----------------------------------------------------------------
void setup() {
  Serial.begin(9600);

  pinMode(IR_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  dht.begin();

  // Print initial block at startup (shows --.-- for DHT until first read)
  printBlock();
  lastPrintedCount = objectCount;
  lastPrintedBeam  = beamBroken;
  lastPrintedTemp  = temperature;
  lastPrintedHum   = humidity;

  lastDHTRead = millis();
}

// ----------------------------------------------------------------
// Main loop
// ----------------------------------------------------------------
void loop() {
  unsigned long now = millis();

  // ----------------------------------------------------
  // 1) DHT22 — read every 2 seconds with range validation
  // ----------------------------------------------------
  if (now - lastDHTRead >= DHT_INTERVAL) {
    lastDHTRead = now;

    bool prevDHTValid = dhtDataValid;
    readDHT();

    // Update display if validity state changed (valid <-> invalid)
    if (dhtDataValid != prevDHTValid) {
      dhtErrorPrinted = !dhtDataValid;
      printBlock();
      syncPrintedValues();
    }
    // Or if values changed while valid
    else if (dhtDataValid && (temperature != lastPrintedTemp ||
                              humidity != lastPrintedHum)) {
      printBlock();
      syncPrintedValues();
    }
    // Or if first time printing an error
    else if (!dhtDataValid && !dhtErrorPrinted) {
      dhtErrorPrinted = true;
      printBlock();
      syncPrintedValues();
    }
  }

  // ----------------------------------------------------
  // 2) IR Break Beam — check every loop iteration
  // ----------------------------------------------------
  beamBroken = (digitalRead(IR_PIN) == LOW);

  if (beamBroken != lastBeamState) {
    lastBeamState = beamBroken;

    if (beamBroken) {
      objectCount++;
      digitalWrite(LED_PIN, HIGH);
    } else {
      digitalWrite(LED_PIN, LOW);
    }

    printBlock();
    syncPrintedValues();
  }
}
