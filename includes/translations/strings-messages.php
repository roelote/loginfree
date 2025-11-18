<?php
/**
 * Traducciones de mensajes (éxito, error, validaciones)
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    // Mensajes de error - Validación
    'error_required_fields' => array(
        'es' => 'Por favor completa todos los campos',
        'en' => 'Please complete all fields',
        'pt-br' => 'Por favor, preencha todos os campos',
        'fr' => 'Veuillez remplir tous les champs',
        'it' => 'Si prega di compilare tutti i campi'
    ),
    
    'error_invalid_email' => array(
        'es' => 'Email no válido',
        'en' => 'Invalid email',
        'pt-br' => 'Email inválido',
        'fr' => 'Email non valide',
        'it' => 'Email non valida'
    ),
    
    'error_password_length' => array(
        'es' => 'La contraseña debe tener al menos 6 caracteres',
        'en' => 'Password must be at least 6 characters',
        'pt-br' => 'A senha deve ter pelo menos 6 caracteres',
        'fr' => 'Le mot de passe doit contenir au moins 6 caractères',
        'it' => 'La password deve contenere almeno 6 caratteri'
    ),
    
    'error_email_exists' => array(
        'es' => 'Este email ya está registrado. Por favor inicia sesión.',
        'en' => 'This email is already registered. Please log in.',
        'pt-br' => 'Este email já está registrado. Por favor, faça login.',
        'fr' => 'Cet email est déjà enregistré. Veuillez vous connecter.',
        'it' => 'Questa email è già registrata. Effettua il login.'
    ),
    
    'error_login_failed' => array(
        'es' => 'Email o contraseña incorrectos',
        'en' => 'Incorrect email or password',
        'pt-br' => 'Email ou senha incorretos',
        'fr' => 'Email ou mot de passe incorrect',
        'it' => 'Email o password errati'
    ),
    
    'error_connection' => array(
        'es' => 'Error de conexión. Inténtalo de nuevo.',
        'en' => 'Connection error. Please try again.',
        'pt-br' => 'Erro de conexão. Tente novamente.',
        'fr' => 'Erreur de connexion. Veuillez réessayer.',
        'it' => 'Errore di connessione. Riprova.'
    ),
    
    // Mensajes de éxito
    'success_registered' => array(
        'es' => '¡Cuenta creada exitosamente! Redirigiendo...',
        'en' => 'Account created successfully! Redirecting...',
        'pt-br' => 'Conta criada com sucesso! Redirecionando...',
        'fr' => 'Compte créé avec succès! Redirection...',
        'it' => 'Account creato con successo! Reindirizzamento...'
    ),
    
    'success_login' => array(
        'es' => '¡Bienvenido! Redirigiendo...',
        'en' => 'Welcome! Redirecting...',
        'pt-br' => 'Bem-vindo! Redirecionando...',
        'fr' => 'Bienvenue! Redirection...',
        'it' => 'Benvenuto! Reindirizzamento...'
    ),
    
    'success_password_reset' => array(
        'es' => 'Te hemos enviado un enlace para restablecer tu contraseña',
        'en' => 'We have sent you a link to reset your password',
        'pt-br' => 'Enviamos um link para redefinir sua senha',
        'fr' => 'Nous vous avons envoyé un lien pour réinitialiser votre mot de passe',
        'it' => 'Ti abbiamo inviato un link per reimpostare la password'
    ),
);
