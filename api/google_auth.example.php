<?php
// Copia este archivo como api/google_auth.php y completa tus credenciales de Google Cloud Console
//
// PASOS:
// 1. Ir a https://console.cloud.google.com/
// 2. APIs & Services → Credentials → OAuth 2.0 Client ID → Web application
// 3. Authorized redirect URI: https://tudominio.com/lms/api/google_auth.php
// 4. Pegar Client ID y Client Secret abajo

define('GOOGLE_CLIENT_ID',     'TU_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'TU_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'https://tudominio.com/lms/api/google_auth.php');
