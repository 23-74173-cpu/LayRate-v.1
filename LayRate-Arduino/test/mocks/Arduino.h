// Minimal Arduino API mock for host-side (native) unit tests only.
// On device builds the real Arduino framework header is used instead.
#ifndef Arduino_h
#define Arduino_h

#include <stdint.h>
#include <stdio.h>
#include <string.h>
#include <stddef.h>
#include <math.h>

#define HIGH 0x1
#define LOW  0x0
#define INPUT 0x0
#define OUTPUT 0x1
#define INPUT_PULLUP 0x2
#define LED_BUILTIN 13

#define PI       3.1415926535897932384626433832795
#define TWO_PI   6.283185307179586476925286766559
#define HALF_PI  1.5707963267948966192313216916398
#define DEG_TO_RAD 0.017453292519943295769236907684886
#define RAD_TO_DEG 57.295779513082320876798154814105

typedef uint8_t byte;
typedef uint16_t word;
typedef bool boolean;

unsigned long millis();
void pinMode(uint8_t pin, uint8_t mode);
void digitalWrite(uint8_t pin, uint8_t val);
int digitalRead(uint8_t pin);

// Mock serial: captures everything printed so tests can assert on it.
class HardwareSerial {
public:
    static const int BUFSIZE = 2048;
    char buffer[BUFSIZE];
    int len;

    HardwareSerial() : len(0) { buffer[0] = '\0'; }

    void begin(long) {}
    void reset() { len = 0; buffer[0] = '\0'; }

    int available() { return 0; }
    int read() { return -1; }

    void write(char c) {
        if (len < BUFSIZE - 1) buffer[len++] = c;
    }
    void print(const char* s) {
        while (s && *s && len < BUFSIZE - 1) buffer[len++] = *s++;
    }
    void print(char c) { write(c); }
    void print(int v) { len += snprintf(buffer + len, BUFSIZE - len, "%d", v); }
    void print(unsigned int v) { len += snprintf(buffer + len, BUFSIZE - len, "%u", v); }
    void print(long v) { len += snprintf(buffer + len, BUFSIZE - len, "%ld", v); }
    void print(unsigned long v) { len += snprintf(buffer + len, BUFSIZE - len, "%lu", v); }
    void print(float v, int digits) { len += snprintf(buffer + len, BUFSIZE - len, "%.*f", digits, (double) v); }
    void println(const char* s) { print(s); write('\n'); }
    void println(char c) { write(c); write('\n'); }
    void println(int v) { print(v); write('\n'); }
    void println(unsigned int v) { print(v); write('\n'); }
    void println(long v) { print(v); write('\n'); }
    void println(float v, int digits) { print(v, digits); write('\n'); }
    void println() { write('\n'); }
};

extern HardwareSerial Serial;

#endif // Arduino_h
