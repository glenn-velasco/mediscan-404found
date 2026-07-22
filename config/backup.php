<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup encryption recipient
    |--------------------------------------------------------------------------
    |
    | GPG key ID/fingerprint/email the nightly database backup is encrypted
    | against. The public key must be imported into the container's GPG
    | keyring (see docs/BACKUPS.md) - the matching private key never touches
    | this server. Leave unset to skip backups entirely (e.g. staging).
    |
    */
    'gpg_recipient' => env('BACKUP_GPG_RECIPIENT'),

    /*
    |--------------------------------------------------------------------------
    | Backup storage disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk (see config/filesystems.php) encrypted backups are
    | uploaded to. Deliberately a distinct disk/bucket from the app's normal
    | `s3` disk (avatars, ID photos, etc.) - see docs/BACKUPS.md for why.
    |
    */
    'disk' => env('BACKUP_DISK', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Retention window
    |--------------------------------------------------------------------------
    |
    | Backups older than this many days are deleted on each run.
    |
    */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

];
