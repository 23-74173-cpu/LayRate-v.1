// Minimal DHT library mock for host-side (native) unit tests only.
// On device builds the real Adafruit DHT library is used instead.
#ifndef DHT_h
#define DHT_h

#include <Arduino.h>

#define DHT11 11
#define DHT12 12
#define DHT21 21
#define DHT22 22

class DHT {
public:
    DHT(uint8_t pin, uint8_t type) {}
    void begin() {}
    float readHumidity(bool force = false) { return 50.0f; }
    float readTemperature(bool S = false, bool force = false) { return 25.0f; }
};

#endif // DHT_h
