<?php
// views/dashboard.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /TiranaSolidare/views/login.php");
    exit();
}

$isAdmin = ($_SESSION['roli'] ?? '') === 'Admin';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container py-4">

    <!-- Welcome -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Mirësevini, <?php echo htmlspecialchars($_SESSION['emri']); ?>!</h2>
            <span class="badge bg-<?php echo $isAdmin ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($_SESSION['roli']); ?></span>
        </div>
        <a href="/TiranaSolidare/src/actions/logout.php" class="btn btn-outline-danger btn-sm">Dil (Logout)</a>
    </div>

    <!-- Dashboard Stats -->
    <div id="dashboard-stats" class="mb-4"></div>

    <!-- Notifications -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Njoftimet <span id="notif-badge" class="badge bg-info ms-2" style="display:none"></span></h5>
            <button class="btn btn-sm btn-outline-primary" onclick="markAllRead()">Shëno të gjitha si të lexuara</button>
        </div>
        <div class="card-body" id="notification-list">
            <p class="text-muted">Duke ngarkuar njoftimet…</p>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- ═══ Admin Panels ═══ -->

    <!-- Event Management -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Menaxho Eventet</h5></div>
        <div class="card-body">
            <!-- Create Event Form -->
            <form id="create-event-form" class="row g-2 mb-3">
                <div class="col-md-3">
                    <input type="text" name="titulli" class="form-control" placeholder="Titulli" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="vendndodhja" class="form-control" placeholder="Vendndodhja" required>
                </div>
                <div class="col-md-2">
                    <input type="datetime-local" name="data" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="id_kategoria" class="form-control" placeholder="ID Kategoria" min="1" max="5">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Krijo Event</button>
                </div>
                <div class="col-12">
                    <textarea name="pershkrimi" class="form-control" rows="2" placeholder="Përshkrimi…"></textarea>
                </div>
            </form>
            <div id="admin-event-list">
                <p class="text-muted">Duke ngarkuar eventet…</p>
            </div>
        </div>
    </div>

    <!-- Event Applications Detail -->
    <div class="card mb-4" id="event-applications-card" style="display:none">
        <div class="card-header"><h5 class="mb-0">Aplikime për Eventin</h5></div>
        <div class="card-body" id="event-applications"></div>
    </div>

    <!-- User Management -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Menaxho Përdoruesit</h5></div>
        <div class="card-body" id="admin-user-list">
            <p class="text-muted">Duke ngarkuar përdoruesit…</p>
        </div>
    </div>

    <!-- Help Requests -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Kërkesat për Ndihmë</h5></div>
        <div class="card-body" id="help-request-list">
            <p class="text-muted">Duke ngarkuar kërkesat…</p>
        </div>
    </div>

    <?php else: ?>
    <!-- ═══ Volunteer Panels ═══ -->

    <!-- Browse Events -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Eventet e Hapura</h5></div>
        <div class="card-body" id="event-list">
            <p class="text-muted">Duke ngarkuar eventet…</p>
        </div>
    </div>

    <!-- My Applications -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Aplikimet e Mia</h5></div>
        <div class="card-body" id="application-list">
            <p class="text-muted">Duke ngarkuar aplikimet…</p>
        </div>
    </div>

    <!-- Submit Help Request -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Dërgo Kërkesë për Ndihmë</h5></div>
        <div class="card-body">
            <form id="help-request-form" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="titulli" class="form-control" placeholder="Titulli" required>
                </div>
                <div class="col-md-3">
                    <select name="tipi" class="form-select" required>
                        <option value="Kërkesë">Kërkesë</option>
                        <option value="Ofertë">Ofertë</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="vendndodhja" class="form-control" placeholder="Vendndodhja">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100">Dërgo</button>
                </div>
                <div class="col-12">
                    <textarea name="pershkrimi" class="form-control" rows="2" placeholder="Përshkrimi…"></textarea>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>