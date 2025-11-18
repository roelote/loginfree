// assets/script.js

/**
 * Inicialización y lógica principal del plugin de registro avanzado.
 * Utiliza Google Identity Services (GIS) para la autenticación de Google.
 */
jQuery(document).ready(function($) {
    
    // ----------------------------------------------------------------------
    // 1. LÓGICA DE GOOGLE IDENTITY SERVICES (GIS)
    // ----------------------------------------------------------------------

    // Esperar a que Google Identity Services esté disponible
    function initializeGoogleAuth() {
        if (typeof google !== 'undefined' && google.accounts && arp_ajax.google_client_id) {
        
        // 1.1. Inicialización del cliente de Google (Obligatorio)
        google.accounts.id.initialize({
            client_id: arp_ajax.google_client_id, // Usamos el ID del localize script
            callback: handleCredentialResponse, // La función que manejará el token
            auto_select: false, // Evita la selección automática, a menos que quieras One Tap
            context: 'signup' // Indica a Google que es para registro
        });

        // 1.2. Renderizar el botón en el div de destino (header)
        var headerButton = document.getElementById("gmail-signin-button");
        if (headerButton) {
            google.accounts.id.renderButton(headerButton, {
                type: "standard",
                theme: "outline",
                size: "large",
                text: "signup_with",
                locale: "es",
                shape: "pill"
            });
        }
        
        // 1.3. Renderizar el botón en el div de comentarios (si existe)
        var commentsButton = document.getElementById("gmail-signin-button-comments");
        if (commentsButton) {
            google.accounts.id.renderButton(commentsButton, {
                type: "standard",
                theme: "outline",
                size: "large",
                text: "signup_with",
                locale: "es",
                shape: "pill"
            });
        }
        
        // Opcional: Mostrar el indicador de "One Tap" si no se está usando el botón tradicional
        // google.accounts.id.prompt(); 

    } else if (!arp_ajax.google_client_id) {
        // Mensaje de error si el Client ID no está configurado
        $('#gmail-signin-button').html('<p class="arp-message error">Error: Client ID de Google no configurado. Ve a Ajustes > Registro Avanzado para configurarlo.</p>');
        } else if (!arp_ajax.google_client_id) {
            // Mensaje de error si el Client ID no está configurado
            $('#gmail-signin-button').html('<p class="arp-message error">Error: Client ID de Google no configurado. Ve a Ajustes > Registro Avanzado para configurarlo.</p>');
        } else {
            // Fallback si la librería no carga
            console.error("Google Identity Services (GIS) no se cargó.");
            createManualGoogleButton('La librería de Google no se pudo cargar.');
        }
    }

    // Intentar inicializar Google Auth
    var attempts = 0;
    var maxAttempts = 20;
    
    function waitForGoogle() {
        attempts++;
        if (typeof google !== 'undefined' && google.accounts) {
            initializeGoogleAuth();
        } else if (attempts < maxAttempts) {
            setTimeout(waitForGoogle, 100);
        } else {
            console.error('Google Identity Services no se cargó después de varios intentos');
            if (arp_ajax.google_client_id) {
                createManualGoogleButton('Error: Google Identity Services no se pudo cargar. Verifica tu conexión a internet.');
            }
        }
    }
    
    // Iniciar la espera
    waitForGoogle();

    /**
     * Función callback que se ejecuta cuando Google devuelve el token (ID Token).
     * @param {Object} response - Objeto CredentialResponse de Google.
     */
    function handleCredentialResponse(response) {
        
        const id_token = response.credential;
        
        $('#gmail-result, #gmail-result-comments').html('<div class="arp-message info"><span class="arp-loading"></span>Verificando credenciales con Google...</div>');
        
        // Detectar si estamos en modo modal (header o comentarios)
        var container = document.getElementById('arp-registration-container');
        var commentsContainer = document.getElementById('arp-registration-container-comments');
        var activeContainer = container || commentsContainer;
        var modalMode = activeContainer && activeContainer.getAttribute('data-modal-mode') === 'true';
        
        console.log('🔍 Container header:', !!container);
        console.log('🔍 Container comments:', !!commentsContainer);
        console.log('🔍 Modal mode detectado:', modalMode);
        
        // 1.3. Enviar el token al servidor (tu función AJAX)
        var ajaxData = {
            action: 'gmail_register',
            nonce: arp_ajax.nonce,
            google_token: id_token // Enviamos el ID Token
        };
        
        // Agregar parámetro modal_mode si es necesario
        if (modalMode) {
            ajaxData.modal_mode = 'true';
        }
        
        console.log('📤 Datos AJAX enviados:', ajaxData);
        
        $.ajax({
            url: arp_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.message, 'gmail-result');
                    
                    // Si es modo modal, disparar evento personalizado en lugar de redirect
                    if (response.modal_mode) {
                        console.log('🎉 Login exitoso en modo modal - disparando evento personalizado');
                        console.log('📊 Datos del usuario recibidos:', response.user_data);
                        
                        // Disparar evento personalizado para que ComentariosFree lo detecte
                        var loginEvent = new CustomEvent('cf_user_logged_in', {
                            detail: {
                                success: true,
                                message: response.message,
                                user_data: response.user_data || null
                            }
                        });
                        
                        console.log('📡 Disparando evento cf_user_logged_in con datos:', loginEvent.detail);
                        window.dispatchEvent(loginEvent);
                        
                    } else if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1000); // Redirección más rápida tras registro/login
                    }
                } else {
                    showMessage('error', response.message, 'gmail-result');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexión con el servidor.', 'gmail-result');
            }
        });
    }
    
    /**
     * Crea un botón manual de Google con mensaje de error si la librería falla.
     * @param {string} msg - Mensaje de error a mostrar.
     */
    function createManualGoogleButton(msg) {
        var manualButton = $('<button>')
            .addClass('google-signin-btn')
            .html('<img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHZpZXdCb3g9IjAgMCAxOCAxOCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0xNy42NCA5LjIwNWMwLS42MzktLjA1Ny0xLjI1Mi0uMTY0LTEuODQxSDl2My40ODFoNC44NDRjLS4yMDkgMS4xMjUtLjg0MyAyLjA3OC0xLjc5NiAyLjcxN3YyLjI1OGgzLjA1NEMxNi4wNSAxMy45NDUgMTcuNjQgMTEuNzk2IDE3LjY0IDkuMjA1eiIgZmlsbD0iIzQyODVGNCI+PC9wYXRoPjxwYXRoIGQ9Ik05IDI1LjIzMWMxLjcyNiAwIDMuMTctLjU3MSA0LjIyNy0xLjU0NkwxMC4xOTMgMjEuOTI3Yy0uNzc1LjUyLTEuNzY3LjgyOS0zLjE5My44MjktMi40NTUgMC00LjUyOS0xLjY1OS01LjI4MS00LjAwNUguNjIzVjIwLjk5N0M0LjczIDI0LjUwNSA2Ljc3MSAyNS4yMzEgOSAyNS4yMzF6IiBmaWxsPSIjMzRBODUzIj48L3BhdGg+PHBhdGggZD0iTTMuNzE5IDE4Ljc1SDEuNjIzVjE4Ljc1YTYuMDEzIDYuMDEzIDAgMDAtLjAyLTEuNzUzaDIuMTE2Yy0uMDM0IDEuMTU5LS4wMzQgMi4zOTQgMCAzLjUwM3oiIGZpbGw9IiNGQkJDMDUiPjwvcGF0aD48cGF0aCBkPSJNOSA3LjcwNGMxLjU4NSAwIDMuMDA5LjU0NCA0LjEyNyAxLjYxbDMuMDkzLTMuMDkzQzE0LjM2IDMuNjUgMTEuOTc2IDIuNTYgOSAyLjU2QzYuNzcgMi41NiA0LjQ5NiAzLjI4NiAxLjY0MiA2Ljc5NWw0LjE3IDQuNTJjLjc1Mi0xLjE1NSAxLjkyLTEuNjExIDMuMTg4LTEuNjExeiIgZmlsbD0iI0VBNDMzNSI+PC9wYXRoPjwvZz48L3N2Zz4K" alt="Google">Continuar con Google')
            .on('click', function(e) {
                e.preventDefault();
                $('#gmail-result').html('<p class="arp-message error">' + msg + '</p>');
            });
        
        $('#gmail-signin-button').html(manualButton);
    }
    
    // ----------------------------------------------------------------------
    // 2. LÓGICA DE LOGIN Y REGISTRO POR EMAIL
    // ----------------------------------------------------------------------
    
    // Toggle entre login, registro y recuperar contraseña (usar delegación para modales)
    $(document).on('click', '.arp-switch-to-register, #arp-switch-to-register', function(e) {
        e.preventDefault();
        var target = $(this).data('target') || '';
        var suffix = target === 'comments' ? '-comments' : '';
        $('#arp-login-mode' + suffix + ', #arp-forgot-mode' + suffix).hide();
        $('#arp-register-mode' + suffix).fadeIn(300);
    });
    
    $(document).on('click', '.arp-switch-to-login, .arp-switch-to-login-from-forgot, #arp-switch-to-login, #arp-switch-to-login-from-forgot', function(e) {
        e.preventDefault();
        var target = $(this).data('target') || '';
        var suffix = target === 'comments' ? '-comments' : '';
        $('#arp-register-mode' + suffix + ', #arp-forgot-mode' + suffix).hide();
        $('#arp-login-mode' + suffix).fadeIn(300);
    });
    
    $(document).on('click', '.arp-switch-to-forgot, #arp-switch-to-forgot', function(e) {
        e.preventDefault();
        var target = $(this).data('target') || '';
        var suffix = target === 'comments' ? '-comments' : '';
        $('#arp-login-mode' + suffix + ', #arp-register-mode' + suffix).hide();
        $('#arp-forgot-mode' + suffix).fadeIn(300);
    });
    
    // Manejar el formulario de login (usar delegación para modales)
    $(document).on('submit', '#email-login-form', function(e) {
        e.preventDefault();
        
        // Detectar si estamos en modo modal
        var container = document.getElementById('arp-registration-container');
        var modalMode = container && container.getAttribute('data-modal-mode') === 'true';
        
        var formData = {
            action: 'email_login',
            nonce: arp_ajax.nonce,
            login_email: $('#login_email').val(),
            login_password: $('#login_password').val()
        };
        
        // Agregar parámetro modal_mode si es necesario
        if (modalMode) {
            formData.modal_mode = 'true';
        }
        
        // Mostrar loading
        var submitBtn = $('#email-login-form button[type="submit"]');
        var originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('🔄 Iniciando sesión...');
        
        $.ajax({
            url: arp_ajax.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.message, resultDiv);
                    
                    // Si es modo modal, disparar evento personalizado
                    if (response.modal_mode) {
                        var loginEvent = new CustomEvent('cf_user_logged_in', {
                            detail: {
                                success: true,
                                message: response.message,
                                user_data: response.user_data || null
                            }
                        });
                        window.dispatchEvent(loginEvent);
                    } else if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1000);
                    }
                } else {
                    showMessage('error', response.message, resultDiv);
                }
            },
            error: function() {
                showMessage('error', 'Error de conexión. Inténtalo de nuevo.', resultDiv);
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Manejar el formulario de registro por email (usar delegación para modales)
    $(document).on('submit', '.email-register-form, #email-registration-form, #email-register-form-comments', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var formId = $form.attr('id');
        var isComments = formId === 'email-register-form-comments';
        var resultDiv = isComments ? 'register-result-comments' : 'email-result';
        
        // Detectar si estamos en modo modal (header o comentarios)
        var container = document.getElementById('arp-registration-container');
        var commentsContainer = document.getElementById('arp-registration-container-comments');
        var activeContainer = container || commentsContainer;
        var modalMode = activeContainer && activeContainer.getAttribute('data-modal-mode') === 'true';
        
        var formData = {
            action: 'email_register',
            nonce: arp_ajax.nonce,
            user_name: $form.find('input[name="user_name"]').val(),
            user_email: $form.find('input[name="user_email"]').val(),
            user_password: $form.find('input[name="user_password"]').val()
        };
        
        // Agregar parámetro modal_mode si es necesario
        if (modalMode) {
            formData.modal_mode = 'true';
        }
        
        // Mostrar loading
        var submitBtn = $form.find('button[type="submit"]');
        var originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="arp-loading"></span>Enviando verificación...');
        
        $.ajax({
            url: arp_ajax.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.message, 'email-result');
                    $('#email-registration-form')[0].reset();
                    
                    // Mensaje adicional de instrucciones con botón de reenvío
                    setTimeout(function() {
                        var userEmail = formData.user_email;
                        var additionalMsg = '<div class="arp-message info">' +
                            '📧 <strong>Próximo paso:</strong> Revisa tu bandeja de entrada (y carpeta de spam) ' +
                            'y haz clic en el enlace para completar tu registro.<br>' +
                            '<button onclick="resendVerificationEmail(\'' + userEmail + '\')" ' +
                            'style="margin-top: 10px; padding: 5px 10px; font-size: 12px;" ' +
                            'class="arp-resend-btn">¿No recibiste el correo? Reenviar</button>' +
                            '</div>';
                        $('#email-result').append(additionalMsg);
                    }, 2000);
                    
                    if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1000);
                    }
                } else {
                    showMessage('error', response.message, 'email-result');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexión. Inténtalo de nuevo.', 'email-result');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Manejar el formulario de recuperar contraseña (usar delegación para modales)
    $(document).on('submit', '#email-forgot-form', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'forgot_password',
            nonce: arp_ajax.nonce,
            forgot_email: $('#forgot_email').val()
        };
        
        // Mostrar loading
        var submitBtn = $('#email-forgot-form button[type="submit"]');
        var originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('📧 Enviando...');
        
        $.ajax({
            url: arp_ajax.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.message, 'forgot-result');
                    $('#email-forgot-form')[0].reset();
                } else {
                    showMessage('error', response.message, 'forgot-result');
                }
            },
            error: function() {
                showMessage('error', 'Error de conexión. Inténtalo de nuevo.', 'forgot-result');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Validación simple de email (usar delegación para modales)
    $(document).on('blur', '#user_email', function() {
        var email = $(this).val();
        if (email && !isValidEmail(email)) {
            $(this).css('border-color', '#dc3545');
        } else if (email) {
            $(this).css('border-color', '#28a745');
        }
    });
    
    // MÉTODO 2: Listener directo al botón como respaldo (para login, registro y forgot)
    $(document).on('click', '.arp-submit-btn', function(e) {
        var $form = $(this).closest('form');
        var formId = $form.attr('id');
        
        if (formId === 'email-registration-form' || formId === 'email-login-form' || formId === 'email-forgot-form') {
            e.preventDefault();
            $form.trigger('submit');
        }
    });
});


// ----------------------------------------------------------------------
// 3. FUNCIONES GLOBALES (SIN CAMBIOS EN LA LÓGICA INTERNA)
// ----------------------------------------------------------------------

function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    
    tabcontent = document.getElementsByClassName("arp-tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("active");
    }
    
    tablinks = document.getElementsByClassName("arp-tab-button");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
    
    // Limpiar mensajes al cambiar de tab
    jQuery('#gmail-result, #email-result').html('');
}

function showMessage(type, message, container = 'email-result') {
    var messageClass = 'arp-message ' + type;
    var messageHtml = '<div class="' + messageClass + '">' + message + '</div>';
    jQuery('#' + container).html(messageHtml);
    
    if (type === 'success') {
        setTimeout(function() {
            jQuery('#' + container).fadeOut(500, function() { jQuery(this).html('').show(); });
        }, 5000);
    }
}

function checkPasswordStrength(password) {
    var strength = 0;
    var strengthBar = jQuery('#user_password').parent().find('.password-strength-bar');
    
    if (strengthBar.length === 0) {
        jQuery('#user_password').parent().append('<div class="password-strength"><div class="password-strength-bar"></div></div>');
        strengthBar = jQuery('#user_password').parent().find('.password-strength-bar');
    }
    
    if (password.length >= 6) strength += 1;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
    if (password.match(/[0-9]/)) strength += 1;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
    
    strengthBar.removeClass('strength-weak strength-fair strength-good strength-strong');
    
    switch(strength) {
        case 1:
            strengthBar.addClass('strength-weak');
            break;
        case 2:
            strengthBar.addClass('strength-fair');
            break;
        case 3:
            strengthBar.addClass('strength-good');
            break;
        case 4:
            strengthBar.addClass('strength-strong');
            break;
        default:
            strengthBar.css('width', '0');
            return;
    }
}

function isValidEmail(email) {
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validateField(field, validationFn, errorMessage) {
    jQuery(field).on('blur', function() {
        var value = jQuery(this).val();
        var isValid = validationFn(value);
        
        if (value && !isValid) {
            jQuery(this).css('border-color', '#dc3545');
            showMessage('error', errorMessage);
        } else if (value && isValid) {
            jQuery(this).css('border-color', '#28a745');
        }
    });
}

function isValidUsername(username) {
    return username.length >= 3 && /^[a-zA-Z0-9_]+$/.test(username);
}

function resendVerificationEmail(email) {
    if (!email || !isValidEmail(email)) {
        showMessage('error', 'Email no válido.', 'email-result');
        return;
    }
    
    var resendBtn = jQuery('.arp-resend-btn');
    var originalText = resendBtn.text();
    resendBtn.prop('disabled', true).text('Reenviando...');
    
    jQuery.ajax({
        url: arp_ajax.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'resend_verification',
            nonce: arp_ajax.nonce,
            user_email: email
        },
        success: function(response) {
            if (response.success) {
                showMessage('success', response.message, 'email-result');
            } else {
                showMessage('error', response.message, 'email-result');
            }
        },
        error: function() {
            showMessage('error', 'Error de conexión. Inténtalo de nuevo.', 'email-result');
        },
        complete: function() {
            resendBtn.prop('disabled', false).text(originalText);
        }
    });
}