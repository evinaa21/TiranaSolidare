<?php
/**
 * Centralised English→Albanian label mapping.
 *
 * Database stores English values; call status_label() to get
 * the Albanian display string for the UI.
 */

const STATUS_LABELS = [
    // Application / help-request-application status
    'pending'     => 'Në pritje',
    'approved'    => 'Pranuar',
    'rejected'    => 'Refuzuar',

    // Attendance
    'present'     => 'Prezent',
    'absent'      => 'Munguar',

    // Account status
    'active'      => 'Aktiv',
    'blocked'     => 'Bllokuar',
    'deactivated' => 'Çaktivizuar',

    // Roles
    'admin'       => 'Admin',
    'super_admin' => 'Super Admin',
    'volunteer'   => 'Vullnetar',

    // Help-request type
    'request'     => 'Kërkesë',
    'offer'       => 'Ofertë',

    // Help-request status
    'open'    => 'Hapur',
    'closed'  => 'Mbyllur',

    // Event status (already English — labels for display)
    'active_event' => 'Aktiv',
    'completed'    => 'Përfunduar',
    'cancelled'    => 'Anuluar',
];

/**
 * Return the Albanian display label for an English DB value.
 * Falls back to the value itself (ucfirst) if no mapping exists.
 */
function status_label(string $value): string
{
    return STATUS_LABELS[strtolower($value)] ?? ucfirst($value);
}
