<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navbar Example</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="../frontend/home.php">DEAA Stocks</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <!-- Home link -->
                <li class="nav-item">
                    <a class="nav-link" href="https://prodwebsite.tail5c772.ts.net/frontend/home.php">Home</a>
                </li>

                <!-- Profile link -->
                <li class="nav-item">
                    <a class="nav-link" href="https://prodwebsite.tail5c772.ts.net/frontend/profile.php">Profile</a>
                </li>

                <!-- New links for Buy and Sell -->
                <li class="nav-item">
                    <a class="nav-link" href="https://prodwebsite.tail5c772.ts.net/frontend/buy.php">Buy</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="https://prodwebsite.tail5c772.ts.net/frontend/sell.php">Sell</a>
                </li>

                <!-- Watchlist -->
                <li class="nav-item">
                    <a class="nav-link" href="https://prodwebsite.tail5c772.ts.net/frontend/watchlist.php">Watchlist</a>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="btn btn-danger" href="https://prodwebsite.tail5c772.ts.net/frontend/logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Optional JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
