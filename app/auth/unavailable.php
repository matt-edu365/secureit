<?php
require __DIR__ . '/../lib.php';

$authError = trim((string) ($_GET['auth_error'] ?? ''));
$authMessage = trim((string) ($_GET['auth_message'] ?? ''));

$title = 'Access not yet available';
$heading = 'This organisation is not onboarded yet';
$body = 'Your Microsoft 365 account signed in successfully, but SecureIT does not have a matching tenant record for this organisation yet.';

if ($authError === 'tenant_unauthorised') {
    $heading = 'This organisation is not subscribed';
    $body = 'Your Microsoft 365 tenant is not currently allowed to access SecureIT. If you expected access, contact ICT365 to confirm the subscription and onboarding status.';
} elseif ($authError === 'tenant_unknown') {
    $heading = 'This organisation has not been onboarded';
    $body = 'Your Microsoft 365 tenant is not yet set up in SecureIT. If you expected to see your portal, contact ICT365 and ask them to onboard your organisation.';
}

if ($authMessage !== '') {
    $body = $authMessage;
}

ob_start();
?>
<section class="section">
  <div class="container">
    <div class="panel" style="max-width:840px; margin:0 auto;">
      <div class="empty-state" style="border-color: rgba(175, 77, 26, 0.3); background:#fff7f2;">
        <strong><?php echo htmlspecialchars($heading); ?></strong>
        <p class="muted" style="margin:8px 0 0;"><?php echo htmlspecialchars($body); ?></p>
      </div>

      <div style="margin-top:22px; display:grid; gap:14px;">
        <div>
          <h2 class="section-title" style="font-size:1.6rem; margin-bottom:10px; text-align:left;">What this means</h2>
          <p class="muted" style="margin:0;">You reached SecureIT successfully, but your organisation is not yet enabled for customer access. This usually means the tenant has not been onboarded, or the subscription is not active for this Microsoft 365 directory.</p>
        </div>

        <div class="empty-state">
          <strong>Next step</strong>
          <p class="muted" style="margin:8px 0 0;">If you believe this is a mistake, contact ICT365 and ask them to confirm your tenant is subscribed and linked to SecureIT.</p>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:12px;">
          <a class="button" href="/login.php" style="text-decoration:none;">Back to login</a>
          <a class="button button-secondary" href="mailto:Sales@ict365.ky" style="text-decoration:none;">Contact ICT365</a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
secureit_render_shell($title, $content, [
    'pageTitle' => 'Access not yet available',
    'pageIntro' => "Your Microsoft 365 account signed in successfully, but this organisation is not yet enabled for SecureIT access.\n\nIf you think this should be available, contact ICT365 to confirm onboarding or subscription status.",
    'eyebrow' => '',
    'hideHeroChrome' => true,
    'heroIntroMaxWidth' => '840px',
    'heroBackground' => secureit_default_hero_background(),
    'navLinks' => [],
    'navCta' => ['href' => 'login.php', 'label' => 'SecureIT Login'],
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
