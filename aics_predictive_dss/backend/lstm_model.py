import numpy as np
import os
import sys
import warnings

# Suppress TensorFlow logging
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3' 
warnings.filterwarnings("ignore")

try:
    import tensorflow as tf
    from tensorflow.keras.models import Sequential
    from tensorflow.keras.layers import LSTM, Dense, Input
    from tensorflow.keras import backend as K
    TENSORFLOW_AVAILABLE = True
except ImportError:
    TENSORFLOW_AVAILABLE = False

# Import your data loader
try:
    from preprocessing import monthly_series
except ImportError:
    # If the file is missing, we need to exit or PHP gets nothing
    sys.stdout.write("0")
    sys.exit()

WINDOW_SIZE = 3
RETRAIN_EPOCHS = 50 # Lowered for faster dashboard loading

def train_lstm():
    # 1. LOAD DATA
    series_data = monthly_series()
    
    # 2. VALIDATE DATA
    if series_data is None or len(series_data) == 0:
        return 0

    if hasattr(series_data, 'values'):
        values = series_data.values.astype('float32')
    else:
        values = np.array(series_data, dtype='float32')

    # 3. DATA SUFFICIENCY CHECK
    # If we don't have enough history for a window, use a growth trend
    if len(values) <= WINDOW_SIZE:
        last_val = values[-1] if len(values) > 0 else 0
        return int(max(last_val, round(float(last_val) * 1.058)))

    # 4. NON-TENSORFLOW FALLBACK
    if not TENSORFLOW_AVAILABLE:
        weights = np.array([0.2, 0.3, 0.5])
        prediction = np.dot(values[-WINDOW_SIZE:], weights)
        return int(round(float(prediction)))

    # 5. PREPARE LSTM DATA
    try:
        from sklearn.preprocessing import MinMaxScaler
        
        data_reshaped = values.reshape(-1, 1)
        
        # Guard against identical values (prevents NaN in scaling)
        if np.all(data_reshaped == data_reshaped[0]):
            return int(round(float(data_reshaped[0][0]) * 1.05))
            
        scaler = MinMaxScaler(feature_range=(0, 1))
        scaled_data = scaler.fit_transform(data_reshaped)

        X, y = [], []
        for i in range(len(scaled_data) - WINDOW_SIZE):
            X.append(scaled_data[i : i + WINDOW_SIZE])
            y.append(scaled_data[i + WINDOW_SIZE])
        
        X = np.array(X) 
        y = np.array(y)

        # 6. BUILD & TRAIN
        K.clear_session()
        model = Sequential([
            Input(shape=(WINDOW_SIZE, 1)),
            LSTM(16, activation='tanh'), # Reduced units for speed
            Dense(8, activation='relu'),
            Dense(1)
        ])
        
        model.compile(optimizer='adam', loss='mse')
        model.fit(X, y, epochs=RETRAIN_EPOCHS, verbose=0, batch_size=1)

        # 7. PREDICT
        last_window = scaled_data[-WINDOW_SIZE:].reshape(1, WINDOW_SIZE, 1)
        prediction_scaled = model.predict(last_window, verbose=0)
        prediction_final = scaler.inverse_transform(prediction_scaled)
        
        return int(round(max(0, float(prediction_final[0][0]))))

    except Exception:
        # Emergency fallback: Simple moving average
        return int(round(float(np.mean(values[-WINDOW_SIZE:]))))

if __name__ == "__main__":
    try:
        final_val = train_lstm()
        # Ensure ONLY the number is sent to PHP
        sys.stdout.write(str(int(final_val)))
    except Exception:
        # If everything fails, return 0 to avoid breaking the PHP string
        sys.stdout.write("0")
    sys.stdout.flush()