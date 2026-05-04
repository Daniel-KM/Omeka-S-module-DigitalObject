-- Uninstallation is blocked in Module::preUninstall while digital_object rows
-- remain (would leave orphan files on disk). When this script runs, the table
-- is guaranteed empty.
DROP TABLE IF EXISTS digital_object;
