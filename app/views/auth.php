<?php /** @var string $mode */ ?>
<section class="section-container">
    <div class="easy-auth-card">
        <div class="auth-header">
            <span class="auth-clover-badge">🍀</span>
            <h1 class="auth-main-title"><?= $mode === 'login' ? 'Welcome Back!' : 'Create Account' ?></h1>
            <p class="auth-sub-desc">
                <?= $mode === 'login' 
                    ? 'Enter your username and password to sign in.' 
                    : 'Create your player account in seconds to start playing.' ?>
            </p>
        </div>

        <form method="post" action="/action/<?= e($mode) ?>" class="easy-auth-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="form-input-group">
                <label class="form-label" for="username-input">
                    <span>👤 Username:</span>
                </label>
                <input type="text" id="username-input" name="username" required minlength="3" maxlength="30"
                       pattern="[a-zA-Z0-9_.-]+" autocomplete="username" class="easy-form-input"
                       value="<?= e($_POST['username'] ?? '') ?>" placeholder="Enter your username">
            </div>

            <div class="form-input-group">
                <label class="form-label" for="password-input">
                    <span>🔒 Password:</span>
                </label>
                <input type="password" id="password-input" name="password" required minlength="4" class="easy-form-input"
                       autocomplete="<?= $mode === 'login' ? 'current-password' : 'new-password' ?>" placeholder="Enter your password">
            </div>

            <button class="big-action-btn btn-green pulse-btn" type="submit" style="margin-top: 10px;">
                <span><?= $mode === 'login' ? '👉 SIGN IN' : '🎁 CREATE ACCOUNT & START' ?></span>
            </button>
        </form>

        <div class="auth-switch-box">
            <?php if ($mode === 'login'): ?>
                <p>Don't have an account? <a href="/register" class="auth-switch-link">Create Free Account ➔</a></p>
            <?php else: ?>
                <p>Already have an account? <a href="/login" class="auth-switch-link">Sign In Here ➔</a></p>
            <?php endif; ?>
        </div>
    </div>
</section>
