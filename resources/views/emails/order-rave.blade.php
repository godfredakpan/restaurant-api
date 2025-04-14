<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            color: #333333;
        }
        .email-container {
            background-color: #ffffff;
            margin: 0 auto;
            padding: 20px;
            max-width: 600px;
            border: 1px solid #e4e4e4;
            border-radius: 5px;
        }
        .email-header {
            text-align: center;
            padding: 10px 0;
            background-color: #0f4c38;
            color: white;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .email-body {
            padding: 20px;
            font-size: 16px;
            line-height: 1.6;
        }
        .email-body p {
            margin: 0 0 20px;
        }
        .button {
            display: inline-block;
            background-color: #0f4c38;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 10px;
        }
        .email-footer {
            text-align: center;
            font-size: 12px;
            color: #777777;
            padding: 20px;
            border-top: 1px solid #e4e4e4;
        }
        .email-footer p {
            margin: 0;
        }
        .social-icons {
            margin-top: 20px;
        }
        .social-icons a {
            margin: 0 10px;
            text-decoration: none;
            color: #333333;
        }
        .social-icons img {
            width: 24px;
            height: 24px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Email Header -->
        <div class="email-header">
            <h3>Manage your orders with OrderRave</h3>
        </div>

        <!-- Email Body -->
        <div class="email-body">
            <p>Hello 👋,</p>
            <p>{!! $messageBody !!}</p> 
            <!-- Button -->
            @isset($actionUrl)
            <a href="{{ $actionUrl }}" class="button">{{ $actionText }}</a>
            @endisset

            @isset($actionUrl)
            <p style="margin-top: 20px;">Or copy the link below and paste it in your browser:</p>
            <p><span>{{ $actionUrl }}</span></p>
            @endisset
        </div>

        <!-- Email Footer -->
        <div class="email-footer">
            <p>&copy; {{ date('Y') }} Order Rave. All rights reserved.</p>
            <p>Your one-stop platform for delicious food, convenient services, and unforgettable experiences.</p>
            <p>If you did not request this, please ignore this email or contact support.</p>

            <!-- Social Links -->
            <div class="social-icons">
                <a href="https://twitter.com/orderrave_app" target="_blank">
                    <img src="https://uxwing.com/wp-content/themes/uxwing/download/brands-and-social-media/x-social-media-black-icon.png" alt="X">
                </a>
                <a href="https://instagram.com/orderrave" target="_blank">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Instagram_icon.png" alt="Instagram">
                </a>
            </div>
        </div>
    </div>
</body>
</html>
