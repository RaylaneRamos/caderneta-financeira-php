<?php
session_start();
require_once __DIR__ . '/config/db.php';
$errors = [];

// Redireciona se o usuário já estiver logado
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email) $errors[] = 'Email inválido.';
    if (!$password) $errors[] = 'Senha obrigatória.';

    if (empty($errors)) {
        // 1. Buscar o usuário pelo email e buscar a coluna 'password_hash' (CORRIGIDO)
        $stmt = $pdo->prepare('SELECT id, password_hash FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 2. Verificar senha (usando 'password_hash')
        if ($user && password_verify($password, $user['password_hash'])) {
            // Sucesso no login
            $_SESSION['user_id'] = $user['id'];
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = 'Email ou senha incorretos.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Caderneta — Entrar</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  
  <style>
    /* PADRÃO DE CORES ROXO/LILÁS */
    .text-purple { color: #9370db !important; }
    .btn-purple { background-color: #9370db; border-color: #9370db; color: white; }
    .btn-purple:hover { background-color: #8360d0; border-color: #8360d0; color: white; }

    /* LAYOUT MINIMALISTA */
    body {
        background-color: #f8f9fa; 
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
    }
    .auth-container {
        display: flex;
        max-width: 900px;
        width: 90%;
        background: white;
        border-radius: 1rem;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .brand-section {
        padding: 3rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .form-section {
        padding: 3rem;
        flex: 1;
        background-color: white;
    }
    .brand-title {
        font-size: 2.5rem;
        font-weight: bold;
    }
    /* Adaptação para telas menores */
    @media (max-width: 768px) {
        .auth-container {
            width: 95%;
            flex-direction: column;
        }
        .brand-section {
            padding: 1.5rem;
            text-align: center;
        }
        .form-section {
            padding: 1.5rem;
        }
    }
  </style>
</head>
<body>
  <div class="auth-container">
    
    <div class="brand-section">
      <h1 class="brand-title text-purple">Caderneta</h1>
      <h2 class="h4 mt-3 fw-bold">Controle total das suas finanças</h2>
      <p class="mt-2 text-muted">Gerencie suas receitas e despesas com simplicidade e segurança.</p>
    </div>

    <div class="form-section">
      <h3 class="mb-4 fw-bold">Entrar</h3>
      
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show small" role="alert">
            <?php echo implode('<br>', $errors); ?>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <div class="mb-3">
          <label for="email" class="form-label small">Email</label>
          <input type="email" class="form-control" id="email" name="email" required placeholder="seu@exemplo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <div class="mb-4">
          <label for="password" class="form-label small">Senha</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        
        <button class="btn btn-purple w-100 mb-3" type="submit">
          Entrar
        </button>
      </form>
      
      <p class="text-center small mt-3">
        Ou <a href="register.php" class="text-purple fw-bold">criar uma conta</a>
      </p>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>