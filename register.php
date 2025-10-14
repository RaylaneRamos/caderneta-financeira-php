<?php
session_start();
require_once __DIR__ . '/config/db.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Lógica de validação (mantida por segurança)
    if (!$name) $errors[] = 'Nome obrigatório.';
    if (!$email) $errors[] = 'Email inválido.';
    if (!$password || strlen($password) < 6) $errors[] = 'Senha deve ter ao menos 6 caracteres.';
    if ($password !== $password_confirm) $errors[] = 'Senhas não conferem.';

    if (empty($errors)) {
        // 1. Verificar se o email já existe
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Email já cadastrado.';
        } else {
            // 2. Registrar novo usuário
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // CORREÇÃO APLICADA: Usando 'password_hash' para corresponder à sua tabela
            $stmt = $pdo->prepare('INSERT INTO usuarios (name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$name, $email, $password_hash]);
            
            // 3. Logar o usuário automaticamente e redirecionar
            $_SESSION['user_id'] = $pdo->lastInsertId();
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Caderneta — Criar Conta</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <style>
    /* PADRÃO DE CORES ROXO/LILÁS */
    .bg-light-purple { background-color: #f2eaff !important; }
    .bg-purple { background-color: #9370db !important; }
    .text-purple { color: #9370db !important; }
    .btn-purple { background-color: #9370db; border-color: #9370db; color: white; }
    .btn-purple:hover { background-color: #8360d0; border-color: #8360d0; color: white; }

    /* LAYOUT DE AUTENTICAÇÃO (Mobile First) */
    body {
        background-color: #f8f9fa; /* Fundo cinza suave */
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
    }
    .auth-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden; 
    }
    .brand-section {
        background-color: #9370db; /* Roxo principal */
        color: white;
        padding: 3rem 2rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .form-section {
        padding: 3rem 2rem;
    }
    /* Ocultar a seção lateral em telas pequenas para Mobile First */
    @media (max-width: 992px) {
        .brand-section {
            display: none;
        }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-9 col-lg-8">
        <div class="card auth-card">
          <div class="row g-0">
            
            <div class="col-lg-5 brand-section">
              <h1 class="display-5 fw-bold mb-3">Caderneta</h1>
              <p class="lead">O seu controle financeiro começa aqui.</p>
              <p class="text-white-50">Crie sua conta em poucos segundos e organize suas finanças com o nosso método simplificado.</p>
            </div>

            <div class="col-lg-7 form-section">
              <h4 class="card-title fw-bold text-center mb-4 text-purple">Criar Conta</h4>
              
              <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <?php echo implode('<br>', $errors); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <form method="post" action="">
                <div class="mb-3">
                  <label for="name" class="form-label small">Nome Completo</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="name" name="name" required placeholder="Seu nome" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                  </div>
                </div>
                <div class="mb-3">
                  <label for="email" class="form-label small">Email</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="seu@exemplo.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                  </div>
                </div>
                <div class="mb-3">
                  <label for="password" class="form-label small">Senha</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required minlength="6">
                  </div>
                  <div class="form-text small">Mínimo de 6 caracteres.</div>
                </div>
                <div class="mb-4">
                  <label for="password_confirm" class="form-label small">Confirmar Senha</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                  </div>
                </div>
                
                <button class="btn btn-purple w-100 mb-3" type="submit">
                  <i class="bi bi-person-plus me-1"></i> Criar Conta
                </button>
              </form>
              
              <p class="text-center small mt-3">
                Já tem uma conta? <a href="index.php" class="text-purple fw-bold">Faça Login</a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>