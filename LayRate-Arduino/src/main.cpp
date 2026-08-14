/*
 * Arduino Uno R3 - IR Break Beam + DHT22 + Relay Fan Control
 *
 * IR Break Beam receiver on pin 3 (external 1K pull-up to 5V), counts objects.
 * DHT22 on pin 2, read every 2 seconds with range-validated readings.
 * Relay module (SRD-05VDC-SL-C, active LOW) on pin 7 drives the cooling fan.
 * Fan control is bidirectional over serial:
 *   - auto mode   : firmware hysteresis. The ON/OFF thresholds are
 *                   runtime-configurable via "THRESH:<on>:<off>" (the bridge
 *                   sends the app-configured temp_max and temp_max - 5C
 *                   dead-band). Boots at 35C ON / 30C OFF until the first
 *                   THRESH command arrives so the fan is never dead on arrival.
 *   - manual mode : commanded via serial lines "RELAY:ON" / "RELAY:OFF",
 *                   resumed with "RELAY:AUTO". Acknowledged with CFM:RELAY:OK.
 *                   THRESH is acknowledged with CFM:THRESH:OK (:INVALID when
 *                   the values are rejected).
 *   - safety default : an invalid DHT22 read ALWAYS forces the fan OFF,
 *                   overriding both auto hysteresis and a manual ON. While
 *                   active the Relay line reports "OFF (SAFETY)" so the
 *                   bridge/backend can tell safety-forced OFF from a plain
 *                   OFF. control_mode stays manual during a manual block.
 * Built-in LED (pin 13) ON when beam is broken.
 * Serial output at 9600 baud.
 *
 * Prints a new block (separator + data lines) only when a value
 * changes or an error state transitions.
 */

#include <Arduino.h>
#include <DHT.h>

// -- Pin assignments --
#define IR_PIN    3            // IR Break Beam receiver signal (external 1K pull-up to 5V)
#define RELAY_PIN 7            // Relay module signal (SRD-05VDC-SL-C, active LOW)
#define LED_PIN   LED_BUILTIN  // onboard LED (pin 13)
#define DHT_PIN   2            // DHT22 data wire
#define DHT_TYPE  DHT22        // sensor model (NOT DHT11)

// -- DHT22 instance (Adafruit library) --
DHT dht(DHT_PIN, DHT_TYPE);

// -- IR beam state (cooldown-based counting) --
bool beamBroken     = false;
bool lastBeamState  = false;
unsigned int objectCount = 0;
unsigned long lastIRCooldown = 0;
const unsigned long IR_COOLDOWN_MS = 1000; // lockout after a count to prevent noise re-trigger

// -- DHT data --
unsigned long lastDHTRead = 0;
const unsigned long DHT_INTERVAL = 2000;  // DHT22 needs >=2 s between reads
float temperature    = 0.0;
float humidity       = 0.0;
bool dhtDataValid    = false;
const char* dhtErrorMsg = "";   // populated when all retries fail

// -- Relay / fan state --
// AUTO-mode hysteresis thresholds. Boot defaults are the historical hardcoded
// values so the fan is never dead on arrival; the bridge overwrites them with
// the app-configured temp_max (+ derived 5C dead-band) via a THRESH command as
// soon as it connects.
float relayOnTemp  = 35.0; // auto: fan ON when temperature reaches this value
float relayOffTemp = 30.0; // auto: fan OFF when temperature drops below this value
bool relayOn = false;             // current relay state (LOW = ON, HIGH = OFF)
bool relaySafetyActive = false;   // true when fan is forced OFF by an invalid DHT22 read

// -- Serial command channel (bidirectional fan control) --
// The Raspberry Pi bridge polls the server and writes "RELAY:ON|OFF|AUTO".
// Manual mode is authoritative until a new command (or AUTO) arrives.
enum RelayMode { RELAY_AUTO, RELAY_MANUAL };
RelayMode relayMode   = RELAY_AUTO;
bool      manualRelayOn = false;
char      cmdBuf[32]    = "";
uint8_t   cmdLen        = 0;

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

  // Relay / fan state line — "OFF (SAFETY)" means the fan was forced off by
  // an invalid DHT22 read (distinct from a user/auto OFF)
  Serial.print("Relay: ");
  if (relayOn) {
    Serial.println("ON");
  } else if (relaySafetyActive) {
    Serial.println("OFF (SAFETY)");
  } else {
    Serial.println("OFF");
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
// Serial command handling — parses "RELAY:ON|OFF|AUTO" and
// "THRESH:<on_temp>:<off_temp>" lines
// ----------------------------------------------------------------
void handleCommandLine(const char* line) {
  if (strncmp(line, "RELAY:", 6) == 0) {
    const char* val = line + 6;
    if (strncmp(val, "ON", 2) == 0) {
      relayMode     = RELAY_MANUAL;
      manualRelayOn = true;
    } else if (strncmp(val, "OFF", 3) == 0) {
      relayMode     = RELAY_MANUAL;
      manualRelayOn = false;
    } else if (strncmp(val, "AUTO", 4) == 0) {
      relayMode = RELAY_AUTO;
    }
    Serial.println("CFM:RELAY:OK");
  } else if (strncmp(line, "THRESH:", 7) == 0) {
    // App-configured AUTO hysteresis thresholds from the bridge.
    // Validation keeps the pair sane: ON strictly above OFF and both within a
    // plausible ambient range, so a malformed command can't break the fan.
    float onT = 0.0, offT = 0.0;
    int matched = sscanf(line + 7, "%f:%f", &onT, &offT);
    if (matched == 2 && onT > offT && onT >= 5.0 && onT <= 60.0 && offT >= -10.0) {
      relayOnTemp  = onT;
      relayOffTemp = offT;
      Serial.println("CFM:THRESH:OK");
    } else {
      Serial.println("CFM:THRESH:INVALID");
    }
  }
}

// Pure hysteresis decision (isolated for unit testing): fan ON once temperature
// reaches the ON threshold, OFF once it drops below the OFF threshold. Both
// thresholds are runtime-configurable via THRESH commands.
bool relayHysteresisNext(bool relayOnNow, float temperatureC, float onTemp, float offTemp) {
  if (!relayOnNow && temperatureC >= onTemp) return true;
  if (relayOnNow && temperatureC < offTemp) return false;
  return relayOnNow;
}

void readSerialCommands() {
  while (Serial.available() > 0) {
    char c = (char) Serial.read();
    if (c == '\n' || c == '\r') {
      if (cmdLen > 0) {
        cmdBuf[cmdLen] = '\0';
        handleCommandLine(cmdBuf);
        cmdLen = 0;
      }
    } else if (cmdLen < sizeof(cmdBuf) - 1) {
      cmdBuf[cmdLen++] = c;
    } else {
      cmdLen = 0; // line too long — discard
    }
  }
}

// ----------------------------------------------------------------
// Setup
// ----------------------------------------------------------------
void setup() {
  Serial.begin(9600);

  pinMode(IR_PIN, INPUT);          // external 1K pull-up resistor to 5V
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH);   // relay is active LOW, HIGH = OFF (fan starts stopped)
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

  // 0) Serial commands (bidirectional relay control) — never blocks
  readSerialCommands();

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
  // 2) IR Break Beam — counts on BREAKING edge with cooldown
  // ----------------------------------------------------
  beamBroken = (digitalRead(IR_PIN) == HIGH);

  if (beamBroken && !lastBeamState && (now - lastIRCooldown) > IR_COOLDOWN_MS) {
    lastBeamState = true;
    lastIRCooldown = now;
    objectCount++;
    digitalWrite(LED_PIN, HIGH);
    printBlock();
    syncPrintedValues();
  }

  if (!beamBroken && lastBeamState) {
    lastBeamState = false;
    digitalWrite(LED_PIN, LOW);
    printBlock();
    syncPrintedValues();
  }

  // ----------------------------------------------------
  // 3) Relay / fan — the DHT22 safety default wins over
  //    BOTH manual override and auto hysteresis: an invalid
  //    read always forces the fan OFF. Manual mode only
  //    suspends the hysteresis thresholds, never this check.
  // ----------------------------------------------------
  {
    bool prevRelay    = relayOn;
    bool prevSafety   = relaySafetyActive;
    if (!dhtDataValid) {
      // SAFETY DEFAULT — forced off regardless of mode
      relayOn          = false;
      relaySafetyActive = true;
    } else {
      relaySafetyActive = false;
      if (relayMode == RELAY_MANUAL) {
        relayOn = manualRelayOn;           // user override is authoritative
      } else {
        relayOn = relayHysteresisNext(relayOn, temperature, relayOnTemp, relayOffTemp);
      }
    }
    digitalWrite(RELAY_PIN, relayOn ? LOW : HIGH); // active LOW relay

    if (relayOn != prevRelay || relaySafetyActive != prevSafety) {
      printBlock();
      syncPrintedValues();
    }
  }
}
