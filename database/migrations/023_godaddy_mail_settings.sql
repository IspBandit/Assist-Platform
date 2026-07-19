-- Configure outgoing email (SMTP) for the GoDaddy-hosted mailbox. Set the mailbox
-- password in Admin -> Settings -> Outgoing email after running this migration.
INSERT INTO site_settings (setting_key, setting_value, setting_group, value_type, updated_at) VALUES
    ('mail_host',         'sg2plzcpnl509286.prod.sin2.secureserver.net', 'email', 'string', NOW()),
    ('mail_port',         '465',                                         'email', 'string', NOW()),
    ('mail_encryption',   'ssl',                                         'email', 'string', NOW()),
    ('mail_username',     'vanassist@condrendigital.com.au',             'email', 'string', NOW()),
    ('mail_from_address', 'vanassist@condrendigital.com.au',             'email', 'string', NOW()),
    ('mail_from_name',    'VanAssist',                                   'email', 'string', NOW())
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    setting_group = VALUES(setting_group),
    updated_at    = NOW();
