import time, os, sys
os.environ['DB_PORT'] = '3307'
os.environ['DB_PASSWORD'] = 'root'

from ForecastingV5 import load_dataset_from_db, automatic_forecast, fit_sarima_model, fit_xgb_model, aggregate_all_cages, fixed_train_test_split, XGB_SEEDS

t0 = time.time()
df = load_dataset_from_db(breed='ALL')
t1 = time.time()
print(f"Load dataset: {t1-t0:.1f}s  rows={len(df)}")
print(f"Columns: {list(df.columns)}")
print(f"Sample feed: {df['Total_Feed_Consumed_kg'].head(3).tolist()}")
print(f"Sample protein: {df['Crude_Protein_Percent'].head(3).tolist()}")
print(f"NaN counts: feed={df['Total_Feed_Consumed_kg'].isna().sum()}, protein={df['Crude_Protein_Percent'].isna().sum()}")

df['Total_Feed_Consumed_kg'] = df['Total_Feed_Consumed_kg'].fillna(27.0)
df['Crude_Protein_Percent'] = df['Crude_Protein_Percent'].fillna(17.0)
print(f"After fill - NaN: feed={df['Total_Feed_Consumed_kg'].isna().sum()}, protein={df['Crude_Protein_Percent'].isna().sum()}")

agg = aggregate_all_cages(df)
print(f"\nAfter aggregate: {len(agg)} rows")
train_df, test_df = fixed_train_test_split(agg)
print(f"Train: {len(train_df)}, Test: {len(test_df)}")

t2 = time.time()
sarima = fit_sarima_model(train_df['Total_Eggs'].astype(float))
t3 = time.time()
print(f"\nSARIMA train fit: {t3-t2:.1f}s")

times = {}
for seed in XGB_SEEDS:
    ts = time.time()
    m = fit_xgb_model(train_df, seed=seed)
    times[seed] = time.time() - ts
    print(f"XGB train fit (seed {seed}): {times[seed]:.1f}s")
print(f"XGB train total: {sum(times.values()):.1f}s")

t4 = time.time()
sarima_full = fit_sarima_model(agg['Total_Eggs'].astype(float))
t5 = time.time()
print(f"\nSARIMA full fit: {t5-t4:.1f}s")

t6 = time.time()
for seed in XGB_SEEDS:
    fit_xgb_model(agg, seed=seed)
t7 = time.time()
print(f"XGB full fit (3 seeds): {t7-t6:.1f}s")

print(f"\n=== MODEL TRAINING TOTAL: {t7-t2:.1f}s ===")

t8 = time.time()
result = automatic_forecast(df, forecast_days=7)
t9 = time.time()
print(f"\nautomatic_forecast end-to-end: {t9-t8:.1f}s")
print(f"RECOMMENDED: {result['recommended_model']}")
print(f"TOTAL PIPELINE: {t9-t0:.1f}s")
print(f"Metrics: {result['metrics']}")
for f in result['forecast']:
    print(f"  {f['date']}: {f['predicted_egg_count']}")
