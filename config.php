<?php
/**
 * Active Directory Configuration
 */

// Active Directory Connection Settings
define('AD_SERVER', 'dc.internal.net');        // AD server hostname or IP
define('AD_PORT', 389);                                    // LDAP port (389 for non-SSL, 636 for SSL)
define('AD_USER', 'CN=ldap,CN=Users,DC=internal,DC=net');              // Service account for AD queries
define('AD_PASSWORD', 'SuperSecret!!');                    // Service account password
define('AD_SEARCH_BASE', 'dc=internal,dc=net');            // LDAP search base
define('AD_USE_SSL', false);         			   // Set to true for LDAPS

// DNS Lookup Configuration
define('DNS_TIMEOUT', 2);                                  // DNS query timeout in seconds
define('DNS_DOMAIN', 'internal.net');                      // Domain to append to hostnames

// Web Server Settings
define('APP_NAME', 'Active Directory Monitoring');
define('APP_VERSION', '1.0.0');
define('REFRESH_INTERVAL', 300000);                        // 5 minutes in milliseconds

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
session_start();

// Timezone
date_default_timezone_set('America/Chicago');
?>
