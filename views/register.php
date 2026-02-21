<?php include '../includes/header.php'; ?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Regjistrimi</h2>
            <p>Bëhuni pjesë e komunitetit Tirana Solidare</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                if ($_GET['error'] == 'empty_fields') echo "Ju lutem plotësoni të gjitha fushat!";
                elseif ($_GET['error'] == 'invalid_email') echo "Formati i email-it nuk është i saktë!";
                elseif ($_GET['error'] == 'password_mismatch') echo "Fjalëkalimet nuk përputhen!";
                elseif ($_GET['error'] == 'email_taken') echo "Ky email është regjistruar tashmë!";
                else echo "Ndodhi një gabim! Provoni përsëri.";
                ?>
            </div>
        <?php endif; ?>

        <form action="../src/actions/register_action.php" method="POST">
            <div class="form-group mb-3">
                <label for="emri">Emri i Plotë</label>
                <input type="text" name="emri" id="emri" class="form-control" placeholder="Emri Mbiemri" required>
            </div>
            <div class="form-group mb-3">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="emri@shembull.com" required>
            </div>
            <div class="form-group mb-3">
                <label for="password">Fjalëkalimi</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="********" required>
            </div>
            <div class="form-group mb-3">
                <label for="confirm_password">Konfirmo Fjalëkalimin</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="********" required>
            </div>
            <button type="submit" class="btn btn-success w-100 btn-auth">Regjistrohu</button>
        </form>
        
        <div class="auth-footer mt-3 text-center">
            <p>Keni llogari? <a href="login.php">Hyni këtu</a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>