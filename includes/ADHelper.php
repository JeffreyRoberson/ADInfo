<?php
/**
 * Active Directory Helper Class
 * Handles all LDAP queries and data processing
 */

class ADHelper {
    private $connection;
    private $logger;
    private $pingHelper;
    private $maxPasswordAge = 0;
    private $minPasswordLength = 0;
    private $passwordHistory = 0;
    private $lockoutDuration = 0;
    private $lockoutThreshold = 0;

    public function __construct() {
        $this->logger = new Logger();
        $this->pingHelper = new PingHelper($this->logger);
        $this->loadPasswordPolicy();
    }

    /**
     * Load password policy from Active Directory
     */
    private function loadPasswordPolicy() {
        try {
            if (!$this->connect()) {
                $this->logger->warning('Could not connect to AD to get password policy');
                return;
            }

            // Query the domain policy
            $search = @ldap_search(
                $this->connection, 
                AD_SEARCH_BASE, 
                '(objectClass=domainDNS)', 
                ['maxPwdAge', 'minPwdLength', 'pwdHistoryLength', 'lockoutDuration', 'lockoutThreshold']
            );

            if ($search) {
                $entries = ldap_get_entries($this->connection, $search);
                
                if ($entries['count'] > 0) {
                    // maxPwdAge - stored as negative large integer (100-nanosecond intervals)
                    if (isset($entries[0]['maxpwdage'][0])) {
                        $maxPwdAge = $entries[0]['maxpwdage'][0];
                        $this->maxPasswordAge = abs(intval($maxPwdAge)) / (10000000 * 86400);
                    }

                    // minPwdLength - minimum password length
                    if (isset($entries[0]['minpwdlength'][0])) {
                        $this->minPasswordLength = intval($entries[0]['minpwdlength'][0]);
                    }

                    // pwdHistoryLength - number of previous passwords to remember
                    if (isset($entries[0]['pwdhistorylength'][0])) {
                        $this->passwordHistory = intval($entries[0]['pwdhistorylength'][0]);
                    }

                    // lockoutDuration - how long account is locked (100-nanosecond intervals)
                    if (isset($entries[0]['lockoutduration'][0])) {
                        $lockoutDuration = $entries[0]['lockoutduration'][0];
                        $this->lockoutDuration = abs(intval($lockoutDuration)) / (10000000 * 60); // Convert to minutes
                    }

                    // lockoutThreshold - number of failed attempts before lockout
                    if (isset($entries[0]['lockoutthreshold'][0])) {
                        $this->lockoutThreshold = intval($entries[0]['lockoutthreshold'][0]);
                    }

                    $this->logger->info('Loaded password policy - Age: ' . $this->maxPasswordAge . ' days, Length: ' . $this->minPasswordLength);
                }
            }

            $this->disconnect();
        } catch (Exception $e) {
            $this->logger->warning('Error loading password policy: ' . $e->getMessage());
        }
    }

    /**
     * Get password policy as array
     */
    public function getPasswordPolicy() {
        return [
            'maxPasswordAge' => $this->maxPasswordAge,
            'minPasswordLength' => $this->minPasswordLength,
            'passwordHistory' => $this->passwordHistory,
            'lockoutDuration' => $this->lockoutDuration,
            'lockoutThreshold' => $this->lockoutThreshold
        ];
    }

    /**
     * Get formatted password policy message
     */
    public function getPasswordPolicyMessage() {
        $messages = [];

        if ($this->maxPasswordAge > 0) {
            $messages[] = 'Passwords expire after ' . intval($this->maxPasswordAge) . ' days';
        } else {
            $messages[] = 'Passwords never expire';
        }

        if ($this->minPasswordLength > 0) {
            $messages[] = 'Minimum password length: ' . $this->minPasswordLength . ' characters';
        }

        if ($this->passwordHistory > 0) {
            $messages[] = 'Password history: ' . $this->passwordHistory . ' previous passwords remembered';
        }

        if ($this->lockoutThreshold > 0) {
            $messages[] = 'Account lockout after ' . $this->lockoutThreshold . ' failed attempts';
        }

        if ($this->lockoutDuration > 0) {
            $messages[] = 'Lockout duration: ' . intval($this->lockoutDuration) . ' minutes';
        }

        return !empty($messages) ? implode(' • ', $messages) : 'No password policy configured';
    }

    /**
     * Check if user account has password never expire flag
     */
    private function hasPasswordNeverExpires($userAccountControl) {
        if (!$userAccountControl) {
            return false;
        }

        // Handle both array and direct value
        $uac = is_array($userAccountControl) ? intval($userAccountControl[0]) : intval($userAccountControl);
        // Check DONT_EXPIRE_PASSWORD flag (bit 16 = 0x10000)
        return ($uac & 65536) == 65536;
    }

    /**
     * Calculate password expiration date
     */
    private function getPasswordExpirationDate($pwdLastSetDateTime, $neverExpires) {
        // If password never expires, return null
        if ($neverExpires) {
            return null;
        }

        // If password was never set, return null
        if (!$pwdLastSetDateTime) {
            return null;
        }

        // If we don't have password policy, we can't calculate expiration
        if ($this->maxPasswordAge <= 0) {
            return null;
        }

        // Calculate expiration date
        $expirationDate = clone $pwdLastSetDateTime;
        $expirationDate->add(new DateInterval('P' . intval($this->maxPasswordAge) . 'D'));

        return $expirationDate;
    }

    /**
     * Get days until password expires
     */
    private function getDaysUntilExpiration($expirationDate) {
        if (!$expirationDate) {
            return null;
        }

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $interval = $now->diff($expirationDate);
        
        // If expired, return negative
        if ($now > $expirationDate) {
            return -($interval->days + ($interval->s / 86400));
        }

        return $interval->days + ($interval->s / 86400);
    }

    /**
     * Get formatted expiration message
     */
    private function getExpirationMessage($neverExpires, $daysUntilExpiration) {
        if ($neverExpires) {
            return 'Never Expires';
        }

        if ($daysUntilExpiration === null) {
            return 'Unknown';
        }

        $days = intval($daysUntilExpiration);

        if ($days < 0) {
            return 'Expired ' . abs($days) . ' days ago';
        }

        if ($days === 0) {
            return 'Expires Today';
        }

        if ($days === 1) {
            return 'Expires Tomorrow';
        }

        return 'Expires in ' . $days . ' days';
    }

    /**
     * Establish connection to Active Directory
     */
    private function connect() {
        try {
            $protocol = AD_USE_SSL ? 'ldaps://' : 'ldap://';
            $server = $protocol . AD_SERVER . ':' . AD_PORT;

            $connection = ldap_connect($server);

            if (!$connection) {
                throw new Exception('Failed to connect to AD server: ' . AD_SERVER);
            }

            ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 5);
            ldap_set_option($connection, LDAP_OPT_DEREF, LDAP_DEREF_NEVER);

            if (AD_USE_SSL) {
                ldap_set_option($connection, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
            }

            $bind = @ldap_bind($connection, AD_USER, AD_PASSWORD);

            if (!$bind) {
                $error = ldap_error($connection);
                throw new Exception('Failed to bind to AD: ' . $error);
            }

            $this->connection = $connection;
            return true;
        } catch (Exception $e) {
            $this->logger->error('AD Connection Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Close LDAP connection
     */
    private function disconnect() {
        if ($this->connection) {
            @ldap_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Convert Windows FILETIME to PHP DateTime
     */
    private function convertWindowsTimestamp($windowsTimestamp) {
        if (!$windowsTimestamp || $windowsTimestamp == 0) {
            return null;
        }

        try {
            $timestamp = (intval($windowsTimestamp) / 10000000) - 11644473600;
            return new DateTime('@' . $timestamp, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Calculate time delta in days
     */
    private function getTimeDeltaDays($dt) {
        if (!$dt) {
            return 0;
        }

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $interval = $now->diff($dt);
        return $interval->days + ($interval->s / 86400);
    }

    /**
     * Get formatted time delta string
     */
    private function getTimeDeltaString($dt) {
        if (!$dt) {
            return 'Never';
        }

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $interval = $now->diff($dt);

        $days = $interval->days;
        $hours = $interval->h;
        $minutes = $interval->i;

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m ago";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m ago";
        } else {
            return "{$minutes}m ago";
        }
    }

    /**
     * Check if user account is disabled
     */
    private function isAccountDisabled($userAccountControl) {
        if (!$userAccountControl) {
            return false;
        }

        // Handle both array and direct value
        $uac = is_array($userAccountControl) ? intval($userAccountControl[0]) : intval($userAccountControl);
        // Check ACCOUNTDISABLE flag (bit 1 = 0x0002)
        return ($uac & 2) == 2;
    }

    /**
     * Get user objects from Active Directory
     */
    public function getUsers() {
        try {
            if (!$this->connect()) {
                return [];
            }

            $searchFilter = '(&(objectClass=user)(!(objectClass=computer)))';
            $searchAttributes = ['sAMAccountName', 'pwdLastSet', 'displayName', 'userAccountControl', 'lastLogonTimestamp'];

            $search = @ldap_search($this->connection, AD_SEARCH_BASE, $searchFilter, $searchAttributes);

            if (!$search) {
                throw new Exception('LDAP search failed: ' . ldap_error($this->connection));
            }

            $entries = ldap_get_entries($this->connection, $search);

            if ($entries['count'] == 0) {
                $this->logger->warning('No users found in AD');
                $this->disconnect();
                return [];
            }

            $users = [];

            for ($i = 0; $i < $entries['count']; $i++) {
                $entry = $entries[$i];

                $accountName = isset($entry['samaccountname'][0]) ? $entry['samaccountname'][0] : 'Unknown';
                $displayName = isset($entry['displayname'][0]) ? $entry['displayname'][0] : '';
                $pwdLastSet = isset($entry['pwdlastset'][0]) ? $entry['pwdlastset'][0] : 0;
                $lastLogonTimestamp = isset($entry['lastlogontimestamp'][0]) ? $entry['lastlogontimestamp'][0] : 0;
                $userAccountControl = isset($entry['useraccountcontrol'][0]) ? $entry['useraccountcontrol'][0] : null;

                $pwdDateTime = $this->convertWindowsTimestamp($pwdLastSet);
                $lastLogonDateTime = $this->convertWindowsTimestamp($lastLogonTimestamp);
                $isDisabled = $this->isAccountDisabled($userAccountControl);
                $neverExpires = $this->hasPasswordNeverExpires($userAccountControl);
                $expirationDate = $this->getPasswordExpirationDate($pwdDateTime, $neverExpires);
                $daysUntilExpiration = $this->getDaysUntilExpiration($expirationDate);

                $users[] = [
                    'account_name' => $accountName,
                    'display_name' => $displayName,
                    'pwd_last_set' => $pwdDateTime ? $pwdDateTime->format('Y-m-d H:i:s') : 'Never',
                    'time_delta' => $this->getTimeDeltaString($pwdDateTime),
                    'time_delta_raw' => intval($this->getTimeDeltaDays($pwdDateTime)),
                    'time_delta_days' => intval($this->getTimeDeltaDays($pwdDateTime)),
                    'last_logon' => $lastLogonDateTime ? $lastLogonDateTime->format('Y-m-d H:i:s') : 'Never',
                    'last_logon_delta' => $this->getTimeDeltaString($lastLogonDateTime),
                    'last_logon_raw' => intval($this->getTimeDeltaDays($lastLogonDateTime)),
                    'is_disabled' => $isDisabled,
                    'never_expires' => $neverExpires,
                    'expiration_date' => $expirationDate ? $expirationDate->format('Y-m-d H:i:s') : 'N/A',
                    'days_until_expiration' => $daysUntilExpiration,
                    'expiration_message' => $this->getExpirationMessage($neverExpires, $daysUntilExpiration)
                ];
            }

            $this->disconnect();

            usort($users, function($a, $b) {
                return strcasecmp($a['account_name'], $b['account_name']);
            });

            return $users;
        } catch (Exception $e) {
            $this->logger->error('Error querying users: ' . $e->getMessage());
            $this->disconnect();
            return [];
        }
    }

    /**
     * Get computer objects from Active Directory
     */
    public function getComputers() {
        try {
            if (!$this->connect()) {
                return [];
            }

            $searchFilter = '(objectClass=computer)';
            $searchAttributes = ['sAMAccountName', 'lastLogonTimestamp', 'operatingSystem'];

            $search = @ldap_search($this->connection, AD_SEARCH_BASE, $searchFilter, $searchAttributes);

            if (!$search) {
                throw new Exception('LDAP search failed: ' . ldap_error($this->connection));
            }

            $entries = ldap_get_entries($this->connection, $search);

            if ($entries['count'] == 0) {
                $this->logger->warning('No computers found in AD');
                $this->disconnect();
                return [];
            }

            $computers = [];

            for ($i = 0; $i < $entries['count']; $i++) {
                $entry = $entries[$i];

                $computerName = isset($entry['samaccountname'][0]) ? rtrim($entry['samaccountname'][0], '$') : 'Unknown';
                $os = isset($entry['operatingsystem'][0]) ? $entry['operatingsystem'][0] : 'Unknown';
                $lastLogon = isset($entry['lastlogontimestamp'][0]) ? $entry['lastlogontimestamp'][0] : 0;

                $lastLogonDateTime = $this->convertWindowsTimestamp($lastLogon);

                $computers[] = [
                    'computer_name' => $computerName,
                    'os' => $os,
                    'last_logon' => $lastLogonDateTime ? $lastLogonDateTime->format('Y-m-d H:i:s') : 'Never',
                    'time_delta' => $this->getTimeDeltaString($lastLogonDateTime),
                    'time_delta_raw' => intval($this->getTimeDeltaDays($lastLogonDateTime)),
                    'time_delta_days' => intval($this->getTimeDeltaDays($lastLogonDateTime))
                ];
            }

            $this->disconnect();

            usort($computers, function($a, $b) {
                return strcasecmp($a['computer_name'], $b['computer_name']);
            });

            $this->logger->info('Loaded ' . count($computers) . ' computers from AD');
            return $computers;
        } catch (Exception $e) {
            $this->logger->error('Error querying computers: ' . $e->getMessage());
            $this->disconnect();
            return [];
        }
    }
}
?>
