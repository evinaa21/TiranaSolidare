<?php
session_start();

if (!isset($_SESSION['user_id'])) {
	header('Location: /TiranaSolidare/views/login.php');
	exit();
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Profili im — Tirana Solidare</title>
	<link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
	<link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/auth.css">
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>
	<section class="auth-shell">
		<div class="auth-blob auth-blob--green"></div>
		<div class="auth-blob auth-blob--warm"></div>

		<div class="auth-card">
			<div class="auth-card__header">
				<span class="auth-pill">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
					Profili im
				</span>
				<h1 class="auth-title">Përditëso të dhënat e profilit</h1>
				<p class="auth-subtitle">Shiko të dhënat kryesore, statistikat personale dhe përditëso emrin ose fjalëkalimin pa dalë nga platforma.</p>

				<div id="profile-status" class="auth-alert" style="display:none"></div>
			</div>

			<div class="profile-panels">
				<div class="profile-card-ui">
					<h3>Informacioni i profilit</h3>
					<div class="profile-meta">
						<div class="profile-meta__item"><span>Emri:</span><strong id="profile-emri">—</strong></div>
						<div class="profile-meta__item"><span>Email:</span><strong id="profile-email">—</strong></div>
						<div class="profile-meta__item"><span>Roli:</span><strong id="profile-roli">—</strong></div>
						<div class="profile-meta__item"><span>Statusi i llogarisë:</span><strong id="profile-statusi">—</strong></div>
					</div>
				</div>

				<div class="profile-card-ui">
					<h3>Statistikat e mia</h3>
					<div class="stat-grid">
						<div class="stat-chip">
							<span>Total aplikime</span>
							<strong id="stat-aplikime">0</strong>
						</div>
						<div class="stat-chip">
							<span>Kërkesa ndihme</span>
							<strong id="stat-kerkesa">0</strong>
						</div>
					</div>
				</div>
			</div>

			<div class="profile-panels">
				<div class="form-card">
					<div class="inline-actions">
						<h3>Ndrysho emrin</h3>
						<span class="muted">Ruaj një përshkrim të qartë për të tjerët.</span>
					</div>
					<form id="name-form" class="auth-form" autocomplete="off">
						<div class="auth-field">
							<label for="emri">Emri</label>
							<input class="auth-input" type="text" id="emri" name="emri" placeholder="Emri Mbiemri" required>
						</div>
						<button type="submit" class="btn_primary auth-submit">Ruaj emrin</button>
					</form>
				</div>

				<div class="form-card">
					<h3>Ndrysho fjalëkalimin</h3>
					<div class="form-divider"></div>
					<form id="password-form" class="auth-form" autocomplete="off">
						<div class="auth-field">
							<label for="current_password">Fjalëkalimi aktual</label>
							<input class="auth-input" type="password" id="current_password" name="current_password" placeholder="********" required>
						</div>
						<div class="auth-field">
							<label for="new_password">Fjalëkalimi i ri</label>
							<input class="auth-input" type="password" id="new_password" name="new_password" placeholder="********" required>
						</div>
						<div class="auth-field">
							<label for="confirm_password">Konfirmo fjalëkalimin</label>
							<input class="auth-input" type="password" id="confirm_password" name="confirm_password" placeholder="********" required>
						</div>
						<button type="submit" class="btn_primary auth-submit">Përditëso fjalëkalimin</button>
					</form>
				</div>
			</div>
		</div>
	</section>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script>
	const profileApi = '/TiranaSolidare/api/auth.php?action=me';
	const statsApi = '/TiranaSolidare/api/stats.php?action=my_stats';
	const updateNameApi = '/TiranaSolidare/api/users.php?action=update_profile';
	const changePasswordApi = '/TiranaSolidare/api/auth.php?action=change_password';

	const statusBox = document.getElementById('profile-status');
	const emriEl = document.getElementById('profile-emri');
	const emailEl = document.getElementById('profile-email');
	const roliEl = document.getElementById('profile-roli');
	const statusiEl = document.getElementById('profile-statusi');

	const statAplikime = document.getElementById('stat-aplikime');
	const statKerkesa = document.getElementById('stat-kerkesa');

	const nameForm = document.getElementById('name-form');
	const passwordForm = document.getElementById('password-form');

	function setStatus(type, message) {
		statusBox.style.display = 'flex';
		statusBox.className = 'auth-alert ' + (type === 'error' ? 'auth-alert--error' : 'auth-alert--success');
		statusBox.textContent = message;
	}

	async function loadProfile() {
		try {
			const res = await fetch(profileApi, { credentials: 'same-origin' });
			const payload = await res.json();
			if (!res.ok || !payload.success) throw new Error(payload.message || 'Nuk u morën të dhënat e profilit.');
			const data = payload.data;
			emriEl.textContent = data.emri || '—';
			emailEl.textContent = data.email || '—';
			roliEl.textContent = data.roli || '—';
			statusiEl.textContent = data.statusi_llogarise || '—';
			document.getElementById('emri').value = data.emri || '';
		} catch (err) {
			setStatus('error', err.message);
		}
	}

	async function loadStats() {
		try {
			const res = await fetch(statsApi, { credentials: 'same-origin' });
			const payload = await res.json();
			if (!res.ok || !payload.success) throw new Error(payload.message || 'Nuk u morën statistikat.');
			const apps = payload.data.applications || {};
			const helps = payload.data.help_requests || {};
			statAplikime.textContent = apps.total_aplikime ?? 0;
			statKerkesa.textContent = helps.total_kerkesa ?? 0;
		} catch (err) {
			setStatus('error', err.message);
		}
	}

	nameForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		const emri = (document.getElementById('emri').value || '').trim();
		if (!emri) {
			setStatus('error', 'Ju lutem shkruani emrin.');
			return;
		}
		try {
			const res = await fetch(updateNameApi, {
				method: 'PUT',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({ emri }),
			});
			const payload = await res.json();
			if (!res.ok || !payload.success) throw new Error(payload.message || 'Nuk u ruajt emri.');
			emriEl.textContent = emri;
			setStatus('success', 'Emri u përditësua me sukses.');
            const headerUser = document.querySelector('.header-user');
            if (headerUser) headerUser.textContent = emri;
		} catch (err) {
			setStatus('error', err.message);
		}
	});

	passwordForm.addEventListener('submit', async (event) => {
		event.preventDefault();
		const current_password = document.getElementById('current_password').value;
		const new_password = document.getElementById('new_password').value;
		const confirm_password = document.getElementById('confirm_password').value;

		if (new_password !== confirm_password) {
			setStatus('error', 'Fjalëkalimet nuk përputhen.');
			return;
		}

		try {
			const res = await fetch(changePasswordApi, {
				method: 'PUT',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({ current_password, new_password, confirm_password }),
			});
			const payload = await res.json();
			if (!res.ok || !payload.success) throw new Error(payload.message || 'Nuk u përditësua fjalëkalimi.');
			passwordForm.reset();
			setStatus('success', 'Fjalëkalimi u përditësua me sukses.');
		} catch (err) {
			setStatus('error', err.message);
		}
	});

	loadProfile();
	loadStats();
</script>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>