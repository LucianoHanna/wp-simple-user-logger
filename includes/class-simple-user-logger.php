<?php
/**
 * WP Simple User Logger
 *
 * @package           WP_Simple_User_Logger
 * @author            Luciano Hanna
 * @copyright         2024 Luciano Hanna
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Main plugin class responsible for logging user activities
 *
 * @since 1.0.0
 */
class SimpleUserLogger {
    /**
     * Path to the log file
     *
     * @since 1.0.0
     * @var string
     */
    private $log_file;
    
    /**
     * Initialize the logger and set up WordPress hooks
     *
     * @since 1.0.0
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = wp_normalize_path($upload_dir['basedir'] . '/user-activities.log');
        
        add_action('wp_login', array($this, 'log_successful_login'), 10, 2);
        add_action('wp_login_failed', array($this, 'log_failed_login'));
        add_action('user_register', array($this, 'log_user_creation'));
        add_action('profile_update', array($this, 'log_user_update'), 10, 2);
        add_action('deleted_user', array($this, 'log_user_deletion'), 10, 2);
        add_action('retrieve_password', array($this, 'log_password_reset'));
        add_action('password_reset', array($this, 'log_password_reset_successful'), 10, 2);
        add_action('set_user_role', array($this, 'log_role_change'), 10, 3);
    }

    /**
     * Sanitize data for log entry
     *
     * @since 1.0.0
     * @param mixed $data The data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_data($data) {
        if (is_array($data)) {
            return array_map(array($this, 'sanitize_data'), $data);
        }
        
        return sanitize_text_field($data);
    }

    /**
     * Write an entry to the log file
     *
     * @since 1.0.0
     * @param string $action The action being logged
     * @param array  $data   Additional data to log
     */
    private function write_log($action, $data) {
        $action = $this->sanitize_data($action);
        $data = $this->sanitize_data($data);
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $request_uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        
        // Build details string from data array
        $details = [];
        foreach ($data as $key => $value) {
            $details[] = sprintf('%s: %s', $key, $value);
        }
        
        $log_message = sprintf(
            "[%s] IP: %s - URI: %s - User-Agent: %s - Action: %s - %s\n",
            $timestamp,
            $ip,
            $request_uri,
            $user_agent,
            $action,
            implode(' - ', $details)
        );
        
        error_log($log_message, 3, $this->log_file);
    }
    
    /**
     * Log successful login attempts
     *
     * @since 1.0.0
     * @param string  $user_login Username
     * @param WP_User $user       User object
     */
    public function log_successful_login($user_login, $user) {
        $this->write_log('LOGIN_SUCCESS', [
            'User' => $user_login,
            'ID' => $user->ID,
            'Display Name' => $user->display_name,
            'Email' => $user->user_email,
            'Roles' => !empty($user->roles) ? implode(', ', $user->roles) : 'none'
        ]);
    }
    
    /**
     * Log failed login attempts
     *
     * @since 1.0.0
     * @param string $username Username
     */
    public function log_failed_login($username) {
        $this->write_log('LOGIN_FAILED', [
            'Username' => $username
        ]);
    }
    
    /**
     * Log user creation
     *
     * @since 1.0.0
     * @param int $user_id ID of the newly created user
     */
    public function log_user_creation($user_id) {
        $user = get_userdata($user_id);
        $creator_id = get_current_user_id();
        $creator = $creator_id ? get_userdata($creator_id) : null;
        
        $this->write_log('USER_CREATED', [
            'ID' => $user_id,
            'Login' => $user->user_login,
            'Email' => $user->user_email,
            'Roles' => implode(', ', $user->roles),
            'Created By' => $creator ? $creator->user_login : 'Unknown',
            'Creator ID' => $creator_id ? $creator_id : 0
        ]);
    }
    
    /**
     * Log user profile updates
     *
     * @since 1.0.0
     * @param int     $user_id       User ID
     * @param WP_User $old_user_data Object containing user's data prior to update
     */
    public function log_user_update($user_id, $old_user_data) {
        $user = get_userdata($user_id);
        $editor_id = get_current_user_id();
        $editor = $editor_id ? get_userdata($editor_id) : null;
        
        $changes = [];
        if ($old_user_data->user_email !== $user->user_email) {
            $changes[] = sprintf('%s => %s', $old_user_data->user_email, $user->user_email);
        }
        if ($old_user_data->display_name !== $user->display_name) {
            $changes[] = sprintf('%s => %s', $old_user_data->display_name, $user->display_name);
        }
        
        $this->write_log('USER_UPDATED', [
            'ID' => $user_id,
            'Login' => $user->user_login,
            'Changes' => !empty($changes) ? implode(', ', $changes) : 'Profile data updated',
            'Updated By' => $editor ? $editor->user_login : 'Unknown',
            'Editor ID' => $editor_id ? $editor_id : 0
        ]);
    }
    
    /**
     * Log user deletion
     *
     * @since 1.0.0
     * @param int      $user_id  ID of the deleted user
     * @param int|null $reassign ID of the user to reassign posts to (null if no reassignment)
     */
    public function log_user_deletion($user_id, $reassign) {
        $deleter_id = get_current_user_id();
        $deleter = $deleter_id ? get_userdata($deleter_id) : null;
        
        $this->write_log('USER_DELETED', [
            'ID' => $user_id,
            'Reassigned To' => $reassign ? $reassign : 0,
            'Deleted By' => $deleter ? $deleter->user_login : 'Unknown',
            'Deleter ID' => $deleter_id ? $deleter_id : 0
        ]);
    }
    
    /**
     * Log password reset requests
     *
     * @since 1.0.0
     * @param string $user_login Username
     */
    public function log_password_reset($user_login) {
        $this->write_log('PASSWORD_RESET_REQUESTED', [
            'User' => $user_login
        ]);
    }
    
    /**
     * Log successful password resets
     *
     * @since 1.0.0
     * @param WP_User $user     User whose password was reset
     * @param string  $new_pass New password (not stored in logs)
     */
    public function log_password_reset_successful($user, $new_pass) {
        $this->write_log('PASSWORD_RESET_SUCCESS', [
            'User' => $user->user_login,
            'ID' => $user->ID
        ]);
    }
    
    /**
     * Log role changes
     *
     * @since 1.0.0
     * @param int      $user_id   The user ID
     * @param string   $new_role  The new role
     * @param string[] $old_roles An array of the user's previous roles
     */
    public function log_role_change($user_id, $new_role, $old_roles) {
        $user = get_userdata($user_id);
        $editor_id = get_current_user_id();
        $editor = $editor_id ? get_userdata($editor_id) : null;
        
        $this->write_log('ROLE_CHANGED', [
            'User' => $user->user_login,
            'ID' => $user_id,
            'Old Roles' => implode(', ', $old_roles),
            'New Role' => $new_role ? $new_role : 'none',
            'Changed By' => $editor ? $editor->user_login : 'Unknown',
            'Editor ID' => $editor_id ? $editor_id : 0
        ]);
    }
}