<?php
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ms_login'])) {
        $m365Email = strtolower(trim($_POST['m365_email'] ?? ''));
        $route = secureit_resolve_login_route($m365Email);

        if (secureit_entra_is_enabled()) {
            if (($route['source'] ?? '') === 'seed') {
                $identity = $route['identity'] ?? [];
                $role = ($identity['role'] ?? null) === 'admin' || ($route['route'] ?? '') === 'dashboard.php' ? 'admin' : 'customer';
                $tenantKey = $identity['tenantKey'] ?? null;
                secureit_set_auth_context($role, $m365Email, is_string($tenantKey) ? $tenantKey : null, ['identitySource' => $route['source'] ?? 'default']);
                header('Location: ' . $route['route'], true, 302);
                exit;
            }

            header('Location: ' . secureit_entra_login_url($m365Email), true, 302);
            exit;
        }

        if (($route['source'] ?? '') === 'seed') {
            $identity = $route['identity'] ?? [];
            $role = ($identity['role'] ?? null) === 'admin' || ($route['route'] ?? '') === 'dashboard.php' ? 'admin' : 'customer';
            $tenantKey = $identity['tenantKey'] ?? null;
            secureit_set_auth_context($role, $m365Email, is_string($tenantKey) ? $tenantKey : null, ['identitySource' => $route['source'] ?? 'default']);
        } else {
            secureit_clear_auth_context();
        }
        header('Location: ' . $route['route'], true, 302);
        exit;
    }

}

$unknownIdentity = isset($_GET['unknown']) && $_GET['unknown'] === '1';
$deniedAccess = isset($_GET['denied']) && $_GET['denied'] === '1';
$authError = trim((string) ($_GET['auth_error'] ?? ''));
$authMessage = trim((string) ($_GET['auth_message'] ?? ''));
$authErrorMessage = '';
if ($authMessage !== '') {
    $authErrorMessage = $authMessage;
} elseif ($authError !== '') {
    $authErrorMessage = match ($authError) {
        'missing_config' => 'Microsoft Entra sign-in is not configured on this environment yet.',
        'missing_code' => 'The sign-in response was missing the authorization code.',
        'state_mismatch' => 'The sign-in session could not be verified.',
        'token_exchange_failed' => 'SecureIT could not complete the sign-in with Microsoft.',
        'token_invalid' => 'Microsoft returned a sign-in token that could not be validated.',
        'tenant_unauthorised' => 'That Microsoft 365 tenant is not allowed to sign in here.',
        'tenant_unknown' => 'No SecureIT tenant is linked to that Microsoft 365 tenant.',
        default => 'The sign-in could not be completed.',
    };
}

ob_start();
?>
<section class="section">
  <div class="container">
    <div style="max-width:680px; margin:0 auto;">
      <article class="panel">
        <div style="margin-bottom:20px; text-align:center;">
          <h2 class="section-title" style="font-size:2rem; margin-bottom:10px; text-align:center;">SecureIT Login</h2>
          <div class="muted">Use your M365 account to login to your SecureIT portal.</div>
        </div>

        <?php if ($authErrorMessage !== ''): ?>
          <div class="empty-state" style="margin-bottom:22px; border-color: rgba(175, 77, 26, 0.3); background: #fff7f2;">
            <strong>Sign-in could not be completed</strong>
            <p class="muted" style="margin:8px 0 0;"><?php echo htmlspecialchars($authErrorMessage); ?></p>
          </div>
        <?php endif; ?>

        <?php if ($unknownIdentity): ?>
          <div class="empty-state" style="margin-bottom:22px; border-color: rgba(175, 77, 26, 0.3); background: #fff7f2;">
            <strong>No matching local identity</strong>
            <p class="muted" style="margin:8px 0 0;">Use `fab@local` or `con@local` on localhost, or sign in with Microsoft Entra for normal access.</p>
          </div>
        <?php endif; ?>

        <?php if ($deniedAccess): ?>
          <div class="empty-state" style="margin-bottom:22px; border-color: rgba(175, 77, 26, 0.3); background: #fff7f2;">
            <strong>Access restricted</strong>
            <p class="muted" style="margin:8px 0 0;">That page is available only to ICT365 administrator accounts.</p>
          </div>
        <?php endif; ?>

        <form method="post" style="display:grid; gap:16px;">
          <div>
            <label for="m365-email" style="margin-top:0;">Business or school email address</label>
            <input id="m365-email" name="m365_email" type="text" inputmode="email" autocomplete="username" placeholder="name@company.com">
            <p class="field-note">SecureIT will redirect you to Microsoft after you press the button.</p>
          </div>

          <button type="submit" name="ms_login" value="1" style="min-height:54px; font-size:1rem;">
            <svg width="20" height="20" viewBox="0 0 23 23" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect x="1" y="1" width="9" height="9" fill="#F25022"/>
              <rect x="12" y="1" width="9" height="9" fill="#7FBA00"/>
              <rect x="1" y="12" width="9" height="9" fill="#00A4EF"/>
              <rect x="12" y="12" width="9" height="9" fill="#FFB900"/>
            </svg>
            Sign in with Microsoft
          </button>

        </form>
      </article>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
secureit_render_shell('SecureIT Login', $content, [
    'pageTitle' => null,
    'pageIntro' => null,
    'eyebrow' => '',
    'hideHeroChrome' => true,
    'heroIntroMaxWidth' => '840px',
    'heroBackground' => secureit_default_hero_background(),
    'navLinks' => [],
    'footerLinks' => [
        ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ['href' => 'login.php', 'label' => 'Customer login'],
    ],
    'footerSecondaryLinks' => [
        ['href' => 'dashboard.php', 'label' => 'Employee portal'],
        ['href' => 'login.php', 'label' => 'Customer login'],
        ['href' => 'admin.php', 'label' => 'Admin'],
    ],
    'footerContact' => [
        ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
        ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
        ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
    ],
]);
