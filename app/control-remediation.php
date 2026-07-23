<?php

$routes = [];
$assignRoute = static function (array $controlIds, string $portal, string $path) use (&$routes): void {
    foreach ($controlIds as $controlId) {
        $routes[$controlId] = [
            'method' => 'GUI',
            'portal' => $portal,
            'path' => $path,
        ];
    }
};

$assignRoute([
    'APPREGISTRATIONS',
    'MTAPPREGISTRATIONOWNERSWITHOUTMFA',
    'MTCISADMINCONSENTWORKFLOWENABLED',
    'MTCISTHIRDPARTYAPPLICATIONSDISALLOWED',
    'MTCISAAPPADMINCONSENT',
    'MTCISAAPPGROUPOWNERCONSENT',
    'MTCISAAPPREGISTRATION',
    'MTCISAAPPUSERCONSENT',
    'MTHIGHRISKAPPPERMISSIONS',
    'MTCISENSUREUSERCONSENTTOAPPSDISALLOWED',
    'MTCISTHIRDPARTYANDCUSTOMAPPS',
    'MTCISUSEROWNEDAPPSRESTRICTED',
], 'Microsoft Entra admin center', 'Identity > Applications, then open App registrations or Enterprise applications as appropriate');

$assignRoute([
    'AUTHENTICATIONMETHODBASELINE',
    'MTCISWEAKAUTHENTICATIONMETHODSDISABLED',
    'MTCISAMETHODSMIGRATION',
    'MTCISAPASSWORDEXPIRATION',
    'MTCISPASSWORDEXPIRY',
    'MTCISAWEAKFACTOR',
], 'Microsoft Entra admin center', 'Protection > Authentication methods');

$assignRoute([
    'CONDITIONALACCESSBASELINE',
    'CONDITIONALACCESSWHATIF',
    'MTCISAAUTHENTICATORCONTEXT',
    'MTCISABLOCKHIGHRISKSIGNINS',
    'MTCISABLOCKHIGHRISKUSERS',
    'MTCISABLOCKLEGACYAUTH',
    'MTCISAMFA',
    'MTCISANOTIFYHIGHRISKUSERS',
    'MTCISAPHISHRESISTANT',
    'MTCISAPRIVILEGEDPHISHRESISTANT',
], 'Microsoft Entra admin center', 'Protection > Conditional Access');

$assignRoute([
    'MTCISGLOBALADMINCOUNT',
    'MTCISAACTIVATIONNOTIFICATIONGLOBALADMIN',
    'MTCISAACTIVATIONNOTIFICATIONOTHER',
    'MTCISACLOUDGLOBALADMIN',
    'MTCISAGLOBALADMINCOUNT',
    'MTCISAGLOBALADMINRATIO',
    'MTCISAPERMANENTROLEASSIGNMENT',
    'MTCISAREQUIREACTIVATIONAPPROVAL',
    'MTCISAUNMANAGEDROLEASSIGNMENTS',
    'PRIVILEGEDASSIGNMENTS',
    'XSPMPRIVILEGEDIDENTITIES',
    'MTCISCLOUDADMIN',
    'INSPECTPARTNERSUPPORT',
], 'Microsoft Entra admin center', 'Identity governance > Privileged Identity Management');

$assignRoute([
    'MTSECURITYGROUPCREATIONRESTRICTED',
    'MTTENANTCREATIONRESTRICTED',
    'MTCISCREATETENANTDISALLOWED',
    'GROUPS',
    'MTCIS365PUBLICGROUP',
    'MTCISENSUREGUESTUSERDYNAMICGROUP',
], 'Microsoft Entra admin center', 'Identity > Groups > All groups, then open the relevant group or group setting');

$assignRoute([
    'MTCISACROSSTENANTINBOUNDDEFAULT',
    'MTCISENSUREGUESTACCESSRESTRICTED',
    'MTCISAGUESTINVITATION',
    'MTCISAGUESTUSERACCESS',
], 'Microsoft Entra admin center', 'Identity > External Identities > External collaboration settings or Cross-tenant access settings');

$assignRoute([
    'MTENTITLEMENTMANAGEMENTDELETEDGROUPS',
    'MTENTITLEMENTMANAGEMENTINACTIVEPOLICIES',
    'MTENTITLEMENTMANAGEMENTORPHANEDRESOURCES',
    'MTENTITLEMENTMANAGEMENTVALIDAPPROVERS',
    'MTENTITLEMENTMANAGEMENTVALIDRESOURCEROLES',
], 'Microsoft Entra admin center', 'Identity governance > Entitlement management');

$assignRoute([
    'MTMDEANTIVIRUSPOLICY',
    'MTMDIHEALTHISSUES',
    'XSPMCRITICALASSETMANAGEMENT',
    'XSPMDEVICES',
], 'Microsoft Defender portal', 'Use the portal search to open the policy, health, asset, or device page named by this control');

$assignRoute([
    'MTENTRADEVICEREGISTRATIONPOLICY',
    'MTINTUNECONNECTORHEALTH',
    'MTINTUNEPLATFORM',
    'MTCISDEVICESWITHOUTCOMPLIANCEPOLICYMARKED',
    'MTCISAMANAGEDDEVICE',
    'MTCISAMANAGEDDEVICEREGISTRATION',
], 'Microsoft Intune admin center', 'Devices, then open Enrollment, Compliance policies, or Connector status as appropriate');

$assignRoute([
    'MTCISSPOB2BINTEGRATION',
    'MTCISSPODEFAULTSHARINGLINK',
    'MTCISSPODEFAULTSHARINGLINKPERMISSION',
    'MTCISSPOGUESTACCESSEXPIRY',
    'MTCISSPOGUESTCANNOTSHAREUNOWNEDITEM',
    'MTCISSPOPREVENTDOWNLOADMALICIOUSFILE',
    'MTCISASPOSHARING',
    'MTCISASPOSHARINGALLOWEDDOMAIN',
    'INSPECTOUTGOINGSHARINGMONITORED',
    'INSPECTSHAREPOINTLEGACYAUTHENABLED',
], 'SharePoint admin center', 'Policies > Sharing, Access control, or Settings as appropriate');

$assignRoute([
    'APPMANAGEMENTPOLICIES',
    'EIDSCA.GENERATED',
    'ENTRARECOMMENDATIONS',
], 'Microsoft Entra admin center', 'Use the portal search to open the policy, baseline, or recommendation named by this control');

$assignRoute([
    'MTENTRAIDCONNECT',
    'MTONPREMISESSYNCHRONIZATION',
], 'Microsoft Entra admin center', 'Identity > Hybrid management > Microsoft Entra Connect');

$assignRoute([
    'MTCISTHIRDPARTYSTORAGESERVICESRESTRICTED',
    'MTCISFORMSPHISHINGPROTECTIONENABLED',
], 'Microsoft 365 admin center', 'Settings > Org settings, then open the service named by this control');

$assignRoute([
    'MTCISCUSTOMERLOCKBOX',
    'MTCISADIAGNOSTICSETTINGS',
    'MTCISAASSIGNMENTNOTIFICATION',
    'INSPECTOFFICEMESSAGEENCRYPTION',
    'INSPECTEDISCOVERYADMINS',
], 'Microsoft Purview portal', 'Use the portal search to open the audit, role, encryption, or governance setting named by this control');

$assignRoute([
    'INSPECTLARGEATTACHMENTBLOCKINGRULE',
    'INSPECTSIMPHISH',
], 'Exchange admin center', 'Mail flow > Rules');

$assignRoute([
    'INSPECTAZPSASSIGNMENT',
    'INSPECTAZPSMODULES',
    'INSPECTMSOLPOWERSHELL',
], 'Microsoft Entra admin center', 'Identity > Applications > Enterprise applications');

$assignRoute([
    'INSPECTDOMAINEXPIRATION',
], 'Microsoft 365 admin center', 'Settings > Domains');

$powerShellSteps = [
    'INSPECTEXOFULLACCESS' => [
        ['method' => 'PowerShell', 'instruction' => 'Connect to Exchange Online PowerShell with an account permitted to manage recipients.'],
        ['method' => 'PowerShell', 'instruction' => 'Review delegates with Get-MailboxPermission -Identity <mailbox> and confirm each non-inherited FullAccess assignment has an approved owner and purpose.'],
        ['method' => 'PowerShell', 'instruction' => 'Remove an unapproved delegate with Remove-MailboxPermission -Identity <mailbox> -User <delegate> -AccessRights FullAccess -Confirm:$false.'],
        ['method' => 'Verification', 'instruction' => 'Run Get-MailboxPermission again, allow the change to propagate, and rerun the SecureIT control.'],
    ],
    'INSPECTEXOHIDDENMAILBOXES' => [
        ['method' => 'PowerShell', 'instruction' => 'Connect to Exchange Online PowerShell and list affected recipients with Get-Mailbox -ResultSize Unlimited | Where-Object HiddenFromAddressListsEnabled -eq $true.'],
        ['method' => 'Review', 'instruction' => 'Confirm which hidden mailboxes are approved service, discovery, or system mailboxes and document those exceptions.'],
        ['method' => 'PowerShell', 'instruction' => 'For an unapproved hidden mailbox, run Set-Mailbox -Identity <mailbox> -HiddenFromAddressListsEnabled $false.'],
        ['method' => 'Verification', 'instruction' => 'Query the property again after propagation and rerun the SecureIT control.'],
    ],
    'INSPECTEXOSENDASPERMISSIONS' => [
        ['method' => 'PowerShell', 'instruction' => 'Connect to Exchange Online PowerShell and review SendAs grants with Get-RecipientPermission -Identity <mailbox>.'],
        ['method' => 'Review', 'instruction' => 'Confirm every delegate is approved, still requires the access, and is assigned to the correct mailbox.'],
        ['method' => 'PowerShell', 'instruction' => 'Remove an unapproved grant with Remove-RecipientPermission -Identity <mailbox> -Trustee <delegate> -AccessRights SendAs -Confirm:$false.'],
        ['method' => 'Verification', 'instruction' => 'Run Get-RecipientPermission again and rerun the SecureIT control.'],
    ],
    'INSPECTEXOSENDONBEHALFOF' => [
        ['method' => 'PowerShell', 'instruction' => 'Connect to Exchange Online PowerShell and inspect GrantSendOnBehalfTo with Get-Mailbox -Identity <mailbox> | Select-Object -ExpandProperty GrantSendOnBehalfTo.'],
        ['method' => 'Review', 'instruction' => 'Confirm every delegate is approved and still needs to send on behalf of the mailbox.'],
        ['method' => 'PowerShell', 'instruction' => 'Remove an unapproved delegate with Set-Mailbox -Identity <mailbox> -GrantSendOnBehalfTo @{Remove="<delegate>"}.'],
        ['method' => 'Verification', 'instruction' => 'Query GrantSendOnBehalfTo again and rerun the SecureIT control.'],
    ],
    'INSPECTMAILBOXESWITHINTERNALFORWARDING' => [
        ['method' => 'PowerShell', 'instruction' => 'Connect to Exchange Online PowerShell and review forwarding with Get-Mailbox -ResultSize Unlimited | Select-Object DisplayName,ForwardingAddress,ForwardingSmtpAddress,DeliverToMailboxAndForward.'],
        ['method' => 'Review', 'instruction' => 'Confirm each forwarding destination is approved, owned, and required for an active business process.'],
        ['method' => 'PowerShell', 'instruction' => 'Remove unapproved forwarding with Set-Mailbox -Identity <mailbox> -ForwardingAddress $null -ForwardingSmtpAddress $null -DeliverToMailboxAndForward $false.'],
        ['method' => 'Verification', 'instruction' => 'Query the forwarding properties again and rerun the SecureIT control.'],
    ],
];

return [
    'areaDefaults' => [
        'Identity & Access Management' => ['method' => 'GUI', 'portal' => 'Microsoft Entra admin center', 'path' => 'Use the portal search to open the setting named by this control'],
        'Email & Calendaring' => ['method' => 'GUI', 'portal' => 'Exchange admin center', 'path' => 'Use the portal search to open the recipient, policy, or mail-flow setting named by this control'],
        'Collaboration & Communication' => ['method' => 'GUI', 'portal' => 'Microsoft 365 admin center', 'path' => 'Use the portal search to open the collaboration or external-access setting named by this control'],
        'Files, Intranet & Content Management' => ['method' => 'GUI', 'portal' => 'SharePoint admin center', 'path' => 'Use the portal search to open the sharing or access-control setting named by this control'],
        'Endpoint & Device Management' => ['method' => 'GUI', 'portal' => 'Microsoft Intune admin center', 'path' => 'Use the portal search to open the device, enrollment, or compliance setting named by this control'],
        'Security Operations & Threat Protection' => ['method' => 'GUI', 'portal' => 'Microsoft Defender portal', 'path' => 'Use the portal search to open the security setting or inventory named by this control'],
        'Compliance, Governance & Data Protection' => ['method' => 'GUI', 'portal' => 'Microsoft Purview portal', 'path' => 'Use the portal search to open the compliance or governance setting named by this control'],
    ],
    'controlRoutes' => $routes,
    'controlSteps' => $powerShellSteps,
];
