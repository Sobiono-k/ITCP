import pandas as pd
import json
import os
import numpy as np
from sklearn.ensemble import RandomForestClassifier
from preprocessing import load_csv_data

def analyze_patterns():
    # 1. Load data using the robust loader from preprocessing.py
    df = load_csv_data()
    
    if df.empty or 'medical_cause' not in df.columns:
        return {}

    # 2. Feature Engineering
    # Ensure date is datetime and extract time-based features
    df['request_date'] = pd.to_datetime(df['request_date'])
    df['day_of_week'] = df['request_date'].dt.dayofweek
    df['month'] = df['request_date'].dt.month
    
    # Clean the medical_cause strings
    df['medical_cause'] = df['medical_cause'].fillna('Unknown').str.strip()
    
    # 3. Random Forest Logic (Pipeline B)
    # We use RF to see how 'Amount' and 'Time' predict the 'Medical Cause'
    # This helps identify if certain causes are tied to specific times of the month
    df['cause_cat'] = df['medical_cause'].astype('category')
    y = df['cause_cat'].cat.codes
    X = df[['amount', 'day_of_week', 'month']]

    # Train model to identify patterns
    rf = RandomForestClassifier(n_estimators=50, random_state=42)
    rf.fit(X, y)

    # 4. Trend Analysis (Growth Calculation)
    # Compare the last 30 days vs the 30 days before that
    now = df['request_date'].max()
    last_30 = df[df['request_date'] > (now - pd.Timedelta(days=30))]
    prev_30 = df[(df['request_date'] <= (now - pd.Timedelta(days=30))) & 
                 (df['request_date'] > (now - pd.Timedelta(days=60)))]

    recent_counts = last_30['medical_cause'].value_counts()
    prior_counts = prev_30['medical_cause'].value_counts()

    # 5. Build JSON for Dashboard
    # The dashboard expects: { "Cause": { "status": "...", "growth": "...", "color": "..." } }
    results = {}
    
    # Focus on the top 4 most frequent causes
    top_causes = df['medical_cause'].value_counts().head(4).index
    
    for cause in top_causes:
        current = recent_counts.get(cause, 0)
        previous = prior_counts.get(cause, 0)
        
        # Calculate Growth Percentage
        if previous == 0:
            growth_pct = 100 if current > 0 else 0
        else:
            growth_pct = ((current - previous) / previous) * 100

        # Determine Status and Color
        if growth_pct > 10:
            status = "Rising"
            color = "#ef4444" # Red
        elif growth_pct < -10:
            status = "Declining"
            color = "#10b981" # Green
        else:
            status = "Stable"
            color = "#3b82f6" # Blue

        results[cause] = {
            "status": status,
            "growth": f"{int(growth_pct)}%",
            "color": color,
            "count": int(df[df['medical_cause'] == cause].shape[0])
        }

    return results

if __name__ == "__main__":
    try:
        # Standardize output for PHP shell_exec
        analysis = analyze_patterns()
        print(json.dumps(analysis))
    except Exception as e:
        # Fallback empty JSON so PHP doesn't crash
        print(json.dumps({}))