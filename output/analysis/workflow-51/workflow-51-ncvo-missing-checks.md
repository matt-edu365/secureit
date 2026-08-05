# Workflow #51 missing-check report — National Children's Voluntary Organisation

- Tenant key: `ncvo`
- Workflow run: [SecureIT Production #51](https://github.com/matt-edu365/secureit/actions/runs/30789576455)
- Raw Maester/Pester results in artifact: **181**
- SecureIT production control catalogue: **100** scored-or-excluded controls (the 101st catalogue item, `CONDITIONALACCESSWHATIF`, is a separate to-do feature and is outside this production total)
- Included in email pass/partial/fail statistics: **50**
- Missing from those three email statistics: **50**

## Reconciliation

| Status | Count | Included in pass/fail/partial email figures? |
|---|---:|---|
| `pass` | 9 | Yes |
| `partial` | 4 | Yes |
| `fail` | 37 | Yes |
| `unmapped` | 24 | No |
| `skipped` | 20 | No |
| `not_run` | 5 | No |
| `error` | 1 | No |
| **Total** | **100** | **50 included / 50 excluded** |

SecureIT only includes canonical controls whose resolved status is `pass`, `partial`, or `fail`. `unmapped`, `skipped`, `not_run`, and `error` remain visible as coverage gaps but are excluded from both the three headline counts and the score denominator.

## Post-run verification and corrected diagnosis

The retained workflow artifact and the matching Maester packages were inspected after this report was generated. That verification changes several causal conclusions below:

- All five canonical `not_run` controls were filtered before execution. The recorded Pester configuration excluded `LongRunning`; `MTHIGHRISKAPPPERMISSIONS` was also excluded by `Preview`. These outcomes do not prove missing permissions or licensing.
- The 23 absent test files are a Maester version-pin problem. They are absent from the pinned Maester 2.0.0 package but present, with matching implementations, in Maester 2.2.0.
- Role-related results that explicitly say `Missing Scope RoleEligibilitySchedule.ReadWrite.Directory` are permission gaps in the 2.0.0 run, not evidence of a missing tenant licence. Maester 2.2.0 documents the read-only `RoleEligibilitySchedule.Read.Directory` application permission instead.
- `Not connected to Exchange Online`, `Not connected to Teams`, and `Not connected to Azure` are runner connection gaps. They should not be described as tenant feature absence.
- A zero-test result from `Test-MtMdiHealthIssues.Tests.ps1` remains ambiguous: it may mean no health issues, but it must not be converted to a pass unless the underlying API call is also known to have succeeded.

## Missing checks

### 1. App registrations (`APPREGISTRATIONS`)

- **Expected `.ps1` check file:** `Test-AppRegistrations.Tests.ps1`
- **SecureIT references:** canonical ID `APPREGISTRATIONS`; evidence mapping `Test-AppRegistrations.Tests.ps1`; raw test ID(s): `MT.1057`, `MT.1058`, `MT.1075`
- **Functional area:** Identity & Access Management
- **Description:** Inspects Entra app registrations, including ownership and registration governance. It passes when app registrations are governed by approved ownership and registration controls; it fails when apps are unmanaged, ownerless, over-permissioned, or can be created outside the approved process. This matters because compromised or poorly governed apps can access tenant data without going through normal user sign-in controls.
- **Workflow #51 outcome/output:**
  - `Test-AppRegistrations.Tests.ps1` / `MT.1057` — App registrations should no longer use secrets.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-AppRegistrations.Tests.ps1` / `MT.1058` — Exchange application access policies must be configured.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-AppRegistrations.Tests.ps1` / `MT.1075` — Require explicit assignment of Third Party Entra Apps.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `not_run`. The file is tagged `LongRunning`, and `Invoke-Maester` automatically added `LongRunning` to Pester's exclusion list because the workflow did not pass `-IncludeLongRunning`. Permissions were therefore not evaluated. Only `pass`, `partial`, and `fail` are included in the headline email figures; `not_run` is excluded from that count and from the score denominator.

### 2. App registration owners without MFA (`MTAPPREGISTRATIONOWNERSWITHOUTMFA`)

- **Expected `.ps1` check file:** `Test-MtAppRegistrationOwnersWithoutMFA.Tests.ps1`
- **SecureIT references:** canonical ID `MTAPPREGISTRATIONOWNERSWITHOUTMFA`; evidence mapping `Test-MtAppRegistrationOwnersWithoutMFA.Tests.ps1`; raw test ID(s): `MT.1063`
- **Functional area:** Identity & Access Management
- **Description:** Inspects owners of app registrations and whether their accounts are protected by MFA. It passes when app registration owners have MFA protection enabled; it fails when one or more app owners can administer applications without MFA protection. This matters because an attacker who compromises an app owner can change app permissions or credentials and gain persistent access.
- **Workflow #51 outcome/output:**
  - `Test-MtAppRegistrationOwnersWithoutMFA.Tests.ps1` / `MT.1063` — All App registration owners should have MFA registered: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `not_run`. The file is tagged `LongRunning`, and the workflow did not pass `-IncludeLongRunning`, so Pester filtered it before execution. Permissions were therefore not evaluated. Only `pass`, `partial`, and `fail` are included in the headline email figures; `not_run` is excluded from that count and from the score denominator.

### 3. Admin consent workflow (`MTCISADMINCONSENTWORKFLOWENABLED`)

- **Expected `.ps1` check file:** `Test-MtCisAdminConsentWorkflowEnabled.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISADMINCONSENTWORKFLOWENABLED`; evidence mapping `Test-MtCisAdminConsentWorkflowEnabled.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Identity & Access Management
- **Description:** Inspects the admin consent workflow for application permission requests. It passes when users must request approval before apps receive permissions that need administrator consent; it fails when the approval workflow is disabled or app consent bypasses the expected review. This matters because consent review helps stop risky apps from gaining access to tenant data without oversight.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 4. Tenant creation disallowed (`MTCISCREATETENANTDISALLOWED`)

- **Expected `.ps1` check file:** `Test-MtCisCreateTenantDisallowed.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISCREATETENANTDISALLOWED`; evidence mapping `Test-MtCisCreateTenantDisallowed.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Identity & Access Management
- **Description:** Inspects whether ordinary users can create new Microsoft Entra tenants. It passes when tenant creation is disabled for non-administrators; it fails when users can create additional tenants without approval. This matters because new unmanaged tenants can bypass company security, retention, and compliance controls.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 5. Forms phishing protection (`MTCISFORMSPHISHINGPROTECTIONENABLED`)

- **Expected `.ps1` check file:** `Test-MtCisFormsPhishingProtectionEnabled.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISFORMSPHISHINGPROTECTIONENABLED`; evidence mapping `Test-MtCisFormsPhishingProtectionEnabled.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Identity & Access Management
- **Description:** Inspects Microsoft Forms phishing protection settings. It passes when Forms phishing protection is enabled; it fails when Forms phishing protection is disabled or not configured. This matters because this reduces the chance that company forms are used to collect credentials or sensitive information.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Requires Microsoft Forms service settings to be available for evaluation. Required: Microsoft Forms org settings. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 6. Global administrator count (`MTCISGLOBALADMINCOUNT`)

- **Expected `.ps1` check file:** `Test-MtCisGlobalAdminCount.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISGLOBALADMINCOUNT`; evidence mapping `Test-MtCisGlobalAdminCount.Tests.ps1`; raw test ID(s): `CIS.M365.1.1.3`
- **Functional area:** Identity & Access Management
- **Description:** Inspects the number of accounts assigned the Global Administrator role. It passes when the number of global administrators is kept within the approved minimum range; it fails when too many accounts have full tenant administration rights. This matters because every extra global administrator increases the chance and impact of privileged account compromise.
- **Workflow #51 outcome/output:**
  - `Test-MtCisGlobalAdminCount.Tests.ps1` / `CIS.M365.1.1.3` — Ensure that between two and four global admins are designated: Skipped — Skipped. Missing Scope RoleEligibilitySchedule.ReadWrite.Directory
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. The emitted reason is a missing Graph application permission, `RoleEligibilitySchedule.ReadWrite.Directory`, in the pinned 2.0.0 test. It does not establish that the tenant lacks P2 or Governance licensing. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 7. Third-party applications disallowed (`MTCISTHIRDPARTYAPPLICATIONSDISALLOWED`)

- **Expected `.ps1` check file:** `Test-MtCisThirdPartyApplicationsDisallowed.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISTHIRDPARTYAPPLICATIONSDISALLOWED`; evidence mapping `Test-MtCisThirdPartyApplicationsDisallowed.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Identity & Access Management
- **Description:** Inspects tenant settings that allow users to add third-party applications. It passes when third-party applications are blocked or require administrator approval; it fails when users can add third-party applications without review. This matters because unreviewed applications may request access to mail, files, contacts, or directory information.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 8. Weak authentication methods disabled (`MTCISWEAKAUTHENTICATIONMETHODSDISABLED`)

- **Expected `.ps1` check file:** `Test-MtCisWeakAuthenticationMethodsDisabled.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISWEAKAUTHENTICATIONMETHODSDISABLED`; evidence mapping `Test-MtCisWeakAuthenticationMethodsDisabled.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Identity & Access Management
- **Description:** Inspects authentication methods considered weak or legacy. It passes when weak methods are disabled or unavailable to users; it fails when weak methods remain enabled. This matters because weak sign-in methods are easier to phish, intercept, or bypass than modern authentication.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 9. Activation notifications for global admins (`MTCISAACTIVATIONNOTIFICATIONGLOBALADMIN`)

- **Expected `.ps1` check file:** `Test-MtCisaActivationNotificationGlobalAdmin.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAACTIVATIONNOTIFICATIONGLOBALADMIN`; evidence mapping `Test-MtCisaActivationNotificationGlobalAdmin.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.8`
- **Functional area:** Identity & Access Management
- **Description:** Inspects notifications for Global Administrator role activation. It passes when the right administrators are notified when Global Administrator access is activated; it fails when activation notifications are missing or sent to the wrong recipients. This matters because timely notification helps detect unexpected privileged access before it is misused.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaActivationNotificationGlobalAdmin.Tests.ps1` / `CISA.MS.AAD.7.8` — User activation of the Global Administrator role SHALL trigger an alert.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Microsoft Entra ID Protection or P2 / Governance licensing and privileged-role notification data. Required: Microsoft Entra ID P2 or Microsoft Entra ID Governance. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 10. Activation notifications for other privileged roles (`MTCISAACTIVATIONNOTIFICATIONOTHER`)

- **Expected `.ps1` check file:** `Test-MtCisaActivationNotificationOther.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAACTIVATIONNOTIFICATIONOTHER`; evidence mapping `Test-MtCisaActivationNotificationOther.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.9`
- **Functional area:** Identity & Access Management
- **Description:** Inspects notifications for activation of other privileged roles. It passes when role activation notifications are enabled for the expected recipients; it fails when activation notifications are disabled or incomplete. This matters because privileged role activation should be visible so suspicious elevation can be investigated quickly.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaActivationNotificationOther.Tests.ps1` / `CISA.MS.AAD.7.9` — User activation of other highly privileged roles SHOULD trigger an alert.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Microsoft Entra ID Protection or P2 / Governance licensing and privileged-role notification data. Required: Microsoft Entra ID P2 or Microsoft Entra ID Governance. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 11. Group owner app consent (`MTCISAAPPGROUPOWNERCONSENT`)

- **Expected `.ps1` check file:** `Test-MtCisaAppGroupOwnerConsent.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAAPPGROUPOWNERCONSENT`; evidence mapping `Test-MtCisaAppGroupOwnerConsent.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.5.4`
- **Functional area:** Identity & Access Management
- **Description:** Inspects whether group owners can grant application consent for group data. It passes when group owner consent is restricted or governed by approval; it fails when group owners can grant app access outside the approved process. This matters because group data often includes files, conversations, and membership information that should not be exposed casually.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaAppGroupOwnerConsent.Tests.ps1` / `CISA.MS.AAD.5.4` — Group owners SHALL NOT be allowed to consent to applications.: Skipped — Skipped. Settings value is not available. This may be due to the change that this API is no longer available for recently created tenants.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the tenant did not expose the feature or configuration this control checks. Requires app consent / group owner consent settings to be available for evaluation. Required: Group owner app consent settings. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 12. Block high-risk sign-ins (`MTCISABLOCKHIGHRISKSIGNINS`)

- **Expected `.ps1` check file:** `Test-MtCisaBlockHighRiskSignIns.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISABLOCKHIGHRISKSIGNINS`; evidence mapping `Test-MtCisaBlockHighRiskSignIns.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.2.3`
- **Functional area:** Identity & Access Management
- **Description:** Inspects Conditional Access protection for high-risk sign-ins. It passes when high-risk sign-ins are blocked or challenged as policy requires; it fails when high-risk sign-ins can continue without the expected control. This matters because risk-based blocking reduces the chance that stolen credentials lead to successful access.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaBlockHighRiskSignIns.Tests.ps1` / `CISA.MS.AAD.2.3` — Sign-ins detected as high risk SHALL be blocked.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Microsoft Entra ID Protection licensing and sign-in risk data. Required: Microsoft Entra ID P2; Identity Protection sign-in risk detections. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 13. Block high-risk users (`MTCISABLOCKHIGHRISKUSERS`)

- **Expected `.ps1` check file:** `Test-MtCisaBlockHighRiskUsers.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISABLOCKHIGHRISKUSERS`; evidence mapping `Test-MtCisaBlockHighRiskUsers.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.2.1`
- **Functional area:** Identity & Access Management
- **Description:** Inspects policy controls for users marked as high risk. It passes when high-risk users are blocked, remediated, or forced through approved recovery; it fails when high-risk users can continue accessing services without remediation. This matters because high-risk users may already be compromised and should not retain normal access.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaBlockHighRiskUsers.Tests.ps1` / `CISA.MS.AAD.2.1` — Users detected as high risk SHALL be blocked.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Microsoft Entra ID Protection licensing and user risk data. Required: Microsoft Entra ID P2; Identity Protection user risk detections. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 14. Cloud global administrator access (`MTCISACLOUDGLOBALADMIN`)

- **Expected `.ps1` check file:** `Test-MtCisaCloudGlobalAdmin.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISACLOUDGLOBALADMIN`; evidence mapping `Test-MtCisaCloudGlobalAdmin.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.3`
- **Functional area:** Identity & Access Management
- **Description:** Inspects Global Administrator access for cloud-only administrator accounts. It passes when cloud global administrator access is limited and protected; it fails when cloud administrator access is excessive or insufficiently controlled. This matters because global administrators can change security settings, create access, and disable protections across the tenant.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaCloudGlobalAdmin.Tests.ps1` / `CISA.MS.AAD.7.3` — Privileged users SHALL be provisioned cloud-only accounts separate from an on-premises directory or other federated identity providers.: Skipped — Skipped. Missing Scope RoleEligibilitySchedule.ReadWrite.Directory
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 15. Global administrator count (`MTCISAGLOBALADMINCOUNT`)

- **Expected `.ps1` check file:** `Test-MtCisaGlobalAdminCount.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAGLOBALADMINCOUNT`; evidence mapping `Test-MtCisaGlobalAdminCount.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.1`
- **Functional area:** Identity & Access Management
- **Description:** Inspects the number of accounts with the Global Administrator role. It passes when global administrator count stays within the approved range; it fails when too many accounts have global administrator rights. This matters because reducing the number of global administrators limits the blast radius of account compromise.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaGlobalAdminCount.Tests.ps1` / `CISA.MS.AAD.7.1` — A minimum of two users and a maximum of eight users SHALL be provisioned with the Global Administrator role.: Skipped — Skipped. Missing Scope RoleEligibilitySchedule.ReadWrite.Directory
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 16. Global administrator ratio (`MTCISAGLOBALADMINRATIO`)

- **Expected `.ps1` check file:** `Test-MtCisaGlobalAdminRatio.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAGLOBALADMINRATIO`; evidence mapping `Test-MtCisaGlobalAdminRatio.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.2`
- **Functional area:** Identity & Access Management
- **Description:** Inspects the share of users who hold Global Administrator rights. It passes when global administrators are a small, controlled subset of the tenant; it fails when the tenant has an unusually high ratio of global administrators. This matters because privileged access should be exceptional, not common, to reduce avoidable security risk.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaGlobalAdminRatio.Tests.ps1` / `CISA.MS.AAD.7.2` — Privileged users SHALL be provisioned with finer-grained roles instead of Global Administrator.: Skipped — Skipped. Missing Scope RoleEligibilitySchedule.ReadWrite.Directory
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 17. Notify high-risk users (`MTCISANOTIFYHIGHRISKUSERS`)

- **Expected `.ps1` check file:** `Test-MtCisaNotifyHighRiskUsers.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISANOTIFYHIGHRISKUSERS`; evidence mapping `Test-MtCisaNotifyHighRiskUsers.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.2.2`
- **Functional area:** Identity & Access Management
- **Description:** Inspects notification settings for users flagged as high risk. It passes when high-risk users receive the expected notification or remediation prompt; it fails when high-risk users are not notified or guided through remediation. This matters because fast notification helps users and administrators respond before suspicious access causes damage.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaNotifyHighRiskUsers.Tests.ps1` / `CISA.MS.AAD.2.2` — A notification SHOULD be sent to the administrator when high-risk users are detected.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Microsoft Entra ID Protection licensing and user risk data. Required: Microsoft Entra ID P2; Identity Protection user risk detections. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 18. Permanent role assignment (`MTCISAPERMANENTROLEASSIGNMENT`)

- **Expected `.ps1` check file:** `Test-MtCisaPermanentRoleAssignment.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAPERMANENTROLEASSIGNMENT`; evidence mapping `Test-MtCisaPermanentRoleAssignment.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.4`
- **Functional area:** Identity & Access Management
- **Description:** Inspects permanent privileged role assignments. It passes when privileged roles are eligible or time-bound rather than permanently active where possible; it fails when privileged roles are assigned permanently without the expected justification. This matters because standing privileged access gives attackers more opportunity if an account is compromised.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaPermanentRoleAssignment.Tests.ps1` / `CISA.MS.AAD.7.4` — Permanent active role assignments SHALL NOT be allowed for highly privileged roles.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Privileged Identity Management / Entra ID P2 or Governance licensing. Required: Microsoft Entra ID P2 or Microsoft Entra ID Governance. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 19. Require activation approval (`MTCISAREQUIREACTIVATIONAPPROVAL`)

- **Expected `.ps1` check file:** `Test-MtCisaRequireActivationApproval.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAREQUIREACTIVATIONAPPROVAL`; evidence mapping `Test-MtCisaRequireActivationApproval.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.6`
- **Functional area:** Identity & Access Management
- **Description:** Inspects approval requirements for activating privileged roles. It passes when role activation requires approval where policy expects it; it fails when privileged roles can be activated without the required approval. This matters because approval creates a human checkpoint before high-impact access becomes active.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaRequireActivationApproval.Tests.ps1` / `CISA.MS.AAD.7.6` — Activation of the Global Administrator role SHALL require approval.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Privileged Identity Management / Entra ID P2 or Governance licensing. Required: Microsoft Entra ID P2 or Microsoft Entra ID Governance. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 20. Unmanaged role assignments (`MTCISAUNMANAGEDROLEASSIGNMENTS`)

- **Expected `.ps1` check file:** `Test-MtCisaUnmanagedRoleAssignments.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAUNMANAGEDROLEASSIGNMENTS`; evidence mapping `Test-MtCisaUnmanagedRoleAssignments.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.5`
- **Functional area:** Identity & Access Management
- **Description:** Inspects privileged role assignments that are not managed through the approved governance process. It passes when privileged assignments are managed, reviewed, and accountable; it fails when unmanaged privileged assignments are present. This matters because unmanaged privileged access is difficult to review and easy to forget after business need ends.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaUnmanagedRoleAssignments.Tests.ps1` / `CISA.MS.AAD.7.5` — Provisioning users to highly privileged roles SHALL NOT occur outside of a PAM system.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Privileged Identity Management / Entra ID P2 or Governance licensing. Required: Microsoft Entra ID P2 or Microsoft Entra ID Governance. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 21. Entitlement management deleted groups (`MTENTITLEMENTMANAGEMENTDELETEDGROUPS`)

- **Expected `.ps1` check file:** `Test-MtEntitlementManagementDeletedGroups.Tests.ps1`
- **SecureIT references:** canonical ID `MTENTITLEMENTMANAGEMENTDELETEDGROUPS`; evidence mapping `Test-MtEntitlementManagementDeletedGroups.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Collaboration & Communication
- **Description:** Inspects entitlement management access packages for references to deleted groups. It passes when access packages do not rely on deleted groups; it fails when deleted groups are still referenced by entitlement management configuration. This matters because stale group references can break access reviews and make access assignments unreliable.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 22. SharePoint B2B integration (`MTCISSPOB2BINTEGRATION`)

- **Expected `.ps1` check file:** `Test-MtCisSpoB2BIntegration.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISSPOB2BINTEGRATION`; evidence mapping `Test-MtCisSpoB2BIntegration.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Collaboration & Communication
- **Description:** Inspects SharePoint and OneDrive B2B integration settings. It passes when external collaboration uses the approved B2B integration model; it fails when B2B integration is disabled or configured in a way that weakens external sharing governance. This matters because B2B integration helps keep external users governed through identity controls and access reviews.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 23. Defender antivirus policy (`MTMDEANTIVIRUSPOLICY`)

- **Expected `.ps1` check file:** `Test-MtMdeAntivirusPolicy.Tests.ps1`
- **SecureIT references:** canonical ID `MTMDEANTIVIRUSPOLICY`; evidence mapping `Test-MtMdeAntivirusPolicy.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Endpoint & Device Management
- **Description:** Inspects Microsoft Defender antivirus policy configuration for managed endpoints. It passes when Defender antivirus is configured and managed through policy; it fails when antivirus policy is missing, incomplete, or not applied as expected. This matters because unmanaged antivirus settings leave endpoints more exposed to malware and make protection inconsistent across the organisation.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 24. Defender identity health issues (`MTMDIHEALTHISSUES`)

- **Expected `.ps1` check file:** `Test-MtMdiHealthIssues.Tests.ps1`
- **SecureIT references:** canonical ID `MTMDIHEALTHISSUES`; evidence mapping `Test-MtMdiHealthIssues.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Endpoint & Device Management
- **Description:** Inspects Defender for Identity health signals and service issues. It passes when no unresolved Defender identity health problems are reported; it fails when health issues are present or sensors are not reporting correctly. This matters because identity threat detection depends on healthy sensors and service connectivity.
- **Workflow #51 outcome/output:**
  - No result object was emitted for this source file, although it was present in the selected bundle at `_selected_tests/Maester/Defender/Test-MtMdiHealthIssues.Tests.ps1`.
  - The script creates Pester cases dynamically for each Defender for Identity health-issue group. This run returned no groups, so it created zero test cases and emitted neither an explicit pass nor fail result.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 25. Devices without compliance policy (`MTCISDEVICESWITHOUTCOMPLIANCEPOLICYMARKED`)

- **Expected `.ps1` check file:** `Test-MtCisDevicesWithoutCompliancePolicyMarked.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISDEVICESWITHOUTCOMPLIANCEPOLICYMARKED`; evidence mapping `Test-MtCisDevicesWithoutCompliancePolicyMarked.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Endpoint & Device Management
- **Description:** Inspects Intune compliance settings for devices without an assigned compliance policy. It passes when devices without a compliance policy are marked non-compliant; it fails when devices without a compliance policy can still be treated as compliant. This matters because unassessed devices should not be trusted for access to business services.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 26. High-risk app permissions (`MTHIGHRISKAPPPERMISSIONS`)

- **Expected `.ps1` check file:** `Test-MtHighRiskAppPermissions.Tests.ps1`
- **SecureIT references:** canonical ID `MTHIGHRISKAPPPERMISSIONS`; evidence mapping `Test-MtHighRiskAppPermissions.Tests.ps1`; raw test ID(s): `MT.1050`, `MT.1051`
- **Functional area:** Security Operations & Threat Protection
- **Description:** Inspects applications with high-risk permissions such as broad directory, mail, file, or tenant access. It passes when high-risk permissions are absent or controlled through an approved consent process; it fails when applications hold sensitive permissions without the expected review or approval. This matters because over-permissioned apps can expose large amounts of data if the app or its credentials are compromised.
- **Workflow #51 outcome/output:**
  - `Test-MtHighRiskAppPermissions.Tests.ps1` / `MT.1050` — Apps with high-risk permissions having a direct path to Global Admin: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-MtHighRiskAppPermissions.Tests.ps1` / `MT.1051` — Apps with high-risk permissions having an indirect path to Global Admin: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `not_run`. The file is tagged both `LongRunning` and `Preview`; the production invocation excluded both tags. It can run only when the workflow deliberately enables long-running and preview tests, after accepting the preview-test stability trade-off. Permissions were not evaluated in this run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `not_run` is excluded from that count and from the score denominator.

### 27. XSPM critical asset management (`XSPMCRITICALASSETMANAGEMENT`)

- **Expected `.ps1` check file:** `Test-XspmCriticalAssetManagement.Tests.ps1`
- **SecureIT references:** canonical ID `XSPMCRITICALASSETMANAGEMENT`; evidence mapping `Test-XspmCriticalAssetManagement.Tests.ps1`; raw test ID(s): `MT.1085`
- **Functional area:** Security Operations & Threat Protection
- **Description:** Inspects critical asset definitions in exposure management. It passes when critical assets are identified and included in exposure review; it fails when critical assets are missing or not classified correctly. This matters because security teams need to know which assets matter most so they can prioritise risk and remediation.
- **Workflow #51 outcome/output:**
  - `Test-XspmCriticalAssetManagement.Tests.ps1` / `MT.1085` — Pending approvals for Critical Asset Management should not be present.: Skipped — no textual result detail was emitted.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 28. XSPM devices (`XSPMDEVICES`)

- **Expected `.ps1` check file:** `Test-XspmDevices.Tests.ps1`
- **SecureIT references:** canonical ID `XSPMDEVICES`; evidence mapping `Test-XspmDevices.Tests.ps1`; raw test ID(s): `MT.1086`, `MT.1087`, `MT.1088`, `MT.1089`
- **Functional area:** Security Operations & Threat Protection
- **Description:** Inspects device exposure management coverage. It passes when devices are included in exposure and risk review; it fails when devices are missing from exposure management or have unresolved exposure findings. This matters because untracked device exposure makes it harder to find and fix attack paths into the organisation.
- **Workflow #51 outcome/output:**
  - `Test-XspmDevices.Tests.ps1` / `MT.1086` — Devices should not share both critical and non-critical user credentials.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-XspmDevices.Tests.ps1` / `MT.1087` — Devices should not be publicly exposed with remotely exploitable, highly likely to be exploited, high or critical severity CVE's.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-XspmDevices.Tests.ps1` / `MT.1088` — Devices with critical credentials should be protected by TPM.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-XspmDevices.Tests.ps1` / `MT.1089` — Devices with critical credentials should be protected by Credential Guard.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `not_run`. The file is tagged `LongRunning`, and the workflow did not pass `-IncludeLongRunning`, so the tests were filtered before their Defender-plan prerequisite could be evaluated. After enabling them, a tenant still needs Defender XDR / Exposure Management data or Maester will skip the block. Only `pass`, `partial`, and `fail` are included in the headline email figures; `not_run` is excluded from that count and from the score denominator.

### 29. XSPM privileged identities (`XSPMPRIVILEGEDIDENTITIES`)

- **Expected `.ps1` check file:** `Test-XspmPrivilegedIdentities.Tests.ps1`
- **SecureIT references:** canonical ID `XSPMPRIVILEGEDIDENTITIES`; evidence mapping `Test-XspmPrivilegedIdentities.Tests.ps1`; raw test ID(s): `MT.1077`, `MT.1078`, `MT.1079`, `MT.1080`, `MT.1081`
- **Functional area:** Security Operations & Threat Protection
- **Description:** Inspects privileged identities in exposure management. It passes when privileged identities are identified and included in exposure review; it fails when privileged identities are missing or have unresolved exposure findings. This matters because privileged identities create the highest-impact attack paths if they are exposed or misused.
- **Workflow #51 outcome/output:**
  - `Test-XspmPrivilegedIdentities.Tests.ps1` / `MT.1077` — App registrations with privileged API permissions should not have owners.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-XspmPrivilegedIdentities.Tests.ps1` / `MT.1078` — App registrations with highly privileged directory roles should not have owners.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-XspmPrivilegedIdentities.Tests.ps1` / `MT.1079` — Privileged API permissions on service principals should not remain unused.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-XspmPrivilegedIdentities.Tests.ps1` / `MT.1080` — Credentials, tokens, or cookies from highly privileged users should not be exposed on vulnerable endpoints.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
  - `Test-XspmPrivilegedIdentities.Tests.ps1` / `MT.1081` — Hybrid users should not be assigned Entra ID role assignments.: NotRun — Pester discovered the test but emitted no `ResultDetail` or `ErrorRecord`; it was not executed.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `not_run`. The file is tagged `LongRunning`, and the workflow did not pass `-IncludeLongRunning`, so the tests were filtered before their Defender-plan prerequisite could be evaluated. After enabling them, a tenant still needs Defender XDR / Exposure Management data or Maester will skip the block. Only `pass`, `partial`, and `fail` are included in the headline email figures; `not_run` is excluded from that count and from the score denominator.

### 30. Cloud admin access (`MTCISCLOUDADMIN`)

- **Expected `.ps1` check file:** `Test-MtCisCloudAdmin.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISCLOUDADMIN`; evidence mapping `Test-MtCisCloudAdmin.Tests.ps1`; raw test ID(s): `CIS.M365.1.1.1`
- **Functional area:** Security Operations & Threat Protection
- **Description:** Inspects cloud administrator accounts and privileged access exposure. It passes when cloud admin access is limited and protected according to policy; it fails when too many accounts have cloud admin rights or those accounts lack expected protections. This matters because cloud admins can alter identity, security, and data access across the tenant.
- **Workflow #51 outcome/output:**
  - `Test-MtCisCloudAdmin.Tests.ps1` / `CIS.M365.1.1.1` — Ensure Administrative accounts are cloud-only: Error — Microsoft Graph returned HTTP 400 `AadPremiumLicenseRequired`: the tenant needs Microsoft Entra ID P2 or Microsoft Entra ID Governance.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `error`. All matched tests errored. Requires Privileged Identity Management / Entra ID P2 or Governance licensing. Required: Microsoft Entra ID P2 or Microsoft Entra ID Governance. Only `pass`, `partial`, and `fail` are included in the headline email figures; `error` is excluded from that count and from the score denominator.

### 31. Customer lockbox (`MTCISCUSTOMERLOCKBOX`)

- **Expected `.ps1` check file:** `Test-MtCisCustomerLockBox.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISCUSTOMERLOCKBOX`; evidence mapping `Test-MtCisCustomerLockBox.Tests.ps1`; raw test ID(s): `CIS.M365.1.3.6`
- **Functional area:** Security Operations & Threat Protection
- **Description:** Inspects Customer Lockbox settings for Microsoft support access. It passes when Customer Lockbox requires approval before Microsoft support can access customer content; it fails when support access can occur without the expected customer approval workflow. This matters because approval control gives the customer visibility and consent over exceptional support access to data.
- **Workflow #51 outcome/output:**
  - `Test-MtCisCustomerLockBox.Tests.ps1` / `CIS.M365.1.3.6` — Ensure the customer lockbox feature is enabled: Skipped — Skipped. Not connected to Exchange Online. See [Connecting to Exchange Online](https://maester.dev/docs/connect-maester/#connect-to-azure-exchange-online-and-teams)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the required license or tenant feature is not available. Requires Customer Lockbox to be enabled and licensed. Required: Customer Lockbox. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 32. Entitlement management inactive policies (`MTENTITLEMENTMANAGEMENTINACTIVEPOLICIES`)

- **Expected `.ps1` check file:** `Test-MtEntitlementManagementInactivePolicies.Tests.ps1`
- **SecureIT references:** canonical ID `MTENTITLEMENTMANAGEMENTINACTIVEPOLICIES`; evidence mapping `Test-MtEntitlementManagementInactivePolicies.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects entitlement management policies that are inactive or no longer used. It passes when access package policies are active, intentional, and still relevant; it fails when inactive policies remain configured where they can confuse or weaken access governance. This matters because unused policies make it harder to prove that access is being granted through the correct approval path.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 33. Entitlement management orphaned resources (`MTENTITLEMENTMANAGEMENTORPHANEDRESOURCES`)

- **Expected `.ps1` check file:** `Test-MtEntitlementManagementOrphanedResources.Tests.ps1`
- **SecureIT references:** canonical ID `MTENTITLEMENTMANAGEMENTORPHANEDRESOURCES`; evidence mapping `Test-MtEntitlementManagementOrphanedResources.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects entitlement management resources that no longer have a valid owner or backing resource. It passes when resources in access packages are valid and owned; it fails when orphaned resources are still available in entitlement management. This matters because orphaned resources can leave access unclear and make cleanup or audit work more difficult.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 34. Entitlement management valid approvers (`MTENTITLEMENTMANAGEMENTVALIDAPPROVERS`)

- **Expected `.ps1` check file:** `Test-MtEntitlementManagementValidApprovers.Tests.ps1`
- **SecureIT references:** canonical ID `MTENTITLEMENTMANAGEMENTVALIDAPPROVERS`; evidence mapping `Test-MtEntitlementManagementValidApprovers.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects approver assignments in entitlement management policies. It passes when approval steps point to valid users or groups; it fails when approval is assigned to missing, invalid, or inappropriate approvers. This matters because access requests should be reviewed by accountable people before users receive sensitive permissions.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 35. Entitlement management valid resource roles (`MTENTITLEMENTMANAGEMENTVALIDRESOURCEROLES`)

- **Expected `.ps1` check file:** `Test-MtEntitlementManagementValidResourceRoles.Tests.ps1`
- **SecureIT references:** canonical ID `MTENTITLEMENTMANAGEMENTVALIDRESOURCEROLES`; evidence mapping `Test-MtEntitlementManagementValidResourceRoles.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects resource role assignments used by entitlement management access packages. It passes when resource roles are valid and still map to the intended access; it fails when invalid or stale resource roles are present. This matters because incorrect resource roles can grant the wrong access or prevent legitimate users from receiving the access they need.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 36. Entra ID Connect (`MTENTRAIDCONNECT`)

- **Expected `.ps1` check file:** `Test-MtEntraIDConnect.Tests.ps1`
- **SecureIT references:** canonical ID `MTENTRAIDCONNECT`; evidence mapping `Test-MtEntraIDConnect.Tests.ps1`; raw test ID(s): `MT.1084`
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects Entra ID Connect or cloud sync configuration for hybrid identity. It passes when identity synchronization is healthy and configured as expected; it fails when sync is unhealthy, stale, or configured in a way that needs attention. This matters because identity sync problems can break access, delay account changes, or leave disabled users active in cloud services.
- **Workflow #51 outcome/output:**
  - `Test-MtEntraIDConnect.Tests.ps1` / `MT.1084` — Microsoft Entra seamless single sign-on should be disabled for all domains in EntraID Connect servers.: Skipped — no textual result detail was emitted.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the tenant did not expose the feature or configuration this control checks. Requires a configured hybrid identity or cloud sync environment. Required: Microsoft Entra Connect or cloud sync. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 37. On-premises synchronization (`MTONPREMISESSYNCHRONIZATION`)

- **Expected `.ps1` check file:** `Test-MtOnPremisesSynchronization.Tests.ps1`
- **SecureIT references:** canonical ID `MTONPREMISESSYNCHRONIZATION`; evidence mapping `Test-MtOnPremisesSynchronization.Tests.ps1`; raw test ID(s): `MT.1073`
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects on-premises synchronization status and related hybrid identity settings. It passes when synchronization is current and operating as expected; it fails when sync is failing, stale, or configured unexpectedly. This matters because synchronization issues can leave old access in place or prevent urgent identity changes reaching Microsoft 365.
- **Workflow #51 outcome/output:**
  - `Test-MtOnPremisesSynchronization.Tests.ps1` / `MT.1073` — Soft- and hard-matching of synchronized objects should be blocked.: Skipped — Skipped. OnPremisesSynchronization is not configured
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the tenant did not expose the feature or configuration this control checks. Requires a configured hybrid identity or cloud sync environment. Required: Microsoft Entra Connect or cloud sync. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 38. Guest access restrictions (`MTCISENSUREGUESTACCESSRESTRICTED`)

- **Expected `.ps1` check file:** `Test-MtCisEnsureGuestAccessRestricted.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISENSUREGUESTACCESSRESTRICTED`; evidence mapping `Test-MtCisEnsureGuestAccessRestricted.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects guest user access restrictions in Entra ID. It passes when guest users have limited visibility into directory and tenant information; it fails when guests can browse more tenant information than required. This matters because restricted guest visibility reduces information exposure to external users.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 39. Guest user dynamic groups (`MTCISENSUREGUESTUSERDYNAMICGROUP`)

- **Expected `.ps1` check file:** `Test-MtCisEnsureGuestUserDynamicGroup.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISENSUREGUESTUSERDYNAMICGROUP`; evidence mapping `Test-MtCisEnsureGuestUserDynamicGroup.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects dynamic groups used to identify and manage guest users. It passes when guest users are captured by an appropriate dynamic group or equivalent control; it fails when guest users are not grouped consistently for policy, review, or reporting. This matters because consistent guest grouping makes it easier to apply controls and review external access.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 40. User app consent disallowed (`MTCISENSUREUSERCONSENTTOAPPSDISALLOWED`)

- **Expected `.ps1` check file:** `Test-MtCisEnsureUserConsentToAppsDisallowed.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISENSUREUSERCONSENTTOAPPSDISALLOWED`; evidence mapping `Test-MtCisEnsureUserConsentToAppsDisallowed.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects user consent settings for third-party applications. It passes when users cannot grant app permissions outside the approved consent process; it fails when users can consent to applications without administrator review. This matters because unreviewed consent can allow phishing apps to gain access to mail, files, or profile data.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 41. SharePoint default sharing link (`MTCISSPODEFAULTSHARINGLINK`)

- **Expected `.ps1` check file:** `Test-MtCisSpoDefaultSharingLink.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISSPODEFAULTSHARINGLINK`; evidence mapping `Test-MtCisSpoDefaultSharingLink.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects the default sharing link type for SharePoint and OneDrive. It passes when new sharing links default to a restricted audience; it fails when new links default to broad or anonymous sharing. This matters because safe defaults reduce accidental data exposure when users share files.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 42. SharePoint default sharing link permissions (`MTCISSPODEFAULTSHARINGLINKPERMISSION`)

- **Expected `.ps1` check file:** `Test-MtCisSpoDefaultSharingLinkPermission.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISSPODEFAULTSHARINGLINKPERMISSION`; evidence mapping `Test-MtCisSpoDefaultSharingLinkPermission.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects the default permission level for SharePoint and OneDrive sharing links. It passes when new sharing links default to view-only or the approved least-privilege permission; it fails when new links default to edit access or another overly permissive setting. This matters because least-privilege sharing helps prevent unintended changes or onward sharing of business files.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 43. SharePoint guest access expiry (`MTCISSPOGUESTACCESSEXPIRY`)

- **Expected `.ps1` check file:** `Test-MtCisSpoGuestAccessExpiry.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISSPOGUESTACCESSEXPIRY`; evidence mapping `Test-MtCisSpoGuestAccessExpiry.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects guest access expiry settings for SharePoint and OneDrive. It passes when guest access expires after the approved period; it fails when guest access does not expire or lasts longer than policy allows. This matters because expiry prevents old external access from remaining after a project or relationship has ended.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 44. SharePoint guest sharing restrictions (`MTCISSPOGUESTCANNOTSHAREUNOWNEDITEM`)

- **Expected `.ps1` check file:** `Test-MtCisSpoGuestCannotShareUnownedItem.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISSPOGUESTCANNOTSHAREUNOWNEDITEM`; evidence mapping `Test-MtCisSpoGuestCannotShareUnownedItem.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects whether guests can reshare SharePoint or OneDrive content they do not own. It passes when guests cannot reshare content unless explicitly allowed; it fails when guests can pass access to other people without owner approval. This matters because resharing by guests can spread sensitive files beyond the intended audience.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 45. SharePoint malicious file download protection (`MTCISSPOPREVENTDOWNLOADMALICIOUSFILE`)

- **Expected `.ps1` check file:** `Test-MtCisSpoPreventDownloadMaliciousFile.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISSPOPREVENTDOWNLOADMALICIOUSFILE`; evidence mapping `Test-MtCisSpoPreventDownloadMaliciousFile.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects SharePoint and OneDrive protection against downloading files marked as malicious. It passes when users are prevented from downloading files identified as malicious; it fails when malicious files can still be downloaded. This matters because blocking malicious downloads reduces the chance of users spreading or executing harmful files.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 46. Third-party and custom apps (`MTCISTHIRDPARTYANDCUSTOMAPPS`)

- **Expected `.ps1` check file:** `Test-MtCisThirdPartyAndCustomApps.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISTHIRDPARTYANDCUSTOMAPPS`; evidence mapping `Test-MtCisThirdPartyAndCustomApps.Tests.ps1`; raw test ID(s): `CIS.M365.8.4.1`
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects third-party and custom app settings in integrated Microsoft 365 services. It passes when third-party and custom apps are restricted or governed by approval; it fails when apps can be added without the expected restrictions. This matters because uncontrolled apps can introduce data access and compliance risks outside normal change control.
- **Workflow #51 outcome/output:**
  - `Test-MtCisThirdPartyAndCustomApps.Tests.ps1` / `CIS.M365.8.4.1` — Ensure all or a majority of third-party and custom apps are blocked: Skipped — Skipped. Not connected to Teams. See [Connecting to Teams](https://maester.dev/docs/connect-maester/#connect-to-azure-exchange-online-and-teams)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the tenant did not expose the feature or configuration this control checks. Requires third-party and custom app governance data to be present. Required: Custom apps or third-party app governance data. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 47. Third-party storage services restricted (`MTCISTHIRDPARTYSTORAGESERVICESRESTRICTED`)

- **Expected `.ps1` check file:** `Test-MtCisThirdPartyStorageServicesRestricted.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISTHIRDPARTYSTORAGESERVICESRESTRICTED`; evidence mapping `Test-MtCisThirdPartyStorageServicesRestricted.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects third-party storage service integration settings. It passes when external storage services are restricted according to policy; it fails when users can connect unapproved third-party storage providers. This matters because unapproved storage paths can move business data outside retention, discovery, and protection controls.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 48. User-owned apps restricted (`MTCISUSEROWNEDAPPSRESTRICTED`)

- **Expected `.ps1` check file:** `Test-MtCisUserOwnedAppsRestricted.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISUSEROWNEDAPPSRESTRICTED`; evidence mapping `Test-MtCisUserOwnedAppsRestricted.Tests.ps1`; raw test ID(s): [none emitted]
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects whether users can create or own applications without approval. It passes when user-owned apps are restricted or governed; it fails when users can create and manage apps outside the approved process. This matters because unmanaged user-owned apps can accumulate permissions and credentials that outlive their business need.
- **Workflow #51 outcome/output:**
  - No raw test object was emitted, and the expected file was absent from `_selected_tests`.
  - The workflow log explicitly warned that this allowlist file was not found in the installed, pinned Maester 2.0.0 test package.
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `unmapped`. No matching test evidence was found in the latest run. Only `pass`, `partial`, and `fail` are included in the headline email figures; `unmapped` is excluded from that count and from the score denominator.

### 49. Assignment notifications (`MTCISAASSIGNMENTNOTIFICATION`)

- **Expected `.ps1` check file:** `Test-MtCisaAssignmentNotification.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISAASSIGNMENTNOTIFICATION`; evidence mapping `Test-MtCisaAssignmentNotification.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.7.7`
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects notifications for privileged role assignment changes. It passes when assignment notifications are sent to the expected recipients; it fails when role assignment changes can happen without the right notification. This matters because new privileged assignments should be visible so unexpected access can be challenged.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaAssignmentNotification.Tests.ps1` / `CISA.MS.AAD.7.7` — Eligible and Active highly privileged role assignments SHALL trigger an alert.: Skipped — Skipped. This test is for tenants that are licensed for Entra ID P2. See [Entra ID licensing](https://learn.microsoft.com/entra/fundamentals/licensing)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

### 50. Diagnostic settings (`MTCISADIAGNOSTICSETTINGS`)

- **Expected `.ps1` check file:** `Test-MtCisaDiagnosticSettings.Tests.ps1`
- **SecureIT references:** canonical ID `MTCISADIAGNOSTICSETTINGS`; evidence mapping `Test-MtCisaDiagnosticSettings.Tests.ps1`; raw test ID(s): `CISA.MS.AAD.4.1`
- **Functional area:** Compliance, Governance & Data Protection
- **Description:** Inspects diagnostic and audit log export settings. It passes when diagnostic logs are enabled and routed to the expected destination; it fails when important logs are not captured or not exported correctly. This matters because security investigation depends on having reliable logs after an incident.
- **Workflow #51 outcome/output:**
  - `Test-MtCisaDiagnosticSettings.Tests.ps1` / `CISA.MS.AAD.4.1` — Security logs SHALL be sent to the agency's security operations center for monitoring.: Skipped — Skipped. Not connected to Azure. See [Connecting to Azure](https://maester.dev/docs/connect-maester/#connect-to-azure-exchange-online-and-teams)
- **Why it was not in pass/fail/partial:** SecureIT resolved the canonical control to `skipped`. All matched tests were skipped because the tenant did not expose the feature or configuration this control checks. Requires diagnostic settings or log export configuration to be present. Required: Diagnostic settings enabled for the relevant service; A configured log destination such as Log Analytics, Event Hub, or Storage. Only `pass`, `partial`, and `fail` are included in the headline email figures; `skipped` is excluded from that count and from the score denominator.

## Principal findings

- **23 catalogue mappings point to files that are not present in the pinned Maester 2.0.0 test package.** All 23 files and their matching implementations are present in Maester 2.2.0, so the production module pin is stale relative to the SecureIT catalogue.
- **All five `not_run` canonical controls were excluded by Maester tags.** The workflow omitted `-IncludeLongRunning`; the high-risk app-permission test is also tagged `Preview`.
- **`Test-MtMdiHealthIssues.Tests.ps1` is present but emits zero tests when no health-issue groups are returned.** SecureIT consequently marks the control `unmapped` instead of treating the absence of issues as a pass.
- **Discovered tests with only `Skipped`, `NotRun`, or `Error` outcomes are intentionally non-scoreable.** Their number differs by tenant because runner connections, permissions, licensing, and feature availability differ.
- **NCVO has one explicit error:** `MTCISCLOUDADMIN` received Microsoft Graph `AadPremiumLicenseRequired` because the tenant lacks Entra ID P2 or Governance licensing.
