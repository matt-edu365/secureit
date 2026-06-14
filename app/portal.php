<?php
require __DIR__ . '/lib.php';

$tenants = secureit_load_tenants()['tenants'] ?? [];

ob_start();
?>
<section class="section">
  <div class="portal-grid">
    <article class="card portal-card">
      <div class="portal-icon">👔</div>
      <div>
        <h3>ICT365 employee login</h3>
        <p class="muted">Use this route if you are part of the ICT365 team and need access to tenant administration, platform settings, and onboarding tools.</p>
      </div>
      <div class="inline-links">
        <a class="button" href="dashboard.php">Continue as ICT365 admin</a>
      </div>
    </article>

    <article class="card portal-card">
      <div class="portal-icon">🏢</div>
      <div>
        <h3>Customer login</h3>
        <p class="muted">Use this route if you are a customer viewing your own SecureIT tenant posture, report history, and latest published report.</p>
      </div>
      <?php if (!$tenants): ?>
        <div class="empty-state">
          <strong>No customer tenants are configured yet.</strong>
          <p class="muted">An ICT365 administrator needs to onboard the tenant before customer access can be routed here.</p>
        </div>
      <?php else: ?>
        <form method="get" action="tenant.php">
          <label for="tenant">Choose your tenant</label>
          <select id="tenant" name="tenant">
            <?php foreach ($tenants as $tenant): ?>
              <option value="<?php echo htmlspecialchars($tenant['id'] ?? ''); ?>"><?php echo htmlspecialchars($tenant['name'] ?? ($tenant['id'] ?? 'Unknown tenant')); ?></option>
            <?php endforeach; ?>
          </select>
          <p class="field-note">This is a prototype stand-in for a proper customer login flow.</p>
          <button type="submit">Continue as customer</button>
        </form>
      <?php endif; ?>
    </article>
  </div>
</section>
<?php
$content = ob_get_clean();
secureit_render_shell('SecureIT Portal Access', $content, [
    'pageTitle' => 'Choose your SecureIT portal',
    'pageIntro' => 'Use the appropriate landing path for your role. ICT365 staff should enter through the administrator route, while customer users should continue to their own tenant portal.',
    'eyebrow' => 'SecureIT access routing',
    'backHref' => 'login.php',
    'backLabel' => 'Back to SecureIT login',
    'heroBadges' => [
        'Admin and customer journeys separated',
        'Prototype login routing without full auth yet',
    ],
    'navLinks' => [],
    'navCta' => ['href' => 'login.php', 'label' => 'SecureIT Login'],
    'footerLinks' => [
        ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ['href' => 'portal.php', 'label' => 'Customer portal'],
    ],
    'footerSecondaryLinks' => [
        ['href' => 'dashboard.php', 'label' => 'Employee portal'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
        ['href' => 'admin.php', 'label' => 'Admin'],
    ],
    'footerContact' => [
        ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
        ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
        ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
    ],
]);
