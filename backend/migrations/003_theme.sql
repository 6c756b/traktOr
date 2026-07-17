-- Dark/light mode preference. NULL means "follow the OS setting" (the CSS
-- @media (prefers-color-scheme: dark) default) -- only set once the user actually
-- flips the switch in Settings.
ALTER TABLE app_settings ADD COLUMN theme ENUM('light', 'dark') NULL DEFAULT NULL AFTER language;
