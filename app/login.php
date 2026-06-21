<?php
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ms_login'])) {
        $m365Email = strtolower(trim($_POST['m365_email'] ?? ''));
        $route = secureit_resolve_login_route($m365Email);
        if (($route['source'] ?? '') === 'seed' || ($route['source'] ?? '') === 'domain') {
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

    if (isset($_POST['enquiry_submit'])) {
        header('Location: login.php?enquiry=received', true, 302);
        exit;
    }
}

$enquiryReceived = isset($_GET['enquiry']) && $_GET['enquiry'] === 'received';
$unknownIdentity = isset($_GET['unknown']) && $_GET['unknown'] === '1';
$deniedAccess = isset($_GET['denied']) && $_GET['denied'] === '1';

ob_start();
?>
<section class="section">
  <div class="container">
    <div class="split" style="grid-template-columns:minmax(0, 1fr) minmax(340px, 0.94fr); align-items:start;">
      <article class="panel">
        <div style="margin-bottom:20px;">
          <h2 class="section-title" style="font-size:2rem; margin-bottom:10px; text-align:left;">SecureIT Login</h2>
          <div class="muted">Use your Microsoft 365 identity to access the SecureIT portal.</div>
        </div>

        <div class="empty-state" style="margin-bottom:22px;">
          <strong>Existing customers</strong>
          <p class="muted" style="margin:8px 0 0;">Use your business or school Microsoft account to sign in and access your SecureIT tenant.</p>
        </div>

        <?php if ($unknownIdentity): ?>
          <div class="empty-state" style="margin-bottom:22px; border-color: rgba(175, 77, 26, 0.3); background: #fff7f2;">
            <strong>No matching local identity</strong>
            <p class="muted" style="margin:8px 0 0;">Use `fab@local`, `con@local`, or an `@ict365.ky` administrator account.</p>
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
            <input id="m365-email" name="m365_email" type="text" inputmode="email" autocomplete="username" placeholder="name@company.com" required>
            <p class="field-note">Local seed routing can map specific identities to a customer tenant page or to the admin dashboard. If no seed is found, any sign-in using an <strong>@ict365.ky</strong> address is sent to the ICT365 admin dashboard and other addresses return here.</p>
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

          <p class="field-note" style="margin-top:0;">Local seeded identities can override the route to a tenant page, otherwise <strong>@ict365.ky</strong> users go to the admin dashboard and other addresses come back to this page.</p>
        </form>
      </article>

      <aside class="panel">
        <div style="margin-bottom:20px;">
          <h2 class="section-title" style="font-size:2rem; margin-bottom:10px; text-align:left;">Not a subscriber?</h2>
          <div class="muted">Fill out the form and one of the ICT365 team will get in touch.</div>
        </div>

        <?php if ($enquiryReceived): ?>
          <div class="success" style="margin-bottom:18px;">
            Thanks, your enquiry has been captured.
          </div>
        <?php endif; ?>

        <form method="post" style="display:grid; gap:14px;">
          <div>
            <label for="contact-name" style="margin-top:0;">Full name</label>
            <input id="contact-name" name="contact_name" type="text" placeholder="Jane Smith" required>
          </div>

          <div>
            <label for="company-name" style="margin-top:0;">Organisation name</label>
            <input id="company-name" name="company_name" type="text" placeholder="Acme School or Acme Ltd" required>
          </div>

          <div>
            <label for="contact-email" style="margin-top:0;">Email address</label>
            <input id="contact-email" name="contact_email" type="email" placeholder="name@company.com" required>
          </div>

          <div>
            <label for="contact-phone" style="margin-top:0;">Phone number</label>
            <input id="contact-phone" name="contact_phone" type="tel" placeholder="+1 (345) 555-0123">
          </div>

          <div>
            <label for="org-type" style="margin-top:0;">Organisation type</label>
            <select id="org-type" name="org_type" required>
              <option value="">Select one</option>
              <option>Business</option>
              <option>School</option>
              <option>Non-profit</option>
              <option>Government</option>
            </select>
          </div>

          <div>
            <label for="interest" style="margin-top:0;">What are you interested in?</label>
            <select id="interest" name="interest" required>
              <option value="">Select one</option>
              <option>M365 security posture reporting</option>
              <option>Managed Microsoft 365 security</option>
              <option>Tenant onboarding</option>
              <option>General SecureIT enquiry</option>
            </select>
          </div>

          <div>
            <label for="notes" style="margin-top:0;">Tell us a little about your environment</label>
            <input id="notes" name="notes" type="text" placeholder="Approximate user count, any concerns, reporting needs, etc.">
          </div>

          <button type="submit" name="enquiry_submit" value="1">Request information</button>
        </form>
      </aside>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
secureit_render_shell('SecureIT Login', $content, [
    'pageTitle' => 'Sign in to SecureIT',
    'pageIntro' => "Existing customers use your business / school email address to access your SecureIT portal.\n\nNot a subscriber? Fill out the form and one of the ICT365 team will get in touch.",
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
