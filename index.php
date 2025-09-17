<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>We’ll be back soon!</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      background: linear-gradient(135deg, #00c6ff, #0072ff);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      color: #fff;
      text-align: center;
    }
    .container {
      max-width: 600px;
      padding: 30px;
      background: rgba(0, 0, 0, 0.4);
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
      animation: fadeIn 1.5s ease-in-out;
    }
    h1 {
      font-size: 2.5rem;
      margin-bottom: 15px;
    }
    p {
      font-size: 1.2rem;
      margin-bottom: 25px;
    }
    .loader {
      border: 6px solid rgba(255, 255, 255, 0.3);
      border-top: 6px solid #fff;
      border-radius: 50%;
      width: 50px;
      height: 50px;
      margin: 0 auto 20px auto;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.9); }
      to { opacity: 1; transform: scale(1); }
    }
    .footer {
      font-size: 0.9rem;
      opacity: 0.8;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="loader"></div>
    <h1>🚧 Under Maintenance 🚧</h1>
    <p>We’re making some improvements. Please check back soon.</p>
    <div class="footer">© 2025 TXPLAYS.COM</div>
  </div>
</body>
</html>
