#!/usr/bin/env python3
"""Throughput plans (Table: sustained RPS): 06/07/08.

Load shape (documented choice): 5 threads ~= maximum realistic concurrent
operators on a small-to-medium farm, ramp 5s (staggered arrival), scheduler
60s with LoopController forever (sustained closed-loop load, NO think time:
devices post continuously and this measures serving rate, not patience).
Override at runtime: -JTHREADS= -JRAMP= -JDURATION= (seconds).

Conventions:
- Thread groups run SERIALLY (TestPlan.serialize_threadgroups=true):
  setup-login group first, load group second. The Test-Plan-level Cookie
  Manager is shared, so the load threads reuse the setup session.
- Measured sampler labels start with "TC-T"; setup samplers start with
  "SETUP". Aggregate ONLY TC-T* labels.
- Ingestion uses a FIXED whole-second recorded_at on 2020-01-01 (predates
  the deployment, so rows are provably synthetic) with IR count=0 (valid
  for any slot: zero-egg writes keep sums intact; reruns collapse via
  updateOrCreate). Device key via -JDEVICE_KEY (never committed).
- Reads carry a code-200 assertion plus a content marker; the ingestion
  POST additionally asserts the accepted marker.

Usage: python3 build_throughput_plans.py   (writes plans/06|07|08-*.jmx)
"""
import os

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "plans")

HEADER_TP = """<?xml version="1.0" encoding="UTF-8"?>
<jmeterTestPlan version="1.2" properties="5.0" jmeter="5.6.3">
  <hashTree>
    <TestPlan guiclass="TestPlanGui" testclass="TestPlan" testname="{title}" enabled="true">
      <boolProp name="TestPlan.functional_mode">false</boolProp>
      <boolProp name="TestPlan.tearDown_on_shutdown">false</boolProp>
      <boolProp name="TestPlan.serialize_threadgroups">true</boolProp>
      <elementProp name="TestPlan.user_defined_variables" elementType="Arguments" guiclass="ArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true">
        <collectionProp name="Arguments.arguments"/>
      </elementProp>
    </TestPlan>
    <hashTree>
      <CookieManager guiclass="CookiePanel" testclass="CookieManager" testname="HTTP Cookie Manager" enabled="true">
        <collectionProp name="CookieManager.cookies"/>
        <boolProp name="CookieManager.clearEachIteration">false</boolProp>
        <boolProp name="CookieManager.controlledByThreadGroup">false</boolProp>
        <stringProp name="CookieManager.policy">standard</stringProp>
        <stringProp name="CookieManager.implementation">org.apache.jmeter.protocol.http.control.HC4CookieHandler</stringProp>
      </CookieManager>
      <hashTree/>
"""

FOOTER = """    </hashTree>
  </hashTree>
</jmeterTestPlan>
"""


def thread_group(name, threads, ramp, scheduler=False, duration=60):
    sched = f"""        <boolProp name="ThreadGroup.scheduler">{str(scheduler).lower()}</boolProp>
        <stringProp name="ThreadGroup.duration">{duration}</stringProp>""" if scheduler else """        <boolProp name="ThreadGroup.scheduler">false</boolProp>
        <stringProp name="ThreadGroup.duration"></stringProp>"""
    loops = "-1" if scheduler else "1"
    return f"""      <ThreadGroup guiclass="ThreadGroupGui" testclass="ThreadGroup" testname="{name}" enabled="true">
        <stringProp name="ThreadGroup.on_sample_error">continue</stringProp>
        <elementProp name="ThreadGroup.main_controller" elementType="LoopController" guiclass="LoopControlPanel" testclass="LoopController" testname="Loop Controller" enabled="true">
          <stringProp name="LoopController.loops">{loops}</stringProp>
          <boolProp name="LoopController.continue_forever">false</boolProp>
        </elementProp>
        <stringProp name="ThreadGroup.num_threads">{threads}</stringProp>
        <stringProp name="ThreadGroup.ramp_time">{ramp}</stringProp>
        <boolProp name="ThreadGroup.delayedStart">false</boolProp>
{sched}
        <stringProp name="ThreadGroup.delay"></stringProp>
        <boolProp name="ThreadGroup.same_user_on_next_iteration">true</boolProp>
      </ThreadGroup>
      <hashTree>
"""


def get_sampler(name, path, children=""):
    return f"""        <HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="{name}" enabled="true">
          <boolProp name="HTTPSampler.postBodyRaw">false</boolProp>
          <elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true">
            <collectionProp name="Arguments.arguments"/>
          </elementProp>
          <stringProp name="HTTPSampler.domain">${{__P(BASE_HOST,LayRatePI.local)}}</stringProp>
          <stringProp name="HTTPSampler.port">${{__P(BASE_PORT,80)}}</stringProp>
          <stringProp name="HTTPSampler.protocol">http</stringProp>
          <stringProp name="HTTPSampler.path">{path}</stringProp>
          <stringProp name="HTTPSampler.method">GET</stringProp>
          <boolProp name="HTTPSampler.follow_redirects">true</boolProp>
          <boolProp name="HTTPSampler.auto_redirects">false</boolProp>
          <boolProp name="HTTPSampler.use_keepalive">true</boolProp>
          <boolProp name="HTTPSampler.DO_MULTIPART_POST">false</boolProp>
          <stringProp name="HTTPSampler.embedded_url_re"></stringProp>
          <stringProp name="HTTPSampler.connect_timeout"></stringProp>
          <stringProp name="HTTPSampler.response_timeout"></stringProp>
        </HTTPSamplerProxy>
        <hashTree>
{children}        </hashTree>
"""


def form_post_sampler(name, path, params, children=""):
    args = ""
    for pname, pvalue in params:
        args += f"""              <elementProp name="{pname}" elementType="HTTPArgument">
                <boolProp name="HTTPArgument.always_encode">false</boolProp>
                <stringProp name="Argument.value">{pvalue}</stringProp>
                <stringProp name="Argument.metadata">=</stringProp>
                <boolProp name="HTTPArgument.use_equals">true</boolProp>
                <stringProp name="Argument.name">{pname}</stringProp>
              </elementProp>
"""
    return f"""        <HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="{name}" enabled="true">
          <boolProp name="HTTPSampler.postBodyRaw">false</boolProp>
          <elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true">
            <collectionProp name="Arguments.arguments">
{args}            </collectionProp>
          </elementProp>
          <stringProp name="HTTPSampler.domain">${{__P(BASE_HOST,LayRatePI.local)}}</stringProp>
          <stringProp name="HTTPSampler.port">${{__P(BASE_PORT,80)}}</stringProp>
          <stringProp name="HTTPSampler.protocol">http</stringProp>
          <stringProp name="HTTPSampler.path">{path}</stringProp>
          <stringProp name="HTTPSampler.method">POST</stringProp>
          <boolProp name="HTTPSampler.follow_redirects">true</boolProp>
          <boolProp name="HTTPSampler.auto_redirects">false</boolProp>
          <boolProp name="HTTPSampler.use_keepalive">true</boolProp>
          <boolProp name="HTTPSampler.DO_MULTIPART_POST">false</boolProp>
          <stringProp name="HTTPSampler.embedded_url_re"></stringProp>
          <stringProp name="HTTPSampler.connect_timeout"></stringProp>
          <stringProp name="HTTPSampler.response_timeout"></stringProp>
        </HTTPSamplerProxy>
        <hashTree>
{children}        </hashTree>
"""


def raw_post_sampler(name, path, body_escaped, headers, children=""):
    hdrs = ""
    for hname, hvalue in headers:
        hdrs += f"""          <HeaderManager guiclass="HeaderPanel" testclass="HeaderManager" testname="HTTP Header Manager" enabled="true">
            <collectionProp name="HeaderManager.headers">
              <elementProp name="" elementType="Header">
                <stringProp name="Header.name">{hname}</stringProp>
                <stringProp name="Header.value">{hvalue}</stringProp>
              </elementProp>
            </collectionProp>
          </HeaderManager>
          <hashTree/>
"""
    return f"""        <HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="{name}" enabled="true">
          <boolProp name="HTTPSampler.postBodyRaw">true</boolProp>
          <elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true">
            <collectionProp name="Arguments.arguments">
              <elementProp name="" elementType="HTTPArgument">
                <boolProp name="HTTPArgument.always_encode">false</boolProp>
                <stringProp name="Argument.value">{body_escaped}</stringProp>
                <stringProp name="Argument.metadata">=</stringProp>
                <boolProp name="HTTPArgument.use_equals">true</boolProp>
                <stringProp name="Argument.name"></stringProp>
              </elementProp>
            </collectionProp>
          </elementProp>
          <stringProp name="HTTPSampler.domain">${{__P(BASE_HOST,LayRatePI.local)}}</stringProp>
          <stringProp name="HTTPSampler.port">${{__P(BASE_PORT,80)}}</stringProp>
          <stringProp name="HTTPSampler.protocol">http</stringProp>
          <stringProp name="HTTPSampler.path">{path}</stringProp>
          <stringProp name="HTTPSampler.method">POST</stringProp>
          <boolProp name="HTTPSampler.follow_redirects">true</boolProp>
          <boolProp name="HTTPSampler.auto_redirects">false</boolProp>
          <boolProp name="HTTPSampler.use_keepalive">true</boolProp>
          <boolProp name="HTTPSampler.DO_MULTIPART_POST">false</boolProp>
          <stringProp name="HTTPSampler.embedded_url_re"></stringProp>
          <stringProp name="HTTPSampler.connect_timeout"></stringProp>
          <stringProp name="HTTPSampler.response_timeout"></stringProp>
        </HTTPSamplerProxy>
        <hashTree>
{hdrs}{children}        </hashTree>
"""


def regex_extract(name, refname, pattern):
    return f"""          <RegexExtractor guiclass="RegexExtractorGui" testclass="RegexExtractor" testname="{name}" enabled="true">
            <stringProp name="RegexExtractor.useHeaders">false</stringProp>
            <stringProp name="RegexExtractor.refname">{refname}</stringProp>
            <stringProp name="RegexExtractor.regex">{pattern}</stringProp>
            <stringProp name="RegexExtractor.template">$1$</stringProp>
            <stringProp name="RegexExtractor.default">NOT_FOUND</stringProp>
            <stringProp name="RegexExtractor.match_number">1</stringProp>
          </RegexExtractor>
          <hashTree/>
"""


def code_assert():
    return """          <ResponseAssertion guiclass="AssertionGui" testclass="ResponseAssertion" testname="Assert 200" enabled="true">
            <collectionProp name="Asserion.test_strings">
              <stringProp name="test">200</stringProp>
            </collectionProp>
            <stringProp name="Assertion.custom_message"></stringProp>
            <stringProp name="Assertion.test_field">Assertion.response_code</stringProp>
            <boolProp name="Assertion.assume_success">false</boolProp>
            <intProp name="Assertion.test_type">16</intProp>
          </ResponseAssertion>
          <hashTree/>
"""


def content_assert(marker):
    safe = marker.replace('"', "'")
    return f"""          <ResponseAssertion guiclass="AssertionGui" testclass="ResponseAssertion" testname="Assert contains {safe}" enabled="true">
            <collectionProp name="Asserion.test_strings">
              <stringProp name="test">{marker}</stringProp>
            </collectionProp>
            <stringProp name="Assertion.custom_message"></stringProp>
            <stringProp name="Assertion.test_field">Assertion.response_data</stringProp>
            <boolProp name="Assertion.assume_success">false</boolProp>
            <intProp name="Assertion.test_type">16</intProp>
          </ResponseAssertion>
          <hashTree/>
"""


def login_setup(admin_email="${__P(ADMIN_EMAIL,admin@layrate.local)}",
                admin_pass="${__P(ADMIN_PASS,password)}"):
    get = get_sampler(
        "SETUP GET login", "/login",
        children=regex_extract("Extract CSRF token", "CSRF_TOKEN",
                               'name="_token" value="([^"]+)"'))
    post = form_post_sampler(
        "SETUP POST login", "/login",
        params=[("_token", "${CSRF_TOKEN}"), ("email", admin_email),
                ("password", admin_pass)],
        children=regex_extract("Extract fresh CSRF token", "CSRF_FRESH",
                               'name="csrf-token" content="([^"]+)"'))
    return get + post


LOAD_OPEN = "${__P(THREADS,5)}", "${__P(RAMP,5)}"


def once_only(inner):
    return f"""        <OnceOnlyController guiclass="OnceOnlyControllerGui" testclass="OnceOnlyController" testname="Once Only Login" enabled="true">
        </OnceOnlyController>
        <hashTree>
{inner}        </hashTree>
"""


def loop_forever(body):
    return f"""        <LoopController guiclass="LoopControlPanel" testclass="LoopController" testname="Sustain" enabled="true">
          <stringProp name="LoopController.loops">-1</stringProp>
          <boolProp name="LoopController.continue_forever">true</boolProp>
        </LoopController>
        <hashTree>
{body}        </hashTree>
"""


def load_group_sessioned(login_body, sampler_body):
    """One group, 5 threads, 60s scheduler: per-thread login once, then loop."""
    t, r = LOAD_OPEN
    return (thread_group("Sustained load", t, r, scheduler=True,
                         duration="${__P(DURATION,60)}")
            + once_only(login_body) + loop_forever(sampler_body)
            + "      </hashTree>\n")


def load_group_anon(sampler_body):
    t, r = LOAD_OPEN
    return (thread_group("Sustained load", t, r, scheduler=True,
                         duration="${__P(DURATION,60)}")
            + loop_forever(sampler_body) + "      </hashTree>\n")



INGEST_BODY = ("{&quot;recorded_at&quot;:&quot;2020-01-01T12:00:00+00:00&quot;,"
               "&quot;readings&quot;:[{&quot;serial_number&quot;:&quot;${__P(DHT_SERIAL,DHT22-001)}&quot;,"
               "&quot;temperature_c&quot;:28.5,&quot;humidity_pct&quot;:67.0},"
               "{&quot;serial_number&quot;:&quot;${__P(IR_SERIAL,IRBBS-001)}&quot;,&quot;count&quot;:0}]}")

PLANS = {
    "06-throughput-ingestion": (
        "Table IX JMeter TP-1/3 - Sustained Sensor Ingestion",
        load_group_anon(raw_post_sampler(
            "TC-T1 Sustained Sensor Ingestion", "/api/sensor-readings",
            INGEST_BODY,
            headers=[("Content-Type", "application/json"),
                     ("Accept", "application/json"),
                     ("X-Device-Key", "${__P(DEVICE_KEY,REPLACE-ME)}")],
            children=code_assert() + content_assert("accepted"),
        )),
    ),
    "07-throughput-retrieval": (
        "Table IX JMeter TP-2/3 - Sustained Real-Time Retrieval",
        load_group_sessioned(login_setup(), get_sampler(
            "TC-T2 Sustained Real-Time Retrieval",
            "/environment/live-data?range=24h",
            children=code_assert() + content_assert("environment-live-data"),
        )),
    ),
    "08-throughput-forecast": (
        "Table IX JMeter TP-3/3 - Sustained Forecast Retrieval",
        load_group_sessioned(login_setup(), get_sampler(
            "TC-T3 Sustained Forecast Retrieval",
            "/forecast?scope=farm&amp;horizon=7",
            children=code_assert() + content_assert("forecast-workspace"),
        )),
    ),
}

# NOTE: & in paths MUST stay &amp; (see TC-T3 path above).


def main():
    os.makedirs(OUT, exist_ok=True)
    for fname, (title, body) in PLANS.items():
        with open(os.path.join(OUT, fname + ".jmx"), "w", encoding="utf-8") as f:
            f.write(HEADER_TP.format(title=title))
            f.write(body)
            f.write(FOOTER)
        print("wrote", fname + ".jmx")


if __name__ == "__main__":
    main()
