import pandas as pd
import os
import sys
from sqlalchemy import create_engine

# =========================
# Data Loading Functions
# =========================

def load_csv_data(path=None):
    try:
        engine = create_engine("mysql+pymysql://root:@localhost/aics_dss")
        query = "SELECT * FROM aics_sample_data ORDER BY id ASC"
        df = pd.read_sql(query, engine)

        if df.empty:
            return pd.DataFrame()

        # 🔍 Find request_date column (robust)
        actual_date_col = None
        for col in df.columns:
            if 'request_date' in col.lower():
                actual_date_col = col
                break

        if not actual_date_col:
            print("No request_date column found.")
            return pd.DataFrame()

        df = df.rename(columns={actual_date_col: 'request_date'})

        # ✅ Robust parsing (handles mixed formats safely)
        df['request_date'] = pd.to_datetime(
            df['request_date'],
                format='%Y-%m-%d',
                errors='coerce'
        )

        # ❗ Drop invalid dates immediately
        df = df.dropna(subset=['request_date'])

        # 🧹 Clean other columns
        df['medical_cause'] = df.get('medical_cause', 'Unknown').fillna('Unknown')
        df['assistance_type'] = df.get('assistance_type', 'Unknown').fillna('Unknown')

        return df

    except Exception as e:
        print(f"Error connecting to MySQL: {e}")
        return pd.DataFrame()

# =========================
# Time-Series Creation
# =========================

def create_time_series(df, freq='D'):
    if df.empty:
        return pd.DataFrame()

    # ✅ DO NOT re-parse date (already parsed earlier)
    df = df.sort_values('request_date')

    # 📊 Group by day (or change freq if needed)
    ts = df.groupby(pd.Grouper(key='request_date', freq=freq)).size()
    ts = ts.rename('request_count').to_frame()

    # 📅 Fill missing dates (CRUCIAL for LSTM continuity)
    full_range = pd.date_range(start=ts.index.min(), end=ts.index.max(), freq=freq)
    ts = ts.reindex(full_range, fill_value=0)

    ts = ts.reset_index()
    ts.columns = ['request_date', 'request_count']

    return ts

def monthly_series(path=None):
    df = load_csv_data(path)
    if df.empty:
        return pd.Series([0])

    ts = create_time_series(df, freq='D')

    # ✅ Last 90 days for LSTM input
    return ts['request_count'].tail(90).astype(float)

# =========================
# Testing Block
# =========================

if __name__ == "__main__":
    print("--- AICS SQL Preprocessing Pipeline ---")
    data = load_csv_data()
    
    if not data.empty:
        print(f"Successfully loaded {len(data)} records.")
        print(f"Latest Date in Data: {data['request_date'].max()}")
        
        # Check the counts for Jan 1st 2025
        ts = create_time_series(data)
        print("\nLast 10 Days of Activity:")
        print(ts.tail(10))
    else:
        print("Data load failed. Verify the table 'aics_sample_data' exists.")