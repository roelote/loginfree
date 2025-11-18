<?php
/**
 * Plugin Name: Registro Avanzado con Gmail
 * Plugin URI: https://tudominio.com/
 * Description: Plugin que permite registro de usuarios con Gmail OAuth y email tradicional
 * Version: 1.0.0
 * Author: Tu Nombre
 * License: GPL v2 or later
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar sistema de traducciones
require_once plugin_dir_path(__FILE__) . 'includes/translations/class-translations.php';

class AdvancedRegistrationPlugin {
    
    private $google_client_id;
    private $google_client_secret;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_nopriv_gmail_register', array($this, 'handle_gmail_register'));
        add_action('wp_ajax_nopriv_email_register', array($this, 'handle_email_register'));
        add_action('wp_ajax_nopriv_email_login', array($this, 'handle_email_login'));
        add_action('wp_ajax_nopriv_forgot_password', array($this, 'handle_forgot_password'));
        add_action('wp_ajax_nopriv_check_username_availability', array($this, 'check_username_availability'));
        add_action('wp_ajax_nopriv_verify_email', array($this, 'handle_email_verification'));
        add_action('wp_ajax_nopriv_resend_verification', array($this, 'handle_resend_verification'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('init', array($this, 'handle_verification_url'));
        add_shortcode('advanced_registration_form', array($this, 'registration_form_shortcode'));
        add_shortcode('advanced_registration_form_comments', array($this, 'registration_form_comments_shortcode'));
        add_shortcode('debug_registration', array($this, 'debug_shortcode'));
        add_shortcode('email_verification_status', array($this, 'verification_status_shortcode'));
        
        // Configuración de Google OAuth (debes configurar estos valores)
        $this->google_client_id = get_option('arp_google_client_id', '');
        $this->google_client_secret = get_option('arp_google_client_secret', '');
    }
    
    public function init() {
        // Crear tabla personalizada si es necesaria
        $this->create_tables();
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        
        // Cargar la API de Google de forma más confiable
        //wp_enqueue_script('google-gapi', 'https://apis.google.com/js/api.js', array(), '1.0', true);
        //wp_enqueue_script('google-platform', 'https://apis.google.com/js/platform.js', array('google-gapi'), '1.0', true);

        wp_enqueue_script('google-gis', 'https://accounts.google.com/gsi/client', array(), null, true);
        
        // Encolar scripts personalizados con timestamp para evitar caché
        wp_enqueue_script('arp-script', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery', 'google-gis'), '2.0.' . time(), true);
        wp_enqueue_style('arp-style', plugin_dir_url(__FILE__) . 'assets/style.css', array(), '2.0.' . time());
        
        // Pasar datos a JavaScript
        wp_localize_script('arp-script', 'arp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('arp_nonce'),
            'google_client_id' => $this->google_client_id,
            'is_ssl' => is_ssl(),
            'site_url' => site_url(),
            'debug_mode' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'current_domain' => $_SERVER['HTTP_HOST'],
            'translations' => LoginFree_Translations::get_js_strings()
        ));
        
        // Debug: Verificar que el client_id no esté vacío
        if (empty($this->google_client_id)) {
            wp_add_inline_script('arp-script', 'console.log("WARNING: Google Client ID is empty. Go to Settings > Advanced Registration to configure it.");');
        } else {
            wp_add_inline_script('arp-script', 'console.log("Client ID configured: " + "' . substr($this->google_client_id, 0, 20) . '...");');
        }
        
        // Advertencia sobre HTTPS
        if (!is_ssl() && !empty($this->google_client_id)) {
            wp_add_inline_script('arp-script', 'console.warn("Google OAuth requires HTTPS. Current site is using HTTP.");');
        }
    }
    
    public function debug_shortcode() {
        ob_start();
        ?>
        <div style="background: #f0f0f0; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
            <h3>Debug Info</h3>
            <p><strong>Google Client ID:</strong> <?php echo esc_html($this->google_client_id ? 'Configurado ✅' : 'NO configurado ❌'); ?></p>
            <p><strong>Plugin Path:</strong> <?php echo plugin_dir_url(__FILE__); ?></p>
            <p><strong>CSS File:</strong> <a href="<?php echo plugin_dir_url(__FILE__) . 'assets/style.css'; ?>" target="_blank">Verificar CSS</a></p>
            <p><strong>JS File:</strong> <a href="<?php echo plugin_dir_url(__FILE__) . 'assets/script.js'; ?>" target="_blank">Verificar JS</a></p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function registration_form_shortcode($atts) {
        // Extraer atributos del shortcode
        $atts = shortcode_atts(array(
            'no_redirect' => 'false',
            'modal_mode' => 'false',
            'redirect_url' => ''
        ), $atts);
        
        ob_start();
        ?>
        <div id="arp-registration-container" 
             data-no-redirect="<?php echo esc_attr($atts['no_redirect']); ?>" 
             data-modal-mode="<?php echo esc_attr($atts['modal_mode']); ?>"
             data-redirect-url="<?php echo esc_attr($atts['redirect_url']); ?>">
            <div class="arp-tabs">
                <button class="arp-tab-button active" onclick="openTab(event, 'gmail-tab')"><?php echo lf_trans('tab_google'); ?></button>
                <button class="arp-tab-button" onclick="openTab(event, 'email-tab')"><?php echo lf_trans('tab_email'); ?></button>
            </div>
            
            <!-- Tab Gmail -->
            <div id="gmail-tab" class="arp-tab-content active">
                <h3><?php echo lf_trans('tab_google'); ?></h3>
                <div id="gmail-signin-button"></div>
                <div id="gmail-result"></div>
            </div>
            
            <!-- Tab Email -->
            <div id="email-tab" class="arp-tab-content">
                <!-- Modo Login (por defecto) -->
                <div id="arp-login-mode">
                    <h3><?php echo lf_trans('login_title'); ?></h3>
                    <p class="arp-help-text"><?php echo lf_trans('login_help'); ?></p>
                    <form id="email-login-form">
                        <div class="arp-form-group">
                            <label for="login_email"><?php echo lf_trans('label_email'); ?></label>
                            <input type="email" id="login_email" name="login_email" required placeholder="<?php echo lf_trans('placeholder_email'); ?>">
                        </div>
                        
                        <div class="arp-form-group">
                            <label for="login_password"><?php echo lf_trans('label_password'); ?></label>
                            <input type="password" id="login_password" name="login_password" required placeholder="<?php echo lf_trans('placeholder_password'); ?>">
                        </div>
                        
                        <button type="submit" class="arp-submit-btn"><?php echo lf_trans('btn_submit_login'); ?></button>
                    </form>
                    <div id="login-result"></div>
                    
                    <div style="text-align: center; margin-top: 10px;">
                        <button type="button" id="arp-switch-to-forgot" class="arp-forgot-password" style="background: none; border: none; color: #666; font-size: 13px; text-decoration: none; cursor: pointer;"><?php echo lf_trans('btn_forgot_password'); ?></button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <p style="color: #666; margin: 0 0 10px;"><?php echo lf_trans('not_registered'); ?></p>
                        <button type="button" id="arp-switch-to-register" class="arp-link-btn"><?php echo lf_trans('btn_create_account'); ?></button>
                    </div>
                </div>
                
                <!-- Modo Registro (oculto por defecto) -->
                <div id="arp-register-mode" style="display: none;">
                    <h3><?php echo lf_trans('register_title'); ?></h3>
                    <p class="arp-help-text"><?php echo lf_trans('register_help_verification'); ?></p>
                    <form id="email-registration-form">
                        <div class="arp-form-group">
                            <label for="register_name"><?php echo lf_trans('label_full_name'); ?></label>
                            <input type="text" id="register_name" name="register_name" required placeholder="<?php echo lf_trans('placeholder_full_name'); ?>">
                        </div>
                        
                        <div class="arp-form-group">
                            <label for="user_email"><?php echo lf_trans('label_email'); ?></label>
                            <input type="email" id="user_email" name="user_email" required placeholder="<?php echo lf_trans('placeholder_email'); ?>">
                        </div>
                        
                        <div class="arp-form-group">
                            <label for="user_password"><?php echo lf_trans('label_password'); ?></label>
                            <input type="password" id="user_password" name="user_password" required placeholder="<?php echo lf_trans('placeholder_password_min'); ?>" minlength="6">
                            <small class="arp-help-text"><?php echo lf_trans('help_password_min'); ?></small>
                        </div>
                        
                        <button type="submit" class="arp-submit-btn"><?php echo lf_trans('btn_submit_register_verification'); ?></button>
                    </form>
                    <div id="email-result"></div>
                    
                    <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <p style="color: #666; margin: 0 0 10px;"><?php echo lf_trans('already_registered'); ?></p>
                        <button type="button" id="arp-switch-to-login" class="arp-link-btn"><?php echo lf_trans('btn_login'); ?></button>
                    </div>
                </div>
                
                <!-- Modo Recuperar Contraseña (oculto por defecto) -->
                <div id="arp-forgot-mode" style="display: none;">
                    <h3><?php echo lf_trans('forgot_title'); ?></h3>
                    <p class="arp-help-text"><?php echo lf_trans('forgot_help_short'); ?></p>
                    <form id="email-forgot-form">
                        <div class="arp-form-group">
                            <label for="forgot_email"><?php echo lf_trans('label_email'); ?></label>
                            <input type="email" id="forgot_email" name="forgot_email" required placeholder="<?php echo lf_trans('placeholder_email'); ?>">
                        </div>
                        
                        <button type="submit" class="arp-submit-btn"><?php echo lf_trans('btn_submit_forgot'); ?></button>
                    </form>
                    <div id="forgot-result"></div>
                    
                    <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <p style="color: #666; margin: 0 0 10px;"><?php echo lf_trans('remembered_password'); ?></p>
                        <button type="button" id="arp-switch-to-login-from-forgot" class="arp-link-btn"><?php echo lf_trans('btn_login'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Shortcode específico para modal de comentarios (evita conflictos con el modal del header)
    public function registration_form_comments_shortcode($atts) {
        // Extraer atributos del shortcode
        $atts = shortcode_atts(array(
            'no_redirect' => 'true',
            'modal_mode' => 'true',
            'redirect_url' => ''
        ), $atts);
        
        ob_start();
        ?>
        <div id="arp-registration-container-comments" 
             data-no-redirect="<?php echo esc_attr($atts['no_redirect']); ?>" 
             data-modal-mode="<?php echo esc_attr($atts['modal_mode']); ?>"
             data-redirect-url="<?php echo esc_attr($atts['redirect_url']); ?>">
            <div class="arp-tabs">
                <button class="arp-tab-button active" onclick="openTab(event, 'gmail-tab-comments')"><?php echo lf_trans('tab_google'); ?></button>
                <button class="arp-tab-button" onclick="openTab(event, 'email-tab-comments')"><?php echo lf_trans('tab_email'); ?></button>
            </div>
            
            <!-- Tab Gmail -->
            <div id="gmail-tab-comments" class="arp-tab-content active">
                <h3><?php echo lf_trans('tab_google'); ?></h3>
                <div id="gmail-signin-button-comments"></div>
                <div id="gmail-result-comments"></div>
            </div>
            
            <!-- Tab Email -->
            <div id="email-tab-comments" class="arp-tab-content">
                <!-- Modo Login (por defecto) -->
                <div id="arp-login-mode-comments">
                    <h3><?php echo lf_trans('login_title'); ?></h3>
                    <p class="arp-help-text"><?php echo lf_trans('login_help'); ?></p>
                    <form id="email-login-form-comments" class="email-login-form">
                        <div class="arp-form-group">
                            <label for="login_email_comments"><?php echo lf_trans('label_email'); ?></label>
                            <input type="email" id="login_email_comments" name="login_email" required placeholder="<?php echo lf_trans('placeholder_email'); ?>">
                        </div>
                        
                        <div class="arp-form-group">
                            <label for="login_password_comments"><?php echo lf_trans('label_password'); ?></label>
                            <input type="password" id="login_password_comments" name="login_password" required placeholder="<?php echo lf_trans('placeholder_password'); ?>">
                        </div>
                        
                        <button type="submit" class="arp-submit-btn"><?php echo lf_trans('btn_submit_login'); ?></button>
                    </form>
                    <div id="login-result-comments"></div>
                    
                    <div style="text-align: center; margin-top: 10px;">
                        <button type="button" class="arp-switch-to-forgot arp-forgot-password" data-target="comments" style="background: none; border: none; color: #666; font-size: 13px; text-decoration: none; cursor: pointer;"><?php echo lf_trans('btn_forgot_password'); ?></button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <p style="color: #666; margin: 0 0 10px;"><?php echo lf_trans('not_registered'); ?></p>
                        <button type="button" class="arp-switch-to-register arp-link-btn" data-target="comments"><?php echo lf_trans('btn_create_account'); ?></button>
                    </div>
                </div>
                
                <!-- Modo Registro -->
                <div id="arp-register-mode-comments" style="display: none;">
                    <h3><?php echo lf_trans('register_title'); ?></h3>
                    <p class="arp-help-text"><?php echo lf_trans('register_help'); ?></p>
                    <form id="email-register-form-comments" class="email-register-form">
                        <div class="arp-form-group">
                            <label for="user_name_comments"><?php echo lf_trans('label_name'); ?></label>
                            <input type="text" id="user_name_comments" name="user_name" placeholder="<?php echo lf_trans('placeholder_name'); ?>">
                        </div>
                        
                        <div class="arp-form-group">
                            <label for="user_email_comments"><?php echo lf_trans('label_email'); ?></label>
                            <input type="email" id="user_email_comments" name="user_email" required placeholder="<?php echo lf_trans('placeholder_email'); ?>">
                        </div>
                        
                        <div class="arp-form-group">
                            <label for="user_password_comments"><?php echo lf_trans('label_password'); ?></label>
                            <input type="password" id="user_password_comments" name="user_password" required placeholder="<?php echo lf_trans('placeholder_password_min'); ?>">
                        </div>
                        
                        <button type="submit" class="arp-submit-btn"><?php echo lf_trans('btn_submit_register'); ?></button>
                    </form>
                    <div id="register-result-comments"></div>
                    
                    <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <p style="color: #666; margin: 0 0 10px;"><?php echo lf_trans('already_registered'); ?></p>
                        <button type="button" class="arp-switch-to-login arp-link-btn" data-target="comments"><?php echo lf_trans('btn_login'); ?></button>
                    </div>
                </div>
                
                <!-- Modo Recuperar contraseña -->
                <div id="arp-forgot-mode-comments" style="display: none;">
                    <h3><?php echo lf_trans('forgot_title'); ?></h3>
                    <p class="arp-help-text"><?php echo lf_trans('forgot_help'); ?></p>
                    <form id="forgot-password-form-comments" class="forgot-password-form">
                        <div class="arp-form-group">
                            <label for="forgot_email_comments"><?php echo lf_trans('label_email'); ?></label>
                            <input type="email" id="forgot_email_comments" name="forgot_email" required placeholder="<?php echo lf_trans('placeholder_email'); ?>">
                        </div>
                        
                        <button type="submit" class="arp-submit-btn"><?php echo lf_trans('btn_submit_forgot'); ?></button>
                    </form>
                    <div id="forgot-result-comments"></div>
                    
                    <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <p style="color: #666; margin: 0 0 10px;"><?php echo lf_trans('remembered_password'); ?></p>
                        <button type="button" class="arp-switch-to-login-from-forgot arp-link-btn" data-target="comments"><?php echo lf_trans('btn_login'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function handle_gmail_register() {
        check_ajax_referer('arp_nonce', 'nonce');
        
        $google_token = sanitize_text_field($_POST['google_token']);
        
        if (empty($google_token)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Token de Google requerido')));
        }
        
        // Verificar token con Google
        $user_info = $this->verify_google_token($google_token);
        
        if (!$user_info) {
            wp_die(json_encode(array('success' => false, 'message' => 'Token de Google inválido')));
        }
        
        // Verificar si el usuario ya existe
        $existing_user = get_user_by('email', $user_info['email']);
        
        if ($existing_user) {
            // Iniciar sesión automáticamente
            wp_set_current_user($existing_user->ID);
            wp_set_auth_cookie($existing_user->ID);
            
            // Verificar si es modo modal (no redirect)
            $modal_mode = isset($_POST['modal_mode']) && $_POST['modal_mode'] === 'true';
            
            // Debug: Log modal mode
            error_log('🔍 LoginFree - Modal mode detectado: ' . ($modal_mode ? 'true' : 'false'));
            
            if ($modal_mode) {
                wp_die(json_encode(array(
                    'success' => true, 
                    'message' => 'Sesión iniciada correctamente', 
                    'modal_mode' => true,
                    'user_data' => array(
                        'name' => $existing_user->display_name,
                        'email' => $existing_user->user_email,
                        'first_name' => $existing_user->first_name,
                        'last_name' => $existing_user->last_name
                    )
                )));
            } else {
                wp_die(json_encode(array('success' => true, 'message' => 'Sesión iniciada correctamente', 'redirect' => home_url())));
            }
        }
        
        // Crear nuevo usuario
        $username = $this->generate_username($user_info['email']);
        $user_data = array(
            'user_login' => $username,
            'user_email' => $user_info['email'],
            'user_pass' => wp_generate_password(),
            'first_name' => $user_info['given_name'],
            'last_name' => $user_info['family_name'],
            'display_name' => $user_info['name']
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Error al crear usuario: ' . $user_id->get_error_message())));
        }
        
        // Guardar información adicional de Google
        update_user_meta($user_id, 'google_id', $user_info['sub']);
        update_user_meta($user_id, 'profile_picture', $user_info['picture']);
        
        // Iniciar sesión automáticamente
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Verificar si es modo modal (no redirect)
        $modal_mode = isset($_POST['modal_mode']) && $_POST['modal_mode'] === 'true';
        
        if ($modal_mode) {
            // Obtener datos del usuario recién creado
            $new_user = get_user_by('id', $user_id);
            wp_die(json_encode(array(
                'success' => true, 
                'message' => 'Registro exitoso', 
                'modal_mode' => true,
                'user_data' => array(
                    'name' => $new_user->display_name,
                    'email' => $new_user->user_email,
                    'first_name' => $new_user->first_name,
                    'last_name' => $new_user->last_name
                )
            )));
        } else {
            wp_die(json_encode(array('success' => true, 'message' => 'Registro exitoso', 'redirect' => home_url())));
        }
    }
    
    public function handle_email_register() {
        check_ajax_referer('arp_nonce', 'nonce');
        
        $name = isset($_POST['user_name']) ? sanitize_text_field($_POST['user_name']) : '';
        $email = sanitize_email($_POST['user_email']);
        $password = $_POST['user_password'];
        
        // Validaciones mínimas
        if (empty($email) || empty($password)) {
            wp_die(json_encode(array('success' => false, 'message' => lf_trans('error_required_fields'))));
        }
        
        if (strlen($password) < 6) {
            wp_die(json_encode(array('success' => false, 'message' => lf_trans('error_password_length'))));
        }
        
        if (!is_email($email)) {
            wp_die(json_encode(array('success' => false, 'message' => lf_trans('error_invalid_email'))));
        }
        
        // Verificar si el email ya existe
        if (email_exists($email)) {
            wp_die(json_encode(array('success' => false, 'message' => lf_trans('error_email_exists'))));
        }
        
        // Verificar si ya existe una solicitud de verificación pendiente
        global $wpdb;
        $verification_table = $wpdb->prefix . 'arp_email_verification';
        
        $existing_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $verification_table WHERE email = %s AND verified = 0 AND expires_at > NOW()",
            $email
        ));
        
        if ($existing_request) {
            wp_die(json_encode(array('success' => false, 'message' => 'Ya tienes una solicitud de verificación pendiente. Revisa tu correo electrónico.')));
        }
        
        // Usar el nombre proporcionado o generar username desde email
        $display_name = !empty($name) ? $name : $this->generate_username($email);
        $verification_token = $this->generate_verification_token();
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Guardar solicitud de registro para verificación
        $result = $wpdb->insert(
            $verification_table,
            array(
                'email' => $email,
                'token' => $verification_token,
                'password_hash' => wp_hash_password($password),
                'display_name' => $display_name,
                'expires_at' => $expires_at,
                'verified' => 0
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result === false) {
            wp_die(json_encode(array('success' => false, 'message' => 'Error al procesar la solicitud de registro')));
        }
        
        // Enviar correo de verificación
        $verification_sent = $this->send_verification_email($email, $verification_token);
        
        if ($verification_sent) {
            wp_die(json_encode(array(
                'success' => true, 
                'message' => '¡Registro iniciado! Hemos enviado un correo de verificación a ' . $email . '. Por favor, revisa tu bandeja de entrada y haz clic en el enlace para completar tu registro.'
            )));
        } else {
            // Eliminar el registro si no se pudo enviar el correo
            $wpdb->delete($verification_table, array('token' => $verification_token));
            wp_die(json_encode(array('success' => false, 'message' => 'Error al enviar el correo de verificación. Por favor, inténtalo de nuevo.')));
        }
    }
    
    public function handle_email_login() {
        check_ajax_referer('arp_nonce', 'nonce');
        
        $email = sanitize_email($_POST['login_email']);
        $password = $_POST['login_password'];
        
        // Validaciones
        if (empty($email) || empty($password)) {
            wp_die(json_encode(array('success' => false, 'message' => lf_trans('error_required_fields'))));
        }
        
        if (!is_email($email)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Email no válido')));
        }
        
        // Verificar si el usuario existe
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_die(json_encode(array('success' => false, 'message' => lf_trans('error_login_failed'))));
        }
        
        // Verificar contraseña
        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            wp_die(json_encode(array('success' => false, 'message' => lf_trans('error_login_failed'))));
        }
        
        // Iniciar sesión
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        // Verificar si es modo modal (no redirect)
        $modal_mode = isset($_POST['modal_mode']) && $_POST['modal_mode'] === 'true';
        
        if ($modal_mode) {
            wp_die(json_encode(array(
                'success' => true, 
                'message' => lf_trans('success_login'), 
                'modal_mode' => true,
                'user_data' => array(
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name
                )
            )));
        } else {
            wp_die(json_encode(array('success' => true, 'message' => lf_trans('success_login'), 'redirect' => home_url())));
        }
    }
    
    public function handle_forgot_password() {
        check_ajax_referer('arp_nonce', 'nonce');
        
        $email = sanitize_email($_POST['forgot_email']);
        
        // Validaciones
        if (empty($email)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Email es obligatorio')));
        }
        
        if (!is_email($email)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Email no válido')));
        }
        
        // Verificar si el usuario existe
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // Por seguridad, no revelamos si el email existe o no
            wp_die(json_encode(array('success' => true, 'message' => 'Si el email existe en nuestro sistema, recibirás un enlace de recuperación en breve.')));
        }
        
        // Generar clave de recuperación
        $reset_key = get_password_reset_key($user);
        
        if (is_wp_error($reset_key)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Error al generar enlace de recuperación. Inténtalo de nuevo.')));
        }
        
        // Crear enlace de recuperación
        $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login), 'login');
        
        // Enviar email
        $to = $user->user_email;
        $subject = 'Recuperar contraseña - ' . get_bloginfo('name');
        
        $message = "Hola " . $user->display_name . ",\n\n";
        $message .= "Recibimos una solicitud para restablecer tu contraseña.\n\n";
        $message .= "Haz clic en el siguiente enlace para crear una nueva contraseña:\n";
        $message .= $reset_url . "\n\n";
        $message .= "Si no solicitaste este cambio, puedes ignorar este correo.\n\n";
        $message .= "Este enlace es válido por 24 horas.\n\n";
        $message .= "Saludos,\n";
        $message .= get_bloginfo('name');
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            error_log('✅ Email de recuperación enviado a: ' . $email);
            wp_die(json_encode(array('success' => true, 'message' => 'Revisa tu email. Te hemos enviado un enlace para restablecer tu contraseña.')));
        } else {
            error_log('❌ Error al enviar email de recuperación a: ' . $email);
            wp_die(json_encode(array('success' => false, 'message' => 'Error al enviar el email. Inténtalo de nuevo más tarde.')));
        }
    }
    
    public function check_username_availability() {
        check_ajax_referer('arp_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        
        if (username_exists($username)) {
            wp_die('taken');
        } else {
            wp_die('available');
        }
    }
    
    public function handle_resend_verification() {
        check_ajax_referer('arp_nonce', 'nonce');
        
        $email = sanitize_email($_POST['user_email']);
        
        if (empty($email) || !is_email($email)) {
            wp_die(json_encode(array('success' => false, 'message' => 'Email no válido')));
        }
        
        global $wpdb;
        $verification_table = $wpdb->prefix . 'arp_email_verification';
        
        // Buscar solicitud de verificación pendiente
        $pending_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $verification_table WHERE email = %s AND verified = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
            $email
        ));
        
        if (!$pending_request) {
            wp_die(json_encode(array('success' => false, 'message' => 'No hay solicitud de verificación pendiente para este email o ya expiró.')));
        }
        
        // Verificar si han pasado al menos 2 minutos desde la última solicitud (evitar spam)
        $time_since_creation = time() - strtotime($pending_request->created_at);
        if ($time_since_creation < 120) { // 2 minutos
            $wait_time = 120 - $time_since_creation;
            wp_die(json_encode(array('success' => false, 'message' => "Debes esperar {$wait_time} segundos antes de solicitar otro correo.")));
        }
        
        // Reenviar correo de verificación
        $verification_sent = $this->send_verification_email($email, $pending_request->token);
        
        if ($verification_sent) {
            wp_die(json_encode(array(
                'success' => true, 
                'message' => 'Correo de verificación reenviado a ' . $email . '. Revisa tu bandeja de entrada.'
            )));
        } else {
            wp_die(json_encode(array('success' => false, 'message' => 'Error al reenviar el correo. Inténtalo más tarde.')));
        }
    }
    
    private function verify_google_token($token) {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $token;
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['email'])) {
            return false;
        }
        
        return $data;
    }
    
    private function generate_username($email) {
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        $original_username = $username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    private function generate_verification_token() {
        return bin2hex(random_bytes(32));
    }
    
    private function send_verification_email($email, $token) {
        // Log del intento de envío
        error_log("ARP: Intentando enviar correo de verificación a: " . $email);
        
        $verification_url = site_url('/') . '?arp_verify_email=1&token=' . $token;
        
        $subject = 'Verificación de correo electrónico - ' . get_bloginfo('name');
        
        $message = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .button { 
                    display: inline-block; 
                    background-color: #007cba; 
                    color: white; 
                    padding: 12px 24px; 
                    text-decoration: none; 
                    border-radius: 4px;
                    margin: 20px 0;
                }
                .footer { 
                    font-size: 12px; 
                    color: #666; 
                    text-align: center; 
                    margin-top: 30px; 
                    padding-top: 20px; 
                    border-top: 1px solid #eee; 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>¡Bienvenido a ' . get_bloginfo('name') . '!</h1>
                </div>
                <div class="content">
                    <h2>Confirma tu dirección de correo electrónico</h2>
                    <p>Hola,</p>
                    <p>Gracias por registrarte en nuestro sitio. Para completar tu registro, necesitamos verificar tu dirección de correo electrónico.</p>
                    <p>Haz clic en el siguiente botón para confirmar tu cuenta:</p>
                    <p style="text-align: center;">
                        <a href="' . $verification_url . '" class="button">Verificar mi correo electrónico</a>
                    </p>
                    <p>Si no puedes hacer clic en el botón, copia y pega la siguiente URL en tu navegador:</p>
                    <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
                        ' . $verification_url . '
                    </p>
                    <p><strong>Este enlace expirará en 24 horas.</strong></p>
                    <p>Si no creaste una cuenta en nuestro sitio, puedes ignorar este correo.</p>
                </div>
                <div class="footer">
                    <p>Este correo fue enviado desde ' . get_bloginfo('name') . '</p>
                    <p>' . site_url() . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Headers mejorados para mejor compatibilidad
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>',
            'Reply-To: ' . $admin_email
        );
        
        // Hook para capturar errores de wp_mail
        $mail_error_captured = false;
        $mail_error_message = '';
        
        $error_handler = function($wp_error) use (&$mail_error_captured, &$mail_error_message) {
            $mail_error_captured = true;
            $mail_error_message = $wp_error->get_error_message();
            error_log("ARP: Error de wp_mail: " . $mail_error_message);
        };
        
        add_action('wp_mail_failed', $error_handler);
        
        // Intentar envío con wp_mail
        $result = wp_mail($email, $subject, $message, $headers);
        
        // Remover el handler
        remove_action('wp_mail_failed', $error_handler);
        
        if ($result) {
            error_log("ARP: Correo enviado exitosamente a: " . $email);
            return true;
        } else {
            error_log("ARP: Error al enviar correo a: " . $email);
            
            // Si wp_mail falló, intentar con PHP mail() básico como fallback
            if (!$mail_error_captured) {
                error_log("ARP: Intentando fallback con PHP mail()");
                
                $simple_message = "Hola,\n\n";
                $simple_message .= "Gracias por registrarte en " . $site_name . ". ";
                $simple_message .= "Para completar tu registro, haz clic en el siguiente enlace:\n\n";
                $simple_message .= $verification_url . "\n\n";
                $simple_message .= "Este enlace expirará en 24 horas.\n\n";
                $simple_message .= "Si no creaste una cuenta, puedes ignorar este correo.\n\n";
                $simple_message .= "Saludos,\n" . $site_name;
                
                $simple_headers = "From: " . $site_name . " <" . $admin_email . ">\r\n";
                $simple_headers .= "Reply-To: " . $admin_email . "\r\n";
                $simple_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                $php_mail_result = mail($email, $subject, $simple_message, $simple_headers);
                
                if ($php_mail_result) {
                    error_log("ARP: Correo enviado exitosamente con PHP mail() a: " . $email);
                    return true;
                } else {
                    error_log("ARP: PHP mail() también falló para: " . $email);
                }
            }
            
            // Log información adicional para debug
            error_log("ARP: Admin email configurado: " . $admin_email);
            error_log("ARP: Site name: " . $site_name);
            error_log("ARP: Server name: " . ($_SERVER['SERVER_NAME'] ?? 'No disponible'));
            
            return false;
        }
    }
    
    public function handle_verification_url() {
        if (isset($_GET['arp_verify_email']) && isset($_GET['token'])) {
            $token = sanitize_text_field($_GET['token']);
            $verification_result = $this->process_email_verification($token);
            
            // Almacenar el resultado en una variable global o sesión para mostrarlo
            global $arp_verification_message;
            $arp_verification_message = $verification_result;
        }
    }
    
    public function handle_email_verification() {
        check_ajax_referer('arp_nonce', 'nonce');
        
        $token = sanitize_text_field($_POST['token']);
        $result = $this->process_email_verification($token);
        
        wp_die(json_encode($result));
    }
    
    private function process_email_verification($token) {
        global $wpdb;
        $verification_table = $wpdb->prefix . 'arp_email_verification';
        
        // Buscar el token
        $verification_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $verification_table WHERE token = %s AND verified = 0",
            $token
        ));
        
        if (!$verification_request) {
            return array('success' => false, 'message' => 'Token de verificación no válido o ya utilizado.');
        }
        
        // Verificar si el token ha expirado
        if (strtotime($verification_request->expires_at) < time()) {
            // Eliminar token expirado
            $wpdb->delete($verification_table, array('token' => $token));
            return array('success' => false, 'message' => 'El enlace de verificación ha expirado. Por favor, regístrate nuevamente.');
        }
        
        // Verificar nuevamente que el email no esté registrado
        if (email_exists($verification_request->email)) {
            // Marcar como verificado pero no crear usuario
            $wpdb->update($verification_table, array('verified' => 1), array('token' => $token));
            return array('success' => false, 'message' => 'Este correo electrónico ya está registrado. Puedes iniciar sesión directamente.');
        }
        
        // Crear el usuario
        $user_data = array(
            'user_login' => $verification_request->display_name,
            'user_email' => $verification_request->email,
            'user_pass' => $verification_request->password_hash,
            'display_name' => $verification_request->display_name
        );
        
        // Necesitamos usar la contraseña hasheada directamente
        add_filter('pre_user_pass', array($this, 'use_prehashed_password'));
        $this->prehashed_password = $verification_request->password_hash;
        
        $user_id = wp_insert_user($user_data);
        
        // Remover el filtro
        remove_filter('pre_user_pass', array($this, 'use_prehashed_password'));
        unset($this->prehashed_password);
        
        if (is_wp_error($user_id)) {
            return array('success' => false, 'message' => 'Error al crear la cuenta: ' . $user_id->get_error_message());
        }
        
        // Marcar como verificado
        $wpdb->update($verification_table, array('verified' => 1), array('token' => $token));
        
        // Agregar metadata del registro
        update_user_meta($user_id, 'registration_type', 'email_verified');
        update_user_meta($user_id, 'verification_date', current_time('mysql'));
        
        // Iniciar sesión automáticamente
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        return array(
            'success' => true, 
            'message' => '¡Correo verificado exitosamente! Tu cuenta ha sido creada y ya estás conectado.',
            'redirect' => home_url()
        );
    }
    
    public function use_prehashed_password($password) {
        return $this->prehashed_password;
    }
    
    public function verification_status_shortcode($atts) {
        global $arp_verification_message;
        
        if (!$arp_verification_message) {
            return '';
        }
        
        $css_class = $arp_verification_message['success'] ? 'arp-message success' : 'arp-message error';
        $icon = $arp_verification_message['success'] ? '✅' : '❌';
        
        $output = '<div class="arp-verification-result ' . $css_class . '">';
        $output .= '<h3>' . $icon . ' Resultado de la verificación</h3>';
        $output .= '<p>' . esc_html($arp_verification_message['message']) . '</p>';
        
        if ($arp_verification_message['success'] && isset($arp_verification_message['redirect'])) {
            $output .= '<p><a href="' . esc_url($arp_verification_message['redirect']) . '" class="arp-submit-btn">Continuar al sitio</a></p>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'arp_user_data';
        $verification_table = $wpdb->prefix . 'arp_email_verification';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            registration_type varchar(20) NOT NULL,
            google_id varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Tabla para tokens de verificación de email
        $verification_sql = "CREATE TABLE $verification_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            token varchar(64) NOT NULL,
            password_hash varchar(255) NOT NULL,
            display_name varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            verified tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_token (token),
            KEY email_index (email)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($verification_sql);
    }
    
    public function admin_menu() {
        add_options_page(
            'Registro Avanzado',
            'Registro Avanzado',
            'manage_options',
            'advanced-registration',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            update_option('arp_google_client_id', sanitize_text_field($_POST['google_client_id']));
            update_option('arp_google_client_secret', sanitize_text_field($_POST['google_client_secret']));
            echo '<div class="notice notice-success"><p>Configuración guardada.</p></div>';
        }
        
        $google_client_id = get_option('arp_google_client_id', '');
        $google_client_secret = get_option('arp_google_client_secret', '');
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        ?>
        <div class="wrap">
            <h1>Configuración del Registro Avanzado</h1>
            
            <?php if (!is_ssl()): ?>
            <div class="notice notice-warning">
                <p><strong>⚠️ Advertencia:</strong> Google OAuth requiere HTTPS. Tu sitio actual usa HTTP. 
                <a href="<?php echo plugin_dir_url(__FILE__) . 'setup-instructions.md'; ?>" target="_blank">Ver instrucciones para configurar HTTPS</a></p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Google Client ID</th>
                        <td>
                            <input type="text" name="google_client_id" value="<?php echo esc_attr($google_client_id); ?>" class="regular-text" />
                            <p class="description">Obténlo desde Google Cloud Console</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Google Client Secret</th>
                        <td>
                            <input type="text" name="google_client_secret" value="<?php echo esc_attr($google_client_secret); ?>" class="regular-text" />
                            <p class="description">Mantén esto seguro y privado</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <div class="card" style="max-width: none;">
                <h2>Estado del Sistema</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>HTTPS habilitado:</strong></td>
                        <td><?php echo is_ssl() ? '✅ Sí' : '❌ No (Requerido para Google OAuth)'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Google Client ID:</strong></td>
                        <td><?php echo !empty($google_client_id) ? '✅ Configurado' : '❌ No configurado'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>URL actual:</strong></td>
                        <td><?php echo esc_html($current_url); ?></td>
                    </tr>
                    <tr>
                        <td><strong>URL para Google Console:</strong></td>
                        <td><?php echo esc_html($current_url); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: none;">
                <h2>Instrucciones de configuración para localhost</h2>
                <ol>
                    <li><strong>Opción 1 - Usar ngrok (Recomendado):</strong>
                        <ul>
                            <li>Descargar ngrok desde <a href="https://ngrok.com/" target="_blank">https://ngrok.com/</a></li>
                            <li>Ejecutar: <code>ngrok http 80</code></li>
                            <li>Usar la URL HTTPS que proporciona ngrok</li>
                        </ul>
                    </li>
                    <li><strong>Configurar Google Cloud Console:</strong>
                        <ul>
                            <li>Ve a <a href="https://console.developers.google.com/" target="_blank">Google Cloud Console</a></li>
                            <li>Crea un proyecto o selecciona uno existente</li>
                            <li>Habilita "Google Identity API" o "Google+ API"</li>
                            <li>Crear credenciales OAuth 2.0</li>
                            <li>Tipo: "Aplicación web"</li>
                            <li>Orígenes JavaScript autorizados: <code><?php echo $current_url; ?></code></li>
                            <li>URIs de redirección: <code><?php echo admin_url('admin-ajax.php'); ?></code></li>
                        </ul>
                    </li>
                </ol>
            </div>
            
            <h2>Uso del Plugin</h2>
            <p>Usa el shortcode <code>[advanced_registration_form]</code> en cualquier página o post.</p>
            <p>Para debug: <code>[debug_registration]</code></p>
        </div>
        <?php
    }
}

// Inicializar el plugin
new AdvancedRegistrationPlugin();