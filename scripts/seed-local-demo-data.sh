#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export SECUREIT_DATA_DIR="${ROOT_DIR}/data"

python3 - <<'PY'
from __future__ import annotations

import json
import os
from collections import Counter, defaultdict
from pathlib import Path

root = Path(os.environ["SECUREIT_DATA_DIR"])

base_tests = [
    "Test-EIDSCA.Generated.Tests.ps1",
    "Test-MtMdeAntivirusPolicy.Tests.ps1",
    "Test-MtMdiHealthIssues.Tests.ps1",
    "Test-AppManagementPolicies.Tests.ps1",
    "Test-AppRegistrations.Tests.ps1",
    "Test-AuthenticationMethodBaseline.Tests.ps1",
    "Test-ConditionalAccessBaseline.Tests.ps1",
    "Test-ConditionalAccessWhatIf.Tests.ps1",
    "Test-EntraRecommendations.Tests.ps1",
    "Test-Groups.Tests.ps1",
    "Test-MtAppRegistrationOwnersWithoutMFA.Tests.ps1",
    "Test-MtEntitlementManagementDeletedGroups.Tests.ps1",
    "Test-MtEntitlementManagementInactivePolicies.Tests.ps1",
    "Test-MtEntitlementManagementOrphanedResources.Tests.ps1",
    "Test-MtEntitlementManagementValidApprovers.Tests.ps1",
    "Test-MtEntitlementManagementValidResourceRoles.Tests.ps1",
    "Test-MtEntraDeviceRegistrationPolicy.Tests.ps1",
    "Test-MtEntraIDConnect.Tests.ps1",
    "Test-MtHighRiskAppPermissions.Tests.ps1",
    "Test-MtOnPremisesSynchronization.Tests.ps1",
    "Test-MtSecurityGroupCreationRestricted.Tests.ps1",
    "Test-MtTenantCreationRestricted.Tests.ps1",
    "Test-PrivilegedAssignments.Tests.ps1",
    "Test-MtIntuneConnectorHealth.Tests.ps1",
    "Test-MtIntunePlatform.Tests.ps1",
    "Test-XspmCriticalAssetManagement.Tests.ps1",
    "Test-XspmDevices.Tests.ps1",
    "Test-XspmPrivilegedIdentities.Tests.ps1",
    "Test-MtCis365PublicGroup.Tests.ps1",
    "Test-MtCisAdminConsentWorkflowEnabled.Tests.ps1",
    "Test-MtCisCloudAdmin.Tests.ps1",
    "Test-MtCisCreateTenantDisallowed.Tests.ps1",
    "Test-MtCisCustomerLockBox.Tests.ps1",
    "Test-MtCisDevicesWithoutCompliancePolicyMarked.Tests.ps1",
    "Test-MtCisEnsureGuestAccessRestricted.Tests.ps1",
    "Test-MtCisEnsureGuestUserDynamicGroup.Tests.ps1",
    "Test-MtCisEnsureUserConsentToAppsDisallowed.Tests.ps1",
    "Test-MtCisFormsPhishingProtectionEnabled.Tests.ps1",
    "Test-MtCisGlobalAdminCount.Tests.ps1",
    "Test-MtCisPasswordExpiry.Tests.ps1",
    "Test-MtCisSpoB2BIntegration.Tests.ps1",
    "Test-MtCisSpoDefaultSharingLink.Tests.ps1",
    "Test-MtCisSpoDefaultSharingLinkPermission.Tests.ps1",
    "Test-MtCisSpoGuestAccessExpiry.Tests.ps1",
    "Test-MtCisSpoGuestCannotShareUnownedItem.Tests.ps1",
    "Test-MtCisSpoPreventDownloadMaliciousFile.Tests.ps1",
    "Test-MtCisThirdPartyAndCustomApps.Tests.ps1",
    "Test-MtCisThirdPartyApplicationsDisallowed.Tests.ps1",
    "Test-MtCisThirdPartyStorageServicesRestricted.Tests.ps1",
    "Test-MtCisUserOwnedAppsRestricted.Tests.ps1",
    "Test-MtCisWeakAuthenticationMethodsDisabled.Tests.ps1",
    "Test-MtCisaActivationNotificationGlobalAdmin.Tests.ps1",
    "Test-MtCisaActivationNotificationOther.Tests.ps1",
    "Test-MtCisaAppAdminConsent.Tests.ps1",
    "Test-MtCisaAppGroupOwnerConsent.Tests.ps1",
    "Test-MtCisaAppRegistration.Tests.ps1",
    "Test-MtCisaAppUserConsent.Tests.ps1",
    "Test-MtCisaAssignmentNotification.Tests.ps1",
    "Test-MtCisaAuthenticatorContext.Tests.ps1",
    "Test-MtCisaBlockHighRiskSignIns.Tests.ps1",
    "Test-MtCisaBlockHighRiskUsers.Tests.ps1",
    "Test-MtCisaBlockLegacyAuth.Tests.ps1",
    "Test-MtCisaCloudGlobalAdmin.Tests.ps1",
    "Test-MtCisaCrossTenantInboundDefault.Tests.ps1",
    "Test-MtCisaDiagnosticSettings.Tests.ps1",
    "Test-MtCisaGlobalAdminCount.Tests.ps1",
    "Test-MtCisaGlobalAdminRatio.Tests.ps1",
    "Test-MtCisaGuestInvitation.Tests.ps1",
    "Test-MtCisaGuestUserAccess.Tests.ps1",
    "Test-MtCisaManagedDevice.Tests.ps1",
    "Test-MtCisaManagedDeviceRegistration.Tests.ps1",
    "Test-MtCisaMethodsMigration.Tests.ps1",
    "Test-MtCisaMfa.Tests.ps1",
    "Test-MtCisaNotifyHighRiskUsers.Tests.ps1",
    "Test-MtCisaPasswordExpiration.Tests.ps1",
    "Test-MtCisaPermanentRoleAssignment.Tests.ps1",
    "Test-MtCisaPhishResistant.Tests.ps1",
    "Test-MtCisaPrivilegedPhishResistant.Tests.ps1",
    "Test-MtCisaRequireActivationApproval.Tests.ps1",
    "Test-MtCisaUnmanagedRoleAssignments.Tests.ps1",
    "Test-MtCisaWeakFactor.Tests.ps1",
    "Test-MtCisaSpoSharing.Tests.ps1",
    "Test-MtCisaSpoSharingAllowedDomain.Tests.ps1",
]

second_variant_tests = {
    "Test-AuthenticationMethodBaseline.Tests.ps1",
    "Test-Groups.Tests.ps1",
    "Test-MtCisAdminConsentWorkflowEnabled.Tests.ps1",
    "Test-MtCisCreateTenantDisallowed.Tests.ps1",
    "Test-MtCisEnsureGuestAccessRestricted.Tests.ps1",
    "Test-MtCisEnsureUserConsentToAppsDisallowed.Tests.ps1",
    "Test-MtCisGlobalAdminCount.Tests.ps1",
    "Test-MtCisSpoDefaultSharingLink.Tests.ps1",
    "Test-MtCisSpoDefaultSharingLinkPermission.Tests.ps1",
    "Test-MtCisSpoGuestAccessExpiry.Tests.ps1",
    "Test-MtCisThirdPartyApplicationsDisallowed.Tests.ps1",
    "Test-MtCisaBlockHighRiskSignIns.Tests.ps1",
    "Test-MtCisaBlockHighRiskUsers.Tests.ps1",
    "Test-MtCisaBlockLegacyAuth.Tests.ps1",
    "Test-MtCisaCrossTenantInboundDefault.Tests.ps1",
    "Test-MtCisaGlobalAdminCount.Tests.ps1",
    "Test-MtCisaGlobalAdminRatio.Tests.ps1",
    "Test-MtCisaGuestInvitation.Tests.ps1",
    "Test-MtCisaGuestUserAccess.Tests.ps1",
    "Test-MtCisaMfa.Tests.ps1",
}

def strip_variant(name: str) -> str:
    name = name.removeprefix("Test-").removesuffix(".Tests.ps1")
    if "-Alt" in name:
        name = name.split("-Alt", 1)[0]
    return name

def family_for(test_name: str) -> str:
    base = strip_variant(test_name)
    if any(token in base for token in [
        "AuthenticationMethodBaseline", "SecurityGroupCreationRestricted", "TenantCreationRestricted",
        "AdminConsentWorkflowEnabled", "CreateTenantDisallowed", "FormsPhishingProtectionEnabled",
        "ThirdPartyApplicationsDisallowed", "WeakAuthenticationMethodsDisabled", "AppAdminConsent",
        "AppGroupOwnerConsent", "AppRegistration", "AppUserConsent", "AuthenticatorContext",
        "BlockHighRiskSignIns", "BlockHighRiskUsers", "BlockLegacyAuth", "CloudGlobalAdmin",
        "CrossTenantInboundDefault", "GlobalAdminCount", "GlobalAdminRatio", "MethodsMigration",
        "Mfa", "NotifyHighRiskUsers", "PasswordExpiration", "PhishResistant",
        "PrivilegedPhishResistant", "WeakFactor", "ActivationNotification", "PermanentRoleAssignment",
        "RequireActivationApproval", "UnmanagedRoleAssignments"
    ]):
        return "Identity & Access Management"
    if any(token in base for token in ["FormsPhishingProtectionEnabled"]):
        return "Email & Calendaring"
    if any(token in base for token in ["Groups", "GuestInvitation", "GuestUserAccess", "SpoB2BIntegration", "CrossTenantInboundDefault", "SpoSharing"]):
        return "Collaboration & Communication"
    if any(token in base for token in ["AppManagementPolicies", "EntitlementManagement", "TenantCreation", "CreateTenant", "UserOwnedApps", "ThirdPartyStorageServicesRestricted"]):
        return "Compliance, Governance & Data Protection"
    if any(token in base for token in ["EntraDeviceRegistrationPolicy", "Intune", "ManagedDevice", "ManagedDeviceRegistration", "DevicesWithoutCompliancePolicyMarked", "MdeAntivirusPolicy", "MdiHealthIssues"]):
        return "Endpoint & Device Management"
    if any(token in base for token in ["HighRiskAppPermissions", "PrivilegedAssignments", "Xspm", "MdiHealthIssues", "MdE", "Defender", "CisCloudAdmin", "CisCustomerLockBox"]):
        return "Security Operations & Threat Protection"
    if any(token in base for token in ["AppManagementPolicies"]):
        return "Productivity, Automation & AI"
    return "Compliance, Governance & Data Protection"

def title_for(test_name: str) -> str:
    base = strip_variant(test_name)
    variants = []
    if "-Alt01" in test_name:
        variants.append("scenario A")
    elif "-Alt02" in test_name:
        variants.append("scenario B")

    replacements = [
        ("Test-", ""),
        ("Mt", ""),
        ("Cisa", ""),
        ("Cis", ""),
        ("Xspm", "XSPM "),
        ("EntraIDConnect", "Entra ID connect"),
        ("EntraDeviceRegistrationPolicy", "Entra device registration policy"),
        ("AuthenticationMethodBaseline", "Authentication method baseline"),
        ("SecurityGroupCreationRestricted", "Security group creation restricted"),
        ("TenantCreationRestricted", "Tenant creation restricted"),
        ("AdminConsentWorkflowEnabled", "Admin consent workflow enabled"),
        ("CreateTenantDisallowed", "Create tenant disallowed"),
        ("FormsPhishingProtectionEnabled", "Forms phishing protection enabled"),
        ("ThirdPartyApplicationsDisallowed", "Third party applications disallowed"),
        ("WeakAuthenticationMethodsDisabled", "Weak authentication methods disabled"),
        ("AppAdminConsent", "App admin consent"),
        ("AppGroupOwnerConsent", "App group owner consent"),
        ("AppRegistration", "App registration"),
        ("AppUserConsent", "App user consent"),
        ("AuthenticatorContext", "Authenticator context"),
        ("BlockHighRiskSignIns", "Block high risk sign-ins"),
        ("BlockHighRiskUsers", "Block high risk users"),
        ("BlockLegacyAuth", "Block legacy auth"),
        ("CloudGlobalAdmin", "Cloud global admin"),
        ("CrossTenantInboundDefault", "Cross tenant inbound default"),
        ("GlobalAdminCount", "Global admin count"),
        ("GlobalAdminRatio", "Global admin ratio"),
        ("GuestInvitation", "Guest invitation"),
        ("GuestUserAccess", "Guest user access"),
        ("MethodsMigration", "Methods migration"),
        ("Mfa", "MFA"),
        ("NotifyHighRiskUsers", "Notify high risk users"),
        ("PasswordExpiration", "Password expiration"),
        ("PhishResistant", "Phish resistant"),
        ("PrivilegedPhishResistant", "Privileged phish resistant"),
        ("WeakFactor", "Weak factor"),
        ("ThirdPartyStorageServicesRestricted", "Third party storage services restricted"),
        ("UserOwnedAppsRestricted", "User owned apps restricted"),
        ("PermanentRoleAssignment", "Permanent role assignment"),
        ("RequireActivationApproval", "Require activation approval"),
        ("UnmanagedRoleAssignments", "Unmanaged role assignments"),
        ("SpoDefaultSharingLink", "SharePoint default sharing link"),
        ("SpoDefaultSharingLinkPermission", "SharePoint default sharing link permission"),
        ("SpoGuestAccessExpiry", "SharePoint guest access expiry"),
        ("SpoGuestCannotShareUnownedItem", "SharePoint guest cannot share unowned item"),
        ("SpoPreventDownloadMaliciousFile", "SharePoint prevent malicious file downloads"),
        ("SpoSharingAllowedDomain", "SharePoint sharing allowed domain"),
        ("SpoSharing", "SharePoint sharing"),
        ("SpoB2BIntegration", "SharePoint B2B integration"),
        ("Groups", "Groups"),
        ("AppManagementPolicies", "App management policies"),
        ("EntitlementManagement", "Entitlement management"),
        ("IntuneConnectorHealth", "Intune connector health"),
        ("IntunePlatform", "Intune platform"),
        ("MdeAntivirusPolicy", "Defender antivirus policy"),
        ("MdiHealthIssues", "Defender identity health issues"),
        ("ManagedDeviceRegistration", "Managed device registration"),
        ("ManagedDevice", "Managed device"),
        ("DevicesWithoutCompliancePolicyMarked", "Devices without compliance policy"),
        ("HighRiskAppPermissions", "High risk app permissions"),
        ("PrivilegedAssignments", "Privileged assignments"),
        ("AppRegistrations", "App registrations"),
        ("EntraRecommendations", "Entra recommendations"),
        ("OnPremisesSynchronization", "On-premises synchronization"),
        ("AppRegistrationOwnersWithoutMFA", "App registration owners without MFA"),
        ("EIDSCA.Generated", "EIDSCA generated"),
    ]

    text = base
    for old, new in replacements:
        text = text.replace(old, new)
    text = text.replace("-", " ")
    text = " ".join(text.split())
    if variants:
        text = f"{text} ({', '.join(variants)})"
    return text

def control_id_for(test_name: str) -> str:
    base = strip_variant(test_name).upper().replace("-", "_")
    if "-Alt01" in test_name:
        return f"{base}_ALT01"
    if "-Alt02" in test_name:
        return f"{base}_ALT02"
    return base

def result_for(tenant: str, test_name: str) -> str:
    base = strip_variant(test_name)
    variant = 0
    if "-Alt01" in test_name:
        variant = 1
    elif "-Alt02" in test_name:
        variant = 2

    contoso_fail = {
        "AuthenticationMethodBaseline",
        "Groups",
        "MtCisAdminConsentWorkflowEnabled",
        "MtCisCreateTenantDisallowed",
        "MtCisEnsureGuestAccessRestricted",
        "MtCisEnsureUserConsentToAppsDisallowed",
        "MtCisGlobalAdminCount",
        "MtCisSpoDefaultSharingLink",
        "MtCisSpoDefaultSharingLinkPermission",
        "MtCisSpoGuestAccessExpiry",
        "MtCisThirdPartyApplicationsDisallowed",
        "MtCisaBlockHighRiskSignIns",
        "MtCisaBlockHighRiskUsers",
        "MtCisaBlockLegacyAuth",
        "MtCisaCrossTenantInboundDefault",
        "MtCisaGlobalAdminCount",
        "MtCisaGlobalAdminRatio",
        "MtCisaGuestInvitation",
        "MtCisaGuestUserAccess",
        "MtCisaMfa",
        "MtCisaPasswordExpiration",
    }
    contoso_skip = {
        "MtEntraDeviceRegistrationPolicy",
        "MtSecurityGroupCreationRestricted",
        "MtCisFormsPhishingProtectionEnabled",
        "MtCisaNotifyHighRiskUsers",
        "MtCisaMethodsMigration",
    }

    if tenant == "contoso-prod":
        if variant == 0:
            if base in contoso_fail:
                return "Fail"
            if base in contoso_skip:
                return "Skipped"
            return "Pass"
        if variant == 1:
            if base in contoso_fail and ("Guest" in base or "CisSpo" in base or "Mfa" in base):
                return "Fail"
            if base in contoso_skip:
                return "Skipped"
            return "Pass"
        if variant == 2:
            if base in contoso_fail and ("Mfa" in base or "GlobalAdmin" in base or "BlockLegacyAuth" in base):
                return "Fail"
            if base in contoso_skip:
                return "Skipped"
            return "Pass"

    if tenant == "fabrikam-prod":
        if variant == 0 and base in {"MtCisaPasswordExpiration", "MtCisaMethodsMigration"}:
            return "Skipped"
        if variant == 1 and base in {"MtCisAdminConsentWorkflowEnabled", "MtCisaGlobalAdminRatio"}:
            return "Skipped"
        return "Pass"

    return "Pass"

def build_test_suite() -> list[str]:
    tests: list[str] = []
    tests.extend(base_tests)
    for test in base_tests:
        tests.append(test.replace(".Tests.ps1", "-Alt01.Tests.ps1"))
        if test in second_variant_tests:
            tests.append(test.replace(".Tests.ps1", "-Alt02.Tests.ps1"))
    assert len(tests) == 186, len(tests)
    return tests

def make_tenant(tenant_key: str, tenant_name: str, generated_at: str, history_at: str) -> None:
    tenant_dir = root / "reports" / tenant_key
    latest = tenant_dir / "latest"
    history = tenant_dir / "history" / history_at[:10] / history_at[11:15]
    latest.mkdir(parents=True, exist_ok=True)
    history.mkdir(parents=True, exist_ok=True)

    tests = build_test_suite()
    results = [result_for(tenant_key, test) for test in tests]
    counts = Counter(results)
    total = len(tests)

    embedded_tests = []
    for test_name, result in zip(tests, results, strict=True):
        embedded_tests.append(
            {
                "Id": test_name,
                "Result": result,
                "Title": title_for(test_name),
                "Severity": {"Pass": "Low", "Fail": "High", "Skipped": "Medium"}[result],
                "Tag": [family_for(test_name)],
            }
        )

    grouped: dict[str, list[str]] = defaultdict(list)
    for test_name in tests:
        grouped[family_for(test_name)].append(test_name)

    blocks = []
    for area, area_tests in grouped.items():
        area_results = [result_for(tenant_key, test_name) for test_name in area_tests]
        blocks.append(
            {
                "Name": area,
                "Result": "Pass" if all(r == "Pass" for r in area_results) else ("Fail" if any(r == "Fail" for r in area_results) else "Skipped"),
                "FailedCount": sum(1 for r in area_results if r == "Fail"),
                "PassedCount": sum(1 for r in area_results if r == "Pass"),
                "ErrorCount": 0,
                "InvestigateCount": 0,
                "SkippedCount": sum(1 for r in area_results if r == "Skipped"),
                "NotRunCount": 0,
                "TotalCount": len(area_results),
                "Tag": [area],
            }
        )

    summary = {
        "generatedAt": generated_at,
        "total": total,
        "passed": counts["Pass"],
        "failed": counts["Fail"],
        "skipped": counts["Skipped"],
        "tenantName": tenant_name,
        "reportUrl": f"http://127.0.0.1:8088/{tenant_key}/latest/index.html",
    }

    prior_summary = {
        "generatedAt": f"{history_at[:10]}T{history_at[11:13]}:{history_at[13:15]}:00+01:00",
        "total": total,
        "passed": max(0, counts["Pass"] - 6),
        "failed": min(total, counts["Fail"] + 3),
        "skipped": counts["Skipped"],
        "tenantName": tenant_name,
        "reportUrl": f"http://127.0.0.1:8088/{tenant_key}/history/{history_at[:10]}/{history_at[11:15]}/index.html",
    }

    (latest / "summary.json").write_text(json.dumps(summary, indent=2) + "\n", encoding="utf-8")
    (latest / "embedded-summary.json").write_text(json.dumps({"Tests": embedded_tests, "Blocks": blocks}, indent=2) + "\n", encoding="utf-8")
    (latest / "index.html").write_text(
        f"<!doctype html><html lang='en'><head><meta charset='utf-8'><title>{tenant_name} - SecureIT</title></head><body><h1>{tenant_name}</h1><p>Demo SecureIT report bundle.</p></body></html>\n",
        encoding="utf-8",
    )
    (history / "summary.json").write_text(json.dumps(prior_summary, indent=2) + "\n", encoding="utf-8")
    (history / "index.html").write_text(
        f"<!doctype html><html lang='en'><head><meta charset='utf-8'><title>{tenant_name} - Historical SecureIT Report</title></head><body><h1>{tenant_name} history</h1></body></html>\n",
        encoding="utf-8",
    )

def build_runtime_tenants() -> dict:
    return {
        "tenants": [
            {
                "id": "contoso-prod",
                "name": "Contoso Production",
                "tenantId": "00000000-0000-0000-0000-000000000000",
                "clientId": "11111111-1111-1111-1111-111111111111",
                "authMode": "certificate",
                "keyVaultName": "",
                "keyVaultUri": "",
                "certificateSecretName": "secureit-contoso-prod-pfx",
                "certificatePasswordSecretName": "secureit-contoso-prod-pfx-password",
                "reportBaseUrl": "http://127.0.0.1:8088/contoso-prod",
                "emailTo": "security@contoso.example",
            },
            {
                "id": "fabrikam-prod",
                "name": "Fabrikam Production",
                "tenantId": "22222222-2222-2222-2222-222222222222",
                "clientId": "33333333-3333-3333-3333-333333333333",
                "authMode": "certificate",
                "keyVaultName": "",
                "keyVaultUri": "",
                "certificateSecretName": "secureit-fabrikam-prod-pfx",
                "certificatePasswordSecretName": "secureit-fabrikam-prod-pfx-password",
                "reportBaseUrl": "http://127.0.0.1:8088/fabrikam-prod",
                "emailTo": "security@fabrikam.example",
            },
        ]
    }

def build_canonical_controls(tests: list[str]) -> dict:
    return {
        "version": 1,
        "description": "Local demo canonical SecureIT control mapping for testing the container app.",
        "functionalAreas": [
            "Identity & Access Management",
            "Email & Calendaring",
            "Collaboration & Communication",
            "Files, Intranet & Content Management",
            "Endpoint & Device Management",
            "Security Operations & Threat Protection",
            "Compliance, Governance & Data Protection",
            "Productivity, Automation & AI",
        ],
        "controls": [
            {
                "id": control_id_for(test_name),
                "title": title_for(test_name),
                "functionalArea": family_for(test_name),
                "description": f"Demo control derived from {test_name}.",
                "frameworkMappings": [test_name],
                "duplicatePolicy": "single",
                "scoring": {"weight": 1, "passLogic": "direct"},
            }
            for test_name in tests
        ],
    }

tests = build_test_suite()
root.mkdir(parents=True, exist_ok=True)
(root / "tenants.json").write_text(json.dumps(build_runtime_tenants(), indent=2) + "\n", encoding="utf-8")
(root / "canonical-controls.json").write_text(json.dumps(build_canonical_controls(tests), indent=2) + "\n", encoding="utf-8")
make_tenant("contoso-prod", "Contoso Production", "2026-06-18T10:15:00+01:00", "2026-06-17T0900")
make_tenant("fabrikam-prod", "Fabrikam Production", "2026-06-18T10:20:00+01:00", "2026-06-16T1530")
print(f"Seeded demo data in {root}")
print(f"Test count: {len(tests)}")
PY
