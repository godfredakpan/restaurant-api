<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification Error: Orderrave</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
        }
        .container {
            text-align: center;
            background-color: white;
            padding: 50px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .crossmark {
            font-size: 50px;
            color: red;
            margin-bottom: 20px;
        }
        .message {
            font-size: 20px;
            color: #333;
        }
        .subtext {
            font-size: 16px;
            color: #666;
        }
        .copyright {
            font-size: 14px;
            color: #999;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="crossmark">✘</div>
        <div class="message">Invalid verification link!</div>
        <div class="subtext">The email verification link is invalid or has expired.</div>
        <div class="copyright">&copy; <span id="current-date"></span> Orderrave.ng. All rights reserved.</div>
    </div>
</body>
<script>
    // current date
    var date = new Date();
    var year = date.getFullYear();
    document.getElementById('current-date').textContent = year;
</script>
</html>