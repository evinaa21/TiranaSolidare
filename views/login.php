<?php include '../includes/header.php'; ?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Mirësevini</h2>
            <p>Hyni në llogarinë tuaj për të vazhduar</p>
        </div>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                if ($_GET['error'] == 'empty_fields') echo "Ju lutem plotësoni të gjitha fushat!";
                elseif ($_GET['error'] == 'wrong_credentials') echo "Email ose fjalëkalimi i gabuar!";
                else echo "Ndodhi një gabim!";
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
            <div class="alert alert-success">
                Llogaria u krijua me sukses! Ju lutem hyni.
            </div>
        <?php endif; ?>

        <form action="../src/actions/login_action.php" method="POST">
            <div class="form-group mb-3">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="emri@shembull.com" required>
            </div>
            <div class="form-group mb-3">
                <label for="password">Fjalëkalimi</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="********" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-auth">Hyni</button>
        </form>
        
        <div class="auth-footer mt-3 text-center">
            <p>Nuk keni llogari? <a href="register.php">Regjistrohuni këtu</a></p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>