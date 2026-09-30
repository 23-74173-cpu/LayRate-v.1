#!/usr/bin/env python3
"""
Seed hardware mock for CAGE-T setup:
- 1 relay (cage-level, CAGE-T)
- 1 pair IR breakbeam (2 sensors, same slot 61 in CAGE-T)
- 1 DHT22 (cage-level, CAGE-T)
All active, assigned to Raspberry Pi device_id=1.
Wipes existing hardware first to match the exact setup description.
"""
import os, pymysql
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
def load_env(path):
    env={}
    with open(path) as f:
        for line in f:
            line=line.strip()
            if not line or line.startswith('#') or '=' not in line: continue
            k,v=line.split('=',1)
            env[k.strip()]=v.strip().strip('"').strip("'")
    return env
env=load_env(os.path.join(ROOT,'.env'))
conn=pymysql.connect(host=env.get('DB_HOST','127.0.0.1'), port=int(env.get('DB_PORT',3306)), user=env.get('DB_USERNAME','root'), password=env.get('DB_PASSWORD',''), database=env.get('DB_DATABASE','layrate'), autocommit=False)
cur=conn.cursor()
try:
    cur.execute("SELECT id, cage_code FROM cages WHERE cage_code='CAGE-T'")
    row=cur.fetchone()
    if not row:
        raise SystemExit("CAGE-T not found")
    cage_t_id=row[0]
    cur.execute("SELECT id FROM cage_slots WHERE cage_id=%s ORDER BY slot_number LIMIT 1", (cage_t_id,))
    slot_id=cur.fetchone()[0]
    cur.execute("SELECT id FROM devices WHERE name='Raspberry Pi' LIMIT 1")
    dev_row=cur.fetchone()
    device_id=dev_row[0] if dev_row else 1
    print(f"CAGE-T id={cage_t_id} slot_id={slot_id} device_id={device_id}")

    cur.execute("SELECT COUNT(*) FROM hardware_items")
    print("Existing hardware:", cur.fetchone()[0])
    cur.execute("DELETE FROM hardware_items")
    print(f"Wiped {cur.rowcount} hardware_items")

    # 1 x DHT22 on CAGE-T — installed 2 months ago, last reading 2 sec ago, online
    cur.execute("""
        INSERT INTO hardware_items (device_type, serial_number, cage_id, cage_slot_id, device_id, installation_date, status, health_state, control_mode, relay_status, relay_safety, last_calibration_date, last_valid_reading_at)
        VALUES ('DHT22', 'DHT22-CAGET-01', %s, NULL, %s, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'active', 'online', 'auto', NULL, 0, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), UTC_TIMESTAMP() - INTERVAL 2 SECOND)
    """, (cage_t_id, device_id))
    dht_id = cur.lastrowid
    print(f"DHT22 id={dht_id}")

    # 2 x IR breakbeam pair on same slot (entry + exit) — same timestamps
    ir_ids = []
    for i, sn in enumerate(["IR-CAGET-01A","IR-CAGET-01B"], start=1):
        cur.execute("""
            INSERT INTO hardware_items (device_type, serial_number, cage_id, cage_slot_id, device_id, installation_date, status, health_state, last_valid_reading_at, last_calibration_date)
            VALUES ('IR_breakbeam', %s, NULL, %s, %s, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'active', 'online', UTC_TIMESTAMP() - INTERVAL 2 SECOND, DATE_SUB(CURDATE(), INTERVAL 2 MONTH))
        """, (sn, slot_id, device_id))
        ir_ids.append(cur.lastrowid)
        print(f"IR pair {i} id={cur.lastrowid} sn={sn} slot={slot_id}")

    # 1 x relay on CAGE-T (cage-level) — same
    cur.execute("""
        INSERT INTO hardware_items (device_type, serial_number, cage_id, cage_slot_id, device_id, installation_date, status, health_state, relay_status, control_mode, relay_safety, last_changed_at, relay_seen_at, last_valid_reading_at, last_calibration_date)
        VALUES ('relay', 'RELAY-CAGET-01', %s, NULL, %s, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'active', 'online', 'off', 'auto', 1, UTC_TIMESTAMP() - INTERVAL 2 SECOND, UTC_TIMESTAMP() - INTERVAL 2 SECOND, UTC_TIMESTAMP() - INTERVAL 2 SECOND, DATE_SUB(CURDATE(), INTERVAL 2 MONTH))
    """, (cage_t_id, device_id))
    relay_id = cur.lastrowid
    print(f"RELAY id={relay_id}")

    # Fresh readings so "Last Reading" shows 2 seconds ago (uses UTC_TIMESTAMP)
    cur.execute("SELECT current_occupancy FROM cage_slots WHERE id=%s", (slot_id,))
    occ = cur.fetchone()[0]
    # DHT22 -> environmental_logs (is_demo=0 so page's latestRealPerCage picks it up)
    cur.execute("""
        INSERT INTO environmental_logs (cage_id, recorded_at, temperature_c, humidity_pct, is_override, is_demo)
        VALUES (%s, UTC_TIMESTAMP() - INTERVAL 2 SECOND, 28.40, 65.20, 0, 0)
    """, (cage_t_id,))
    print(f"env log for CAGE-T 2s ago")
    for hid in ir_ids:
        cur.execute("""
            INSERT INTO sensor_occupancy_readings (hardware_item_id, cage_slot_id, reported_count, recorded_at, created_at, updated_at)
            VALUES (%s, %s, %s, UTC_TIMESTAMP() - INTERVAL 2 SECOND, UTC_TIMESTAMP(), UTC_TIMESTAMP())
        """, (hid, slot_id, occ))
        print(f"IR reading hid={hid} occ={occ} 2s ago")

    conn.commit()
    print("COMMITTED")

    cur.execute("""
        SELECT device_type, serial_number, cage_id, cage_slot_id, status FROM hardware_items ORDER BY device_type, serial_number
    """)
    for r in cur.fetchall():
        print(r)
    cur.execute("SELECT device_type, COUNT(*) FROM hardware_items WHERE status='active' GROUP BY device_type")
    print("Counts:", cur.fetchall())

    # verify via HardwareItemController logic
    cur.execute("SELECT COUNT(*) FROM hardware_items WHERE device_type='IR_breakbeam' AND status='active'")
    print("breakbeam active:", cur.fetchone()[0])
    cur.execute("SELECT COUNT(*) FROM hardware_items WHERE device_type='DHT22' AND status='active'")
    print("DHT22 active:", cur.fetchone()[0])

finally:
    conn.close()
