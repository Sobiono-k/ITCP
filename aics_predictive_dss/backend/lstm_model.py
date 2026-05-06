import numpy as np
import sys
import matplotlib.pyplot as plt

from preprocessing import monthly_series
from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import LSTM, Dense, Dropout, Input
from sklearn.metrics import mean_absolute_error, mean_squared_error


# =========================
# Predictive Scheduling - LSTM
# =========================
"""
(Predictive - LSTM):
- Analyzes chronological request counts.
- Identifies seasonal patterns and cycles.
- Result: Projected request volumes for upcoming days, weeks, or months.
"""


# =========================
# FORECAST FUNCTION
# =========================
def forecast(model, values, window_size, min_val, max_val):
    last_window = values[-window_size:]
    last_window = last_window.reshape((1, window_size, 1))

    pred = model.predict(last_window, verbose=0)[0][0]

    # Denormalize
    pred = pred * (max_val - min_val) + min_val

    daily = max(0, pred)
    weekly = daily * 7
    monthly = daily * 30

    return daily, weekly, monthly


# =========================
# MAIN FUNCTION
# =========================
def predict_volume():
    # Load data
    series_data = monthly_series()

    print("DATA LENGTH:", len(series_data))
    print(series_data.tail(10))

    if series_data is None or len(series_data) < 10:
        return 0

    values = series_data.values.astype(float)

    # =========================
    # NORMALIZATION
    # =========================
    min_val = values.min()
    max_val = values.max()

    values = (values - min_val) / (max_val - min_val + 1e-8)

    # =========================
    # SEQUENCE CREATION
    # =========================
    WINDOW_SIZE = 7
    X, y = [], []

    for i in range(len(values) - WINDOW_SIZE):
        X.append(values[i:i+WINDOW_SIZE])
        y.append(values[i+WINDOW_SIZE])

    X = np.array(X)
    y = np.array(y)

    X = X.reshape((X.shape[0], X.shape[1], 1))

    # =========================
    # LSTM MODEL
    # =========================
    model = Sequential()

    model.add(Input(shape=(WINDOW_SIZE, 1)))

    model.add(LSTM(64, return_sequences=True))
    model.add(Dropout(0.2))

    model.add(LSTM(32))
    model.add(Dropout(0.2))

    model.add(Dense(1))

    model.compile(optimizer='adam', loss='mse')

    # =========================
    # TRAINING (EPOCHS)
    # =========================
    history = model.fit(
        X,
        y,
        epochs=20,
        verbose=1
    )

    # =========================
    # LOSS GRAPH
    # =========================
    plt.plot(history.history['loss'])
    plt.title('LSTM Training Loss - Predictive Scheduling')
    plt.xlabel('Epoch')
    plt.ylabel('Loss')
    plt.show()

    # =========================
    # ACCURACY METRICS (MAE / RMSE)
    # =========================
    y_pred = model.predict(X, verbose=0).flatten()

    mae = mean_absolute_error(y, y_pred)
    rmse = np.sqrt(mean_squared_error(y, y_pred))

    mae_real = mae * (max_val - min_val)
    rmse_real = rmse * (max_val - min_val)

    print("\n📊 MODEL ACCURACY METRICS")
    print("MAE  :", round(mae, 4))
    print("RMSE :", round(rmse, 4))

    print("\n📊 REAL-SCALE ERRORS")
    print("MAE (real):", round(mae_real, 2))
    print("RMSE (real):", round(rmse_real, 2))

    # =========================
    # FORECAST OUTPUT
    # =========================
    daily, weekly, monthly = forecast(
        model, values, WINDOW_SIZE, min_val, max_val
    )

    print("\n📊 Projected Request Volumes")
    print("Daily Forecast:", int(daily))
    print("Weekly Forecast:", int(weekly))
    print("Monthly Forecast:", int(monthly))

    return int(daily)


# =========================
# EXECUTION
# =========================
if __name__ == "__main__":
    try:
        result = predict_volume()
        print("\nFinal Prediction:", result)
    except Exception as e:
        print("ERROR:", e)
        sys.stdout.write("0")