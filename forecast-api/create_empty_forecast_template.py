"""Create an empty Excel template with the columns required by ForecastingV5.py."""

import pandas as pd

REQUIRED_COLUMNS = [
    "Date",
    "Cage_ID",
    "Breed",
    "Live_Hens",
    "Flock_Age_Weeks",
    "Temperature_C",
    "Humidity_Percent",
    "Crude_Protein_Percent",
    "Total_Feed_Consumed_kg",
    "Monthly_Mortality",
    "Total_Eggs",
    "Lay_Rate_Percent",
    "Heat_Stress",
]

OUTPUT_FILE = "forecast_template.xlsx"


def main() -> None:
    df = pd.DataFrame(columns=REQUIRED_COLUMNS)
    df.to_excel(OUTPUT_FILE, index=False, engine="openpyxl")
    print(f"Created empty forecast template: {OUTPUT_FILE}")
    print(f"Columns ({len(REQUIRED_COLUMNS)}): {', '.join(REQUIRED_COLUMNS)}")


if __name__ == "__main__":
    main()
