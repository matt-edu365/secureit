<?php
require __DIR__ . '/lib.php';

$tenants = secureit_load_tenants()['tenants'] ?? [];
$tenantCount = count($tenants);
$controlCount = secureit_total_canonical_control_count();

ob_start();
?>
<section class="metrics-strip">
  <div class="metrics-grid">
    <div class="metric-stat">
      <div class="metric-value"><?php echo htmlspecialchars((string) $controlCount); ?></div>
      <div class="metric-label">M365 Security Checks</div>
      <div class="metric-label">A clear view of Microsoft 365 posture.</div>
    </div>
    <div class="metric-stat">
      <div class="metric-value">8</div>
      <div class="metric-label">Functional Reporting Areas</div>
      <div class="metric-label">Coverage across the security areas that matter.</div>
    </div>
    <div class="metric-stat">
      <div class="metric-value" style="font-size:1.5rem; line-height:1.2;">Continuous monitoring</div>
      <div class="metric-label">Repeatable checks keep posture visible with less manual effort.</div>
    </div>
    <div class="metric-stat">
      <div class="metric-value" style="font-size:1.5rem; line-height:1.2;">Actionable reporting</div>
      <div class="metric-label">Reports in PDF, Excel, and HTML for faster decisions.</div>
    </div>
  </div>
</section>

<section class="section" id="overview">
  <div class="section-heading">
    <h2 class="section-title" style="text-align:center; font-size:clamp(2rem, 4vw, 3.15rem);">Continuous Microsoft 365 Security Visibility</h2>
    <p class="section-intro" style="max-width:960px; margin-left:auto; margin-right:auto; text-align:center;">Your M365 environment is central to the way your organisation operates. SecureIT gives you clear, ongoing visibility of your M365 security configuration through automated checks, regular reporting, and an easy-to-use portal.</p>
  </div>

  <div class="feature-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
    <article class="card feature-card">
      <div class="feature-icon">🛡️</div>
      <h3>See your current posture clearly</h3>
      <p>SecureIT reviews key Microsoft 365 checks against recognised security best practices, so IT teams and business leaders can quickly see where the tenant is strong, where gaps exist, and what to improve next.</p>
    </article>
    <article class="card feature-card">
      <div class="feature-icon">📈</div>
      <h3>Spot drift before it spreads</h3>
      <p>Regular monitoring highlights posture changes as they happen, giving MSPs and security leads an early view of risk, compliance drift, and progress over time without waiting for an audit or incident.</p>
    </article>
    <article class="card feature-card">
      <div class="feature-icon">📬</div>
      <h3>Turn reports into action</h3>
      <p>Clear email reports and a simple customer portal keep managers and decision-makers informed with plain-English findings, practical recommendations, and the context they need to approve next steps quickly.</p>
    </article>
  </div>
</section>

<section class="section" id="why-ict365">
  <div class="section-heading">
    <h2 class="section-title" style="text-align:center; font-size:clamp(2rem, 4vw, 3.15rem);">Bring security into focus</h2>
    <p class="section-intro" style="max-width:960px; margin-left:auto; margin-right:auto; text-align:center;">Microsoft 365 gives you a wide range of security settings, but not always a simple way to review them regularly. SecureIT turns that complexity into a repeatable process with clear results you can act on.</p>
  </div>

  <div class="split">
    <article class="card panel" style="grid-column:1 / -1;">
      <h3 style="font-size:1.35rem; margin-bottom:14px; color:var(--eden);">What SecureIT helps you do</h3>
      <p class="muted" style="margin-bottom:18px;">Each customer gets access to a SecureIT portal for current posture, trend review, and on-demand reporting whenever a fresh view is needed.</p>
      <div class="feature-grid" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
        <article class="card feature-card">
          <h3 style="margin-bottom:6px; font-size:1.02rem;">Track posture consistently</h3>
          <p class="muted" style="margin-bottom:0;">Review Microsoft 365 posture on a regular basis and keep a reliable view of change, risk, and progress.</p>
        </article>
        <article class="card feature-card">
          <h3 style="margin-bottom:6px; font-size:1.02rem;">Read findings in plain English</h3>
          <p class="muted" style="margin-bottom:0;">See current posture, key findings, and recommended actions in a format that works for technical and non-technical stakeholders.</p>
        </article>
        <article class="card feature-card">
          <h3 style="margin-bottom:6px; font-size:1.02rem;">Support governance decisions</h3>
          <p class="muted" style="margin-bottom:0;">Use SecureIT to support compliance reviews, risk discussions, cyber insurance preparation, and ongoing improvement planning.</p>
        </article>
      </div>
    </article>
  </div>
</section>

<?php
$content = ob_get_clean();
secureit_render_shell(
    'SecureIT | ICT365',
    $content,
    [
        'pageTitle' => 'SecureIT - Continuous, clear M365 security monitoring.',
        'pageIntro' => "ICT365 are pleased to present 'SecureIT' - your security portal for M365.",
        'eyebrow' => '',
        'heroBadges' => [],
        'heroActions' => [],
        'heroBackground' => secureit_default_hero_background(),
        'heroTextAlign' => 'center',
        'navLinks' => [],
        'navCta' => ['href' => 'login.php', 'label' => 'SecureIT Login'],
        'footerLinks' => [
            ['href' => '#overview', 'label' => 'Overview'],
            ['href' => '#why-ict365', 'label' => 'Why SecureIT'],
            ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ],
        'footerContact' => [
            ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
            ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
            ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
        ],
        'footerSecondaryLinks' => [
            ['href' => 'dashboard.php', 'label' => 'Employee portal'],
            ['href' => 'login.php', 'label' => 'Customer login'],
            ['href' => 'admin.php', 'label' => 'Admin'],
        ],
    ]
);
