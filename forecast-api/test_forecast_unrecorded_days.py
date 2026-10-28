"""Sensor-only days with no egg counted are 'nothing recorded', not 0 eggs.

Run: .venv/Scripts/python.exe -m unittest test_forecast_unrecorded_days -v
"""
import unittest
import warnings
from datetime import date

import numpy as np
import pandas as pd

import ForecastingV5 as forecasting

warnings.filterwarnings("ignore")


def raw_frame(eggs, hens, non_sensor_logs, cage="CAGE-X", end=None):
    """A query-shaped frame for one cage, ending yesterday by default."""
    end = end or pd.Timestamp(date.today()) - pd.Timedelta(days=1)
    n = len(eggs)
    return pd.DataFrame({
        "Date": pd.date_range(end=end, periods=n, freq="D"),
        "Cage_ID": cage,
        "Breed": "Dekalb White",
        "Flock_Age_Weeks": 40.0,
        "Live_Hens": np.asarray(hens, dtype=float),
        "Total_Eggs": np.asarray(eggs, dtype=float),
        "Temperature_C": 28.0,
        "Humidity_Percent": 70.0,
        "Crude_Protein_Percent": 17.0,
        "Total_Feed_Consumed_kg": 6.0,
        "Daily_Mortality": 0,
        "Non_Sensor_Logs": np.asarray(non_sensor_logs, dtype=float),
    })


def steady(n, level=50.0, seed=7):
    return level + np.random.default_rng(seed).normal(0, 2, n)


class UnrecordedDaysTest(unittest.TestCase):
    def test_sensor_only_zero_days_are_left_out_of_a_cage_history(self):
        eggs = steady(120); eggs[-3:] = 0
        logs = np.ones(120); logs[-3:] = 0
        series = forecasting.aggregate_all_cages(forecasting.build_modeling_frame(raw_frame(eggs, np.full(120, 60), logs)))

        self.assertEqual(len(series), 117)
        self.assertGreater(series["Total_Eggs"].iloc[-1], 0)
        self.assertNotIn(forecasting.RECORDED_COLUMN, series.columns)

    def test_a_zero_logged_by_a_person_is_kept(self):
        eggs = steady(120); eggs[-3:] = 0
        series = forecasting.aggregate_all_cages(forecasting.build_modeling_frame(raw_frame(eggs, np.full(120, 60), np.ones(120))))

        self.assertEqual(len(series), 120)
        self.assertEqual(series["Total_Eggs"].iloc[-1], 0)

    def test_a_farm_day_with_an_unrecorded_cage_is_left_out_not_partly_summed(self):
        a_logs = np.ones(100); a_logs[-1] = 0
        a_eggs = steady(100, 200); a_eggs[-1] = 0
        farm = pd.concat([
            raw_frame(a_eggs, np.full(100, 240), a_logs, cage="CAGE-A"),
            raw_frame(steady(100, 120, seed=8), np.full(100, 144), np.ones(100), cage="CAGE-B"),
        ])
        series = forecasting.aggregate_all_cages(forecasting.build_modeling_frame(farm))

        self.assertEqual(len(series), 99)
        self.assertGreater(series["Total_Eggs"].min(), 250)

    def test_forecast_after_unrecorded_sensor_days_is_not_zero(self):
        eggs = steady(150); eggs[-3:] = 0
        logs = np.ones(150); logs[-3:] = 0
        df = forecasting.build_modeling_frame(raw_frame(eggs, np.full(150, 60), logs))

        out = forecasting.automatic_forecast(df, forecast_days=7)
        predicted = [f["predicted_egg_count"] for f in out["forecast"]]

        self.assertTrue(all(p >= 40 for p in predicted), predicted)

    def test_an_emptied_cage_still_forecasts_a_genuine_zero(self):
        eggs = steady(150); eggs[-14:] = 0
        hens = np.full(150, 60.0); hens[-14:] = 0
        df = forecasting.build_modeling_frame(raw_frame(eggs, hens, np.ones(150)))

        out = forecasting.automatic_forecast(df, forecast_days=7)

        self.assertEqual([f["predicted_egg_count"] for f in out["forecast"]], [0] * 7)

    def test_frames_without_sensor_information_are_unchanged(self):
        frame = raw_frame(steady(100), np.full(100, 60), np.ones(100)).drop(columns=["Non_Sensor_Logs"])
        built = forecasting.build_modeling_frame(frame)

        self.assertNotIn(forecasting.RECORDED_COLUMN, built.columns)
        self.assertEqual(len(forecasting.aggregate_all_cages(built)), 100)


if __name__ == "__main__":
    unittest.main()
