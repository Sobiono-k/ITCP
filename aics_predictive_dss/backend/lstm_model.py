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

    # Monthly aggregation
    ts = df.groupby(pd.Grouper(key='request_date', freq='ME')).size()

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


def create_sequences(data, window=6):
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
    WINDOW = 6
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

    plt.figure(figsize=(12, 5))
    plt.plot(actual_final, label="Actual")
    plt.plot(pred_final, '--', label="Predicted")
    plt.axvline(x=split, color='red', linestyle=':', label="Test Start")
    plt.legend(); plt.grid(True); plt.show()

# -------- FUTURE FORECAST (REAL ML CURVE) --------
    last_sequence = X[-1]
    future_steps = 6  # next 6 months

    future_forecast = forecast_future(
        model,
        last_sequence,
        future_steps,
        min_v,
        max_v
    )

    # Combine actual + future for dashboard
    full_curve = np.concatenate([actual_final, future_forecast])

    # Output ONLY clean JSON for PHP
    import json

    print(json.dumps({
        "actual": actual_final.tolist(),
        "predicted": pred_final.tolist(),
        "forecast": future_forecast.tolist(),
        "full_curve": full_curve.tolist()
    }))    
if __name__ == "__main__":
    run_lstm()