// Native host tests for the firmware relay/fan logic.
//
// Includes the real src/main.cpp against the mocked Arduino/DHT headers so we
// exercise the actual code: boot-time threshold defaults, the THRESH command
// parser, and the hysteresis decision with runtime-configurable thresholds.
//
// Run with:  pio test -e native
//
// On device builds (ARDUINO defined) the Arduino framework provides the real
// Serial global, so we skip defining the mock's.

#include <unity.h>
#include <Arduino.h>
#include <DHT.h>

#ifndef ARDUINO
HardwareSerial Serial; // mock global used by main.cpp on native builds
#endif

#include "../src/main.cpp"

// -- host stubs for the Arduino API used inside main.cpp -------------------
static unsigned long _mock_millis = 0;
unsigned long millis() { return _mock_millis; }
void pinMode(uint8_t, uint8_t) {}
void digitalWrite(uint8_t, uint8_t) {}
int digitalRead(uint8_t) { return LOW; }

void setUp(void) { Serial.reset(); }
void tearDown(void) {}

static void test_boot_defaults_match_historical_thresholds(void) {
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 35.0f, relayOnTemp);
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 30.0f, relayOffTemp);
}

static void test_thresh_command_updates_thresholds(void) {
    handleCommandLine("THRESH:31.0:26.0");
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 31.0f, relayOnTemp);
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 26.0f, relayOffTemp);
    TEST_ASSERT(strstr(Serial.buffer, "CFM:THRESH:OK") != NULL);
}

static void test_invalid_thresh_is_rejected_and_keeps_last_values(void) {
    handleCommandLine("THRESH:31.0:26.0");
    Serial.reset();

    handleCommandLine("THRESH:20.0:25.0"); // ON below OFF — invalid
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 31.0f, relayOnTemp);
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 26.0f, relayOffTemp);
    TEST_ASSERT(strstr(Serial.buffer, "CFM:THRESH:INVALID") != NULL);

    Serial.reset();
    handleCommandLine("THRESH:99.0:98.0"); // absurdly hot — invalid
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 31.0f, relayOnTemp);
    TEST_ASSERT(strstr(Serial.buffer, "CFM:THRESH:INVALID") != NULL);
}

static void test_malformed_thresh_is_rejected(void) {
    handleCommandLine("THRESH:31.0:26.0");
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 31.0f, relayOnTemp);
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 26.0f, relayOffTemp);

    // Truncated: only one value — must be rejected, values unchanged.
    Serial.reset();
    handleCommandLine("THRESH:31.0");
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 31.0f, relayOnTemp);
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 26.0f, relayOffTemp);
    TEST_ASSERT(strstr(Serial.buffer, "CFM:THRESH:INVALID") != NULL);

    // Non-numeric — rejected.
    Serial.reset();
    handleCommandLine("THRESH:abc:def");
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 31.0f, relayOnTemp);
    TEST_ASSERT_FLOAT_WITHIN(0.001f, 26.0f, relayOffTemp);
    TEST_ASSERT(strstr(Serial.buffer, "CFM:THRESH:INVALID") != NULL);
}

static void test_hysteresis_uses_runtime_thresholds(void) {
    // Set an explicit pair so the test is independent of test execution order.
    handleCommandLine("THRESH:35.0:30.0"); // the boot-default equivalent
    Serial.reset();

    // ON at >= 35C, OFF below 30C.
    TEST_ASSERT_FALSE(relayHysteresisNext(false, 34.9f, relayOnTemp, relayOffTemp));
    TEST_ASSERT_TRUE(relayHysteresisNext(false, 35.0f, relayOnTemp, relayOffTemp));
    TEST_ASSERT_TRUE(relayHysteresisNext(true, 30.0f, relayOnTemp, relayOffTemp));
    TEST_ASSERT_FALSE(relayHysteresisNext(true, 29.9f, relayOnTemp, relayOffTemp));

    // After a THRESH command the same logic follows the new pair.
    handleCommandLine("THRESH:31.0:26.0");
    TEST_ASSERT_FALSE(relayHysteresisNext(false, 30.9f, relayOnTemp, relayOffTemp));
    TEST_ASSERT_TRUE(relayHysteresisNext(false, 31.0f, relayOnTemp, relayOffTemp));
    TEST_ASSERT_TRUE(relayHysteresisNext(true, 26.0f, relayOnTemp, relayOffTemp));
    TEST_ASSERT_FALSE(relayHysteresisNext(true, 25.9f, relayOnTemp, relayOffTemp));
}

static void test_relay_commands_still_work_alongside_thresh(void) {
    handleCommandLine("RELAY:ON");
    TEST_ASSERT_EQUAL(RELAY_MANUAL, relayMode);
    TEST_ASSERT_TRUE(manualRelayOn);
    TEST_ASSERT(strstr(Serial.buffer, "CFM:RELAY:OK") != NULL);

    Serial.reset();
    handleCommandLine("RELAY:AUTO");
    TEST_ASSERT_EQUAL(RELAY_AUTO, relayMode);
}

int main(void) {
    UNITY_BEGIN();
    RUN_TEST(test_boot_defaults_match_historical_thresholds);
    RUN_TEST(test_thresh_command_updates_thresholds);
    RUN_TEST(test_invalid_thresh_is_rejected_and_keeps_last_values);
    RUN_TEST(test_malformed_thresh_is_rejected);
    RUN_TEST(test_hysteresis_uses_runtime_thresholds);
    RUN_TEST(test_relay_commands_still_work_alongside_thresh);
    return UNITY_END();
}
