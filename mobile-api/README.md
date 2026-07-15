# LayRate Mobile API

A minimal Flask REST API for the LayRate mobile app. Designed to run on a Raspberry Pi on the same local network as the Android/iOS app.

## Base URL

The mobile app expects:

```
http://192.168.4.1:8000
```

You can change the host/port with environment variables if needed.

## Project Structure

```
mobile-api/
├── app.py              # Flask application (single-file)
├── requirements.txt    # Python dependencies
├── README.md           # This file
└── layrate_mobile.db   # SQLite database (created on first run)
```

## Installation

1. **Install Python 3.9+ and pip** on the Raspberry Pi.

2. **Create a virtual environment (recommended):**

   ```bash
   cd mobile-api
   python3 -m venv venv
   source venv/bin/activate
   ```

3. **Install dependencies:**

   ```bash
   pip install -r requirements.txt
   ```

## Running the Server

```bash
cd mobile-api
python app.py
```

The server starts on:

```
http://0.0.0.0:8000
```

You can override host/port/debug mode with environment variables:

```bash
FLASK_HOST=0.0.0.0 FLASK_PORT=8000 python app.py
```

### Run on Boot (Optional)

Use a systemd service on Raspberry Pi OS:

1. Create a service file:

   ```bash
   sudo nano /etc/systemd/system/layrate-api.service
   ```

2. Add the following (update paths to match your Pi):

   ```ini
   [Unit]
   Description=LayRate Mobile API
   After=network.target

   [Service]
   Type=simple
   User=pi
   WorkingDirectory=/home/pi/layrate/mobile-api
   ExecStart=/home/pi/layrate/mobile-api/venv/bin/python app.py
   Restart=always

   [Install]
   WantedBy=multi-user.target
   ```

3. Enable and start:

   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable layrate-api
   sudo systemctl start layrate-api
   ```

## Finding the Pi's IP Address

On the Pi, run:

```bash
hostname -I
```

Or:

```bash
ip addr show | grep "inet "
```

Use the IP address in the mobile app base URL, e.g. `http://192.168.4.1:8000`.

## API Endpoints

### Register (optional)

```bash
curl -X POST http://192.168.4.1:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"secret"}'
```

### Login

```bash
curl -X POST http://192.168.4.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@layrate.com","password":"password"}'
```

Response:

```json
{
  "token": "a-unique-bearer-token",
  "user": {
    "id": 1,
    "email": "admin@layrate.com",
    "name": "Admin"
  }
}
```

### Get Incubator Status

```bash
curl -X GET http://192.168.4.1:8000/api/incubator/status \
  -H "Authorization: Bearer <token-from-login>"
```

Response:

```json
{
  "temperature": 37.5,
  "humidity": 55.0,
  "egg_count": 12
}
```

## Default Credentials

| Email                 | Password |
|-----------------------|----------|
| admin@layrate.com     | password |

## Notes

- CORS is enabled for all origins to allow the mobile app to connect from any local IP.
- Passwords are hashed with bcrypt.
- A single bearer token is stored per user. Logging in again generates a new token and invalidates the old one.
- The SQLite database (`layrate_mobile.db`) is created automatically on first run.
- The `incubator_status` table is seeded with defaults: temperature 37.5°C, humidity 55.0%, egg count 12.
