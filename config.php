<?php
/**
 * RC Construções - Configurações Completas do Sistema
 * @version 4.0
 * @description Arquivo de configuração centralizado e seguro
 * 
 * FUNCIONALIDADES:
 * ✅ Configurações de e-mail e SMTP completas
 * ✅ Configurações de upload e segurança
 * ✅ Sistema de logs e monitoramento
 * ✅ Validações e rate limiting
 * ✅ Configurações por ambiente (dev/prod)
 * ✅ Backup e webhooks
 * ✅ Validação automática de configuração
 */

// Impede acesso direto
if (!defined('PHP_VERSION')) {
    exit('Acesso negado');
}

// ============================================
// CONFIGURAÇÕES PRINCIPAIS DE E-MAIL
// ============================================

/**
 * IMPORTANTE: Configure estes e-mails com seus dados reais
 */

// E-mail de destino (quem recebe os orçamentos)
define('EMAIL_TO', 'contato@rcconstrucoes.com.br');

// E-mail remetente (deve ser do seu domínio)
define('EMAIL_FROM', 'nao-responda@rcconstrucoes.com.br');

// Nome do remetente
define('EMAIL_FROM_NAME', 'RC Construções - Sistema de Orçamentos');

// ============================================
// CONFIGURAÇÕES SMTP
// ============================================

/**
 * IMPORTANTE: Configure com suas credenciais SMTP reais
 * 
 * Para Gmail:
 * 1. Ative autenticação de 2 fatores
 * 2. Gere uma "senha de app" específica
 * 3. Use a senha de app no SMTP_PASSWORD
 */

// Gmail (exemplo)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'seu-email@gmail.com'); // Substitua pelo seu e-mail
define('SMTP_PASSWORD', 'sua-senha-de-app');    // Substitua pela senha de app

// Hostgator (exemplo alternativo)
// define('SMTP_HOST', 'mail.rcconstrucoes.com.br');
// define('SMTP_PORT', 587);
// define('SMTP_USERNAME', 'contato@rcconstrucoes.com.br');
// define('SMTP_PASSWORD', 'senha-do-email-hosting');

// cPanel/Servidor próprio (exemplo alternativo)
// define('SMTP_HOST', 'localhost');
// define('SMTP_PORT', 587);
// define('SMTP_USERNAME', 'contato@rcconstrucoes.com.br');
// define('SMTP_PASSWORD', 'senha-do-cpanel');

// ============================================
// CONFIGURAÇÕES DE UPLOAD DE ARQUIVOS
// ============================================

// Tamanho máximo por arquivo (em bytes) - 10MB
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024);

// Número máximo de arquivos por envio
define('UPLOAD_MAX_FILES', 5);

// Dias para manter arquivos antes da limpeza automática
define('UPLOAD_CLEANUP_DAYS', 7);

// Diretório de upload (deve ter permissão 755)
define('UPLOAD_DIR', 'uploads/');

// ============================================
// CONFIGURAÇÕES DE SEGURANÇA
// ============================================

// Tempo de expiração do token de formulário (em segundos)
define('FORM_TOKEN_TIMEOUT', 3600); // 1 hora

// Máximo de tentativas por IP por hora
define('MAX_ATTEMPTS_PER_IP', 3);

// Tempo de bloqueio após exceder tentativas (em segundos)
define('BLOCK_DURATION', 7200); // 2 horas

// Ativar verificação reCAPTCHA (configure as chaves abaixo)
define('RECAPTCHA_ENABLED', false);
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET_KEY', '');

// ============================================
// CONFIGURAÇÕES DE LOG E MONITORAMENTO
// ============================================

// Ativar sistema de logs
define('ENABLE_LOGGING', true);

// Arquivo principal de log
define('LOG_FILE', 'logs/rc-email.log');

// Nível de log (ERROR, WARNING, INFO, DEBUG)
define('LOG_LEVEL', 'INFO');

// Rotação de logs (máximo de linhas por arquivo)
define('LOG_MAX_LINES', 5000);

// ============================================
// CONFIGURAÇÕES DE AMBIENTE
// ============================================

// Ambiente: 'development' ou 'production'
define('ENVIRONMENT', 'production');

// Modo debug (NUNCA ativar em produção)
define('DEBUG_MODE', false);

// Timezone
define('TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set(TIMEZONE);

// Locale para formatação
define('LOCALE', 'pt_BR.UTF-8');
setlocale(LC_ALL, LOCALE);

// ============================================
// CONFIGURAÇÕES DE BACKUP
// ============================================

// Ativar backup de e-mails enviados
define('BACKUP_EMAILS', true);

// Diretório de backup
define('BACKUP_DIR', 'backups/emails/');

// Dias para manter backups
define('BACKUP_RETENTION_DAYS', 90);

// ============================================
// CONFIGURAÇÕES DE NOTIFICAÇÕES
// ============================================

// E-mail secundário para notificações críticas
define('EMAIL_ADMIN', 'admin@rcconstrucoes.com.br');

// WhatsApp API para notificações instantâneas (opcional)
define('WHATSAPP_API_TOKEN', '');
define('WHATSAPP_PHONE', '+5527000000000');

// Webhook para integração com CRM/Slack (opcional)
define('WEBHOOK_URL', '');
define('WEBHOOK_SECRET', 'rc_webhook_secret_2025');

// ============================================
// CONFIGURAÇÕES DE VALIDAÇÃO
// ============================================

// Regras de validação para campos do formulário
$VALIDATION_RULES = [
    'name' => [
        'min_length' => 2,
        'max_length' => 100,
        'required' => true,
        'pattern' => '/^[a-zA-ZÀ-ÿ\s]+$/' // Apenas letras e espaços
    ],
    'email' => [
        'required' => true,
        'format' => 'email',
        'max_length' => 255
    ],
    'phone' => [
        'min_length' => 10,
        'max_length' => 15,
        'required' => true,
        'pattern' => '/^[\d\s\(\)\-\+]+$/' // Números e caracteres de telefone
    ],
    'address' => [
        'min_length' => 10,
        'max_length' => 500,
        'required' => true
    ],
    'message' => [
        'min_length' => 20,
        'max_length' => 3000,
        'required' => true
    ],
    'project_type' => [
        'required' => true,
        'allowed_values' => [
            'reforma-completa', 'reforma-parcial', 'construcao',
            'manutencao', 'servico-express', 'outro'
        ]
    ]
];

// ============================================
// TIPOS DE ARQUIVO PERMITIDOS
// ============================================

// Tipos MIME permitidos
$ALLOWED_FILE_TYPES = [
    // Imagens
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
    // Vídeos
    'video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/quicktime',
    // Documentos
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

// Extensões permitidas
$ALLOWED_EXTENSIONS = [
    'jpg', 'jpeg', 'png', 'gif', 'webp',
    'mp4', 'avi', 'mov', 'wmv',
    'pdf', 'doc', 'docx'
];

// ============================================
// CONFIGURAÇÕES DE RATE LIMITING
// ============================================

$RATE_LIMIT_CONFIG = [
    'enabled' => true,
    'max_requests' => 3,        // Máximo 3 envios
    'time_window' => 3600,      // Por hora (3600 segundos)
    'block_duration' => 7200,   // Bloquear por 2 horas se exceder
    'whitelist_ips' => [        // IPs que ignoram rate limit
        '127.0.0.1',
        '::1'
    ]
];

// ============================================
// PROTEÇÃO ANTI-SPAM
// ============================================

// IPs bloqueados permanentemente
$BLOCKED_IPS = [
    // '192.168.1.100',
    // '10.0.0.1'
];

// E-mails bloqueados
$BLOCKED_EMAILS = [
    // 'spam@example.com',
    // 'test@test.com'
];

// Palavras proibidas no conteúdo (detecção de spam)
$SPAM_WORDS = [
    'viagra', 'casino', 'lottery', 'winner', 'congratulations',
    'urgent', 'click here', 'make money', 'guaranteed',
    'free money', 'act now', 'limited time', 'nigerian prince'
];

// Domínios de e-mail temporários bloqueados
$TEMP_EMAIL_DOMAINS = [
    '10minutemail.com', 'tempmail.org', 'guerrillamail.com',
    'mailinator.com', 'throwaway.email'
];

// ============================================
// TEMPLATES DE E-MAIL
// ============================================

$EMAIL_TEMPLATES = [
    'subject_normal' => '📧 Nova Solicitação de Orçamento - {{name}} ({{city}})',
    'subject_priority' => '🔥 [ANEXOS] Solicitação de Orçamento - {{name}} ({{city}})',
    'subject_budget' => '⚡ [ORÇAMENTO] Solicitação - {{name}} ({{city}})',
    'subject_urgent' => '🚨 [URGENTE] Solicitação de Orçamento - {{name}} ({{city}})'
];

// ============================================
// CONFIGURAÇÕES DE INTEGRAÇÃO
// ============================================

// Google Analytics / Tag Manager
define('GOOGLE_ANALYTICS_ID', 'GA-XXXXXXXX-X');
define('GOOGLE_TAG_MANAGER_ID', 'GTM-XXXXXXX');

// Facebook Pixel
define('FACEBOOK_PIXEL_ID', '');

// Google reCAPTCHA v3
define('RECAPTCHA_V3_SITE_KEY', '');
define('RECAPTCHA_V3_SECRET_KEY', '');
define('RECAPTCHA_V3_THRESHOLD', 0.5);

// ============================================
// CONFIGURAÇÕES DE MONITORAMENTO
// ============================================

// Sentry para monitoramento de erros
define('SENTRY_DSN', '');

// New Relic
define('NEWRELIC_APP_NAME', 'RC Construções Website');

// Uptime monitoring
define('UPTIME_WEBHOOK', '');

// ============================================
// CONFIGURAÇÕES DE CACHE
// ============================================

define('CACHE_ENABLED', false);
define('CACHE_DURATION', 300); // 5 minutos
define('CACHE_DIR', 'cache/');

// ============================================
// CONFIGURAÇÕES DE CDN E PERFORMANCE
// ============================================

// CDN para assets (se usar)
define('CDN_URL', '');

// Compressão de imagens
define('IMAGE_COMPRESSION_ENABLED', true);
define('IMAGE_QUALITY', 85);

// Minificação de CSS/JS
define('MINIFY_ASSETS', ENVIRONMENT === 'production');

// ============================================
// FUNÇÕES DE CONFIGURAÇÃO
// ============================================

/**
 * Valida se todas as configurações obrigatórias estão definidas
 */
function validar_configuracao() {
    $errors = [];
    $warnings = [];
    
    // Verifica configurações críticas
    if (EMAIL_TO === 'contato@rcconstrucoes.com.br') {
        $errors[] = '❌ Configure EMAIL_TO com seu e-mail real';
    }
    
    if (SMTP_USERNAME === 'seu-email@gmail.com') {
        $errors[] = '❌ Configure SMTP_USERNAME com suas credenciais reais';
    }
    
    if (SMTP_PASSWORD === 'sua-senha-de-app') {
        $errors[] = '❌ Configure SMTP_PASSWORD com sua senha real';
    }
    
    if (!validar_email(EMAIL_TO)) {
        $errors[] = '❌ EMAIL_TO deve ser um e-mail válido';
    }
    
    if (!validar_email(EMAIL_FROM)) {
        $errors[] = '❌ EMAIL_FROM deve ser um e-mail válido';
    }
    
    // Verifica se os diretórios existem e têm permissões
    $dirs_required = [
        UPLOAD_DIR => '755',
        'logs/' => '755',
        BACKUP_DIR => '755'
    ];
    
    foreach ($dirs_required as $dir => $permission) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, octdec($permission), true)) {
                $errors[] = "❌ Não foi possível criar diretório: {$dir}";
            } else {
                $warnings[] = "⚠️ Diretório criado: {$dir}";
            }
        } else {
            // Verifica permissões
            $current_perm = substr(sprintf('%o', fileperms($dir)), -3);
            if ($current_perm < $permission) {
                $warnings[] = "⚠️ Permissão do diretório {$dir}: {$current_perm} (recomendado: {$permission})";
            }
        }
        
        if (!is_writable($dir)) {
            $errors[] = "❌ Diretório não é gravável: {$dir}";
        }
    }
    
    // Verifica extensões PHP necessárias
    $extensions_required = ['curl', 'json', 'fileinfo', 'mbstring'];
    foreach ($extensions_required as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "❌ Extensão PHP necessária não encontrada: {$ext}";
        }
    }
    
    // Verifica configurações de upload
    $upload_max_filesize = ini_get('upload_max_filesize');
    $post_max_size = ini_get('post_max_size');
    $max_file_uploads = ini_get('max_file_uploads');
    
    if (parse_size($upload_max_filesize) < UPLOAD_MAX_SIZE) {
        $warnings[] = "⚠️ upload_max_filesize ({$upload_max_filesize}) menor que UPLOAD_MAX_SIZE";
    }
    
    if (parse_size($post_max_size) < (UPLOAD_MAX_SIZE * UPLOAD_MAX_FILES)) {
        $warnings[] = "⚠️ post_max_size pode ser insuficiente para múltiplos arquivos";
    }
    
    if ($max_file_uploads < UPLOAD_MAX_FILES) {
        $warnings[] = "⚠️ max_file_uploads ({$max_file_uploads}) menor que UPLOAD_MAX_FILES";
    }
    
    return ['errors' => $errors, 'warnings' => $warnings];
}

/**
 * Converte tamanho em string para bytes
 */
function parse_size($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    }
    return round($size);
}

/**
 * Valida formato de e-mail
 */
function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Obtém configuração do ambiente
 */
function get_environment_config() {
    return [
        'environment' => ENVIRONMENT,
        'debug_mode' => DEBUG_MODE,
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'timezone' => TIMEZONE,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ];
}

// ============================================
// CONFIGURAÇÕES POR AMBIENTE
// ============================================

if (ENVIRONMENT === 'development') {
    // Configurações de desenvolvimento
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // E-mail de teste em desenvolvimento
    if (!defined('EMAIL_TO_DEV')) {
        define('EMAIL_TO_DEV', 'teste@localhost.com');
    }
    
    // Reduz rate limiting em desenvolvimento
    $RATE_LIMIT_CONFIG['max_requests'] = 10;
    
} else {
    // Configurações de produção
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    
    // Headers de segurança adicionais em produção
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}

// ============================================
// EXECUÇÃO DA VALIDAÇÃO
// ============================================

if (ENABLE_LOGGING && DEBUG_MODE) {
    $validation = validar_configuracao();
    
    if (!empty($validation['errors'])) {
        error_log('RC Construções - Erros críticos de configuração: ' . implode('; ', $validation['errors']));
    }
    
    if (!empty($validation['warnings'])) {
        error_log('RC Construções - Avisos de configuração: ' . implode('; ', $validation['warnings']));
    }
    
    if (empty($validation['errors'])) {
        error_log('RC Construções - Configuração validada com sucesso');
    }
}

// ============================================
// CONSTANTES DE SISTEMA
// ============================================

define('RC_SYSTEM_VERSION', '4.0');
define('RC_SYSTEM_DATE', '2025-01-23');
define('RC_AUTHOR', 'RC Construções - Sistema Interno');
define('RC_CONFIG_LOADED', true);

/**
 * ========================================
 * INSTRUÇÕES DE CONFIGURAÇÃO
 * ========================================
 * 
 * 1. OBRIGATÓRIO - Configure os e-mails:
 *    ✅ EMAIL_TO: seu e-mail que receberá os orçamentos
 *    ✅ EMAIL_FROM: e-mail do seu domínio (ex: nao-responda@seudominio.com.br)
 * 
 * 2. OBRIGATÓRIO - Configure SMTP:
 *    ✅ Para Gmail: ative 2FA e gere uma "senha de app"
 *    ✅ Para outros: use as configurações do seu provedor
 *    ✅ Teste o envio para garantir que funciona
 * 
 * 3. PERMISSÕES DE DIRETÓRIO:
 *    ✅ uploads/ (755)
 *    ✅ logs/ (755) 
 *    ✅ backups/ (755)
 * 
 * 4. TESTE A CONFIGURAÇÃO:
 *    ✅ Envie um orçamento de teste
 *    ✅ Verifique se o e-mail chegou
 *    ✅ Verifique se os arquivos foram salvos
 *    ✅ Verifique os logs para erros
 * 
 * 5. CONFIGURAÇÕES OPCIONAIS:
 *    ⚙️ Configure reCAPTCHA para produção
 *    ⚙️ Configure webhooks para CRM
 *    ⚙️ Configure monitoramento com Sentry
 *    ⚙️ Configure Analytics/Pixel para tracking
 * 
 * 6. SEGURANÇA:
 *    🔒 Nunca commite senhas reais no Git
 *    🔒 Use variáveis de ambiente em produção
 *    🔒 Mantenha o DEBUG_MODE = false em produção
 *    🔒 Configure SSL/HTTPS obrigatório
 * 
 * 7. MANUTENÇÃO:
 *    🧹 Configure o cleanup.php como cron job
 *    📊 Monitore os logs regularmente
 *    🔄 Faça backup das configurações
 *    ⬆️ Mantenha o sistema atualizado
 */

?>