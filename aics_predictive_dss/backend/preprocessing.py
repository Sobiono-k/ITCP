import pandas as pd
import os
import sys

# Get the base directory (project root)
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# =========================
# Data Loading Functions
# =========================

def load_csv_data(path=None):
    """
    Load AICS data from a CSV file with robust column mapping.
    """
    if path is None:
        path = os.path.join(BASE_DIR, 'dataset', 'aics_sample_data.csv')
    elif not os.path.isabs(path):
        path = os.path.join(BASE_DIR, path)
    
    if not os.path.exists(path):
        print(f"Error: File not found at {path}")
        return pd.DataFrame()

    df = pd.read_csv(path)
    
    # 1. Flexible Column Mapping (Handles different CSV exports)
    column_mapping = {
        'Date of Request or Application': 'request_date',
        'Medical Cause Reason for Assistance': 'medical_cause',
        'Type of Medical Assistance Requested': 'assistance_type',
        'Medical Cause': 'medical_cause',
        'Request Date': 'request_date'
    }
    
    # Rename only columns that exist in the dataframe
    df = df.rename(columns={k: v for k, v in column_mapping.items() if k in df.columns})
    
    # 2. Robust Date Parsing
    if 'request_date' in df.columns:
        df['request_date'] = pd.to_datetime(df['request_date'], errors='coerce', dayfirst=False)
        df = df.dropna(subset=['request_date'])
    
    # 3. Ensure essential columns exist for ML models
    if 'amount' not in df.columns:
        df['amount'] = 0
    if 'medical_cause' not in df.columns:
        df['medical_cause'] = 'Unknown'
        
    return df

def load_data(path=None):
    """
    Load AICS data - wrapper function for compatibility
    """
    return load_csv_data(path)

# =========================
# Preprocessing Functions
# =========================

def preprocess_data(df):
    """
    Clean and prepare data for Random Forest / Classification.
    """
    if df.empty: return df
    
    processed_df = df.copy()
    processed_df['medical_cause'] = processed_df['medical_cause'].fillna('Unknown')
    processed_df['assistance_type'] = processed_df['assistance_type'].fillna('Unknown')
    
    processed_df['medical_cause_code'] = processed_df['medical_cause'].astype('category').cat.codes
    processed_df['assistance_code'] = processed_df['assistance_type'].astype('category').cat.codes
    
    processed_df = processed_df.sort_values('request_date').reset_index(drop=True)
    return processed_df

# =========================
# Time-Series Creation
# =========================

def create_time_series(df, freq='W'):
    """
    Aggregate request counts. Freq options: 'W' (Weekly), 'D' (Daily), 'ME' (Month End).
    """
    if df.empty or 'request_date' not in df.columns:
        return pd.DataFrame(columns=['request_date', 'request_count'])
    
    df = df.sort_values('request_date')
    ts = df.groupby(pd.Grouper(key='request_date', freq=freq)).size().reset_index(name='request_count')
    return ts

def monthly_series(path=None):
    """
    Helper for LSTM/Regression pipelines.
    """
    df = load_csv_data(path)
    if df.empty:
        return pd.Series([0], dtype=float)
    
    ts = create_time_series(df, freq='ME')
    return ts['request_count'].astype(float)

# =========================
# Feature Engineering
# =========================

def add_lag_features(df, n_lags=3):
    """
    Shift data to create 'memory' for the LSTM model.
    """
    if df.empty or len(df) <= n_lags:
        return df
        
    df_lag = df.copy()
    for lag in range(1, n_lags + 1):
        df_lag[f'request_count_lag_{lag}'] = df_lag['request_count'].shift(lag)
    
    return df_lag.dropna().reset_index(drop=True)

# =========================
# Testing Block
# =========================

if __name__ == "__main__":
    print("--- AICS Preprocessing Pipeline ---")
    data = load_csv_data()
    
    if not data.empty:
        print(f"Successfully loaded {len(data)} records.")
        prep = preprocess_data(data)
        print("\nTop 3 Medical Causes (Codes):")
        print(prep[['medical_cause', 'medical_cause_code']].drop_duplicates().head(3))
        weekly = create_time_series(data, freq='W')
        print(f"\nWeekly Points: {len(weekly)}")
        lags = add_lag_features(weekly, n_lags=2)
        print("\nLag Feature Sample (X -> Y):")
        print(lags.head(3))
    else:
        print("Preprocessing test failed: No data found.")

