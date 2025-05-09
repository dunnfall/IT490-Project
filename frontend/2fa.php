<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>2FA Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .container {
            max-width: 400px;
            margin-top: 80px;
            padding: 20px;
            border-radius: 8px;
            background-color: #ffffff;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-light">

<div class="container">
    <h4 class="text-center mb-4">Two-Factor Authentication</h4>

    <form method="POST" action="/verify2fa.php">
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($_GET['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-group">
            <label for="code">Enter the 6-digit code sent to your email</label>
            <input type="text" id="code" name="code" maxlength="6" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Verify Code</button>
    </form>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'invalid_code'): ?>
        <div class="alert alert-danger mt-3">Invalid or expired code. Please try again.</div>
    <?php endif; ?>
</div>

</body>
</html>
