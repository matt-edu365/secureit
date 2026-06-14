<?php
require __DIR__ . '/lib.php';
$config = secureit_load_tenants();
$tenants = $config['tenants'] ?? [];
$app = secureit_config();
$dashboard = secureit_dashboard_stats($tenants);

ob_start();
?>
<section class="section">
  <div class="metrics-grid">
    <article class="card metric-card"><div class="muted">Tenants</div><div class="metric-value"><?php echo htmlspecialchars((string) $dashboard['tenantCount']); ?></div><div class="metric-note">Total tenants configured in this SecureIT instance.</div></article>
    <article class="card metric-card"><div class="muted">Reporting</div><div class="metric-value"><?php echo htmlspecialchars((string) $dashboard['reportingCount']); ?></div><div class="metric-note">Tenants with a latest summary available.</div></article>
    <article class="card metric-card"><div class="muted">Healthy</div><div class="metric-value"><?php echo htmlspecialchars((string) $dashboard['healthyCount']); ?></div><div class="metric-note">Tenants with no failed checks in the latest published run.</div></article>
    <article class="card metric-card"><div class="muted">Needs attention</div><div class="metric-value"><?php echo htmlspecialchars((string) $dashboard['attentionCount']); ?></div><div class="metric-note">Tenants whose latest posture needs review.</div></article>
  </div>
</section>

<section class="section">
  <div class="section-header">
    <div>
      <h2 class="section-title">Tenant overview</h2>
      <div class="muted">An operational view across all onboarded customers, with quick posture and report access.</div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:24px; padding:20px 24px;">
    <label for="tenant-search" style="margin-top:0;">Find a tenant</label>
    <input id="tenant-search" type="search" placeholder="Start typing a tenant name, key, or report URL" oninput="const query=this.value.toLowerCase().trim();document.querySelectorAll('[data-tenant-card]').forEach(card=>{const haystack=(card.dataset.tenantSearch||'').toLowerCase();card.style.display=haystack.includes(query)?'flex':'none';});">
    <p class="field-note">Filter the tenant cards instantly by name, tenant key, or report link.</p>
  </div>

  <?php if (!$tenants): ?>
    <div class="empty-state">
      <strong>No tenants configured yet.</strong>
      <p class="muted">Use Customer Onboarding to add your first tenant, then published reports will appear here automatically.</p>
    </div>
  <?php else: ?>
    <div class="tenant-grid">
      <?php foreach ($tenants as $tenant): ?>
        <?php
          $tenantKey = $tenant['id'] ?? 'unknown';
          $summary = secureit_tenant_summary($tenantKey);
          $counts = secureit_summary_counts($summary);
          $toneClass = 'tone-' . strtolower($counts['riskTone']);
          $searchValue = implode(' ', [
              $tenant['name'] ?? '',
              $tenantKey,
              $tenant['tenantId'] ?? '',
              $tenant['clientId'] ?? '',
              $tenant['reportBaseUrl'] ?? '',
          ]);
        ?>
        <article class="card tenant-card" data-tenant-card data-tenant-search="<?php echo htmlspecialchars($searchValue); ?>">
          <div class="tenant-head">
            <div>
              <h3 class="tenant-name"><?php echo htmlspecialchars($tenant['name'] ?? $tenantKey); ?></h3>
              <div class="tenant-meta">
                <div>Tenant key: <?php echo htmlspecialchars($tenantKey); ?></div>
                <div>Tenant ID: <?php echo htmlspecialchars($tenant['tenantId'] ?? 'Unknown'); ?></div>
                <div>Client ID: <?php echo htmlspecialchars($tenant['clientId'] ?? 'Unknown'); ?></div>
                <div>Report URL: <?php echo htmlspecialchars($tenant['reportBaseUrl'] ?? secureit_build_report_base_url($tenantKey)); ?></div>
              </div>
            </div>
            <div class="badge <?php echo htmlspecialchars($toneClass); ?>"><?php echo htmlspecialchars($counts['riskLevel']); ?></div>
          </div>

          <?php if ($summary): ?>
            <div>
              <div class="muted" style="margin-bottom:8px;">Pass rate</div>
              <div class="progress" aria-label="Pass rate progress"><div class="progress-bar" style="width: <?php echo htmlspecialchars((string) $counts['passRate']); ?>%"></div></div>
              <div class="muted" style="margin-top:8px;"><?php echo htmlspecialchars((string) $counts['passRate']); ?>% of checks passed in the latest published run.</div>
            </div>
            <div class="stats-row">
              <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['total']); ?></strong><span>Total checks</span></div>
              <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['passed']); ?></strong><span>Passed</span></div>
              <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['failed']); ?></strong><span>Failed</span></div>
              <div class="stat-chip"><strong><?php echo htmlspecialchars((string) $counts['skipped']); ?></strong><span>Skipped</span></div>
            </div>
            <div class="inline-links">
              <a class="textlink" href="tenant.php?tenant=<?php echo rawurlencode($tenantKey); ?>">Open tenant page</a>
              <a class="textlink" href="<?php echo htmlspecialchars($tenantKey); ?>/latest/index.html">Open latest report</a>
            </div>
            <div class="muted">Last generated: <?php echo htmlspecialchars(secureit_format_datetime($summary['generatedAt'] ?? null)); ?></div>
          <?php else: ?>
            <div class="empty-state" style="padding:16px; box-shadow:none;">
              <strong>No published report yet.</strong>
              <p class="muted">This tenant is configured, but a latest summary has not been published yet.</p>
            </div>
            <div class="inline-links">
              <a class="textlink" href="tenant.php?tenant=<?php echo rawurlencode($tenantKey); ?>">Open tenant page</a>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

      <article class="card tenant-card" data-tenant-card data-tenant-search="placeholder tenant finance school coming soon">
        <div class="tenant-head">
          <div>
            <h3 class="tenant-name">Placeholder tenant 02</h3>
            <div class="tenant-meta">
              <div>Tenant key: coming-soon-02</div>
              <div>Tenant ID: Pending onboarding</div>
              <div>Client ID: Pending onboarding</div>
              <div>Report URL: Reserved for future tenant</div>
            </div>
          </div>
          <div class="badge tone-neutral">Pending</div>
        </div>
        <div class="empty-state">
          <strong>Awaiting tenant onboarding.</strong>
          <p class="muted">This placeholder card reserves space for another customer environment once onboarding details are available.</p>
        </div>
        <div class="inline-links"><a class="textlink" href="onboard.php">Start onboarding</a></div>
      </article>

      <article class="card tenant-card" data-tenant-card data-tenant-search="placeholder tenant legal hospitality coming soon">
        <div class="tenant-head">
          <div>
            <h3 class="tenant-name">Placeholder tenant 03</h3>
            <div class="tenant-meta">
              <div>Tenant key: coming-soon-03</div>
              <div>Tenant ID: Pending onboarding</div>
              <div>Client ID: Pending onboarding</div>
              <div>Report URL: Reserved for future tenant</div>
            </div>
          </div>
          <div class="badge tone-neutral">Pending</div>
        </div>
        <div class="empty-state">
          <strong>Awaiting tenant onboarding.</strong>
          <p class="muted">Use this slot as a visual placeholder for another managed customer as the SecureIT portfolio grows.</p>
        </div>
        <div class="inline-links"><a class="textlink" href="onboard.php">Start onboarding</a></div>
      </article>
    </div>
  <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
secureit_render_shell('SecureIT Admin Dashboard', $content, [
    'pageTitle' => 'SecureIT Administrator Portal',
    'pageIntro' => 'Multi-Tenant Operational view for ICT365 administrators',
    'eyebrow' => '',
    'navLinks' => [],
    'headerMenu' => [
        ['href' => 'admin.php', 'label' => 'Admin actions'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
    ],
    'footerLinks' => [
        ['href' => 'login.php', 'label' => 'SecureIT Login'],
        ['href' => 'portal.php', 'label' => 'Customer portal'],
    ],
    'footerSecondaryLinks' => [
        ['href' => 'dashboard.php', 'label' => 'Admin dashboard'],
        ['href' => 'onboard.php', 'label' => 'Customer onboarding'],
        ['href' => 'admin.php', 'label' => 'Admin actions'],
    ],
    'footerContact' => [
        ['href' => 'mailto:Sales@ict365.ky', 'label' => 'Sales@ict365.ky'],
        ['href' => 'tel:+13457450365', 'label' => '+1 (345) 745-0365'],
        ['href' => 'https://ict365.ky', 'label' => 'https://ict365.ky'],
    ],
]);
