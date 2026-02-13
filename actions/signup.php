<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regjistrohu - Tirana Solidare</title>
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <style>
        /* Temporary CSS */
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f4f4f4; }
        .signup-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 350px; }
        input { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #28a745; color: white; border: none; cursor: pointer; font-size: 16px; margin-top: 10px;}
        button:hover { background: #218838; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; font-size: 0.9em; margin-bottom: 15px; }
        .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; font-size: 0.9em; margin-bottom: 15px; }
        h2 { text-align: center; color: #333; }
        label { font-weight: bold; font-size: 0.9em; color: #555; }
    </style>
</head>
<body>

<div class="signup-container">
    <h2>Krijo Llogari</h2>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <?php 
                if ($_GET['error'] == "empty_fields") echo "Ju lutem plotësoni të gjitha fushat!";
                elseif ($_GET['error'] == "invalid_email") echo "Formati i email-it nuk është i saktë!";
                elseif ($_GET['error'] == "password_mismatch") echo "Fjalëkalimet nuk përputhen!";
                elseif ($_GET['error'] == "email_taken") echo "Ky email është regjistruar tashmë!";
                elseif ($_GET['error'] == "sql_error") echo "Ndodhi një gabim në sistem. Provoni përsëri.";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="success">
            Llogaria u krijua me sukses! <a href="login.php">Hyni këtu</a>.
        </div>
    <?php endif; ?>

    <form action="../actions/signup_action.php" method="POST">
        <label>Emri i plotë</label>
        <input type="text" name="emri" required placeholder="Shembull: Agim Hysi">

        <label>Email</label>
        <input type="email" name="email" required placeholder="emri@shembull.com">
        
        <label>Fjalëkalimi</label>
        <input type="password" name="password" required placeholder="Së paku 6 karaktere">

        <label>Konfirmo Fjalëkalimin</label>
        <input type="password" name="confirm_password" required placeholder="Përsërit fjalëkalimin">
        
        <button type="submit" name="signup_submit">Regjistrohu</button>
    </form>
    
    <p style="text-align:center; margin-top:15px; font-size: 0.9em;">
        Keni tashmë llogari? <a href="login.php" style="color: #007bff; text-decoration: none;">Hyni këtu</a>
    </p>
</div>

</body>
</html>