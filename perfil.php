<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// --- 1. Lógica de CRUD (POST) para o Perfil ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 1.1 CRUD de Categorias ---

    // Adicionar Nova Categoria
    if (isset($_POST['add_categoria'])) {
        $nome = trim($_POST['nome_categoria']);
        // Remove a cerquilha se o usuário digitar
        $cor = trim(ltrim($_POST['cor_categoria'], '#')); 
        $cor_hex = '#' . $cor; 

        if (!empty($nome)) {
            $stmt = $pdo->prepare('INSERT INTO categorias (usuario_id, nome, cor_hex) VALUES (?, ?, ?)');
            // Valor padrão para cor se não for inserido
            $final_cor = (strlen($cor) == 6) ? $cor_hex : '#9370db'; 
            try {
                $stmt->execute([$user_id, $nome, $final_cor]);
            } catch (PDOException $e) {
                // Em caso de erro (ex: categoria duplicada), você pode adicionar um alerta aqui
            }
        }
        header('Location: perfil.php');
        exit;
    }

    // Deletar Categoria
    if (isset($_POST['delete_categoria']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        // A foreign key na tabela saidas (se existir) deve lidar com o ON DELETE. 
        // Idealmente, você deve verificar se a categoria está sendo usada antes de excluir.
        $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $user_id]);
        header('Location: perfil.php');
        exit;
    }
    
    // --- 1.2 CRUD de Receitas Recorrentes ---

    // Adicionar Nova Receita Recorrente
    if (isset($_POST['add_receita'])) {
        $nome = trim($_POST['nome_receita']);
        // Formata o valor, trocando vírgula por ponto para o SQL
        $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_receita'])); 

        if (!empty($nome) && $valor >= 0) {
            $stmt = $pdo->prepare('INSERT INTO receitas_recorrentes (usuario_id, nome, valor_padrao) VALUES (?, ?, ?)');
            $stmt->execute([$user_id, $nome, $valor]);
        }
        header('Location: perfil.php');
        exit;
    }
    
    // Deletar Receita Recorrente
    if (isset($_POST['delete_receita']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare('DELETE FROM receitas_recorrentes WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, $user_id]);
        header('Location: perfil.php');
        exit;
    }

    // --- 1.3 Edição do Nome do Usuário ---
    if (isset($_POST['edit_user_name'])) {
        $new_name = trim($_POST['new_name']);
        if (!empty($new_name)) {
            $stmt = $pdo->prepare('UPDATE usuarios SET name = ? WHERE id = ?');
            $stmt->execute([$new_name, $user_id]);
            // Atualiza a sessão, se necessário
            $_SESSION['user_name'] = $new_name; 
        }
        header('Location: perfil.php');
        exit;
    }
}


// --- 2. Busca de Dados (READ) ---

// 2.1 Dados do Usuário
$stmtUser = $pdo->prepare('SELECT name, email FROM usuarios WHERE id = ? LIMIT 1');
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();

// 2.2 Receitas Recorrentes
$stmtReceitas = $pdo->prepare('SELECT * FROM receitas_recorrentes WHERE usuario_id = ? ORDER BY valor_padrao DESC');
$stmtReceitas->execute([$user_id]);
$receitas = $stmtReceitas->fetchAll();
// Calcula o total para exibir no card
$total_receita_padrao = array_sum(array_column($receitas, 'valor_padrao'));


// 2.3 Categorias de Gastos
$stmtCategorias = $pdo->prepare('SELECT * FROM categorias WHERE usuario_id = ? ORDER BY nome ASC');
$stmtCategorias->execute([$user_id]);
$categorias = $stmtCategorias->fetchAll();

// --- 2.4 Dados para o Checklist do Painel Lateral (Reutilização do Dashboard) ---
$currentMonth = date('m');
$currentYear = date('Y');
$firstDayOfMonth = "{$currentYear}-{$currentMonth}-01";
$lastDayOfMonth = date('Y-m-t');
$hoje = date('Y-m-d');

$stmtSaidas = $pdo->prepare('SELECT * FROM saidas WHERE usuario_id = ? AND data BETWEEN ? AND ? ORDER BY data ASC');
$stmtSaidas->execute([$user_id, $firstDayOfMonth, $lastDayOfMonth]);
$saidas = $stmtSaidas->fetchAll();

// Saídas Pagas
$stmtPagas = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) AS total, COUNT(id) AS count FROM saidas WHERE usuario_id = ? AND status = 'Pago' AND data BETWEEN ? AND ?");
$stmtPagas->execute([$user_id, $firstDayOfMonth, $lastDayOfMonth]);
$pagas = $stmtPagas->fetch();

// Saídas A Pagar
$stmtAPagar = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) AS total, COUNT(id) AS count FROM saidas WHERE usuario_id = ? AND status = 'A Pagar' AND data >= ? AND data BETWEEN ? AND ?");
$stmtAPagar->execute([$user_id, $hoje, $firstDayOfMonth, $lastDayOfMonth]);
$a_pagar = $stmtAPagar->fetch();

// Saídas Vencidas
$stmtVencidas = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) AS total, COUNT(id) AS count FROM saidas WHERE usuario_id = ? AND status = 'A Pagar' AND data < ? AND data BETWEEN ? AND ?");
$stmtVencidas->execute([$user_id, $hoje, $firstDayOfMonth, $lastDayOfMonth]);
$vencidas = $stmtVencidas->fetch();


// Função auxiliar para determinar a cor do texto do Card
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
  <title>Caderneta — Perfil e Configurações</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <style>
    body { background-color: #f8f9fa; }
    .custom-card { border: none; border-radius: 1rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }

    /* Padrão de cor Roxo (utilizado nos botões principais e Navbar) */
    .bg-light-purple { background-color: #f2eaff !important; } 
    .bg-purple { background-color: #9370db !important; }
    .text-purple { color: #9370db !important; }
    .btn-purple { background-color: #9370db; border-color: #9370db; color: white; }
    .btn-purple:hover { background-color: #8360d0; border-color: #8360d0; color: white; }

    /* Tags/Cards de Categoria e Receita */
    .tag-item { 
        display: inline-flex; 
        align-items: center; 
        border-radius: .75rem; 
        padding: 0.5rem 0.75rem; 
        margin-right: 0.5rem; 
        margin-bottom: 0.5rem;
    }
    .tag-item .btn-action { 
        padding: 0 0.3rem; 
        font-size: 1.1rem; 
        opacity: 0.8;
    }
    .tag-item .btn-action:hover { opacity: 1; }

    /* Checklist Status Cards (Reutilizado do Dashboard) */
    .summary-card { padding: 0.75rem; border-radius: 0.75rem; margin-bottom: 0.5rem; }
    .summary-card.paid { background-color: #d4edda; color: #155724; }
    .summary-card.pending { background-color: #fff3cd; color: #856404; }
    .summary-card.overdue { background-color: #f8d7da; color: #721c24; }
    .action-btn { 
        color: #9370db; 
        padding: 0 0.2rem;
        font-size: 1.1rem;
    }
    .action-btn.delete { color: #dc3545; } 
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
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Início</a></li>
          <li class="nav-item"><a class="nav-link active" aria-current="page" href="perfil.php">Perfil</a></li>
          <li class="nav-item"><a class="nav-link btn btn-outline-secondary ms-2" href="logout.php">Sair</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="container my-4">
    <div class="row">

      <div class="col-12 col-lg-7 mb-4">
        
        <div class="card p-3 mb-4 custom-card">
          <h4 class="mb-3">Dados do Usuário</h4>
          <div class="row align-items-center mb-2">
            <div class="col-12 col-md-4">
              <p class="mb-0 fw-bold">Nome</p>
            </div>
            <div class="col-12 col-md-8 d-flex align-items-center">
              <p class="mb-0 me-3"><?php echo htmlspecialchars($user['name']); ?></p>
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUserModal">
                <i class="bi bi-pencil me-1"></i> Editar
              </button>
            </div>
          </div>
          <div class="row align-items-center">
            <div class="col-12 col-md-4">
              <p class="mb-0 fw-bold">E-mail</p>
            </div>
            <div class="col-12 col-md-8">
              <p class="mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
          </div>
        </div>

        <div class="card p-3 mb-4 custom-card">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Fontes de Receita</h4>
            <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#addReceitaModal">
              <i class="bi bi-plus-lg me-1"></i> Adicionar nova
            </button>
          </div>
          <p class="text-muted small">Total Receita Padrão: **R$ <?php echo number_format($total_receita_padrao, 2, ',', '.'); ?>**</p>
          
          <?php if (empty($receitas)): ?>
              <div class="alert alert-info small">Nenhuma fonte de receita recorrente cadastrada.</div>
          <?php else: ?>
              <?php foreach ($receitas as $receita): ?>
                  <div class="tag-item bg-light-purple d-flex justify-content-between align-items-center mb-2">
                      <div>
                          <span class="fw-bold"><?php echo htmlspecialchars($receita['nome']); ?></span>
                          <span class="badge bg-secondary ms-2">Padrão</span>
                          <span class="text-muted ms-3 small">
                              Padrão: R$ <?php echo number_format($receita['valor_padrao'], 2, ',', '.'); ?>
                          </span>
                      </div>
                      <div class="ms-4">
                          <button class="btn btn-sm btn-action text-purple" title="Editar">
                              <i class="bi bi-pencil-fill"></i>
                          </button>
                          
                          <form method="post" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta receita?');">
                              <input type="hidden" name="id" value="<?php echo $receita['id']; ?>">
                              <button class="btn btn-sm btn-action delete text-danger" name="delete_receita" value="1" title="Excluir" type="submit">
                                  <i class="bi bi-trash-fill"></i>
                              </button>
                          </form>
                      </div>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="card p-3 custom-card">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Categorias de Gastos</h4>
            <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#addCategoriaModal">
              <i class="bi bi-plus-lg me-1"></i> Adicionar
            </button>
          </div>
          
          <div class="d-flex flex-wrap">
              <?php if (empty($categorias)): ?>
                  <div class="alert alert-info small w-100">Nenhuma categoria cadastrada.</div>
              <?php else: ?>
                  <?php foreach ($categorias as $cat): ?>
                      <?php 
                          $cor = htmlspecialchars($cat['cor_hex'] ?? '#9370db');
                          $textColorClass = getTextColor($cor); // Usa a função para definir a cor do texto
                      ?>
                      <div class="tag-item shadow-sm" style="background-color: <?php echo $cor; ?>;">
                          <span class="fw-bold me-3 <?php echo $textColorClass; ?>"><?php echo htmlspecialchars($cat['nome']); ?></span>
                          
                          <button class="btn btn-sm btn-action me-1" style="color: <?php echo $textColorClass; ?>;" title="Editar">
                              <i class="bi bi-pencil-fill"></i>
                          </button>

                          <form method="post" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir a categoria: <?php echo htmlspecialchars($cat['nome']); ?>? Isso pode afetar despesas existentes!');">
                              <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                              <button class="btn btn-sm btn-action" name="delete_categoria" value="1" style="color: <?php echo $textColorClass; ?>;" title="Excluir" type="submit">
                                  <i class="bi bi-x-circle-fill"></i>
                              </button>
                          </form>
                      </div>
                  <?php endforeach; ?>
              <?php endif; ?>
          </div>
        </div>

      </div>

      <div class="col-12 col-lg-5">
        <div class="card p-3 custom-card">
            <h4 class="card-title mb-3">Checklist de Despesas</h4>
            
            <div class="row mb-3 g-2">
                <div class="col-4">
                    <div class="summary-card paid">
                        <small class="text-muted d-block">Pagos</small>
                        <span class="d-block"><strong>R$ <?php echo number_format($pagas['total'], 2, ',', '.'); ?></strong></span>
                        <small class="d-block"><?php echo $pagas['count']; ?> despesas</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="summary-card pending">
                        <small class="text-muted d-block">A Pagar</small>
                        <span class="d-block"><strong>R$ <?php echo number_format($a_pagar['total'], 2, ',', '.'); ?></strong></span>
                        <small class="d-block"><?php echo $a_pagar['count']; ?> despesas</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="summary-card overdue">
                        <small class="text-muted d-block">Vencidos</small>
                        <span class="d-block"><strong>R$ <?php echo number_format($vencidas['total'], 2, ',', '.'); ?></strong></span>
                        <small class="d-block"><?php echo $vencidas['count']; ?> despesas</small>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle">
                    <thead>
                        <tr class="text-muted small">
                            <th>Nome</th>
                            <th>Valor</th>
                            <th>Dia</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach(array_slice($saidas, 0, 8) as $s): // Limita a 8 despesas ?>
                            <tr>
                                <td class="fw-bold small"><?php echo htmlspecialchars($s['descricao'] ?: 'Despesa'); ?></td>
                                <td class="text-danger small">R$ <?php echo number_format($s['valor'],2,',','.'); ?></td>
                                <td class="text-muted small"><?php echo (new DateTime($s['data']))->format('d'); ?></td>
                                <td>
                                    <span class="badge rounded-pill <?php echo ($s['status'] === 'Pago') ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                        <?php echo htmlspecialchars($s['status'] ?? 'A Pagar'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($saidas)): ?>
                            <tr><td colspan="4" class="text-center text-muted small">Nenhuma despesa registrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary mt-2">
                Ver Checklist Completo
            </a>
        </div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-light-purple border-bottom-0">
          <h5 class="modal-title" id="editUserModalLabel">Editar Nome</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" action="">
            <div class="modal-body">
                <label for="new_name" class="form-label">Novo Nome:</label>
                <input name="new_name" id="new_name" class="form-control" type="text" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-purple" name="edit_user_name" value="1" type="submit">Salvar Alterações</button>
            </div>
        </form>
      </div>
    </div>
  </div>


  <div class="modal fade" id="addReceitaModal" tabindex="-1" aria-labelledby="addReceitaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-light-purple border-bottom-0">
          <h5 class="modal-title" id="addReceitaModalLabel">Adicionar Fonte de Receita Recorrente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" action="">
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label for="nome_receita" class="form-label">Nome da Fonte</label>
                    <input name="nome_receita" id="nome_receita" class="form-control" type="text" placeholder="Ex: Salário, Freelance Fixo" required>
                </div>
                <div class="col-12">
                    <label for="valor_receita" class="form-label">Valor Padrão (R$)</label>
                    <input name="valor_receita" id="valor_receita" class="form-control" type="text" placeholder="Ex: 1750,00" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-success" name="add_receita" value="1" type="submit">
                    <i class="bi bi-cash-stack me-1"></i> Cadastrar Receita
                </button>
            </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="addCategoriaModal" tabindex="-1" aria-labelledby="addCategoriaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-light-purple border-bottom-0">
          <h5 class="modal-title" id="addCategoriaModalLabel">Adicionar Nova Categoria de Gastos</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" action="">
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label for="nome_categoria" class="form-label">Nome da Categoria</label>
                    <input name="nome_categoria" id="nome_categoria" class="form-control" type="text" placeholder="Ex: Transporte, Lazer, Saúde" required>
                </div>
                <div class="col-12">
                    <label for="cor_categoria" class="form-label">Cor (Código Hexadecimal)</label>
                    <div class="input-group">
                         <span class="input-group-text">#</span>
                         <input name="cor_categoria" id="cor_categoria" class="form-control" type="text" maxlength="6" placeholder="Ex: 9370db (Roxo)">
                    </div>
                    <small class="form-text text-muted">Deixe em branco para usar a cor padrão (Roxo).</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button class="btn btn-purple" name="add_categoria" value="1" type="submit">
                    <i class="bi bi-tag-fill me-1"></i> Cadastrar Categoria
                </button>
            </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>