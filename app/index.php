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
      <div class="metric-label">M365 security checks</div>
    </div>
    <div class="metric-stat">
      <div class="metric-value">8</div>
      <div class="metric-label">Functional Reporting Areas</div>
    </div>
    <div class="metric-stat">
      <div class="metric-value" style="font-size:1.5rem; line-height:1.2;">Automated reporting</div>
      <div class="metric-label">run repeatable checks and deliver reports without manual effort</div>
    </div>
    <div class="metric-stat">
      <div class="metric-value" style="font-size:1.5rem; line-height:1.2;">Flexible reporting</div>
      <div class="metric-label">SecureIT generates reports in PDF, Excel, and HTML formats</div>
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
      <h3>Know where you stand</h3>
      <p>SecureIT continuously reviews key areas of your Microsoft 365 environment against recognised security best practices, helping you understand where your tenant is performing well and where improvements are recommended.</p>
    </article>
    <article class="card feature-card">
      <div class="feature-icon">📈</div>
      <h3>Track security over time</h3>
      <p>Rather than waiting for an audit, incident, or manual review, SecureIT gives your organisation regular visibility of posture changes so you can identify drift and track progress with confidence.</p>
    </article>
    <article class="card feature-card">
      <div class="feature-icon">📬</div>
      <h3>Clear reporting for real-world decisions</h3>
      <p>Automated email reports and an accessible customer portal help IT stakeholders, managers, and decision-makers stay informed without needing to dig through complex admin portals.</p>
    </article>
  </div>
</section>

<section class="section" id="why-ict365">
  <div class="section-heading">
    <div class="section-kicker">Why SecureIT?</div>
    <h2 class="section-title">Turn Microsoft 365 security complexity into clear action</h2>
    <p class="section-intro">Microsoft 365 contains a wide range of powerful security controls, but keeping track of them can be challenging. SecureIT simplifies that challenge by automatically reviewing your tenant and presenting the results in a clear, structured way.</p>
  </div>

  <div class="split">
    <article class="panel">
      <h3 style="font-size:1.35rem; margin-bottom:14px; color:var(--eden);">Built for organisations using Microsoft 365</h3>
      <p class="muted" style="margin-bottom:18px;">SecureIT is ideal for organisations that want better visibility of their Microsoft 365 security configuration without adding unnecessary complexity. It supports regular IT reviews, compliance discussions, cyber insurance preparation, governance conversations, and continuous improvement.</p>
      <div class="empty-state">
        <strong>Confidence through consistency</strong>
        <p class="muted" style="margin:8px 0 0;">Manual reviews are useful, but easy to miss. SecureIT helps bring consistency to Microsoft 365 security monitoring by running checks regularly and highlighting potential weaknesses before they become bigger issues.</p>
      </div>
    </article>

    <article class="panel">
      <h3 style="font-size:1.35rem; margin-bottom:14px; color:var(--eden);">What SecureIT helps you do</h3>
      <div class="feature-grid" style="grid-template-columns:1fr; gap:18px;">
        <article>
          <h3 style="margin-bottom:6px; font-size:1.02rem;">Monitor security posture regularly</h3>
          <p class="muted" style="margin-bottom:0;">Review Microsoft 365 security configuration on a regular basis and gain confidence that your environment is being assessed consistently.</p>
        </article>
        <article>
          <h3 style="margin-bottom:6px; font-size:1.02rem;">Understand findings in plain language</h3>
          <p class="muted" style="margin-bottom:0;">See your current posture, key findings, and recommended areas for improvement in a way that makes sense to both technical and non-technical stakeholders.</p>
        </article>
        <article>
          <h3 style="margin-bottom:6px; font-size:1.02rem;">Support governance and risk conversations</h3>
          <p class="muted" style="margin-bottom:0;">Use SecureIT to support compliance reviews, internal governance discussions, cyber insurance preparation, and ongoing security improvement planning.</p>
        </article>
      </div>
    </article>
  </div>
</section>

<section class="section alt">
  <div class="section-heading">
    <div class="section-kicker">Your security portal</div>
    <h2 class="section-title">Automated reports, clear insight, practical visibility</h2>
    <p class="section-intro">Each subscribing customer receives access to their own SecureIT portal, where they can review current posture, track results over time, and trigger manual report runs whenever an up-to-date view is needed.</p>
  </div>
  <div class="partner-grid">
    <article class="partner-card">
      <h3>Current posture visibility</h3>
      <p>See where your Microsoft 365 environment is performing well and where security could be strengthened.</p>
    </article>
    <article class="partner-card">
      <h3>Automated reporting</h3>
      <p>SecureIT delivers scheduled reporting so key stakeholders receive clear, consistent updates without manual preparation.</p>
    </article>
    <article class="partner-card">
      <h3>Manual report runs</h3>
      <p>Trigger a report whenever needed to support board meetings, reviews, audits, or live security conversations.</p>
    </article>
    <article class="partner-card">
      <h3>Managed by ICT365</h3>
      <p>SecureIT combines automation, best practice, practical reporting, and expert support from ICT365.</p>
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
