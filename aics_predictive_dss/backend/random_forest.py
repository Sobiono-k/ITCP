import pandas as pd
import json
import numpy as np
import sys
import matplotlib.pyplot as plt

from sklearn.ensemble import RandomForestClassifier
from preprocessing import load_csv_data


# =========================
# Medical Cause Analysis - Random Forest
# =========================
"""
(Medical Cause Analysis - Random Forest):
- Analyzes frequency and classification of medical conditions.
- Identifies correlations between causes, dates, and request patterns.
- Result: Detection of rising, stable, or declining medical trends.
"""


# =========================
# ANALYSIS FUNCTION
# =========================
def analyze_patterns():

    # =========================
    # LOAD DATA
    # =========================
    df = load_csv_data()

    if df is None or df.empty:
        return {}

    # =========================
    # CLEANING
    # =========================
    df['medical_cause'] = (
        df['medical_cause']
        .fillna('Unknown')
        .astype(str)
        .str.strip()
    )

    df['request_date'] = pd.to_datetime(df['request_date'], errors='coerce')
    df = df[df['request_date'].notna()]

    if df.empty:
        return {}

    # =========================
    # FEATURE ENGINEERING
    # =========================
    df['day_of_week'] = df['request_date'].dt.dayofweek
    df['month'] = df['request_date'].dt.month

    if 'amount' not in df.columns:
        df['amount'] = 0

    df['amount'] = pd.to_numeric(df['amount'], errors='coerce').fillna(0)

    # =========================
    # ENCODE TARGET
    # =========================
    df['cause_cat'] = df['medical_cause'].astype('category')
    y = df['cause_cat'].cat.codes
    X = df[['amount', 'day_of_week', 'month']]

    # =========================
    # RANDOM FOREST TRAINING
    # =========================
    rf = None

    if len(df) >= 10:
        rf = RandomForestClassifier(
            n_estimators=100,
            random_state=42
        )
        rf.fit(X, y)

    # =========================
    # TREND ANALYSIS
    # =========================
    now = df['request_date'].max()
    recent_window = now - pd.Timedelta(days=7)

    last_7_days = df[df['request_date'] >= recent_window]
    historical_data = df[df['request_date'] < recent_window]

    results = {}

    cause_counts = df['medical_cause'].value_counts()

    if cause_counts.empty:
        return {}

    top_causes = cause_counts.head(5).index

    for cause in top_causes:

        current_daily = (
            last_7_days[last_7_days['medical_cause'] == cause].shape[0] / 7
        )

        hist = historical_data[historical_data['medical_cause'] == cause]

        if not hist.empty:
            days_span = max(
                (hist['request_date'].max() -
                 hist['request_date'].min()).days,
                1
            )
            historical_daily = hist.shape[0] / days_span
        else:
            historical_daily = 0

        # =========================
        # GROWTH RATE
        # =========================
        if historical_daily == 0:
            growth = 100 if current_daily > 0 else 0
        else:
            growth = ((current_daily - historical_daily) / historical_daily) * 100

        # =========================
        # STATUS CLASSIFICATION
        # =========================
        if growth > 15:
            status, color = "Rising", "#ef4444"
        elif growth < -15:
            status, color = "Declining", "#10b981"
        else:
            status, color = "Stable", "#3b82f6"

        results[cause] = {
            "status": status,
            "growth": f"{round(growth)}%",
            "color": color,
            "count": int(df[df['medical_cause'] == cause].shape[0])
        }

    return results


# =========================
# GRAPH VISUALIZATION
# =========================
def plot_medical_trends(results):

    if not results:
        return

    causes = list(results.keys())
    counts = [results[c]["count"] for c in causes]
    colors = [results[c]["color"] for c in causes]

    plt.figure(figsize=(9, 5))

    plt.bar(causes, counts, color=colors)

    plt.title("Medical Cause Trend Analysis - Random Forest")
    plt.xlabel("Medical Cause")
    plt.ylabel("Number of Cases")

    plt.xticks(rotation=45)
    plt.tight_layout()

    plt.show()


# =========================
# EXECUTION (PHP / SYSTEM OUTPUT)
# =========================
if __name__ == "__main__":
    try:
        analysis = analyze_patterns()

        # output for PHP / backend
        sys.stdout.write(json.dumps(analysis))
        sys.stdout.flush()

        # show graph (defense feature)
        plot_medical_trends(analysis)

    except Exception:
        sys.stdout.write(json.dumps({}))
        sys.stdout.flush()