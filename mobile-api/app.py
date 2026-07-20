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
import sqlite3
from datetime import datetime, timezone
from functools import wraps
from pathlib import Path

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


# ── Main entry point ───────────────────────────────────────────────────────

if __name__ == "__main__":
    init_db()
    host = os.getenv("FLASK_HOST", "0.0.0.0")
    port = int(os.getenv("FLASK_PORT", "5000"))
    debug = os.getenv("FLASK_DEBUG", "0") == "1"
    app.run(host=host, port=port, debug=debug)
