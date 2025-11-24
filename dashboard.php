<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// --- Inicialização de variáveis ---
$entradasParaCalculo = 0.00; 
$totSaidasMes = 0.00;
$saldoPrevisto = 0.00;
$totSaidasPagas = 0.00;
$saldoDia = 0.00;
$totSaidasAPagar = 0.00;
$totVencidas = 0.00; 
// --------------------------------------------------------

// --- 1. Lógica de Filtro por Mês/Ano ---
$currentMonth = isset($_GET['month']) && is_numeric($_GET['month']) ? str_pad($_GET['month'], 2, '0', STR_PAD_LEFT) : date('m');
$currentYear = isset($_GET['year']) && is_numeric($_GET['year']) ? $_GET['year'] : date('Y');

// O primeiro e o último dia do mês filtrado (necessário para filtrar saídas)
$firstDayOfMonth = "{$currentYear}-{$currentMonth}-01";
$lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
$hoje = date('Y-m-d');


// --- 2. Tratamento de Adicionar/Excluir/Toggle Status (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar Despesa (Saída)
    if (isset($_POST['add_type']) && $_POST['add_type'] === 'saida') {
        $valor = floatval(str_replace(',', '.', $_POST['valor'])); 
        $categoria = trim($_POST['categoria']);
        $descricao = trim($_POST['descricao']);
        $data = $_POST['data'] ?: date('Y-m-d');
        
        if ($valor > 0 && !empty($categoria)) {
            $stmt = $pdo->prepare('INSERT INTO saidas (usuario_id, valor, categoria, descricao, data, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user_id, $valor, $categoria, $descricao, $data, 'A Pagar']); 
        }
        header('Location: dashboard.php?month='.$currentMonth.'&year='.$currentYear);
        exit;
    }
    
    // Deletar Despesa (Saída)
    if (isset($_POST['delete']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare('DELETE FROM saidas WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $user_id]);
        header('Location: dashboard.php?month='.$currentMonth.'&year='.$currentYear);
        exit;
    }

    // Toggle status de despesa (A Pagar <-> Pago)
    if (isset($_POST['toggle_status']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $currentStatus = $pdo->prepare('SELECT status FROM saidas WHERE id = ? AND usuario_id = ?');
        $currentStatus->execute([$id, $user_id]);
        $status = $currentStatus->fetchColumn();

        $newStatus = ($status === 'Pago') ? 'A Pagar' : 'Pago';

        $pdo->prepare('UPDATE saidas SET status = ? WHERE id = ? AND usuario_id = ?')->execute([$newStatus, $id, $user_id]);

        header('Location: dashboard.php?month='.$currentMonth.'&year='.$currentYear);
        exit;
    }
}

// --- 3. Busca de Totais e Listas ---

// 1. Total de Receitas RECORRENTES (única fonte de entrada)
$stmtReceitaRecorrente = $pdo->prepare('SELECT COALESCE(SUM(valor_padrao), 0) AS s FROM receitas_recorrentes WHERE usuario_id = ?');
$stmtReceitaRecorrente->execute([$user_id]);
$entradasParaCalculo = (float) ($stmtReceitaRecorrente->fetch()['s'] ?? 0.00); 

// Total de Saídas (Despesas) no Mês (Independentemente do status de pagamento)
$stmtTotSaidasMes = $pdo->prepare('SELECT COALESCE(SUM(valor), 0) AS s FROM saidas WHERE usuario_id = ? AND data BETWEEN ? AND ?');
$stmtTotSaidasMes->execute([$user_id, $firstDayOfMonth, $lastDayOfMonth]);
$totSaidasMes = (float) ($stmtTotSaidasMes->fetch()['s'] ?? 0.00);

// Saldo Previsto (Receita Recorrente - Saídas Mês)
// OBS: $saldoPrevisto continuará sendo a projeção considerando todas as saídas do mês (pagas ou não)
$saldoPrevisto = $entradasParaCalculo - $totSaidasMes;

// Saldo Realizado (Saldo do Mês) -> considerar apenas saídas PAGAS
// Total de Saídas PAGAS (Contagem de despesas pagas no mês)
$stmtTotSaidasPagas = $pdo->prepare('SELECT COALESCE(SUM(valor), 0) AS s FROM saidas WHERE usuario_id = ? AND status = ? AND data BETWEEN ? AND ?');
$stmtTotSaidasPagas->execute([$user_id, 'Pago', $firstDayOfMonth, $lastDayOfMonth]);
$totSaidasPagas = (float) ($stmtTotSaidasPagas->fetch()['s'] ?? 0.00);

// Saldo Realizado = receita recorrente - saídas pagas (o que já impactou o caixa)
$saldoRealizado = $entradasParaCalculo - $totSaidasPagas;

// Total de Saídas A PAGAR no mês
$stmtTotSaidasAPagar = $pdo->prepare('SELECT COALESCE(SUM(valor), 0) AS s FROM saidas WHERE usuario_id = ? AND status = ? AND data BETWEEN ? AND ?');
$stmtTotSaidasAPagar->execute([$user_id, 'A Pagar', $firstDayOfMonth, $lastDayOfMonth]);
$totSaidasAPagar = (float) ($stmtTotSaidasAPagar->fetch()['s'] ?? 0.00);

// Total de Saídas Vencidas
$hoje = date('Y-m-d');
/*
 * Aqui simplificamos: consideramos vencidas as 'A Pagar' com data < hoje,
 * mas ainda dentro do mês filtrado (ou com data antes de hoje).
 */
$vencidas_end = (strtotime($hoje) > strtotime($lastDayOfMonth)) ? $lastDayOfMonth : date('Y-m-d', strtotime('-1 day'));
$stmtTotSaidasVencidas = $pdo->prepare('SELECT COALESCE(SUM(valor), 0) AS s FROM saidas WHERE usuario_id = ? AND status = ? AND data BETWEEN ? AND ?');
$stmtTotSaidasVencidas->execute([$user_id, 'A Pagar', $firstDayOfMonth, $vencidas_end]);
$totVencidas = (float) ($stmtTotSaidasVencidas->fetch()['s'] ?? 0.00);

// Lista de Saídas (Checklist de Despesas) no Mês
$stmtSaidas = $pdo->prepare('SELECT * FROM saidas WHERE usuario_id = ? AND data BETWEEN ? AND ? ORDER BY data ASC');
$stmtSaidas->execute([$user_id, $firstDayOfMonth, $lastDayOfMonth]);
$saidas = $stmtSaidas->fetchAll();

// Busca de Despesas por Categoria para o Gráfico
$categoriasData = [];
if ($totSaidasMes > 0) {
    $stmtCategorias = $pdo->prepare('
        SELECT categoria, SUM(valor) AS total
        FROM saidas
        WHERE usuario_id = ? AND data BETWEEN ? AND ?
        GROUP BY categoria
        ORDER BY total DESC
    ');
    $stmtCategorias->execute([$user_id, $firstDayOfMonth, $lastDayOfMonth]);
    $categorias = $stmtCategorias->fetchAll();
    
    foreach ($categorias as $cat) {
        $percent = round(($cat['total'] / $totSaidasMes) * 100);
        $categoriasData[] = [
            'categoria' => $cat['categoria'],
            'total' => $cat['total'],
            'percent' => $percent
        ];
    }
}


// Cálculo Percentual: Receita x Despesas
$percentReceitaXDespesa = ($entradasParaCalculo > 0) ? round(($totSaidasMes / $entradasParaCalculo) * 100) : 0;
$percentReceitaXDespesa = min(100, $percentReceitaXDespesa);


// Dados do Usuário
$stmtUser = $pdo->prepare('SELECT name, email FROM usuarios WHERE id = ? LIMIT 1');
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

// --- ATUALIZADO: Buscar Categorias com NOME e COR para o Modal ---
$stmtCategoriasDisp = $pdo->prepare('SELECT nome, cor_hex FROM categorias WHERE usuario_id = ? ORDER BY nome ASC');
$stmtCategoriasDisp->execute([$user_id]);
// Usamos fetchAll() sem FETCH_COLUMN para pegar as duas colunas
$categoriasDisponiveis = $stmtCategoriasDisp->fetchAll(PDO::FETCH_ASSOC); 
// --------------------------------------------------------------------------

// Função auxiliar para nomes dos meses
function getMonthName($monthNum) {
    $months = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
        '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
        '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
    ];
    return $months[$monthNum] ?? 'Mês Inválido';
}

// NOVO: Função auxiliar para determinar a cor do texto (copiado do perfil.php)
function getTextColor($bgColor) {
    if (empty($bgColor)) return 'text-dark';
    
    // Converte a cor hexadecimal para RGB
    $hex = str_replace('#', '', $bgColor);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    
    // Calcula o brilho (Luminosidade perceptiva)
    $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
    
    // Retorna branco para cores escuras e preto para cores claras
    return ($brightness > 180) ? 'text-dark' : 'text-white';
}
?>

<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Caderneta — Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <style>
    body { background-color: #f8f9fa; }
    .custom-card { border: none; border-radius: 1rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }

    /* Padrão de cor Roxo (utilizado nos botões principais e Navbar) */
    .bg-light-purple { background-color: #f2eaff !important; } 
    .bg-purple { background-color: #9370db !important; }
    .text-purple { color: #9370db !important; }
    
    .btn-add-custom { 
        background-color: #9370db; 
        border-color: #9370db; 
        color: white;
    } 
    .btn-add-custom:hover { 
        background-color: #8360d0; 
        border-color: #8360d0; 
        color: white; 
    } 

    /* Cards de Resumo */
    .card-summary {
        text-align: center;
        border: none;
        border-radius: .75rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .card-summary .value {
        font-size: 1.5rem;
        font-weight: 700;
    }
    .bg-summary-positive { background-color: #d4edda !important; color: #155724 !important; }
    .bg-summary-negative { background-color: #f8d7da !important; color: #721c24 !important; }
    .bg-summary-info { background-color: #d1ecf1 !important; color: #0c5460 !important; }
    
    /* Checklist Status Cards */
    .summary-card { padding: 0.75rem; border-radius: 0.75rem; margin-bottom: 0.5rem; }
    .summary-card.paid { background-color: #d4edda; color: #155724; }
    .summary-card.pending { background-color: #fff3cd; color: #856404; }
    .summary-card.overdue { background-color: #f8d7da; color: #721c24; }
    
    /* Ações na Tabela */
    .action-btn { 
        color: #9370db; /* Padrão roxo */
        padding: 0 0.2rem;
        font-size: 1.1rem;
    }
    .action-btn:hover { color: #8360d0; }
    .action-btn.delete { color: #dc3545; } /* Vermelho para deletar */
    .action-btn.delete:hover { color: #c82333; }

    /* Cores de Gráfico (Define um ciclo de cores para as barras) */
    .chart-color-0 { background-color: #9370db; } /* Roxo Principal */
    .chart-color-1 { background-color: #ff9999; } /* Rosa Suave */
    .chart-color-2 { background-color: #ffcc00; } /* Amarelo */
    .chart-color-3 { background-color: #00bcd4; } /* Ciano */
    .chart-color-4 { background-color: #4CAF50; } /* Verde */

    /* --- ESTILOS REFINADOS PARA OS BLOCOS DE CATEGORIA (Modal) --- */
    .category-block {
        display: inline-flex;
        align-items: center;
        /* Reduzindo o padding para blocos menores */
        padding: 0.3rem 0.7rem; 
        border-radius: 0.5rem; /* Bordas mais suaves */
        margin: 0.2rem; /* Margem menor para agrupamento */
        cursor: pointer;
        font-weight: 500; /* Peso de fonte menor */
        font-size: 0.85rem; /* Fonte menor */
        white-space: nowrap; 
        transition: transform 0.1s, opacity 0.1s, box-shadow 0.1s, border 0.1s; 
        border: 1px solid transparent; /* Borda fina transparente para harmonia */
    }
    .category-block:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    .category-block.selected {
        /* Borda de destaque mais fina e sutil */
        border: 2px solid #6c5ce7; 
        box-shadow: 0 2px 6px rgba(108, 92, 231, 0.2); 
        opacity: 1;
        transform: scale(1.02);
    }

    /* Estilização da Box que contém os blocos no modal */
    #categoria-selection-container {
        border: 1px solid #dee2e6; /* Borda sutil como a do form-control */
        border-radius: 0.5rem;
        padding: 0.5rem; 
        background-color: #ffffff;
    }
  </style>
</head>
<body>
  
  <nav class="navbar navbar-expand-lg navbar-light bg-light-purple border-bottom">
    <div class="container-fluid">
      <a class="navbar-brand" href="dashboard.php">Caderneta</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="dashboard.php">Início</a></li>
          <li class="nav-item"><a class="nav-link" href="perfil.php">Perfil</a></li>
          <li class="nav-item"><a class="nav-link btn btn-outline-secondary ms-2" href="logout.php">Sair</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="container my-4">
    <h1 class="mb-4">Dashboard</h1>

    <div class="card p-3 mb-4 custom-card">
        <form class="row g-3 align-items-center" method="GET" action="dashboard.php">
            <div class="col-12 col-md-3">
                <label for="month" class="form-label mb-0">Visualizar Mês/Ano:</label>
            </div>
            <div class="col-6 col-md-3">
                <select name="month" id="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): $m_str = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?php echo $m_str; ?>" <?php echo ($currentMonth == $m_str) ? 'selected' : ''; ?>>
                            <?php echo getMonthName($m_str); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="year" id="year" class="form-select">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($currentYear == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button type="submit" class="btn btn-purple w-100">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </div>
    
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
             <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#addDespesaModal">
                <i class="bi bi-plus-circle-fill me-1"></i> Adicionar Despesa
             </button>
        </div>
    </div>


    <div class="row g-3 mb-4">
        
        <div class="col-6 col-lg-3">
            <div class="card card-summary bg-summary-positive">
                <div class="card-body p-3">
                    <div class="label small opacity-75">
                        Receita Prevista
                    </div>
                    <div class="value">R$ <?php echo number_format($entradasParaCalculo, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card card-summary bg-summary-negative">
                <div class="card-body p-3">
                    <div class="label small opacity-75">Total de Saídas</div>
                    <div class="value">R$ <?php echo number_format($totSaidasMes, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card card-summary <?php echo ($saldoRealizado >= 0) ? 'bg-summary-positive' : 'bg-summary-negative'; ?>">
                <div class="card-body p-3">
                    <div class="label small opacity-75">Saldo do Mês</div>
                    <div class="value">R$ <?php echo number_format($saldoRealizado, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-lg-3">
            <div class="card card-summary <?php echo ($saldoPrevisto >= 0) ? 'bg-summary-info' : 'bg-summary-negative'; ?>">
                <div class="card-body p-3">
                    <div class="label small opacity-75">Saldo Previsto</div>
                    <div class="value">R$ <?php echo number_format($saldoPrevisto, 2, ',', '.'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        
        <div class="col-12 col-lg-8 mb-4">
            <div class="card p-3 h-100 custom-card">
                <h4 class="card-title mb-3">Checklist de Despesas</h4>

                <div class="row mb-3 g-2">
                    <div class="col-4">
                        <div class="summary-card paid">
                            <small class="text-muted d-block">Pagos</small>
                            <span class="d-block"><strong>R$ <?php echo number_format($totSaidasPagas, 2, ',', '.'); ?></strong></span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="summary-card pending">
                            <small class="text-muted d-block">A Pagar</small>
                            <span class="d-block"><strong>R$ <?php echo number_format($totSaidasAPagar, 2, ',', '.'); ?></strong></span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="summary-card overdue">
                            <small class="text-muted d-block">Vencidos</small>
                            <span class="d-block"><strong>R$ <?php echo number_format($totVencidas, 2, ',', '.'); ?></strong></span>
                        </div>
                    </div>
                    <div class="col-12 mt-3">
                        <?php if ($totVencidas > 0): ?>
                             <div class="alert alert-danger d-flex align-items-center py-2" role="alert">
                                 <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                 <div>
                                     Atenção! Você tem contas vencidas no valor de R$ <?php echo number_format($totVencidas, 2, ',', '.'); ?>
                                 </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success d-flex align-items-center py-2" role="alert">
                                 <i class="bi bi-check-circle-fill me-2"></i>
                                 <div>
                                     Tudo em dia! Não há contas para vencer nos próximos dias.
                                 </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Nome</th>
                                <th class="d-none d-md-table-cell">Categoria</th>
                                <th class="text-end">Valor</th>
                                <th>Dia</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($saidas as $s): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($s['descricao'] ?: 'Despesa'); ?></td>
                                    <td class="d-none d-md-table-cell text-muted small"><?php echo htmlspecialchars($s['categoria']); ?></td>
                                    <td class="text-end text-danger">R$ <?php echo number_format($s['valor'],2,',','.'); ?></td>
                                    <td class="text-muted small"><?php echo (new DateTime($s['data']))->format('d'); ?></td>
                                    <td>
                                        <span class="badge rounded-pill <?php echo ($s['status'] === 'Pago') ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                            <?php echo htmlspecialchars($s['status'] ?? 'A Pagar'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                            <button class="btn btn-sm action-btn p-0 me-2" name="toggle_status" value="1" title="Marcar como <?php echo ($s['status'] === 'Pago') ? 'A Pagar' : 'Pago'; ?>">
                                                <i class="bi bi-check-circle-fill"></i>
                                            </button>
                                            <button class="btn btn-sm action-btn delete p-0" name="delete" value="1" title="Excluir" type="submit">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($saidas)): ?>
                                <tr><td colspan="6" class="text-center text-muted small">Nenhuma despesa registrada neste mês.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            
            <div class="card p-3 mb-4 custom-card">
                <h5 class="card-title">Receita x Despesas</h5>
                <div class="text-center mb-3">
                    <span class="fs-4 fw-bold text-purple"><?php echo $percentReceitaXDespesa; ?>%</span> da receita utilizada
                </div>
                <div class="progress" role="progressbar" aria-label="Progresso" aria-valuenow="<?php echo $percentReceitaXDespesa; ?>" aria-valuemin="0" aria-valuemax="100" style="height: 20px;">
                    <div class="progress-bar bg-purple" style="width: <?php echo $percentReceitaXDespesa; ?>%"></div>
                </div>
            </div>

            <div class="card p-3 custom-card">
                <h5 class="card-title">Classificação de Despesas</h5>
                <p class="text-muted small">Distribuição dos gastos no mês</p>
                
                <?php if (!empty($categoriasData)): ?>
                    <?php $colorIndex = 0; ?>
                    <?php foreach ($categoriasData as $cat): ?>
                        <div class="mb-2">
                            <small><?php echo htmlspecialchars($cat['categoria']); ?> (<?php echo $cat['percent']; ?>%)</small>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar chart-color-<?php echo $colorIndex % 5; ?>" 
                                    role="progressbar" 
                                    style="width: <?php echo $cat['percent']; ?>%" 
                                    aria-valuenow="<?php echo $cat['percent']; ?>" 
                                    aria-valuemin="0" 
                                    aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    <?php $colorIndex++; endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info small">
                        Sem despesas registradas neste mês para classificar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </main>

  <div class="modal fade" id="addDespesaModal" tabindex="-1" aria-labelledby="addDespesaModalLabel" aria-hidden="true">
    <div class="modal-dialog"> 
      <div class="modal-content">
        <div class="modal-header bg-light-purple border-bottom-0">
          <h5 class="modal-title" id="addDespesaModalLabel">Adicionar Nova Despesa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" action="" id="formAddDespesa">
            <input type="hidden" name="add_type" value="saida"> 
            <input type="hidden" name="categoria" id="categoria_input_hidden" value=""> <div class="modal-body row g-3">
                
                <div class="col-12">
                    <label for="descricao_saida" class="form-label">1. Descrição (Nome da conta)</label>
                    <input name="descricao" id="descricao_saida" class="form-control" type="text" placeholder="Ex: Conta de Luz / Aluguel" required>
                </div>

                <div class="col-12 mb-3">
                    <label class="form-label">2. Selecione a Categoria</label>
                    <div id="categoria-selection-container" class="d-flex flex-wrap">
                        <?php if (!empty($categoriasDisponiveis)): ?>
                            <?php foreach ($categoriasDisponiveis as $cat): 
                                $cor = htmlspecialchars($cat['cor_hex'] ?? '#9370db');
                                $textColorClass = getTextColor($cor);
                            ?>
                                <div 
                                    class="category-block <?php echo $textColorClass; ?>" 
                                    style="background-color: <?php echo $cor; ?>;"
                                    data-category-name="<?php echo htmlspecialchars($cat['nome']); ?>"
                                    title="<?php echo htmlspecialchars($cat['nome']); ?>"
                                >
                                    <?php echo htmlspecialchars($cat['nome']); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning small w-100 mb-0">
                                Nenhuma categoria cadastrada. Cadastre-as no <a href="perfil.php" target="_blank">Perfil</a>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-6">
                    <label for="valor_saida" class="form-label">3. Valor (R$)</label>
                    <input name="valor" id="valor_saida" class="form-control" type="text" placeholder="Ex: 50,00" required>
                </div>
                <div class="col-6">
                    <label for="data_saida" class="form-label">4. Data de Vencimento</label>
                    <input name="data" id="data_saida" class="form-control" type="date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-danger" type="submit">
                    <i class="bi bi-wallet-fill me-1"></i> Salvar Despesa
                </button>
            </div>
        </form>
      </div>
    </div>
  </div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const categoryBlocks = document.querySelectorAll('.category-block');
        const hiddenInput = document.getElementById('categoria_input_hidden');
        const form = document.getElementById('formAddDespesa');

        // Seletor para aplicar o efeito de seleção nos blocos
        categoryBlocks.forEach(block => {
            block.addEventListener('click', function() {
                // 1. Limpa a seleção de todos os blocos
                categoryBlocks.forEach(b => b.classList.remove('selected'));

                // 2. Marca o bloco clicado
                this.classList.add('selected');

                // 3. Define o valor no campo hidden para o PHP
                hiddenInput.value = this.getAttribute('data-category-name');
            });
        });

        // Garantir que a seleção seja obrigatória antes de enviar
        form.addEventListener('submit', function(e) {
            if (!hiddenInput.value) {
                alert('Por favor, selecione uma categoria na Etapa 2.');
                e.preventDefault();
            }
        });
        
        // Marcar a primeira categoria por padrão ao abrir o modal (Melhora a UX)
        const addDespesaModal = document.getElementById('addDespesaModal');
        if (addDespesaModal) {
             addDespesaModal.addEventListener('shown.bs.modal', function () {
                // Se nenhum valor estiver selecionado e houver categorias disponíveis, clique no primeiro bloco.
                if (categoryBlocks.length > 0 && !hiddenInput.value) {
                    categoryBlocks[0].click(); 
                }
             });
        }

        // Função para formatar o campo de valor para R$ (opcional, mas melhora a UX)
        const valorInput = document.getElementById('valor_saida');
        if (valorInput) {
            valorInput.addEventListener('input', function(e) {
                let value = e.target.value;
                // Remove tudo que não for dígito e ponto/vírgula
                value = value.replace(/[^\d,.]/g, ''); 

                // Troca ponto por vírgula se for o único separador
                if (value.indexOf('.') !== -1 && value.indexOf(',') === -1) {
                    value = value.replace('.', ',');
                }
                
                e.target.value = value;
            });
        }
    });
  </script>
</body>
</html>
