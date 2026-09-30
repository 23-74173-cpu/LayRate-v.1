#!/usr/bin/env python3
"""Generate the 5 Table IX JMeter plans (.jmx) from one spec.

Single-user load: 1 thread, 1 setup pass (login + value scraping), then a
LoopController x100 around the measured sampler with a 300 ms Constant Timer
(operator pacing; think time is NOT counted in response times).

Conventions (load-bearing for aggregation):
- The measured sampler in each plan is labelled exactly "TC-<n> <name>";
  setup samplers are labelled "SETUP ...". Aggregate ONLY TC-* labels
  (100 requests each) — run_jmeter.sh enforces this by label.
- Auth: HTTP Cookie Manager (Test Plan scope) + GET /login -> Regex _token
  -> POST /login (admin). Threshold values are scraped from a live session
  and posted back UNCHANGED (zero net mutation); a scrape miss yields
  NOT_FOUND -> server-side 422 -> loud failure, never silent clobber.
- ResponseAssertion test_type 16 = Contains on response data.

Usage: python3 build_plans.py   (writes jmeter/plans/*.jmx)
"""
import os

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "plans")

HEADER = """<?xml version="1.0" encoding="UTF-8"?>
<jmeterTestPlan version="1.2" properties="5.0" jmeter="5.6.3">
  <hashTree>
    <TestPlan guiclass="TestPlanGui" testclass="TestPlan" testname="{title}" enabled="true">
      <boolProp name="TestPlan.functional_mode">false</boolProp>
      <boolProp name="TestPlan.tearDown_on_shutdown">false</boolProp>
      <boolProp name="TestPlan.serialize_threadgroups">false</boolProp>
      <elementProp name="TestPlan.user_defined_variables" elementType="Arguments" guiclass="ArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true">
        <collectionProp name="Arguments.arguments">
          <elementProp name="BASE_HOST" elementType="Argument">
            <stringProp name="Argument.name">BASE_HOST</stringProp>
            <stringProp name="Argument.value">LayRatePI.local</stringProp>
            <stringProp name="Argument.metadata">=</stringProp>
          </elementProp>
          <elementProp name="BASE_PORT" elementType="Argument">
            <stringProp name="Argument.name">BASE_PORT</stringProp>
            <stringProp name="Argument.value">80</stringProp>
            <stringProp name="Argument.metadata">=</stringProp>
          </elementProp>
          <elementProp name="ADMIN_EMAIL" elementType="Argument">
            <stringProp name="Argument.name">ADMIN_EMAIL</stringProp>
            <stringProp name="Argument.value">admin@layrate.local</stringProp>
            <stringProp name="Argument.metadata">=</stringProp>
          </elementProp>
          <elementProp name="ADMIN_PASS" elementType="Argument">
            <stringProp name="Argument.name">ADMIN_PASS</stringProp>
            <stringProp name="Argument.value">password</stringProp>
            <stringProp name="Argument.metadata">=</stringProp>
          </elementProp>
        </collectionProp>
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
      <ThreadGroup guiclass="ThreadGroupGui" testclass="ThreadGroup" testname="Single operator" enabled="true">
        <stringProp name="ThreadGroup.on_sample_error">continue</stringProp>
        <elementProp name="ThreadGroup.main_controller" elementType="LoopController" guiclass="LoopControlPanel" testclass="LoopController" testname="Loop Controller" enabled="true">
          <stringProp name="LoopController.loops">1</stringProp>
          <boolProp name="LoopController.continue_forever">false</boolProp>
        </elementProp>
        <stringProp name="ThreadGroup.num_threads">1</stringProp>
        <stringProp name="ThreadGroup.ramp_time">1</stringProp>
        <boolProp name="ThreadGroup.delayedStart">false</boolProp>
        <boolProp name="ThreadGroup.scheduler">false</boolProp>
        <stringProp name="ThreadGroup.duration"></stringProp>
        <stringProp name="ThreadGroup.delay"></stringProp>
        <boolProp name="ThreadGroup.same_user_on_next_iteration">true</boolProp>
      </ThreadGroup>
      <hashTree>
"""

FOOTER = """      </hashTree>
    </hashTree>
  </hashTree>
</jmeterTestPlan>
"""

LOGIN_SEQ = """        <HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="SETUP GET login" enabled="true">
          <boolProp name="HTTPSampler.postBodyRaw">false</boolProp>
          <elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true">
            <collectionProp name="Arguments.arguments"/>
          </elementProp>
          <stringProp name="HTTPSampler.domain">${__P(BASE_HOST,LayRatePI.local)}</stringProp>
          <stringProp name="HTTPSampler.port">${__P(BASE_PORT,80)}</stringProp>
          <stringProp name="HTTPSampler.protocol">http</stringProp>
          <stringProp name="HTTPSampler.path">/login</stringProp>
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
          <RegexExtractor guiclass="RegexExtractorGui" testclass="RegexExtractor" testname="Extract CSRF token" enabled="true">
            <stringProp name="RegexExtractor.useHeaders">false</stringProp>
            <stringProp name="RegexExtractor.refname">CSRF_TOKEN</stringProp>
            <stringProp name="RegexExtractor.regex">name="_token" value="([^"]+)"</stringProp>
            <stringProp name="RegexExtractor.template">$1$</stringProp>
            <stringProp name="RegexExtractor.default">NOT_FOUND</stringProp>
            <stringProp name="RegexExtractor.match_number">1</stringProp>
          </RegexExtractor>
          <hashTree/>
        </hashTree>
        <HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="SETUP POST login" enabled="true">
          <boolProp name="HTTPSampler.postBodyRaw">false</boolProp>
          <elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true">
            <collectionProp name="Arguments.arguments">
              <elementProp name="_token" elementType="HTTPArgument">
                <boolProp name="HTTPArgument.always_encode">false</boolProp>
                <stringProp name="Argument.value">${CSRF_TOKEN}</stringProp>
                <stringProp name="Argument.metadata">=</stringProp>
                <boolProp name="HTTPArgument.use_equals">true</boolProp>
                <stringProp name="Argument.name">_token</stringProp>
              </elementProp>
              <elementProp name="email" elementType="HTTPArgument">
                <boolProp name="HTTPArgument.always_encode">false</boolProp>
                <stringProp name="Argument.value">${__P(ADMIN_EMAIL,admin@layrate.local)}</stringProp>
                <stringProp name="Argument.metadata">=</stringProp>
                <boolProp name="HTTPArgument.use_equals">true</boolProp>
                <stringProp name="Argument.name">email</stringProp>
              </elementProp>
              <elementProp name="password" elementType="HTTPArgument">
                <boolProp name="HTTPArgument.always_encode">false</boolProp>
                <stringProp name="Argument.value">${__P(ADMIN_PASS,password)}</stringProp>
                <stringProp name="Argument.metadata">=</stringProp>
                <boolProp name="HTTPArgument.use_equals">true</boolProp>
                <stringProp name="Argument.name">password</stringProp>
              </elementProp>
            </collectionProp>
          </elementProp>
          <stringProp name="HTTPSampler.domain">${__P(BASE_HOST,LayRatePI.local)}</stringProp>
          <stringProp name="HTTPSampler.port">${__P(BASE_PORT,80)}</stringProp>
          <stringProp name="HTTPSampler.protocol">http</stringProp>
          <stringProp name="HTTPSampler.path">/login</stringProp>
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
          <RegexExtractor guiclass="RegexExtractorGui" testclass="RegexExtractor" testname="Extract fresh CSRF token" enabled="true">
            <stringProp name="RegexExtractor.useHeaders">false</stringProp>
            <stringProp name="RegexExtractor.refname">CSRF_FRESH</stringProp>
            <stringProp name="RegexExtractor.regex">name="csrf-token" content="([^"]+)"</stringProp>
            <stringProp name="RegexExtractor.template">$1$</stringProp>
            <stringProp name="RegexExtractor.default">NOT_FOUND</stringProp>
            <stringProp name="RegexExtractor.match_number">1</stringProp>
          </RegexExtractor>
          <hashTree/>
        </hashTree>
"""


def sampler(name, method, path, params=(), assertions=(), headers=(), children=""):
    """params: [(name, value)]; assertions: [marker]; headers: [(name, value)];
    children: extra XML nested inside the sampler's hashTree (extractors)."""
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
    xml = f"""        <HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="{name}" enabled="true">
          <boolProp name="HTTPSampler.postBodyRaw">false</boolProp>
          <elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments" testname="User Defined Variables" enabled="true">
            <collectionProp name="Arguments.arguments">
{args}            </collectionProp>
          </elementProp>
          <stringProp name="HTTPSampler.domain">${{BASE_HOST}}</stringProp>
          <stringProp name="HTTPSampler.port">${{BASE_PORT}}</stringProp>
          <stringProp name="HTTPSampler.protocol">http</stringProp>
          <stringProp name="HTTPSampler.path">{path}</stringProp>
          <stringProp name="HTTPSampler.method">{method}</stringProp>
          <boolProp name="HTTPSampler.follow_redirects">true</boolProp>
          <boolProp name="HTTPSampler.auto_redirects">false</boolProp>
          <boolProp name="HTTPSampler.use_keepalive">true</boolProp>
          <boolProp name="HTTPSampler.DO_MULTIPART_POST">false</boolProp>
          <stringProp name="HTTPSampler.embedded_url_re"></stringProp>
          <stringProp name="HTTPSampler.connect_timeout"></stringProp>
          <stringProp name="HTTPSampler.response_timeout"></stringProp>
        </HTTPSamplerProxy>
        <hashTree>
"""
    for hname, hvalue in headers:
        xml += f"""          <HeaderManager guiclass="HeaderPanel" testclass="HeaderManager" testname="HTTP Header Manager" enabled="true">
            <collectionProp name="HeaderManager.headers">
              <elementProp name="" elementType="Header">
                <stringProp name="Header.name">{hname}</stringProp>
                <stringProp name="Header.value">{hvalue}</stringProp>
              </elementProp>
            </collectionProp>
          </HeaderManager>
          <hashTree/>
"""
    for marker in assertions:
        safe = marker.replace(chr(34), chr(39))
        xml += f"""          <ResponseAssertion guiclass="AssertionGui" testclass="ResponseAssertion" testname="Assert contains {safe}" enabled="true">
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
    xml += children + "        </hashTree>\n"
    return xml


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


def loop100(sampler_xml):
    return f"""        <LoopController guiclass="LoopControlPanel" testclass="LoopController" testname="100 requests" enabled="true">
          <stringProp name="LoopController.loops">100</stringProp>
          <boolProp name="LoopController.continue_forever">false</boolProp>
        </LoopController>
        <hashTree>
{sampler_xml}          <ConstantTimer guiclass="ConstantTimerGui" testclass="ConstantTimer" testname="Pacing 300ms" enabled="true">
            <stringProp name="ConstantTimer.delay">300</stringProp>
          </ConstantTimer>
          <hashTree/>
        </hashTree>
"""


def threshold_scrape():
    """GET /environment + extract the 4 live threshold values (posted back unchanged)."""
    ext = ""
    for ref, field in [("TMIN", "temp_min"), ("TMAX", "temp_max"),
                       ("HMIN", "hum_min"), ("HMAX", "hum_max")]:
        ext += regex_extract(
            f"Extract {field}", ref,
            f'name="{field}"[^>]*value="([^"]+)"')
    return sampler("SETUP GET environment thresholds", "GET", "/environment",
                   children=ext)


def threshold_post():
    return sampler(
        "TC-5 Alert Threshold Configuration", "POST", "/environment/thresholds",
        params=[("_token", "${CSRF_FRESH}"), ("temp_min", "${TMIN}"),
                ("temp_max", "${TMAX}"), ("hum_min", "${HMIN}"),
                ("hum_max", "${HMAX}")],
        assertions=['"success":true'],
        headers=[("Accept", "application/json")],
    )


PLANS = {
    "01-dashboard-loading": (
        "Table IX JMeter 1/5 - Dashboard Loading",
        LOGIN_SEQ + loop100(sampler(
            "TC-1 Dashboard Loading", "GET", "/dashboard",
            assertions=['data-route="dashboard"'],
        )),
    ),
    "02-sensor-retrieval": (
        "Table IX JMeter 2/5 - Real-Time Sensor Data Retrieval",
        LOGIN_SEQ + loop100(sampler(
            "TC-2 Real-Time Sensor Data Retrieval",
            "GET", "/environment/live-data?range=24h",
            assertions=["environment-live-data"],
        )),
    ),
    "03-forecast-retrieval": (
        "Table IX JMeter 3/5 - Forecast Retrieval",
        LOGIN_SEQ + loop100(sampler(
            "TC-3 Forecast Retrieval", "GET", "/forecast?scope=farm&amp;horizon=7",
            assertions=["forecast-workspace"],
        )),
    ),
    "04-report-generation": (
        "Table IX JMeter 4/5 - Historical Report Generation",
        LOGIN_SEQ + loop100(sampler(
            "TC-4 Historical Report Generation", "GET",
            "/reports?type=production&amp;from=2026-05-01&amp;to=2026-05-31&amp;cage=all",
            assertions=["Total Eggs"],
        )),
    ),
    "05-threshold-config": (
        "Table IX JMeter 5/5 - Alert Threshold Configuration",
        LOGIN_SEQ + threshold_scrape() + loop100(threshold_post()),
    ),
}

# NOTE: query-string & in XML attribute/text content MUST be &amp; (see TC-3/TC-4 paths above).


def main():
    os.makedirs(OUT, exist_ok=True)
    for fname, (title, body) in PLANS.items():
        with open(os.path.join(OUT, fname + ".jmx"), "w", encoding="utf-8") as f:
            f.write(HEADER.format(title=title))
            f.write(body)
            f.write(FOOTER)
        print("wrote", fname + ".jmx")


if __name__ == "__main__":
    main()
