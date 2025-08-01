<?php
/**
 * RC Construções - Sistema de Envio de E-mail Completo e Funcional
 * @version 4.0
 * @description Script robusto com PHPMailer, validações completas e tratamento de erros
 * 
 * FUNCIONALIDADES:
 * ✅ Formulário de contato 100% funcional
 * ✅ Upload de arquivos seguro com validações
 * ✅ Envio via PHPMailer com SMTP
 * ✅ Template HTML responsivo e profissional
 * ✅ Validações robustas e sanitização
 * ✅ Sistema de logs e monitoramento
 * ✅ Rate limiting e proteção contra spam
 * ✅ Limpeza automática de arquivos antigos
 * ✅ Compatibilidade total com formulário
 */

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // Manter em 0 para produção
ini_set('log_errors', 1);

// Carrega configurações
require_once 'config.php';

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Tenta carregar PHPMailer
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    // Fallback para arquivos manuais
    $phpmailer_files = [
        'phpmailer/src/Exception.php',
        'phpmailer/src/PHPMailer.php',
        'phpmailer/src/SMTP.php'
    ];
    
    foreach ($phpmailer_files as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

/**
 * INÍCIO DO PROCESSAMENTO
 */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.html");
    exit('Acesso inválido');
}

try {
    // ============================================
    // 1. CONFIGURAÇÕES E INICIALIZAÇÃO
    // ============================================
    
    $config = [
        'upload_dir' => "uploads/",
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'max_files' => 5,
        'allowed_types' => [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/avi', 'video/mov', 'video/wmv',
            'application/pdf'
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'mp4', 'avi', 'mov', 'wmv', 'pdf'
        ]
    ];
    
    $errors = [];
    $upload_warnings = [];
    $upload_success = [];
    
    // ============================================
    // 2. FUNÇÕES AUXILIARES
    // ============================================
    
    function limpar_dado($dado) {
        if (is_array($dado)) {
            return array_map('limpar_dado', $dado);
        }
        $dado = trim($dado);
        $dado = stripslashes($dado);
        $dado = htmlspecialchars($dado, ENT_QUOTES, 'UTF-8');
        return $dado;
    }
    
    function validar_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    function validar_telefone($phone) {
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone_clean) >= 10 && strlen($phone_clean) <= 15;
    }
    
    function formatar_data($date) {
        if (empty($date)) return 'Não informada';
        try {
            $date_obj = new DateTime($date);
            return $date_obj->format('d/m/Y');
        } catch (Exception $e) {
            return 'Data inválida';
        }
    }
    
    function formatar_tamanho($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    function limpar_arquivos_antigos($dir, $dias = 7) {
        if (!is_dir($dir)) return;
        
        $cutoff_time = time() - ($dias * 24 * 60 * 60);
        $files = glob($dir . '*');
        $removed = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $removed++;
                }
            }
        }
        
        if ($removed > 0) {
            error_log("RC: Limpeza automática - {$removed} arquivo(s) antigo(s) removido(s)");
        }
    }
    
    function log_acao($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $log_entry = "[{$timestamp}] {$level}: {$message} | IP: {$ip} | UA: " . substr($user_agent, 0, 100);
        
        if (defined('ENABLE_LOGGING') && ENABLE_LOGGING) {
            error_log($log_entry . "\n", 3, LOG_FILE);
        }
        
        if ($level === 'ERROR') {
            error_log($log_entry);
        }
    }
    
    // ============================================
    // 3. RATE LIMITING E PROTEÇÃO ANTI-SPAM
    // ============================================
    
    function verificar_rate_limit() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rate_file = sys_get_temp_dir() . '/rc_rate_' . md5($ip);
        
        $current_time = time();
        $window = 3600; // 1 hora
        $max_requests = 3;
        
        $requests = [];
        if (file_exists($rate_file)) {
            $data = file_get_contents($rate_file);
            $requests = json_decode($data, true) ?: [];
        }
        
        // Remove requisições antigas
        $requests = array_filter($requests, function($timestamp) use ($current_time, $window) {
            return ($current_time - $timestamp) < $window;
        });
        
        if (count($requests) >= $max_requests) {
            log_acao("Rate limit excedido para IP: {$ip}", 'WARNING');
            return false;
        }
        
        // Adiciona nova requisição
        $requests[] = $current_time;
        file_put_contents($rate_file, json_encode($requests));
        
        return true;
    }
    
    if (!verificar_rate_limit()) {
        header("Location: orcamento.html?error=" . urlencode("Muitas tentativas. Tente novamente em 1 hora."));
        exit;
    }
    
    // ============================================
    // 4. VALIDAÇÃO DOS DADOS DO FORMULÁRIO
    // ============================================
    
    // Dados obrigatórios
    $name = limpar_dado($_POST['name'] ?? '');
    $email = limpar_dado($_POST['email'] ?? '');
    $phone = limpar_dado($_POST['phone'] ?? '');
    $address = limpar_dado($_POST['address'] ?? '');
    $message = limpar_dado($_POST['message'] ?? '');
    
    // Dados opcionais
    $project_type = limpar_dado($_POST['project-type'] ?? 'Não informado');
    $start_date = formatar_data($_POST['start-date'] ?? '');
    $budget_range = limpar_dado($_POST['budget-range'] ?? 'Não informado');
    $city = limpar_dado($_POST['city'] ?? 'Não informada');
    
    // Validações obrigatórias
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Nome deve ter pelo menos 2 caracteres";
    }
    
    if (!validar_email($email)) {
        $errors[] = "E-mail inválido";
    }
    
    if (empty($phone) || !validar_telefone($phone)) {
        $errors[] = "Telefone deve conter entre 10 e 15 dígitos";
    }
    
    if (empty($address) || strlen($address) < 10) {
        $errors[] = "Endereço deve ser mais específico (mínimo 10 caracteres)";
    }
    
    if (empty($message) || strlen($message) < 20) {
        $errors[] = "Descrição do projeto deve ter pelo menos 20 caracteres";
    }
    
    // Validação de projeto
    if (empty($project_type) || $project_type === 'Não informado') {
        $errors[] = "Tipo de projeto é obrigatório";
    }
    
    // ============================================
    // 5. PROCESSAMENTO DOS SERVIÇOS SELECIONADOS
    // ============================================
    
    $services_list = "Nenhum serviço específico selecionado.";
    if (!empty($_POST['services']) && is_array($_POST['services'])) {
        $servicos_limpos = [];
        foreach ($_POST['services'] as $service) {
            $service_clean = limpar_dado($service);
            if (!empty($service_clean)) {
                $servicos_limpos[] = "✓ " . $service_clean;
            }
        }
        if (!empty($servicos_limpos)) {
            $services_list = implode("\n", $servicos_limpos);
        }
    }
    
    // ============================================
    // 6. PROCESSAMENTO DE ARQUIVOS (UPLOAD)
    // ============================================
    
    $anexos = [];
    $anexos_info = "";
    
    // Cria diretório de upload se não existir
    if (!is_dir($config['upload_dir'])) {
        if (!mkdir($config['upload_dir'], 0755, true)) {
            $upload_warnings[] = "Erro ao criar diretório de upload";
        }
    }
    
    // Limpeza automática de arquivos antigos
    limpar_arquivos_antigos($config['upload_dir'], 7);
    
    if (!empty($_FILES['attachments']) && empty($upload_warnings)) {
        $files = $_FILES['attachments'];
        
        // Normaliza estrutura para múltiplos arquivos
        if (!is_array($files['name'])) {
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']]
            ];
        }
        
        $processed_files = 0;
        
        for ($i = 0; $i < count($files['name']) && $processed_files < $config['max_files']; $i++) {
            $file_name = $files['name'][$i];
            $file_type = $files['type'][$i];
            $file_tmp = $files['tmp_name'][$i];
            $file_size = $files['size'][$i];
            $file_error = $files['error'][$i];
            
            // Pula arquivos vazios
            if (empty($file_name)) continue;
            
            // Verifica erros de upload
            if ($file_error !== UPLOAD_ERR_OK) {
                switch ($file_error) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $upload_warnings[] = "Arquivo '{$file_name}' excede tamanho máximo";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $upload_warnings[] = "Upload do arquivo '{$file_name}' foi interrompido";
                        break;
                    default:
                        $upload_warnings[] = "Erro no upload do arquivo '{$file_name}'";
                }
                continue;
            }
            
            // Validações de segurança
            if ($file_size > $config['max_file_size']) {
                $upload_warnings[] = "Arquivo '{$file_name}' muito grande (" . formatar_tamanho($file_size) . "). Máx: " . formatar_tamanho($config['max_file_size']);
                continue;
            }
            
            // Validação de tipo MIME
            if (!in_array($file_type, $config['allowed_types'])) {
                $upload_warnings[] = "Tipo de arquivo '{$file_name}' não permitido";
                continue;
            }
            
            // Validação adicional por extensão
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_extension, $config['allowed_extensions'])) {
                $upload_warnings[] = "Extensão do arquivo '{$file_name}' não permitida";
                continue;
            }
            
            // Validação adicional de conteúdo (magic bytes)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            if (!in_array($detected_type, $config['allowed_types'])) {
                $upload_warnings[] = "Conteúdo do arquivo '{$file_name}' não corresponde à extensão";
                continue;
            }
            
            // Gera nome único e seguro
            $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
            $unique_name = 'rc_' . date('Ymd_His') . '_' . uniqid() . '_' . substr($safe_filename, 0, 50) . '.' . $file_extension;
            $destination = $config['upload_dir'] . $unique_name;
            
            // Move arquivo para destino final
            if (move_uploaded_file($file_tmp, $destination)) {
                $anexos[] = $destination;
                $anexos_info .= "📎 {$file_name} (" . formatar_tamanho($file_size) . ")\n";
                $upload_success[] = $file_name;
                $processed_files++;
                
                log_acao("Arquivo enviado com sucesso: {$file_name} ({$file_size} bytes)");
            } else {
                $upload_warnings[] = "Falha ao salvar arquivo '{$file_name}'";
                log_acao("Falha ao mover arquivo: {$file_name}", 'ERROR');
            }
        }
        
        // Aviso se limite de arquivos foi atingido
        if (count($files['name']) > $config['max_files']) {
            $upload_warnings[] = "Apenas os primeiros {$config['max_files']} arquivos foram processados";
        }
    }
    
    // ============================================
    // 7. VERIFICAÇÃO FINAL DE ERROS
    // ============================================
    
    if (!empty($errors)) {
        $error_msg = urlencode(implode('. ', $errors));
        $warning_msg = !empty($upload_warnings) ? urlencode(implode('. ', $upload_warnings)) : '';
        
        log_acao("Validação falhou: " . implode('; ', $errors), 'WARNING');
        
        $redirect_url = "orcamento.html?error=" . $error_msg;
        if ($warning_msg) {
            $redirect_url .= "&warning=" . $warning_msg;
        }
        
        header("Location: " . $redirect_url);
        exit;
    }
    
    // ============================================
    // 8. PREPARAÇÃO DO E-MAIL
    // ============================================
    
    // Detecta cidade automaticamente se não informada
    if ($city === 'Não informada') {
        $cidade_detectada = '';
        $endereco_lower = strtolower($address);
        
        $cidades_regiao = [
            'vitória' => ['vitoria', 'centro vitoria', 'enseada', 'praia do canto'],
            'vila velha' => ['vila velha', 'praia da costa', 'itaparica', 'itapua'],
            'cariacica' => ['cariacica', 'campo grande', 'itaciba'],
            'serra' => ['serra', 'laranjeiras', 'jacaraipe'],
            'viana' => ['viana'],
            'guarapari' => ['guarapari', 'meaipe', 'enseada azul']
        ];
        
        foreach ($cidades_regiao as $cidade => $bairros) {
            foreach ($bairros as $bairro) {
                if (strpos($endereco_lower, $bairro) !== false) {
                    $cidade_detectada = ucfirst($cidade);
                    break 2;
                }
            }
        }
        
        $city = $cidade_detectada ?: 'Grande Vitória';
    }
    
    // Variáveis do template
    $template_vars = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'city' => $city,
        'address' => $address,
        'project_type' => $project_type,
        'start_date' => $start_date,
        'budget_range' => $budget_range,
        'services_list' => $services_list,
        'message' => $message,
        'anexos_info' => $anexos_info,
        'upload_warnings' => !empty($upload_warnings) ? implode('<br>', $upload_warnings) : '',
        'upload_success' => !empty($upload_success) ? implode(', ', $upload_success) : '',
        'current_date' => date('d/m/Y \à\s H:i'),
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
    ];
    
    // ============================================
    // 9. TEMPLATE HTML DO E-MAIL
    // ============================================
    
    $email_body = "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Nova Solicitação de Orçamento - RC Construções</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                margin: 0; 
                padding: 20px; 
                background-color: #f5f5f5; 
                color: #333;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: white; 
                border-radius: 12px; 
                overflow: hidden; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #1a3a6c 0%, #f58220 100%); 
                color: white; 
                padding: 30px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0 0 10px 0; 
                font-size: 1.8rem; 
                font-weight: 700; 
            }
            .header p { 
                margin: 0; 
                opacity: 0.9; 
                font-size: 1rem; 
            }
            .priority-badge { 
                display: inline-block; 
                background: rgba(255,255,255,0.2); 
                padding: 5px 15px; 
                border-radius: 20px; 
                margin-top: 10px; 
                font-size: 0.9rem; 
                font-weight: 600; 
            }
            .content { 
                padding: 30px; 
            }
            .section { 
                margin-bottom: 25px; 
            }
            .section h3 { 
                color: #1a3a6c; 
                border-bottom: 2px solid #f58220; 
                padding-bottom: 8px; 
                margin-bottom: 15px; 
                font-size: 1.2rem; 
            }
            .info-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 15px; 
                margin-bottom: 20px; 
            }
            .info-item { 
                background: #f8f9fa; 
                padding: 15px; 
                border-radius: 8px; 
                border-left: 4px solid #f58220; 
            }
            .info-label { 
                font-weight: bold; 
                color: #1a3a6c; 
                margin-bottom: 5px; 
                font-size: 0.9em; 
            }
            .info-value { 
                color: #333; 
                word-break: break-word; 
            }
            .services-list { 
                background: #f0f8ff; 
                padding: 20px; 
                border-radius: 8px; 
                white-space: pre-wrap; 
                border-left: 4px solid #1a3a6c; 
                font-family: inherit; 
            }
            .message-content { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                white-space: pre-wrap; 
                border-left: 4px solid #f58220; 
                font-family: inherit; 
            }
            .attachments-section { 
                background: #fff3e0; 
                padding: 20px; 
                border-radius: 8px; 
                border-left: 4px solid #f58220; 
            }
            .warning-box { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                padding: 15px; 
                border-radius: 8px; 
                margin: 15px 0; 
                border-left: 4px solid #fdcb6e; 
            }
            .success-box { 
                background: #d4edda; 
                border: 1px solid #c3e6cb; 
                padding: 15px; 
                border-radius: 8px; 
                margin: 15px 0; 
                border-left: 4px solid #28a745; 
            }
            .footer { 
                background: #1a3a6c; 
                color: white; 
                padding: 20px; 
                text-align: center; 
                font-size: 14px; 
            }
            .priority-high { 
                border-left-color: #e74c3c !important; 
            }
            .cta-section { 
                background: linear-gradient(135deg, #f58220 0%, #e07014 100%); 
                color: white; 
                padding: 20px; 
                border-radius: 8px; 
                text-align: center; 
                margin: 20px 0; 
            }
            .cta-section h4 { 
                margin: 0 0 10px 0; 
                font-size: 1.1rem; 
            }
            .cta-section p { 
                margin: 0; 
                opacity: 0.9; 
            }
            @media (max-width: 600px) { 
                .info-grid { 
                    grid-template-columns: 1fr; 
                } 
                body { 
                    padding: 10px; 
                } 
                .content { 
                    padding: 20px; 
                } 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏗️ Nova Solicitação de Orçamento</h1>
                <p>RC Construções - Tradição que constrói, inovação que transforma</p>
                <div class='priority-badge'>
                    Prioridade: " . (!empty($anexos) ? 'ALTA 🔥' : ($budget_range !== 'Não informado' ? 'MÉDIA ⚡' : 'NORMAL 📧')) . "
                </div>
            </div>
            
            <div class='content'>
                <div class='section'>
                    <h3>👤 Dados do Cliente</h3>
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-label'>Nome Completo</div>
                            <div class='info-value'>{$template_vars['name']}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>E-mail</div>
                            <div class='info-value'>
                                <a href='mailto:{$template_vars['email']}' style='color: #f58220; text-decoration: none;'>
                                    {$template_vars['email']}
                                </a>
                            </div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Telefone/WhatsApp</div>
                            <div class='info-value'>
                                <a href='tel:{$template_vars['phone']}' style='color: #f58220; text-decoration: none;'>
                                    {$template_vars['phone']}
                                </a>
                            </div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Cidade</div>
                            <div class='info-value'>{$template_vars['city']}</div>
                        </div>
                    </div>
                </div>

                <div class='section'>
                    <h3>🏠 Detalhes do Projeto</h3>
                    <div class='info-grid'>
                        <div class='info-item " . ($template_vars['budget_range'] !== 'Não informado' ? 'priority-high' : '') . "'>
                            <div class='info-label'>Tipo de Projeto</div>
                            <div class='info-value'>{$template_vars['project_type']}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Previsão de Início</div>
                            <div class='info-value'>{$template_vars['start_date']}</div>
                        </div>
                        <div class='info-item " . ($template_vars['budget_range'] !== 'Não informado' ? 'priority-high' : '') . "'>
                            <div class='info-label'>Orçamento Estimado</div>
                            <div class='info-value'>{$template_vars['budget_range']}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Endereço da Obra</div>
                            <div class='info-value'>{$template_vars['address']}</div>
                        </div>
                    </div>
                </div>

                <div class='section'>
                    <h3>🔧 Serviços Solicitados</h3>
                    <div class='services-list'>{$template_vars['services_list']}</div>
                </div>

                <div class='section'>
                    <h3>📝 Descrição Detalhada do Projeto</h3>
                    <div class='message-content'>{$template_vars['message']}</div>
                </div>";

    // Adiciona seção de anexos se houver
    if (!empty($template_vars['anexos_info'])) {
        $email_body .= "
                <div class='section'>
                    <h3>📎 Arquivos Anexados (" . count($anexos) . ")</h3>
                    <div class='attachments-section'>
                        <div style='white-space: pre-wrap; font-family: inherit;'>{$template_vars['anexos_info']}</div>
                    </div>
                </div>";
    }

    // Adiciona sucessos de upload se houver
    if (!empty($template_vars['upload_success'])) {
        $email_body .= "
                <div class='section'>
                    <div class='success-box'>
                        <h4 style='margin: 0 0 10px 0; color: #155724;'>✅ Arquivos Processados com Sucesso</h4>
                        <p style='margin: 0; color: #155724;'>{$template_vars['upload_success']}</p>
                    </div>
                </div>";
    }

    // Adiciona avisos de upload se houver
    if (!empty($template_vars['upload_warnings'])) {
        $email_body .= "
                <div class='section'>
                    <div class='warning-box'>
                        <h4 style='margin: 0 0 10px 0; color: #856404;'>⚠️ Avisos de Upload</h4>
                        <div style='margin: 0; color: #856404;'>{$template_vars['upload_warnings']}</div>
                    </div>
                </div>";
    }

    // CTA e finalização
    $email_body .= "
                <div class='cta-section'>
                    <h4>🚀 Próximos Passos</h4>
                    <p>1. Entrar em contato com cliente em até 24h<br>
                       2. Agendar visita técnica gratuita<br>
                       3. Elaborar proposta detalhada e personalizada</p>
                </div>
            </div>
            
            <div class='footer'>
                <p><strong>RC Construções</strong> - Sistema de Orçamentos v4.0</p>
                <p>📧 E-mail enviado em {$template_vars['current_date']}</p>
                <p>🌐 {$template_vars['client_ip']} | www.rcconstrucoes.com.br</p>
            </div>
        </div>
    </body>
    </html>";

    // ============================================
    // 10. CONFIGURAÇÃO E ENVIO VIA PHPMAILER
    // ============================================
    
    if (!class_exists('PHPMailer')) {
        throw new Exception('PHPMailer não está disponível. Instale via Composer ou inclua os arquivos manualmente.');
    }
    
    $mail = new PHPMailer(true);

    try {
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Configurações de debug (apenas para desenvolvimento)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        // Configurações do e-mail
        $mail->setFrom(EMAIL_FROM, 'RC Construções - Sistema de Orçamentos');
        $mail->addAddress(EMAIL_TO, 'RC Construções');
        $mail->addReplyTo($email, $name);
        
        // E-mail de cópia oculta para admin (se configurado)
        if (defined('EMAIL_ADMIN') && !empty(EMAIL_ADMIN) && EMAIL_ADMIN !== EMAIL_TO) {
            $mail->addBCC(EMAIL_ADMIN);
        }

        // Configurações de prioridade
        if (!empty($anexos) || $budget_range !== 'Não informado') {
            $mail->Priority = 1; // Alta prioridade
            $mail->addCustomHeader('X-Priority', '1');
            $mail->addCustomHeader('Importance', 'High');
        }

        // Assunto dinâmico e inteligente
        $subject_parts = [];
        
        if (!empty($anexos)) {
            $subject_parts[] = '🔥 [ANEXOS]';
        } elseif ($budget_range !== 'Não informado') {
            $subject_parts[] = '⚡ [ORÇAMENTO]';
        } else {
            $subject_parts[] = '📧';
        }
        
        $subject_parts[] = 'Solicitação de Orçamento';
        $subject_parts[] = '-';
        $subject_parts[] = $name;
        $subject_parts[] = "({$city})";
        $subject_parts[] = '-';
        $subject_parts[] = ucfirst($project_type);
        
        $mail->Subject = implode(' ', $subject_parts);

        // Corpo do e-mail
        $mail->isHTML(true);
        $mail->Body = $email_body;
        
        // Versão texto (fallback)
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $email_body));

        // Adiciona anexos se houver
        foreach ($anexos as $anexo) {
            if (file_exists($anexo)) {
                $original_name = '';
                // Tenta recuperar o nome original do arquivo
                foreach ($upload_success as $original) {
                    if (strpos(basename($anexo), str_replace([' ', '.'], ['_', '_'], $original)) !== false) {
                        $original_name = $original;
                        break;
                    }
                }
                $mail->addAttachment($anexo, $original_name ?: basename($anexo));
            }
        }

        // Envia o e-mail
        if ($mail->send()) {
            log_acao("E-mail enviado com sucesso para {$email} - Assunto: {$mail->Subject}");
            
            // Backup do e-mail (se configurado)
            if (defined('BACKUP_EMAILS') && BACKUP_EMAILS) {
                salvar_backup_email($template_vars, $email_body, $anexos);
            }
            
            // Analytics/Webhook (se configurado)
            if (defined('WEBHOOK_URL') && !empty(WEBHOOK_URL)) {
                enviar_webhook_notificacao($template_vars);
            }
            
            // Monta parâmetros de sucesso
            $success_params = [
                'sent=true',
                'files=' . count($anexos)
            ];
            
            if (!empty($upload_warnings)) {
                $success_params[] = 'warnings=' . urlencode(implode('. ', $upload_warnings));
            }
            
            if (!empty($upload_success)) {
                $success_params[] = 'uploaded=' . urlencode(implode(', ', $upload_success));
            }
            
            header("Location: obrigado.html?" . implode('&', $success_params));
            exit;
            
        } else {
            throw new Exception('Falha no envio do e-mail: ' . $mail->ErrorInfo);
        }

    } catch (Exception $e) {
        log_acao("Erro PHPMailer: " . $e->getMessage(), 'ERROR');
        
        // Remove arquivos em caso de erro
        foreach ($anexos as $anexo) {
            if (file_exists($anexo)) {
                unlink($anexo);
            }
        }
        
        $error_details = urlencode('Erro no envio: ' . $e->getMessage());
        header("Location: orcamento.html?error={$error_details}");
        exit;
    }

} catch (Exception $e) {
    log_acao("Erro geral no processamento: " . $e->getMessage(), 'ERROR');
    
    $error_msg = urlencode('Erro interno. Entre em contato por telefone: (27) 99999-9999');
    header("Location: orcamento.html?error={$error_msg}");
    exit;
}

// ============================================
// FUNÇÕES AUXILIARES ADICIONAIS
// ============================================

function salvar_backup_email($dados, $email_body, $anexos) {
    try {
        $backup_dir = defined('BACKUP_DIR') ? BACKUP_DIR : 'backups/emails/';
        
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $backup_dir . "email_backup_{$timestamp}.json";
        
        $backup_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'client_data' => $dados,
            'email_html' => $email_body,
            'attachments' => array_map('basename', $anexos),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
    } catch (Exception $e) {
        error_log("Erro ao salvar backup: " . $e->getMessage());
    }
}

function enviar_webhook_notificacao($dados) {
    try {
        $webhook_data = [
            'event' => 'novo_orcamento',
            'timestamp' => date('c'),
            'data' => [
                'nome' => $dados['name'],
                'email' => $dados['email'],
                'telefone' => $dados['phone'],
                'cidade' => $dados['city'],
                'tipo_projeto' => $dados['project_type'],
                'orcamento' => $dados['budget_range']
            ]
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($webhook_data),
                'timeout' => 5
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents(WEBHOOK_URL, false, $context);
        
        if ($result === FALSE) {
            error_log("Webhook falhou para: " . WEBHOOK_URL);
        }
        
    } catch (Exception $e) {
        error_log("Erro no webhook: " . $e->getMessage());
    }
}

?>