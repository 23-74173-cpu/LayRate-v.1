import unittest
from unittest.mock import patch

import numpy as np
import pandas as pd
import ForecastingV5 as forecasting


class ConstantModel:
    def predict(self, frame):
        return np.full(len(frame), 100.0)


class ForecastAgeTest(unittest.TestCase):
    def test_deployment_age_increases_each_day_including_gap_before_target(self):
        row = pd.Series({'Date': pd.Timestamp('2026-01-01'), 'Breed': 'ISA Brown', 'Live_Hens': 120,
                         'Flock_Age_Weeks': 30, 'Crude_Protein_Percent': 16.5})
        frame = forecasting.build_deployment_feature_frame(
            row, pd.date_range('2026-01-04', periods=7), 28, 70, 14, 0, 0)
        self.assertAlmostEqual(frame.iloc[0]['Flock_Age_Weeks'], 30 + 1 / 7)
        self.assertAlmostEqual(frame.iloc[-1]['Flock_Age_Weeks'], 30 + 9 / 7)
        self.assertTrue(frame['Flock_Age_Weeks'].is_monotonic_increasing)

    def test_manual_forecast_supplies_increasing_age_to_models(self):
        dates = pd.date_range(end=pd.Timestamp.today().normalize() - pd.Timedelta(days=1), periods=100)
        history = pd.DataFrame({'Date': dates, 'Total_Eggs': 100})
        captured = []

        def predict(models, data, features):
            captured.append(features.copy())
            return np.full(len(features), 100.0)

        with patch.object(forecasting, 'fit_xgb_model', return_value=ConstantModel()), \
             patch.object(forecasting, 'recursive_xgb_forecast', return_value=np.full(20, 100.0)), \
             patch.object(forecasting, 'recursive_xgb_forecast_ensemble', side_effect=predict):
            result = forecasting.manual_forecast(history, 7, 'ISA Brown', 120, 30, 28, 70, 16.5, 14, 0, 0)
        self.assertEqual(len(result['forecast']), 7)
        self.assertAlmostEqual(captured[0].iloc[0]['Flock_Age_Weeks'], 30 + 1 / 7)
        self.assertAlmostEqual(captured[0].iloc[-1]['Flock_Age_Weeks'], 31)
        self.assertTrue(captured[0]['Flock_Age_Weeks'].is_monotonic_increasing)


class ForecastZeroProductionTest(unittest.TestCase):
    def test_zero_production_returns_forecasts_with_undefined_mape(self):
        today = pd.Timestamp.today().normalize()
        history = pd.DataFrame({'Date': pd.date_range(end=today - pd.Timedelta(days=1), periods=100), 'Total_Eggs': 0})

        class ZeroSarima:
            def forecast(self, steps):
                return np.zeros(steps)

        with patch.object(forecasting, 'fit_sarima_model', return_value=ZeroSarima()):
            result = forecasting.automatic_forecast(history, forecast_days=7)
        self.assertEqual(len(result['forecast']), 7)
        self.assertIsNone(result['metrics']['sarima']['MAPE'])
        self.assertTrue(all(row['predicted_egg_count'] == 0 for row in result['forecast']))


if __name__ == '__main__':
    unittest.main()
