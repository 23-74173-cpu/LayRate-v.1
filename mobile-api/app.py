"""LayRate Mobile API.

A minimal Flask REST API for the LayRate mobile app. Runs on a Raspberry Pi
and provides authentication plus sensor status.

Usage:
    python app.py

The server binds to 0.0.0.0:5000. Set environment variables to override:
    FLASK_HOST (default: 0.0.0.0)
    FLASK_PORT (default: 5000)
    FLASK_DEBUG (default: 0)
"""

import os
import secrets
import socket
import sqlite3
import subprocess  # nosec — used for authorized nftables updates only
import atexit
from datetime import datetime, timezone
from functools import wraps
from pathlib import Path

from zeroconf import Zeroconf, ServiceInfo

import bcrypt
import pymysql
from flask import Flask, g, jsonify, request
from flask_cors import CORS

# ── Configuration ───────────────────────────────────────────────────────────
BASE_DIR = Path(__file__).resolve().parent
DATABASE = BASE_DIR / "layrate_mobile.db"

MYSQL_CONFIG = {
    "host": os.getenv("MYSQL_HOST", "127.0.0.1"),
    "port": int(os.getenv("MYSQL_PORT", "3307")),
    "database": os.getenv("MYSQL_DATABASE", "layrate"),
    "user": os.getenv("MYSQL_USER", "root"),
    "password": os.getenv("MYSQL_PASSWORD", "root"),
}

app = Flask(__name__)
CORS(app)  # Allow all origins for local mobile app access

# ── Database helpers ───────────────────────────────────────────────────────

def get_db():
    """Open a new SQLite connection for the current request."""
    if "db" not in g:
        g.db = sqlite3.connect(DATABASE)
        g.db.row_factory = sqlite3.Row
    return g.db


@app.teardown_appcontext
def close_db(exception=None):
    """Close the SQLite connection at the end of the request."""
    db = g.pop("db", None)
    if db is not None:
        db.close()


def get_mysql():
    """Open a new MySQL connection for the current request."""
    if "mysql" not in g:
        g.mysql = pymysql.connect(**MYSQL_CONFIG)
    return g.mysql


@app.teardown_appcontext
def close_mysql(exception=None):
    """Close the MySQL connection at the end of the request."""
    conn = g.pop("mysql", None)
    if conn is not None:
        conn.close()


def init_db():
    """Create tables and seed default data if they do not exist."""
    db = sqlite3.connect(DATABASE)
    db.row_factory = sqlite3.Row
    cursor = db.cursor()

    cursor.executescript(
        """
        CREATE TABLE IF NOT EXISTS auth_tokens (
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            token TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
        """
    )

    db.commit()
    db.close()


def now_iso():
    """Return current UTC time as ISO 8601 string."""
    return datetime.now(timezone.utc).isoformat()


# ── Authentication helpers ─────────────────────────────────────────────────

def generate_token():
    """Generate a URL-safe random token."""
    return secrets.token_urlsafe(32)


def get_user_by_token(token):
    """Look up a user by their bearer token."""
    if not token:
        return None
    db = get_db()
    row = db.execute("SELECT user_id AS id, name, email FROM auth_tokens WHERE token = ?", (token,)).fetchone()
    return dict(row) if row else None


def require_auth(f):
    """Decorator to protect routes with Bearer token authentication."""
    @wraps(f)
    def decorated(*args, **kwargs):
        auth_header = request.headers.get("Authorization", "")
        token = ""
        if auth_header.lower().startswith("bearer "):
            token = auth_header[7:].strip()

        if not token:
            return jsonify({"message": "Authorization header missing or invalid"}), 401

        user = get_user_by_token(token)
        if not user:
            return jsonify({"message": "Invalid or expired token"}), 401

        g.current_user = user
        return f(*args, **kwargs)

    return decorated


# ── nftables client authorization ─────────────────────────────────────────

def authorize_client_ip():
    """Add the requesting client's IP to the nftables authenticated_clients set.

    This allows the client through the walled garden for services that
    have no auth layer of their own (e.g. ICMP ping). Flask's own
    Bearer-token-protected endpoints are already open via the
    unconditional port 5000 accept rule.

    This is called after successful login/registration to make the
    nftables state consistent even though Flask is already open.
    """
    client_ip = request.remote_addr
    if client_ip and client_ip not in ("127.0.0.1", "::1"):
        try:
            subprocess.run(
                ["sudo", "/usr/local/bin/layrate-auth-client", client_ip],
                capture_output=True,
                timeout=5,
            )
        except Exception:
            pass  # Non-critical — auth still works via Bearer token


# ── Routes ─────────────────────────────────────────────────────────────────

@app.route("/api/register", methods=["POST"])
def register():
    """Register a new user in MySQL and return a bearer token."""
    data = request.get_json(silent=True) or {}
    name = data.get("name", "").strip()
    email = data.get("email", "").strip().lower()
    password = data.get("password", "")

    if not name or not email or not password:
        return jsonify({"message": "Name, email, and password are required"}), 400

    password_hash = bcrypt.hashpw(password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")
    # Python generates $2b$ hashes; convert to $2y$ for Laravel/PHP compatibility
    password_hash = password_hash.replace("$2b$", "$2y$")
    conn = get_mysql()
    with conn.cursor(pymysql.cursors.DictCursor) as cursor:
        try:
            cursor.execute(
                "INSERT INTO users (name, email, password, created_at, updated_at) VALUES (%s, %s, %s, NOW(), NOW())",
                (name, email, password_hash),
            )
            conn.commit()
            user_id = cursor.lastrowid
        except pymysql.err.IntegrityError:
            return jsonify({"message": "Email already registered"}), 409

    token = generate_token()
    db = get_db()
    db.execute(
        "INSERT INTO auth_tokens (user_id, name, email, token, created_at) VALUES (?, ?, ?, ?, ?)",
        (user_id, name, email, token, now_iso()),
    )
    db.commit()

    authorize_client_ip()

    return jsonify({"token": token, "user": {"id": user_id, "email": email, "name": name}}), 201


@app.route("/api/login", methods=["POST"])
def login():
    """Validate credentials against MySQL and return a bearer token."""
    data = request.get_json(silent=True) or {}
    email = data.get("email", "").strip().lower()
    password = data.get("password", "")

    if not email or not password:
        return jsonify({"message": "Email and password are required"}), 400

    conn = get_mysql()
    with conn.cursor(pymysql.cursors.DictCursor) as cursor:
        cursor.execute(
            "SELECT id, name, email, password FROM users WHERE email = %s", (email,)
        )
        user = cursor.fetchone()

    if user is None:
        return jsonify({"message": "Invalid email or password"}), 401

    stored_hash = user["password"].replace("$2y$", "$2b$")
    if not bcrypt.checkpw(password.encode("utf-8"), stored_hash.encode("utf-8")):
        return jsonify({"message": "Invalid email or password"}), 401

    token = generate_token()
    db = get_db()
    db.execute(
        "DELETE FROM auth_tokens WHERE user_id = ?", (user["id"],)
    )
    db.execute(
        "INSERT INTO auth_tokens (user_id, name, email, token, created_at) VALUES (?, ?, ?, ?, ?)",
        (user["id"], user["name"], user["email"], token, now_iso()),
    )
    db.commit()

    authorize_client_ip()

    return jsonify(
        {
            "token": token,
            "user": {
                "id": user["id"],
                "email": user["email"],
                "name": user["name"],
            },
        }
    ), 200


@app.route("/api/alerts", methods=["GET"])
@require_auth
def list_alerts():
    """Return alerts with optional is_read filter and limit/offset pagination."""
    limit = request.args.get("limit", 50, type=int)
    offset = request.args.get("offset", 0, type=int)
    limit = min(limit, 200)

    is_read = request.args.get("is_read")
    where = ""
    params = []
    if is_read is not None:
        where = "WHERE a.is_read = %s"
        params.append(int(is_read))

    conn = get_mysql()
    with conn.cursor(pymysql.cursors.DictCursor) as cursor:
        cursor.execute(
            f"""SELECT a.id, a.alert_type, a.message, a.is_read,
                        a.triggered_at, a.created_at,
                        c.cage_code
                 FROM alerts a
                 LEFT JOIN cages c ON c.id = a.cage_id
                 {where}
                 ORDER BY a.triggered_at DESC
                 LIMIT %s OFFSET %s""",
            (*params, limit, offset),
        )
        alerts = cursor.fetchall()

        cursor.execute(
            f"SELECT COUNT(*) AS total FROM alerts a {where}",
            params,
        )
        total = cursor.fetchone()["total"]

    return jsonify({"alerts": alerts, "total": total}), 200


@app.route("/api/alerts/<int:alert_id>/read", methods=["PUT"])
@require_auth
def mark_alert_read(alert_id):
    """Mark a single alert as read."""
    conn = get_mysql()
    with conn.cursor() as cursor:
        cursor.execute("UPDATE alerts SET is_read = 1 WHERE id = %s", (alert_id,))
        conn.commit()
        if cursor.rowcount == 0:
            return jsonify({"message": "Alert not found"}), 404
    return jsonify({"message": "Alert marked as read"}), 200


@app.route("/api/dashboard/status", methods=["GET"])
@require_auth
def dashboard_status():
    """Return latest environmental readings and today's egg production from MySQL for the dashboard."""
    conn = get_mysql()
    with conn.cursor(pymysql.cursors.DictCursor) as cursor:
        cursor.execute(
            "SELECT temperature_c, humidity_pct FROM environmental_logs ORDER BY recorded_at DESC LIMIT 1"
        )
        env = cursor.fetchone()

        cursor.execute(
            "SELECT COALESCE(SUM(egg_count), 0) AS total_eggs FROM production_logs WHERE log_date = CURDATE()"
        )
        eggs = cursor.fetchone()

        cursor.execute(
            "SELECT COALESCE(SUM(current_occupancy), 0) AS total_hens FROM cage_slots"
        )
        hens = cursor.fetchone()

    return jsonify(
        {
            "temperature": float(env["temperature_c"]) if env else 0.0,
            "humidity": float(env["humidity_pct"]) if env else 0.0,
            "egg_count": eggs["total_eggs"] if eggs else 0,
            "total_hens": hens["total_hens"] if hens else 0,
        }
    ), 200


@app.route("/api/environment/live", methods=["GET"])
@require_auth
def environment_live():
    """Return per-cage environment data with status classification.

    Mirrors the web UI's EnvironmentStatusService logic:
      OK:     min < value < max   (strictly inside)
      Watch:  value == min || value == max  (exactly at boundary)
      Alert:  value < min || value > max   (strictly outside)
    Combined status = worst of temp + hum (Alert > Watch > Normal).
    """
    # Default thresholds — will be replaced by GET /api/environment/thresholds once built.
    TEMP_MIN, TEMP_MAX = 18.0, 30.0
    HUM_MIN, HUM_MAX = 40.0, 70.0
    STALE_MINUTES = 30

    def classify(value, lo, hi):
        if value < lo or value > hi:
            return "Alert"
        if value == lo or value == hi:
            return "Watch"
        return "OK"

    def worst(*statuses):
        if "Alert" in statuses:
            return "Alert"
        if "Watch" in statuses:
            return "Watch"
        return "Normal"

    conn = get_mysql()
    with conn.cursor(pymysql.cursors.DictCursor) as cursor:
        cursor.execute(
            """SELECT c.cage_code,
                      e.temperature_c, e.humidity_pct, e.recorded_at
               FROM cages c
               LEFT JOIN environmental_logs e
                 ON e.cage_id = c.id
                 AND e.recorded_at = (
                   SELECT MAX(e2.recorded_at)
                   FROM environmental_logs e2
                   WHERE e2.cage_id = c.id
                 )
               WHERE c.is_active = 1
               ORDER BY c.cage_code"""
        )
        rows = cursor.fetchall()

    from datetime import datetime, timezone, timedelta
    now = datetime.now(timezone.utc)
    stale_cutoff = timedelta(minutes=STALE_MINUTES)

    cages = []
    for r in rows:
        temp = float(r["temperature_c"]) if r["temperature_c"] is not None else None
        hum = float(r["humidity_pct"]) if r["humidity_pct"] is not None else None
        recorded_at = r["recorded_at"]

        if temp is not None and hum is not None and recorded_at is not None:
            temp_status = classify(temp, TEMP_MIN, TEMP_MAX)
            hum_status = classify(hum, HUM_MIN, HUM_MAX)
            combined = worst(temp_status, hum_status)

            if isinstance(recorded_at, datetime):
                if recorded_at.tzinfo is None:
                    recorded_at = recorded_at.replace(tzinfo=timezone.utc)
                is_stale = (now - recorded_at) > stale_cutoff
            else:
                is_stale = True

            cages.append({
                "cageCode": r["cage_code"],
                "temperature": temp,
                "humidity": hum,
                "tempStatus": temp_status,
                "humStatus": hum_status,
                "status": combined,
                "lastReadingAt": recorded_at.isoformat(),
                "isStale": is_stale,
            })
        else:
            # Cage has no environmental data — include with defaults.
            cages.append({
                "cageCode": r["cage_code"],
                "temperature": 0.0,
                "humidity": 0.0,
                "tempStatus": "Alert",
                "humStatus": "Alert",
                "status": "Alert",
                "lastReadingAt": now.isoformat(),
                "isStale": True,
            })

    return jsonify({"cages": cages}), 200


@app.route("/api/environment/thresholds", methods=["GET"])
@require_auth
def get_thresholds():
    """Return current environment threshold values."""
    conn = get_mysql()
    with conn.cursor(pymysql.cursors.DictCursor) as cursor:
        cursor.execute(
            "SELECT `key`, `value` FROM settings WHERE `key` IN ('temp_min','temp_max','hum_min','hum_max')"
        )
        rows = {r["key"]: r["value"] for r in cursor.fetchall()}

    return jsonify({
        "tempMin": float(rows.get("temp_min", 18)),
        "tempMax": float(rows.get("temp_max", 30)),
        "humMin": float(rows.get("hum_min", 40)),
        "humMax": float(rows.get("hum_max", 70)),
    }), 200


@app.route("/api/environment/thresholds", methods=["PUT"])
@require_auth
def update_thresholds():
    """Update environment threshold values."""
    data = request.get_json(silent=True) or {}

    fields = {
        "tempMin": ("temp_min", 0, 50),
        "tempMax": ("temp_max", 0, 50),
        "humMin": ("hum_min", 0, 100),
        "humMax": ("hum_max", 0, 100),
    }

    parsed = {}
    for js_key, (db_key, lo, hi) in fields.items():
        val = data.get(js_key)
        if val is None:
            return jsonify({"errors": {js_key: ["This field is required."]}}), 422
        try:
            val = float(val)
        except (TypeError, ValueError):
            return jsonify({"errors": {js_key: ["Must be a number."]}}), 422
        if val < lo or val > hi:
            return jsonify({"errors": {js_key: [f"Must be between {lo} and {hi}."]}}), 422
        parsed[js_key] = val

    # Cross-field validation.
    if parsed["tempMax"] < parsed["tempMin"]:
        return jsonify({"errors": {"tempMax": ["Must be greater than or equal to tempMin."]}}), 422
    if parsed["humMax"] < parsed["humMin"]:
        return jsonify({"errors": {"humMax": ["Must be greater than or equal to humMin."]}}), 422

    conn = get_mysql()
    with conn.cursor() as cursor:
        for js_key, (db_key, _, _) in fields.items():
            cursor.execute(
                "INSERT INTO settings (`key`, `value`, `updated_at`) VALUES (%s, %s, NOW()) "
                "ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()",
                (db_key, str(parsed[js_key])),
            )
        conn.commit()

    return jsonify({"success": True}), 200


@app.route("/api/ping", methods=["GET"])
def ping():
    """Unauthenticated endpoint used by the mobile app for auto-discovery."""
    return jsonify({"ok": True}), 200


# ── Health check ────────────────────────────────────────────────────────────

@app.route("/api/health", methods=["GET"])
def health():
    """Simple health check for deployment monitoring."""
    return jsonify({"status": "ok"}), 200


# ── Error handlers ─────────────────────────────────────────────────────────

@app.errorhandler(404)
def not_found(error):
    return jsonify({"message": "Endpoint not found"}), 404


@app.errorhandler(405)
def method_not_allowed(error):
    return jsonify({"message": "Method not allowed"}), 405


@app.errorhandler(500)
def internal_error(error):
    return jsonify({"message": "Internal server error"}), 500


# ── mDNS service discovery ─────────────────────────────────────────────────

def _get_local_ip() -> str:
    """Get the LAN IP address of this machine."""
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("10.254.254.254", 1))
        ip = s.getsockname()[0]
    except Exception:
        ip = "127.0.0.1"
    finally:
        s.close()
    return ip


def _start_mdns(port: int) -> Zeroconf:
    """Register this server on the LAN via mDNS so the mobile app can discover it."""
    ip = _get_local_ip()
    info = ServiceInfo(
        "_http._tcp.local.",
        f"Layrate Server._http._tcp.local.",
        addresses=[socket.inet_aton(ip)],
        port=port,
        properties={"path": "/"},
    )
    zc = Zeroconf()
    zc.register_service(info)
    print(f"  mDNS: Layrate Server advertised at http://{ip}:{port}")
    return zc


# ── Main entry point ───────────────────────────────────────────────────────
# __ TEST COMMENT --------------------
if __name__ == "__main__":
    init_db()
    host = os.getenv("FLASK_HOST", "0.0.0.0")
    port = int(os.getenv("FLASK_PORT", "5000"))
    debug = os.getenv("FLASK_DEBUG", "0") == "1"

    if not host.startswith("127."):
        try:
            zc = _start_mdns(port)
            atexit.register(lambda: zc.close())
        except Exception as e:
            print(f"  mDNS: could not register ({e}) — continuing anyway")

    app.run(host=host, port=port, debug=debug)
