<?php

function envBool(array $keys, bool $default = false): bool {
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }
    }
    return $default;
}

define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: (SMTP_USERNAME ?: 'no-reply@queuecare.local'));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'QueueCare');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
define('DEBUG_OTP', envBool(['DEBUG_OTP', 'APP_DEBUG_OTP'], false));
