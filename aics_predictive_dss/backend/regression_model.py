import numpy as np
import json # Important
from sklearn.linear_model import LinearRegression
from preprocessing import monthly_series

def forecast_next():
    series = monthly_series()
    if len(series) < 2:
        return {"prediction": 0, "slope": 0, "trend": "Stable", "annual_impact": 0}

    X = np.arange(len(series)).reshape(-1,1)
    y = series.values

    model = LinearRegression()
    model.fit(X,y)

    # Calculate Slope (Growth Rate)
    slope = model.coef_[0]
    prediction = model.predict(np.array([[len(series)]]))[0]
    
    # Determine Trend
    trend = "Increasing" if slope > 0.1 else "Decreasing" if slope < -0.1 else "Stable"

    # Return as a DICTIONARY
    return {
        "prediction": int(round(prediction)),
        "slope": round(float(slope), 2),
        "trend": trend,
        "annual_impact": int(round(slope * 12))
    }

if __name__ == "__main__":
    # Convert dictionary to JSON string so PHP can read it
    print(json.dumps(forecast_next()))