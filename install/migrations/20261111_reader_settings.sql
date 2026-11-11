INSERT INTO settings (type, datakey, datavalue, description)
SELECT 'reader', defaults.datakey, defaults.datavalue, defaults.description
FROM (
    SELECT 'download_timeout_seconds' AS datakey, '15' AS datavalue, 'Download timeout per HTTP request in seconds' AS description
    UNION ALL SELECT 'startup_timeout_seconds', '30', 'Reader readiness timeout in seconds'
    UNION ALL SELECT 'timeout_seconds', '300', 'Reader completion timeout in seconds'
    UNION ALL SELECT 'max_tokens', '4096', 'Maximum output tokens'
    UNION ALL SELECT 'download_max_bytes', '4194304', 'Maximum decompressed HTML download in bytes'
    UNION ALL SELECT 'input_max_bytes', '48000', 'Maximum cleaned HTML input in bytes'
) AS defaults
WHERE NOT EXISTS (
    SELECT 1 FROM settings AS existing
    WHERE existing.type = 'reader' AND existing.datakey = defaults.datakey
);
