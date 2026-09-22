<section class="authcard admin-authcard">
    <div class="admin-auth-header">
        <span class="admin-badge"><?= icon('shield', 'badge-icon', 16) ?> Administration</span>
        <h2 class="auth-title">Admin Sign In</h2>
        <p class="muted">Management console for game draws, user accounts, and economics simulation.</p>
    </div>

    <div class="admin-credentials-notice">
        <div class="cred-notice-icon"><?= icon('lock', 'cred-icon', 18) ?></div>
        <div class="cred-notice-body">
            <strong>Default Credentials for Setup</strong>
            <div class="cred-list">
                <span>Username: <code><?= e(Config::get('admin')['username'] ?? 'admin') ?></code></span>
                <span>Password: <code><?= e(Config::get('admin')['password'] ?? 'admin123') ?></code></span>
            </div>
            <span class="cred-hint">You can change these in <code>config.local.php</code>.</span>
        </div>
    </div>

    <form method="post" action="/admin/action/login" class="authform">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
            <span>Admin Username</span>
            <input type="text" name="username" required autocomplete="username"
                   value="<?= e($_POST['username'] ?? 'admin') ?>" placeholder="Admin username">
        </label>
        <label>
            <span>Admin Password</span>
            <input type="password" name="password" required autocomplete="current-password"
                   placeholder="Admin password">
        </label>
        <button class="cta cta-amber cta-btn" type="submit">
            <span class="cta-mid"><?= icon('shield', 'btn-icon', 18) ?> Authenticate Admin</span>
        </button>
    </form>

    <p class="muted center"><a href="/">&larr; Back to Lucky Buzz</a></p>
</section>
