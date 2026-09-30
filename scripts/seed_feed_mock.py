#!/usr/bin/env python3
"""
Seed realistic feed mock data:
- 60 kg per day every month (total farm, distributed hen-proportionally)
- 1 sack (50 kg) = ₱1500 in March, price rises realistically per month
"""
import os, re, pymysql, random
from datetime import date, timedelta

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
conn=pymysql.connect(
    host=env.get('DB_HOST','127.0.0.1'),
    port=int(env.get('DB_PORT',3306)),
    user=env.get('DB_USERNAME','root'),
    password=env.get('DB_PASSWORD',''),
    database=env.get('DB_DATABASE','layrate'),
    autocommit=False
)
cur=conn.cursor()

# --- sack prices: realistic monthly increase ---
# March 1500 base, then + inflation/feed cost pressure
sacks = [
    ("2026-03-01", 1500, 17.5, "Prime Layer Mash 50kg"),
    ("2026-04-01", 1545, 17.4, "Prime Layer Mash 50kg"),
    ("2026-05-01", 1585, 17.6, "Prime Layer Mash 50kg"),
    ("2026-06-01", 1620, 17.5, "Prime Layer Mash 50kg"),
    ("2026-07-01", 1670, 17.3, "Prime Layer Mash 50kg"),
    ("2026-08-01", 1725, 17.4, "Prime Layer Mash 50kg"),
    ("2026-09-01", 1780, 17.5, "Prime Layer Mash 50kg"),
]
# total kg per day (farm total)
DAILY_TOTAL_KG = 60.0
START = date(2026,3,1)
END = date(2026,9,18)  # inclusive, up to today per reporting date

# fetch cages
cur.execute("SELECT id, cage_code FROM cages WHERE is_active=1 ORDER BY cage_code")
cages = cur.fetchall()  # list of (id, code)
# fetch active hen counts per cage
cage_hens = {}
for cid, code in cages:
    cur.execute("SELECT COUNT(*) FROM hens WHERE cage_slot_id IN (SELECT id FROM cage_slots WHERE cage_id=%s) AND is_active=1", (cid,))
    cnt = cur.fetchone()[0]
    cage_hens[cid] = cnt if cnt>0 else 1  # fallback 1 to avoid div0
total_hens = sum(cage_hens.values())
print(f"Active cages: {cages}")
print(f"Hen counts: {cage_hens} total {total_hens}")

try:
    # backup check
    cur.execute("SELECT COUNT(*) FROM feed_batches")
    print("Existing batches:", cur.fetchone()[0])
    cur.execute("SELECT COUNT(*) FROM feed_consumption_logs")
    print("Existing consumption logs:", cur.fetchone()[0])

    # wipe - order matters FK
    cur.execute("DELETE FROM feed_consumption_logs")
    print(f"Deleted consumption logs: {cur.rowcount}")
    cur.execute("DELETE FROM farm_feed_entries")
    print(f"Deleted farm entries: {cur.rowcount}")
    cur.execute("DELETE FROM feed_batches")
    print(f"Deleted batches: {cur.rowcount}")

    # insert batches
    batch_ids = {}
    for dstr, sack_price, cp, brand in sacks:
        unit_cost = round(sack_price / 50.0, 2)  # per kg
        batch_code = f"F-{dstr[:7].replace('-','')}"  # F-202603 etc
        cur.execute(
            "INSERT INTO feed_batches (batch_code, brand, crude_protein, total_quantity_kg, unit_cost, date_received, low_stock_threshold, notes) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
            (batch_code, brand, cp, 2500.00, unit_cost, dstr, 300.00,
             f"Mock batch 50kg sack @ ₱{sack_price} (unit ₱{unit_cost}/kg)")
        )
        bid = cur.lastrowid
        batch_ids[dstr[:7]] = bid
        print(f"Inserted {batch_code} {dstr} sack ₱{sack_price} unit ₱{unit_cost} id={bid}")

    # generate daily logs Mar01 - Sep18
    random.seed(42)
    d = START
    inserted = 0
    # map YYYY-MM -> batch_id
    def batch_for(d):
        key = d.strftime("%Y-%m")
        return batch_ids[key]

    # distribution: hen-proportional with largest-remainder to sum exactly DAILY_TOTAL_KG
    # Add tiny jitter ±0.3kg per day total to look realistic while keeping average 60
    while d <= END:
        bid = batch_for(d)
        # daily total with jitter ±0.4kg except keep exactly 60 on average? Use uniform -0.4 to +0.4
        # But to satisfy "60kg per day every month" strictly, we keep jitter small and round to 2 decimals still sum 60
        # We'll jitter total by -0.3 to +0.3 and still distribute
        jitter = round(random.uniform(-0.3, 0.3), 2) if d != START else 0  # stable first day
        daily = round(DAILY_TOTAL_KG + jitter, 2)
        # distribute per cage proportionally
        exact = {}
        for cid,_ in cages:
            exact[cid] = (cage_hens[cid]/total_hens) * daily
        # largest remainder to cents
        total_cents = int(round(daily*100))
        shares = []
        for cid,_ in cages:
            base = int(exact[cid]*100 // 1)  # floor cents
            rem = exact[cid]*100 - base
            shares.append((cid, base, rem))
        distributed = sum(s[1] for s in shares)
        remaining = total_cents - distributed
        shares_sorted = sorted(shares, key=lambda x: x[2], reverse=True)
        # allocate remaining cents to largest remainders
        alloc = {cid: base for cid,base,_ in shares}
        for i in range(remaining):
            cid = shares_sorted[i % len(shares_sorted)][0]
            alloc[cid] += 1
        for cid, code in cages:
            kg = alloc[cid]/100.0
            cur.execute(
                "INSERT INTO feed_consumption_logs (cage_id, feed_batch_id, log_date, log_time, feed_consumed_kg, source, recorded_by) VALUES (%s,%s,%s,%s,%s,'direct',NULL)",
                (cid, bid, d.strftime("%Y-%m-%d"), "08:00:00", kg)
            )
            inserted += 1
        d += timedelta(days=1)

    print(f"Inserted {inserted} consumption logs ({len(cages)} cages x {(END-START).days+1} days)")

    # verification
    cur.execute("SELECT DATE(log_date) d, SUM(feed_consumed_kg) s FROM feed_consumption_logs GROUP BY d ORDER BY d LIMIT 3")
    for row in cur.fetchall():
        print(row)
    cur.execute("SELECT DATE_FORMAT(log_date,'%Y-%m') m, SUM(feed_consumed_kg) s, COUNT(*) c FROM feed_consumption_logs GROUP BY m ORDER BY m")
    for m,s,c in cur.fetchall():
        print(f"{m}: {float(s):.1f} kg across {c} rows (~{float(s)/(c/len(cages)):.1f} per day)")

    conn.commit()
    print("COMMITTED")

    # show cost per month
    cur.execute("""
        SELECT DATE_FORMAT(fcl.log_date,'%Y-%m') m,
               fb.batch_code, fb.unit_cost,
               SUM(fcl.feed_consumed_kg) kg,
               SUM(fcl.feed_consumed_kg * fb.unit_cost) cost
        FROM feed_consumption_logs fcl JOIN feed_batches fb ON fcl.feed_batch_id=fb.id
        GROUP BY m, fb.batch_code, fb.unit_cost ORDER BY m
    """)
    for row in cur.fetchall():
        print(f"{row[0]} {row[1]} @ ₱{row[2]}/kg: {float(row[3]):.1f}kg = ₱{float(row[4]):.2f}")

finally:
    conn.close()
