import numpy as np
import matplotlib.pyplot as plt
import pandas as pd
import os

os.environ['TF_CPP_MIN_LOG_LEVEL'] = '2'
os.environ['TF_ENABLE_ONEDNN_OPTS'] = '0'

import tensorflow as tf
from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import LSTM, Dense, Input, Dropout

from preprocessing import load_csv_data


def prepare_data():
    df = load_csv_data()

    df['request_date'] = pd.to_datetime(df['request_date'])

    # CHANGED TO WEEKLY: Captures sub-monthly variations and weekly spikes
    ts = df.groupby(pd.Grouper(key='request_date', freq='W')).size()

    ts = ts.fillna(0)

    # log transform FIRST (important)
    ts_log = np.log1p(ts.values)

    # spike detection on log scale
    threshold = ts_log.mean() + (2 * ts_log.std())
    spike_flag = (ts_log > threshold).astype(int)

    # normalize log values
    min_v = ts_log.min()
    max_v = ts_log.max()
    norm_v = (ts_log - min_v) / (max_v - min_v + 1e-8)

    data = np.column_stack([norm_v, spike_flag])

    return data, min_v, max_v, ts.values


def create_sequences(data, window=52):
    X, y = [], []

    for i in range(len(data) - window):
        X.append(data[i:i + window])
        y.append(data[i + window, 0])  # predict volume only

    return np.array(X, dtype=np.float32), np.array(y, dtype=np.float32)

def forecast_future(model, last_sequence, steps, min_v, max_v):
    future = []
    current = last_sequence.copy()

    for _ in range(steps):
        pred = model.predict(current[np.newaxis, :, :], verbose=0)[0][0]
        future.append(pred)

        # shift window
        new_row = np.array([pred, 0])  # spike unknown → 0
        current = np.vstack([current[1:], new_row])

    # inverse transform
    future = np.array(future)
    future = np.expm1(future * (max_v - min_v) + min_v)

    return future


def run_lstm():
    data, min_v, max_v, raw_ts = prepare_data()
    
    # EXPANDED WINDOW TO 52: Represents 52 weeks (1 full historical year)
    # This allows the LSTM to compare the current week against this time last year
    WINDOW = 52 
    
    # Ensure there is enough data to support a 52-week window + testing split
    if len(data) <= WINDOW:
        raise ValueError(f"Dataset too small ({len(data)} rows) for a 52-week window. Provide more history.")

    X, y = create_sequences(data, WINDOW)

    # Use 90% for training
    split = int(len(X) * 0.9)
    X_train, X_test = X[:split], X[split:]
    y_train, y_test = y[:split], y[split:]

    model = Sequential([
        Input(shape=(WINDOW, 2)),
        LSTM(64, return_sequences=False),
        Dense(32, activation='relu'),
        Dense(1)
    ])

    # Using Mean Absolute Error (MAE) instead of Huber for sharper spikes
    model.compile(optimizer='adam', loss='mae')
    model.fit(X_train, y_train, epochs=200, verbose=0)

    # Predict across the whole set
    pred_norm = model.predict(X, verbose=0).flatten()
    print(f"Normalized Prediction: {pred_norm}")
    
    # Inverse transform
    pred_final = np.expm1(pred_norm * (max_v - min_v) + min_v)
    actual_final = np.expm1(y * (max_v - min_v) + min_v)
    print(f"Max used for scaling: {max_v}")

    # -------- CALCULATE MARGIN OF ERROR METRICS --------
    test_actual = actual_final[split:]
    test_pred = pred_final[split:]
    test_residuals = test_actual - test_pred

    mae_original = float(np.mean(np.abs(test_residuals))) if len(test_residuals) > 0 else 0.0
    moe_95 = float(1.96 * np.std(test_residuals)) if len(test_residuals) > 0 else 0.0

    # -------- PLOT SYSTEM TIMELINE --------
    plt.figure(figsize=(12, 5))
    plt.plot(actual_final, label="Actual")
    plt.plot(pred_final, '--', label="Predicted")
    # TEST START LINE REMOVED FROM THIS BLOCK AS REQUESTED
    plt.legend(); plt.grid(True); plt.show()

# -------- FUTURE FORECAST (REAL ML CURVE) --------
    last_sequence = X[-1]
    
    # FUTURE FORECAST EXTENDED: Forecast next 24 weeks (~6 months) on a weekly grain
    future_steps = 24  

    future_forecast = forecast_future(
        model,
        last_sequence,
        future_steps,
        min_v,
        max_v
    )

    # Establish localized upper and lower boundary bounds for the forecast line
    forecast_lower = np.maximum(0, future_forecast - moe_95).tolist()
    forecast_upper = (future_forecast + moe_95).tolist()

    # Combine actual + future for dashboard
    full_curve = np.concatenate([actual_final, future_forecast])

    # Output ONLY clean JSON for PHP
    import json

    print(json.dumps({
        "actual": actual_final.tolist(),
        "predicted": pred_final.tolist(),
        "forecast": future_forecast.tolist(),
        "forecast_lower": forecast_lower,
        "forecast_upper": forecast_upper,
        "full_curve": full_curve.tolist(),
        "metrics": {
            "average_error_volume": round(mae_original, 2),
            "margin_of_error_95": round(moe_95, 2)
        }
    }))    

if __name__ == "__main__":
    run_lstm()