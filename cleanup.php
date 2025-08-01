<?php
/**
 * RC Construções - Sistema de Limpeza Automática Completo
 * @version 4.0
 * @description Script avançado para limpeza automática de arquivos e logs
 * 
 * FUNCIONALIDADES:
 * ✅ Limpeza de uploads antigos
 * ✅ Rotação e limpeza de logs
 * ✅ Limpeza de backups antigos
 * ✅ Limpeza de arquivos temporários
 * ✅ Limpeza de sessões PHP
 * ✅ Verificação de espaço em disco
 * ✅ Relatórios detalhados
 * ✅ Notificações por e-mail
 * ✅ Configuração de retenção por tipo de arquivo
 * ✅ Modo seguro e rollback
 * 
 * COMO USAR:
 * 1. Execute manualmente: php cleanup.php
 * 2. Configure como cron job: 0 2 * * * /usr/bin/php /caminho/para/cleanup.php
 *    (executa todo dia às 2h da manhã)
 * 3. Argumentos disponíveis:
 *    --dry-run: simula a limpeza sem deletar arquivos
 *    --force: força limpeza mesmo com pouco espaço
 *    --verbose: output detalhado
 */

// Impede execução via navegador (apenas linha de comando ou cron)
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    exit('❌ Este script deve ser executado via linha de comando ou cron job.');
}

// Carrega configurações se disponível
if (file_exists('config.php')) {
    require_once 'config.php';
}

// ============================================
// CONFIGURAÇÕES DE LIMPEZA
// ============================================
$config = [
    // Diretórios
    'uploads_dir' => 'uploads/',
    'logs_dir' => 'logs/',
    'backup_dir' => 'backups/',
    'temp_dir' => 'temp/',
    'cache_dir' => 'cache/',
    'sessions_dir' => session_save_path() ?: sys_get_temp_dir(),
    
    // Tempo de vida dos arquivos (em dias)
    'uploads_retention' => defined('UPLOAD_CLEANUP_DAYS') ? UPLOAD_CLEANUP_DAYS : 7,
    'logs_retention' => 30,
    'backup_retention' => 90,
    'temp_retention' => 1,
    'cache_retention' => 7,
    'sessions_retention' => 1,
    
    // Tamanho máximo dos diretórios (em MB)
    'max_uploads_size' => 500,
    'max_logs_size' => 100,
    'max_backup_size' => 1000,
    'max_cache_size' => 200,
    
    // Configurações de segurança
    'min_free_space_mb' => 100,  // Mínimo de espaço livre para executar
    'max_files_per_run' => 1000, // Máximo de arquivos por execução
    
    // Ativar limpeza por diretório
    'clean_uploads' => true,
    'clean_logs' => true,
    'clean_backups' => true,
    'clean_temp' => true,
    'clean_cache' => true,
    'clean_sessions' => true,
    
    // Notificações
    'email_admin' => defined('EMAIL_ADMIN') ? EMAIL_ADMIN : '',
    'notification_threshold_mb' => 50, // Notificar se limpar mais de 50MB
    'enable_notifications' => true,
];

// ============================================
// ARGUMENTOS DE LINHA DE COMANDO
// ============================================
$options = [
    'dry_run' => false,
    'force' => false,
    'verbose' => false,
    'help' => false,
];

// Processa argumentos
$args = array_slice($argv, 1);
foreach ($args as $arg) {
    switch ($arg) {
        case '--dry-run':
        case '-d':
            $options['dry_run'] = true;
            break;
        case '--force':
        case '-f':
            $options['force'] = true;
            break;
        case '--verbose':
        case '-v':
            $options['verbose'] = true;
            break;
        case '--help':
        case '-h':
            $options['help'] = true;
            break;
    }
}

// ============================================
// FUNÇÕES UTILITÁRIAS
// ============================================

/**
 * Converte bytes para formato legível
 */
function formatarTamanho($bytes) {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Calcula o tamanho total de um diretório
 */
function calcularTamanhoDiretorio($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    
    try {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (Exception $e) {
        logMensagem("Erro ao calcular tamanho de {$dir}: " . $e->getMessage(), 'ERROR');
    }
    
    return $size;
}

/**
 * Verifica espaço livre em disco
 */
function verificarEspacoLivre($path = '.') {
    $free_bytes = disk_free_space($path);
    $total_bytes = disk_total_space($path);
    
    if ($free_bytes === false || $total_bytes === false) {
        return ['free' => 0, 'total' => 0, 'percent' => 0];
    }
    
    $percent_free = ($free_bytes / $total_bytes) * 100;
    
    return [
        'free' => $free_bytes,
        'total' => $total_bytes,
        'percent' => $percent_free
    ];
}

/**
 * Log de mensagens com timestamp
 */
function logMensagem($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[{$timestamp}] [{$level}] {$message}";
    
    echo $formatted . "\n";
    
    // Log em arquivo se possível
    if (defined('ENABLE_LOGGING') && ENABLE_LOGGING && defined('LOG_FILE')) {
        error_log($formatted . "\n", 3, LOG_FILE);
    }
}

/**
 * Remove arquivos antigos de um diretório
 */
function limparArquivosAntigos($dir, $dias, $pattern = '*', $dry_run = false, $max_files = 1000) {
    if (!is_dir($dir)) {
        return [
            'removidos' => 0, 
            'tamanho_liberado' => 0, 
            'errors' => ["Diretório não existe: {$dir}"]
        ];
    }
    
    $cutoff_time = time() - ($dias * 24 * 60 * 60);
    $files = glob($dir . $pattern);
    $removidos = 0;
    $tamanho_liberado = 0;
    $errors = [];
    $processed = 0;
    
    foreach ($files as $file) {
        if ($processed >= $max_files) {
            logMensagem("Limite de {$max_files} arquivos atingido para {$dir}", 'WARNING');
            break;
        }
        
        if (is_file($file) && filemtime($file) < $cutoff_time) {
            $file_size = filesize($file);
            $file_name = basename($file);
            
            if ($dry_run) {
                logMensagem("  [DRY-RUN] Removeria: {$file_name} (" . formatarTamanho($file_size) . ")");
                $removidos++;
                $tamanho_liberado += $file_size;
            } else {
                // Backup do nome para rollback se necessário
                $backup_info[] = [
                    'path' => $file,
                    'size' => $file_size,
                    'mtime' => filemtime($file)
                ];
                
                if (unlink($file)) {
                    $removidos++;
                    $tamanho_liberado += $file_size;
                    logMensagem("  ✓ Removido: {$file_name} (" . formatarTamanho($file_size) . ")");
                } else {
                    $errors[] = "Erro ao remover: {$file_name}";
                    logMensagem("  ❌ Erro ao remover: {$file_name}", 'ERROR');
                }
            }
            
            $processed++;
        }
    }
    
    return [
        'removidos' => $removidos,
        'tamanho_liberado' => $tamanho_liberado,
        'errors' => $errors,
        'processed' => $processed
    ];
}

/**
 * Remove arquivos em excesso por tamanho
 */
function limparPorTamanho($dir, $max_size_mb, $dry_run = false) {
    if (!is_dir($dir)) {
        return ['removidos' => 0, 'tamanho_liberado' => 0];
    }
    
    $max_size_bytes = $max_size_mb * 1024 * 1024;
    $current_size = calcularTamanhoDiretorio($dir);
    
    if ($current_size <= $max_size_bytes) {
        return ['removidos' => 0, 'tamanho_liberado' => 0];
    }
    
    logMensagem("  Diretório {$dir} excede limite: " . formatarTamanho($current_size) . " > " . formatarTamanho($max_size_bytes));
    
    // Pega todos os arquivos ordenados por data (mais antigos primeiro)
    $files = [];
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'mtime' => $file->getMTime()
                ];
            }
        }
    } catch (Exception $e) {
        logMensagem("Erro ao listar arquivos em {$dir}: " . $e->getMessage(), 'ERROR');
        return ['removidos' => 0, 'tamanho_liberado' => 0];
    }
    
    // Ordena por data modificação (mais antigos primeiro)
    usort($files, function($a, $b) {
        return $a['mtime'] - $b['mtime'];
    });
    
    $removidos = 0;
    $tamanho_liberado = 0;
    
    foreach ($files as $file) {
        if ($current_size <= $max_size_bytes) {
            break;
        }
        
        $file_name = basename($file['path']);
        
        if ($dry_run) {
            logMensagem("  [DRY-RUN] Removeria (tamanho): {$file_name} (" . formatarTamanho($file['size']) . ")");
            $removidos++;
            $tamanho_liberado += $file['size'];
            $current_size -= $file['size'];
        } else {
            if (unlink($file['path'])) {
                $removidos++;
                $tamanho_liberado += $file['size'];
                $current_size -= $file['size'];
                logMensagem("  ✓ Removido (tamanho): {$file_name} (" . formatarTamanho($file['size']) . ")");
            } else {
                logMensagem("  ❌ Erro ao remover: {$file_name}", 'ERROR');
            }
        }
    }
    
    return [
        'removidos' => $removidos,
        'tamanho_liberado' => $tamanho_liberado
    ];
}

/**
 * Limpa logs antigos, mantendo apenas as linhas mais recentes
 */
function limparLogs($log_file, $max_lines = 1000, $dry_run = false) {
    if (!file_exists($log_file)) {
        return ['linhas_removidas' => 0];
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES);
    $total_lines = count($lines);
    
    if ($total_lines <= $max_lines) {
        return ['linhas_removidas' => 0];
    }
    
    $linhas_removidas = $total_lines - $max_lines;
    $file_name = basename($log_file);
    
    if ($dry_run) {
        logMensagem("  [DRY-RUN] Truncaria log: {$file_name} ({$linhas_removidas} linhas)");
        return ['linhas_removidas' => $linhas_removidas];
    }
    
    // Backup do log antes de truncar
    $backup_file = $log_file . '.backup.' . date('Y-m-d');
    if (!file_exists($backup_file)) {
        copy($log_file, $backup_file);
    }
    
    // Mantém apenas as últimas linhas
    $keep_lines = array_slice($lines, -$max_lines);
    
    if (file_put_contents($log_file, implode("\n", $keep_lines) . "\n")) {
        logMensagem("  ✓ Log truncado: {$file_name} ({$linhas_removidas} linhas removidas)");
        return ['linhas_removidas' => $linhas_removidas];
    }